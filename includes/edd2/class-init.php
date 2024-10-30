<?php

namespace ConvesioConvert\EDD2;

use ConvesioConvert\EDD\Init as New_EDD_Init;

class Init extends New_EDD_Init {

	/**
	 * Looks exactly like the parent class, but in fact this is initializing classes from the EDD2 namespace.
	 *
	 * @noinspection PhpMissingParentConstructorInspection
	 */
	public function __construct() {
		// DO nothing if EDD is not active.
		if ( ! is_edd_active() ) {
			return;
		}

		new Checkout();
		new Commerce_Data_Layer();
		new Discount_Handler();
		new Routes();

		$this->add_hooks();
	}

	public function attach_customer_data( $ecommerce_data, $user_id, $is_sync ) {
		$customer              = new Customer( $user_id );
		$ecommerce_data['edd'] = $customer->get_customer( $is_sync );
		return $ecommerce_data;
	}

	public function add_post_type_modifications( $post_types ) {
		$post_types[] = 'download';
		$post_types[] = 'edd_payment';
		return $post_types;
	}

}
