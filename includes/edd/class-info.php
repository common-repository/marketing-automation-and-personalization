<?php

namespace ConvesioConvert\EDD;

class Info {

	public function status() {
		return array(
			'version'         => EDD_VERSION,
			'currency'        => edd_get_currency(),
			'currency_symbol' => edd_currency_symbol(),
		);
	}

	public function general_information() {
		$currencies = edd_get_currencies();
		$symbols    = array();

		foreach ( $currencies as $currency => $name ) {
			$symbols[ $currency ] = edd_currency_symbol( $currency );
		}

		return array(
			'currencies'       => edd_get_currencies(),
			'currency_symbols' => $symbols,
			'payment_gateways' => $this->get_edd_payment_gateways(),
		);
	}

	private function get_edd_payment_gateways() {
		$payment_gateways = array();
		$getways          = edd_get_payment_gateways();
		$enabled_get_ways = edd_get_enabled_payment_gateways();

		foreach ( $getways as $key => $props ) {
			$payment_gateways[] = array(
				'id'        => $key,
				'title'     => edd_get_gateway_checkout_label( $key ),
				'enabled'   => edd_is_gateway_active( $key ),
				'available' => in_array( $key, array_keys( $enabled_get_ways ), true ),
			);
		}

		return $payment_gateways;
	}

}
