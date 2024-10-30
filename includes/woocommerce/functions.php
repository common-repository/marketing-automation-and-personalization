<?php

namespace ConvesioConvert\Woocommerce;

use Automattic\WooCommerce\Utilities\OrderUtil;

function is_woocommerce_active() {
	return did_action( 'woocommerce_loaded' );
}

function woocommerce_uses_hpos() {
	return class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled();
}
