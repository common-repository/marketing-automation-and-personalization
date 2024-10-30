<?php

namespace ConvesioConvert\EDD;

use EDD\Orders\Order;
use EDD\Orders\Order_Adjustment;
use EDD\Orders\Order_Item;
use function ConvesioConvert\meta_site_prefix;

/**
 * Follows {@see Post_Type_Sync} conventions.
 */
class Orders {

	private $order_statuses;
	private $order_types;

	public function __construct() {
		/**
		 * Note: It's possible to fine-tune the following using other EDD order status functions.
		 */
		$this->order_statuses = array_merge(
			edd_get_complete_order_statuses(),
			edd_get_incomplete_order_statuses()
		);

		/**
		 * Other values can come from {@see edd_get_order_types()} e.g 'refund', 'invoice', etc.
		 * We're only interested about these for now.
		 */
		$this->order_types = array( 'sale' );
	}

	public function index( $request ) {
		$params = $request->get_params();
		if ( isset( $params['type'] ) ) {
			switch ( $params['type'] ) {
				case 'new':
					return $this->get_new( $params );
				case 'modified':
					return $this->get_modified( $params );
				default:
					break;
			}
		}

		if ( isset( $params['ids'] ) ) {
			return $this->get_by_id( $params['ids'] );
		}

		return null;
	}

	protected function get_new( $args ) {
		global $wpdb;

		$last_id = isset( $args['last_id'] ) ? $args['last_id'] : 0;
		$limit   = 100;

		$sql_statuses = implode( ', ', array_fill( 0, count( $this->order_statuses ), '%s' ) );
		$sql_types    = implode( ', ', array_fill( 0, count( $this->order_types ), '%s' ) );

		$values = array_merge(
			$this->order_statuses,
			$this->order_types,
			array( $last_id ),
			array( $limit )
		);

		$query = "
			SELECT id
			FROM $wpdb->edd_orders
			WHERE status IN ($sql_statuses) AND type IN ($sql_types) AND id > %d
			ORDER BY id
			LIMIT %d";

		$sql = $wpdb->prepare( $query, $values ); // phpcs:ignore

		$order_ids = $wpdb->get_results( $sql ); // phpcs:ignore

		return $this->prepare( $order_ids );
	}

	public function get_modified( $args ) {
		global $wpdb;

		$first_synced_id = isset( $args['first_synced_id'] ) ? $args['first_synced_id'] : 0;
		$last_id         = isset( $args['last_id'] ) ? $args['last_id'] : 0;
		$limit           = 1000;

		$query = "
			SELECT edd_order_id AS id, meta_value AS ts
			FROM $wpdb->edd_ordermeta
			WHERE meta_key = %s AND edd_order_id BETWEEN %d AND %d
			ORDER BY id
			LIMIT %d";

		$sql = $wpdb->prepare(
			$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'_convesioconvert_edd_payment_last_modification',
			$last_id,
			$first_synced_id,
			$limit
		);

		return $wpdb->get_results( $sql ); // phpcs:ignore
	}

	public function get_by_id( $ids ) {
		$ids       = explode( ',', $ids );
		$order_ids = array_slice( $ids, 0, 100, true );

		$args = array(
			'id__in'     => $order_ids,
			'status__in' => $this->order_statuses,
			'type__in'   => $this->order_types,
			'fields'     => array( 'id' ),
		);

		$orders = edd_get_orders( $args );

		return $this->prepare( $orders );
	}

	/**
	 * @param Order[] $orders
	 * @return array[]
	 */
	protected function prepare( $orders ) {
		$meta_prefix = meta_site_prefix();

		return array_map(
			function ( $order_id ) use ( $meta_prefix ) {
				$order = edd_get_order( $order_id->id );

				$downloads = array_map(
					/**
					 * @param Order_Item $item
					 * @return array
					 */
					function( $item ) {
						/** @noinspection PhpCastIsUnnecessaryInspection */
						return array(
							'id'  => (int) $item->product_id,
							'qty' => (int) $item->quantity,
						);
					},
					$order->get_items()
				);

				return array(
					'id'             => $order->id,
					'user_id'        => $order->user_id,
					'products'       => array_values( $downloads ),
					'published_at'   => $order->date_created,
					'session_id'     => edd_get_order_meta( $order->id, "{$meta_prefix}_session_id", true ),
					'client_id'      => edd_get_order_meta( $order->id, "{$meta_prefix}_client_id", true ),
					'coupon_ids'     => $this->get_discounts( $order->get_discounts() ),
					'order_total'    => $order->total,
					'currency'       => $order->currency,
					'payment_method' => $order->gateway,
					'payment_status' => $order->status,
					'modified_at'    => $this->get_order_modified_at( $order->id ),
				);
			},
			$orders
		);

	}

	/**
	 * @param Order_Adjustment[] $order_adjustments The result of $order->get_discounts() which already ensures the
	 *                                              order adjustments' type is 'discount'; no need to filter this array.
	 *
	 * @return array|int[]|null[]
	 */
	private function get_discounts( array $order_adjustments ) {
		$ids = array_map(
			function( $order_adjustment ) {
				return (int) $order_adjustment->type_id;
			},
			$order_adjustments
		);

		return array_filter( $ids );
	}

	/** The name 'edd_payment' is used to keep using the existing meta. In reality, we are using edd_ordermeta. */
	public function get_order_modified_at( $order_id ) {
		$modification = edd_get_order_meta( $order_id, '_convesioconvert_edd_payment_last_modification', true );

		if ( empty( $modification ) ) {
			$modification = self::update_order_modified_at( $order_id );
		}

		return $modification;
	}

	public static function update_order_modified_at( $order_id ) {
		$modification = time();
		edd_update_order_meta( $order_id, '_convesioconvert_edd_payment_last_modification', $modification );

		return $modification;
	}

}
