<?php

namespace ConvesioConvert\EDD2;

class Orders extends \ConvesioConvert\Controller\Post_Type_Sync {

	public function __construct() {
		$this->post_type = 'edd_payment';
		$this->fields    = array( 'ID', 'post_date_gmt' );

		if ( function_exists( 'edd_get_payment_statuses' ) ) {
			$this->post_status = array_keys( edd_get_payment_statuses() );
		}
	}

	protected function prepare( $orders ) {
		$meta_prefix = \ConvesioConvert\meta_site_prefix();

		return array_map(
			function ( $post ) use ( $meta_prefix ) {
				$order = edd_get_payment( $post->ID );

				$downloads = array_map(
					function( $item ) {
						return array(
							'id'  => $item['id'],
							'qty' => $item['quantity'],
						);
					},
					$order->downloads
				);

				return array(
					'id'             => $post->ID,
					'user_id'        => $order->user_id,
					'products'       => array_values( $downloads ),
					'published_at'   => $post->post_date_gmt,
					'session_id'     => $order->get_meta( "{$meta_prefix}_session_id" ),
					'client_id'      => $order->get_meta( "{$meta_prefix}_client_id" ),
					'coupon_ids'     => $this->get_discounts( $order->discounts ),
					'order_total'    => $order->total,
					'currency'       => $order->currency,
					'payment_method' => $order->gateway,
					'payment_status' => $order->status,
					'modified_at'    => $this->get_post_type_modified_at( $post->ID ),
				);
			},
			$orders
		);

	}

	private function get_discounts( $discounts ) {
		$ids = array_map(
			function( $code ) {
				$discount = edd_get_discount_by_code( $code );
				return isset( $discount->ID ) ? $discount->ID : null;
			},
			explode( ',', $discounts )
		);

		$ids = array_filter( $ids );

		return $ids;
	}
}
