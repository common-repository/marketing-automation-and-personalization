<?php

namespace ConvesioConvert\Form_Integration;

class Utils {
	const CONSENT_GRANTED = 'granted';
	const CONSENT_DENIED  = 'denied';
	const CONSENT_UNKNOWN = 'unknown';

	const ACCEPTABLE_GRANTED_VALUES = array(
		self::CONSENT_GRANTED,
		'grant',
		'allow',
		'allowed',
		'permitted',
		'authorized',
		'checked',
		'ok',
		'yes',
		'on',
		'true',
		'1',
		'y',
		1,
		true,
	);

	const ACCEPTABLE_DENIED_VALUES = array(
		self::CONSENT_DENIED,
		'deny',
		'disallow',
		'disallowed',
		'not allowed',
		'not allow',
		'not grant',
		'not granted',
		'no',
		'off',
		'refused',
		'forbidden',
		'false',
		'0',
		0,
		false,
	);

	/** Same logic as BE ConsentEnum::guessConsentType(). */
	public static function guess_consent_type( $type ) {
		$type = is_string( $type ) ? strtolower( $type ) : $type;

		if ( in_array( $type, self::ACCEPTABLE_GRANTED_VALUES, true ) ) {
			return self::CONSENT_GRANTED;
		} elseif ( in_array( $type, self::ACCEPTABLE_DENIED_VALUES, true ) ) {
			return self::CONSENT_DENIED;
		}
		return self::CONSENT_UNKNOWN;
	}

	/**
	 * @param string $field_code One of '001', '002', etc. fields coming from BE FORM_BASIC_USER_ATTRIBUTES.
	 *
	 * @return array
	 */
	public static function form_data_entry( $field_code, $field_value ) {
		return array(
			'key'   => $field_code,
			'value' => $field_value,
			'type'  => Integration::instance()->get_attribute_type( $field_code ),
		);
	}
}
