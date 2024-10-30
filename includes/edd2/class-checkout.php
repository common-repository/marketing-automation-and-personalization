<?php

namespace ConvesioConvert\EDD2;

use EDD_Payment;
use ConvesioConvert\EDD\Checkout as New_EDD_Checkout;

class Checkout extends New_EDD_Checkout {

	/** @var EDD_Payment */
	protected $order;

	/** @return EDD_Payment */
	protected function get_order_from_order_id( $order_id ) {
		return new EDD_Payment( $order_id );
	}

	protected function get_order_details_for_data_layer() {
		return Commerce_Data_Layer::get_order_details_for_data_layer( $this->order );
	}

	public function fixup_data_layer_commerce_details() {
		$orders = new User_Orders();
		add_filter( 'convesioconvert_user_purchased_product_ids_edd', array( $orders, 'list_of_purchased_product_ids' ) );
		add_filter( 'convesioconvert_user_total_purchased_items_edd', array( $orders, 'get_total_purchased_items' ) );
	}

	protected function order_as_array() {
		// Select only main order data.
		$order_data = (object) $this->order->array_convert();

		// Plus the id.
		$order_data->id = $this->order->ID;

		return $order_data;
	}

	protected function get_order_meta( $meta_key ) {
		$this->order->get_meta( $meta_key );
	}

	protected function add_unique_order_meta( $meta_key, $meta_value ) {
		return $this->order->add_meta( $meta_key, $meta_value, true );
	}
}
