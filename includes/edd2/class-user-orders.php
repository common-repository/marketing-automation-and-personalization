<?php

namespace ConvesioConvert\EDD2;

use EDD_Customer;
use ConvesioConvert\Ecommerce\User_Order_Manager;
use ConvesioConvert\Session_Manager;
use function ConvesioConvert\get_user_type;

class User_Orders extends User_Order_Manager {
	const OPTION_PREFIX = 'convesioconvert_edd_cache_';
	const META_PREFIX   = '_' . self::OPTION_PREFIX;

	public function get_user_order_count( $user_id ) {

		if ( ! $user_id || ! is_edd_active() ) {
			return 0;
		}

		// Set true to use user_id, otherwise it is considered as customer id from edd table.
		$customer = new EDD_Customer( $user_id, true );

		return $customer->purchase_count;
	}

	public function user_has_ordered_by_user( $user_id ) {
		return (bool) $this->get_user_order_count( $user_id );

	}

	public function list_of_purchased_product_ids() {
		if ( ! is_edd_active() ) {
			return array();
		}

		$products  = array();
		$user_id   = null;
		$email     = null;
		$cache_key = 'edd_downloads_list';

		if ( 'guest' !== get_user_type() ) {
			$user_id  = get_current_user_id();
			$customer = new EDD_Customer( $user_id, true );
		} else {
			$session = new Session_Manager();
			$email   = $session->get_effective_user_property( 'email' );

			if ( $this->has_guest_cache( $email, $cache_key ) ) {
				return $this->get_guest_cache( $email, $cache_key ) ?: array();
			}

			// We don't save cart in meta for this user. Possibly we can get it from cookie.
			$customer = new EDD_Customer( $email );
		}

		$payments = $customer->get_payments();

		foreach ( $payments as $order ) {
			foreach ( $order->cart_details as $item ) {
				$products[ $item['id'] ] = true;
			}
		}

		// Front-end 'Purchased Product' rule execution expects these to be strings, ensure they are strings
		$products_list = array_map( 'strval', array_keys( $products ) );

		if ( 'guest' !== get_user_type() ) {
			$this->set_user_cache( $user_id, $cache_key, $products_list );
		} else {
			$this->set_guest_cache( $email, $cache_key, $products_list );
		}

		return $products_list;
	}

	/**
	 * Get current user or guest last order.
	 *
	 * @return array
	 */
	public function get_last_order() {
		$order = Commerce_Data_Layer::empty_order( 'edd' );

		if ( ! is_edd_active() ) {
			return $order;
		}

		$cache_key = 'last_order';

		if ( 'guest' !== get_user_type() ) {
			$user_id = get_current_user_id();

			if ( $this->has_user_cache( $user_id, $cache_key ) ) {
				return $this->get_user_cache( $user_id, $cache_key ) ?: $order;
			}

			$customer = new EDD_Customer( $user_id, true );

		} else {
			// Effective user ID for guest-customer.
			$session = new Session_Manager();
			$user_id = $session->get_effective_user_property( 'userId' );
			$email   = $session->get_effective_user_property( 'email' );

			if ( ! $email ) {
				return $order;
			}
			if ( $this->has_guest_cache( $email, $cache_key ) ) {
				return $this->get_guest_cache( $email, $cache_key ) ?: $order;
			}

			$customer = new EDD_Customer( $email );
		}

		$edd_last_order = $this->get_edd_last_order( $customer );

		// Order didn't exists.
		if ( ! $edd_last_order ) {
			return $order;
		}

		$order = Commerce_Data_Layer::get_order_details_for_data_layer( $edd_last_order );

		if ( 'guest' !== get_user_type() ) {
			$this->set_user_cache( $user_id, $cache_key, $order );
		} else {
			$this->set_guest_cache( $email, $cache_key, $order );
		}

		return $order;
	}

	public function get_total_purchased_items() {
		if ( ! is_edd_active() ) {
			return 0;
		}

		$cache_key             = 'total_purchased_items';
		$total_purchased_items = 0;

		if ( 'guest' !== get_user_type() ) {
			$user_id = get_current_user_id();

			if ( $this->has_user_cache( $user_id, $cache_key ) ) {
				return $this->get_user_cache( $user_id, $cache_key ) ?: 0;
			}

			$customer = new EDD_Customer( $user_id, true );

		} else {
			// Effective user ID for guest-customer.
			$session = new Session_Manager();
			$user_id = $session->get_effective_user_property( 'userId' );
			$email   = $session->get_effective_user_property( 'email' );

			if ( ! $email ) {
				return 0;
			}
			if ( $this->has_guest_cache( $email, $cache_key ) ) {
				return $this->get_guest_cache( $email, $cache_key ) ?: 0;
			}

			$customer = new EDD_Customer( $email );
		}

		$payments = $customer->get_payments();

		foreach ( $payments as $payment ) {
			$order = edd_get_payment( $payment );

			foreach ( $order->cart_details as $item ) {
				$total_purchased_items += $item['quantity'];
			}
		}

		if ( 'guest' !== get_user_type() ) {
			$this->set_user_cache( $user_id, $cache_key, $total_purchased_items );
		} else {
			$this->set_guest_cache( $email, $cache_key, $total_purchased_items );
		}

		return (int) $total_purchased_items;
	}

	private function get_edd_last_order( $customer ) {
		$payments = $customer->get_payments();
		return end( $payments ); // Last order.
	}

}
