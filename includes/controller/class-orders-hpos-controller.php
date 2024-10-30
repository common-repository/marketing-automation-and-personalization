<?php

namespace ConvesioConvert\Controller;

use WC_Order;
use function ConvesioConvert\meta_site_prefix;

/**
 * Syncs orders when WooCommerce HPOS (high-performance order storage) is active.
 *
 * Follows {@see Post_Type_Sync} conventions, even though it doesn't derive from it.
 */
class Orders_HPOS_Controller {

	private $order_statuses;
	private $order_types;

	public function __construct() {
		$this->order_statuses = array_keys( wc_get_order_statuses() );

		// Could be gotten using wc_get_order_types(), but we are only interested about non-refunded orders for now.
		$this->order_types = array( 'shop_order' );
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
			FROM {$wpdb->prefix}wc_orders
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
			SELECT order_id AS id, meta_value AS ts
			FROM {$wpdb->prefix}wc_orders_meta
			WHERE meta_key = %s AND order_id BETWEEN %d AND %d
			ORDER BY id
			LIMIT %d";

		$sql = $wpdb->prepare(
			$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'_convesioconvert_shop_order_last_modification',
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
			'id'     => $order_ids,
			'status' => $this->order_statuses,
			'type'   => $this->order_types,
			'return' => 'ids',
		);

		$orders = wc_get_orders( $args );

		return $this->prepare( $orders );
	}

	protected function prepare( $orders ) {
		$meta_prefix = meta_site_prefix();

		return array_map(
			function ( $order_id ) use ( $meta_prefix ) {
				$order_id = isset( $order_id->id ) ? $order_id->id : $order_id;
				$order    = new WC_Order( $order_id );

				$products = array_map(
					function ( $item ) {
						return array(
							'id'  => $item['product_id'],
							'qty' => $item['qty'],
						);
					},
					$order->get_items()
				);

				$aggregated_payment_status = '';
				if ( in_array( $order->get_status(), wc_get_is_paid_statuses(), true ) ) {
					$aggregated_payment_status = 'completed';
				} elseif ( in_array( $order->get_status(), wc_get_is_pending_statuses(), true ) ) {
					$aggregated_payment_status = 'processing';
				}

				return array(
					'id'                        => (string) $order->get_id(),
					'user_id'                   => $order->get_user_id(),
					'products'                  => array_values( $products ),
					'published_at'              => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
					'session_id'                => $order->get_meta( "{$meta_prefix}_session_id" ),
					'client_id'                 => $order->get_meta( "{$meta_prefix}_client_id" ),
					'coupon_ids'                => $order->get_coupon_codes(),
					'order_total'               => $order->get_total(),
					'currency'                  => $order->get_currency(),
					'payment_method'            => $order->get_payment_method(),
					'payment_status'            => $order->get_status(),
					'aggregated_payment_status' => $aggregated_payment_status,
					'modified_at'               => self::get_order_modified_at( $order ),
				);
			},
			$orders
		);
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return int
	 */
	private static function get_order_modified_at( $order ) {
		$modification = $order->get_meta( '_convesioconvert_shop_order_last_modification' );

		if ( empty( $modification ) ) {
			$modification = self::update_order_modified_at( $order );
		}

		return $modification;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return int
	 */
	public static function update_order_modified_at( $order, $save = true ) {
		$modification = time();

		$order->update_meta_data( '_convesioconvert_shop_order_last_modification', $modification );
		if ( $save ) {
			$order->save();
		}

		return $modification;
	}
}
