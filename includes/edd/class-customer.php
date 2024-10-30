<?php

namespace ConvesioConvert\EDD;

use EDD\Customers\Customer_Address;
use EDD_Customer;
use ConvesioConvert\Ecommerce\Customer as EcommerceCustomer;

class Customer implements EcommerceCustomer {

	/** @var EDD_Customer */
	protected $customer;
	/** @var int|string A WordPress user ID (not EDD customer ID), or an email.  */
	protected $user;
	private $is_customer = false;
	private $order_count = 0;
	private $has_cart    = false;
	private $cart_items  = array();
	private $address     = array();

	public function __construct( $user = 0 ) {
		$this->user = $user;
	}

	public function get_customer( $is_sync ) {
		$this->setup_customer();

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

	private function setup_customer() {
		$this->customer    = new EDD_Customer( $this->user, is_numeric( $this->user ) );
		$this->is_customer = (bool) $this->customer->purchase_count;
		$this->order_count = (int) $this->customer->purchase_count;

		// Future note: Can differentiate sync scenarios like Woo.
		//   Can use the real-time edd_is_cart_empty() items for data layer purposes.
		if ( ! edd_is_cart_saving_disabled() ) {
			$this->cart_items = $this->get_cart_ids();
			$this->has_cart   = (bool) count( $this->cart_items );
		}

		$this->address = $this->populate_customer_address();
	}

	private function get_cart_ids() {
		// If customer is a guest, we don't have this meta and pass user as email, so no need to get user id.
		$cart = get_user_meta( $this->user, 'edd_saved_cart', true );

		return $cart
			? array_unique( array_map( 'intval', array_column( $cart, 'id' ) ) )
			: array();
	}

	protected function populate_customer_address() {
		$address = $this->customer->get_address();

		if ( ! ( $address instanceof Customer_Address ) ) {
			return array();
		}

		$full_address = implode(
			', ',
			array_filter(
				array(
					$address->country,
					$address->address,
					$address->address2,
					$address->city,
					$address->region,
					$address->postal_code,
				)
			)
		);

		return array(
			'fullAddress' => $full_address,
			'phone'       => null,
			'country'     => $address->country,
			'region'      => $address->region,
			'city'        => $address->city,
		);
	}

}
