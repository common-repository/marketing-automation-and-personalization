<?php

namespace ConvesioConvert\Woocommerce;

use ConvesioConvert\Controller\User_Order_Controller;
use ConvesioConvert\Ecommerce\Commerce_Data_Layer as Commerce_Data_Layer_Base;
use WC_Order;

class Commerce_Data_Layer extends Commerce_Data_Layer_Base {

	const PLATFORM_KEY = 'woo';

	protected function get_ecommerce_customer( $user_id ) {
		return new Customer( $user_id );
	}

	protected function get_cart_data() {
		return WC()->cart->get_cart();
	}

	protected function get_ecommerce_order_manager() {
		return new User_Order_Controller();
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public static function get_order_details_for_data_layer( $order ) {
		$purchased_at = $order
			->get_date_created()
			->setTimezone( new \DateTimeZone( 'UTC' ) )
			->format( 'Y-m-d H:i:s' );

		$last_order = array(
			'platform'      => 'woo',
			'paymentMethod' => $order->get_payment_method(),
			'currency'      => $order->get_currency(),
			'purchasedAt'   => $purchased_at,
			'totalAmount'   => $order->get_total(),
			'itemCount'     => $order->get_item_count(),
			'items'         => array(),
			'categories'    => array(),
		);

		foreach ( $order->get_items() as $item ) {
			$product_id                         = $item->get_product_id();
			$last_order['items'][ $product_id ] = $item->get_name();
			$categories                         = get_the_terms( $product_id, 'product_cat' ) ?: array();
			$last_order['categories']           = $last_order['categories'] + wp_list_pluck( $categories, 'name', 'term_id' );
		}

		return $last_order;
	}

}
