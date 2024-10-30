<?php

namespace ConvesioConvert\Woocommerce;

use ConvesioConvert\Controller\User_Order_Controller;
use ConvesioConvert\Ecommerce\Customer as EcommerceCustomer;
use Throwable;
use WC_Customer;

class Customer implements EcommerceCustomer {

	/** @var WC_Customer|null $customer */
	private $customer;
	private $user_id;
	private $is_customer = false;
	private $order_count = 0;
	private $has_cart    = false;
	private $cart_items  = array();
	private $address     = array();

	public function __construct( $user_id = 0 ) {
		$this->user_id = $user_id;
	}

	public function get_customer( $is_sync ) {
		$this->setup_customer( $is_sync );

		return array(
			'is_customer' => $this->is_customer,
			'order_count' => $this->order_count,
			'has_cart'    => $this->has_cart,
			'cart_items'  => $this->cart_items,
			'address'     => $this->address,
		);
	}

	public function is_customer() {
		return $this->is_customer;
	}

	public function get_orders_count() {
		return $this->order_count;
	}

	public function has_cart() {
		return $this->has_cart;
	}

	public function get_cart_items() {
		return $this->cart_items;
	}

	public function get_address() {
		return $this->address;
	}

	private function setup_customer( $is_sync ) {
		try {
			$this->customer = new WC_Customer( $this->user_id );
		} catch ( Throwable $ex ) {
			$this->customer = null;
		}

		$orders            = new User_Order_Controller();
		$this->is_customer = $orders->user_has_ordered_by_user( $this->user_id );
		$this->order_count = $orders->get_user_order_count( $this->user_id );

		$this->cart_items = $is_sync ? $this->get_cart_items_for_sync() : $this->find_cart_items();
		$this->has_cart   = (bool) count( $this->cart_items );

		$this->address = $this->populate_customer_address();
	}

	/**
	 * Get WooCommerce user persistent cart items.
	 *
	 * We can't use the normal get_cart_items in wp-admin / rest api code due to:
	 * - https://wordpress.org/support/topic/wc-cart-is-null-in-custom-rest-api/
	 * There is a solution on GitHub, but we didn't try implementing it for now.
	 *
	 * @return array Cart items.
	 */
	private function get_cart_items_for_sync() {
		$cart_items = array();

		$wc_meta_key = '_woocommerce_persistent_cart_' . get_current_blog_id();
		$meta_value  = get_user_meta( $this->user_id, $wc_meta_key, true );

		if ( $meta_value ) {
			// Found a persistent cart; check if empty.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$cart = maybe_unserialize( $meta_value );

			// If a cart exists, $cart['cart'] will be set and contain an item otherwise will be empty.
			// If found a cart, return it; otherwise continue checking the rest of the metas.
			if ( ! empty( $cart['cart'] ) ) {
				$cart_items = array_column( $cart['cart'], 'product_id' );
			}
		}

		return $cart_items;
	}

	/**
	 * Get current user cart items.
	 *
	 * Retrieve the current visitor cart items via WC session, supports
	 * both guest and logged in user.
	 *
	 * @return array Cart items.
	 */
	private function find_cart_items() {
		$cart_items = array();
		// Requires WC to be loaded first.
		if ( ! class_exists( 'woocommerce' ) || ! wc()->cart ) {
			return $cart_items;
		}

		$wc_cart_items = wc()->cart->get_cart();
		if ( ! empty( $wc_cart_items ) ) {
			$cart_items = array_column( $wc_cart_items, 'product_id' );
		}

		return $cart_items;
	}

	private function populate_customer_address() {
		$full_address = implode(
			', ',
			array_filter(
				array(
					$this->customer->get_billing_country(),
					$this->customer->get_billing_address_1(),
					$this->customer->get_billing_address_2(),
					$this->customer->get_billing_city(),
					$this->customer->get_billing_state(),
					$this->customer->get_billing_postcode(),
					$this->customer->get_billing_company(),
				)
			)
		);

		return array(
			'fullAddress' => $full_address,
			'phone'       => $this->customer->get_billing_phone(),
			'country'     => $this->customer->get_billing_country(),
			'region'      => $this->customer->get_billing_state(),
			'city'        => $this->customer->get_billing_city(),
		);
	}

}
