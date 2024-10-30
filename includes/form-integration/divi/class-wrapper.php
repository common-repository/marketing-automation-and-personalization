<?php

namespace ConvesioConvert\Form_Integration\Divi;

class Wrapper {
	private $providers = null;

	public function __construct( $providers ) {
		$this->providers = $providers;
	}

	public function __call( $name, $args ) {
		if ( null !== $this->providers ) {
			$result = call_user_func_array(
				array(
					$this->providers,
					$name,
				),
				$args
			);
			if ( 'get' === $name && count( $args ) > 1 && '' !== $args[1] && false !== $result && null !== $result ) {
				// The new ET_Core_API_Email_Providers class does not handle this, so we will until they fix that bug.
				$result->set_account_name( $args[1] );
			}
			return $result;
		}
	}
}
