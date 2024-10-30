<?php

namespace ConvesioConvert\Woocommerce;

class Info {

	public function status() {
		global $woocommerce;

		return array(
			'version'         => $woocommerce->version,
			'currency'        => get_woocommerce_currency(),
			'currency_symbol' => get_woocommerce_currency_symbol(),
		);
	}

	public function general_information() {
		return array(
			'currencies'       => get_woocommerce_currencies(),
			'currency_symbols' => get_woocommerce_currency_symbols(),
			'payment_gateways' => $this->get_woocommerce_payment_gateways(),
		);
	}

	/**
	 * Returns Woocommerce Payment Gateways in the order defined in its UI.
	 *
	 * @return array
	 */
	private function get_woocommerce_payment_gateways() {
		/** @var \WooCommerce $woocommerce */
		global $woocommerce;

		$gateways           = array();
		$available_gateways = wp_list_pluck( $woocommerce->payment_gateways()->get_available_payment_gateways(), 'id' );

		/** @var \WC_Payment_Gateway $gateway */
		foreach ( $woocommerce->payment_gateways()->payment_gateways() as $gateway ) {
			$gateways[] = array(
				'id'        => $gateway->id,
				'title'     => $gateway->method_title, // More complete than `$gateway->title`
				'enabled'   => 'yes' === $gateway->enabled,
				'available' => isset( $available_gateways[ $gateway->id ] ),
			);
		}

		return $gateways;
	}

}
