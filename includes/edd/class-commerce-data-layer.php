<?php

namespace ConvesioConvert\EDD;

use EDD\Orders\Order;
use ConvesioConvert\Ecommerce\Commerce_Data_Layer as Commerce_Data_Layer_Base;

class Commerce_Data_Layer extends Commerce_Data_Layer_Base {

	const PLATFORM_KEY = 'edd';

	protected function get_ecommerce_customer( $user_id ) {
		return new Customer( $user_id );
	}

	protected function get_cart_data() {
		return array();
	}

	protected function get_ecommerce_order_manager() {
		return new User_Orders();
	}

	/**
	 * @param Order $order
	 *
	 * @return array
	 */
	public static function get_order_details_for_data_layer( $order ) {
		$last_order = array(
			'platform'      => 'edd',
			'paymentMethod' => $order->gateway,
			'currency'      => $order->currency,
			'purchasedAt'   => $order->date_completed,
			'totalAmount'   => $order->total,
			'itemCount'     => 0,
			'items'         => array(),
			'categories'    => array(),
		);

		$count = 0;
		foreach ( $order->get_items() as $item ) {
			$count += $item->quantity;

			$product_id                         = $item->product_id;
			$last_order['items'][ $product_id ] = $item->product_name;
			$categories                         = get_the_terms( $product_id, 'download_category' ) ?: array();
			$last_order['categories']           = $last_order['categories'] + wp_list_pluck( $categories, 'name', 'term_id' );

		}

		$last_order['itemCount'] = $count;

		return $last_order;
	}

}
