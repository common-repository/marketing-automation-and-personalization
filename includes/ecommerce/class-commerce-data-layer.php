<?php

namespace ConvesioConvert\Ecommerce;

use function ConvesioConvert\get_user_type;

abstract class Commerce_Data_Layer {

	const PLATFORM_KEY = '';

	public function __construct() {
		add_filter( 'convesioconvert_data_layer_commerce_entry', array( $this, 'generate_commerce_entry' ), 10 );
	}

	/**
	 * Generate the window._convesioconvert.commerce entry for the specific ecommerce platform.
	 *
	 * @return array
	 */
	public function generate_commerce_entry( $data_layer_commerce ) {
		$user_type    = get_user_type();
		$current_user = ( 'guest' === $user_type ) ? null : wp_get_current_user();

		if ( $current_user ) {
			$customer = $this->get_ecommerce_customer( $current_user->ID );
			$customer->get_customer( false );
			$cart_items = $customer->get_cart_items();
			$has_cart   = $customer->has_cart();
		} else {
			$cart_items = array();
			$has_cart   = false;
		}
		$cart = $this->get_cart_data();
		if ( count( $cart ) > 0 ) {
			foreach ( $cart as $item ) {
				$cart_items[] = isset( $item['product_id'] ) ? $item['product_id'] : null;
			}
			$cart_items = array_filter( $cart_items );
			$has_cart   = true;
		}

		$orders     = $this->get_ecommerce_order_manager();
		$last_order = $orders->get_last_order();

		$data_layer_commerce[ static::PLATFORM_KEY ] = array(
			'cart'               => $cart,
			'cartItems'          => $cart_items,
			'hasCart'            => $has_cart,
			'lastOrder'          => $last_order,
			'noOfOrders'         => $current_user ? (int) esc_html( $orders->get_user_order_count( $current_user->ID ) ) : 0,
			'noOfPurchasedItems' => (int) apply_filters(
				'convesioconvert_user_total_purchased_items_' . static::PLATFORM_KEY,
				$orders->get_total_purchased_items()
			),
			'products'           => apply_filters(
				'convesioconvert_user_purchased_product_ids_' . static::PLATFORM_KEY,
				$orders->list_of_purchased_product_ids()
			),
		);

		return $data_layer_commerce;
	}

	/**
	 * @param $user_id
	 *
	 * @return Customer
	 */
	abstract protected function get_ecommerce_customer( $user_id );

	abstract protected function get_cart_data();

	/**
	 * @return User_Order_Manager
	 */
	abstract protected function get_ecommerce_order_manager();

	/**
	 * @param $platform
	 *
	 * @return array
	 */
	public static function empty_order( $platform ) {
		return array(
			'platform'      => $platform,
			'paymentMethod' => '',
			'currency'      => '',
			'totalAmount'   => 0,
			'itemCount'     => 0,
			'purchasedAt'   => '',
			'items'         => array(),
			'categories'    => array(),
		);
	}

	/**
	 * Get order details in a format suitable for using as the 'lastOrder' in data layer.
	 *
	 * @param object $order
	 *
	 * @return array
	 */
	abstract public static function get_order_details_for_data_layer( $order );

}
