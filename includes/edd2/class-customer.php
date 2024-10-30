<?php

namespace ConvesioConvert\EDD2;

use ConvesioConvert\Ecommerce\Customer as EcommerceCustomer;
use ConvesioConvert\EDD\Customer as ConvesioConvert_EDD_3_Customer;

class Customer extends ConvesioConvert_EDD_3_Customer implements EcommerceCustomer {

	protected function populate_customer_address() {
		if ( ! is_numeric( $this->user ) ) {
			// edd_get_customer_address() only works for WordPress user IDs.
			return array();
		}

		$address = edd_get_customer_address( $this->user );

		$full_address = implode(
			', ',
			array_filter(
				array(
					$address['country'] ?? null,
					$address['line1'] ?? null,
					$address['line2'] ?? null,
					$address['city'] ?? null,
					$address['state'] ?? null,
					$address['zip'] ?? null,
				)
			)
		);

		return array(
			'fullAddress' => $full_address,
			'phone'       => null,
			'country'     => $address['country'] ?? null,
			'region'      => $address['state'] ?? null,
			'city'        => $address['city'] ?? null,
		);
	}

}
