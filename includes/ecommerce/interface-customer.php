<?php

namespace ConvesioConvert\Ecommerce;

interface Customer {

	public function get_customer( $is_sync );

	public function is_customer();

	public function get_orders_count();

	public function has_cart();

	public function get_cart_items();

	public function get_address();

}
