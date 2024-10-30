<?php

namespace ConvesioConvert\Form_Integration\Divi;

class Loader {

	private static $instance;

	public static function instantiate() {
		if ( null === self::$instance ) {
			self::$instance = new Loader();
		}

		return self::$instance;
	}

	public function __construct() {
		add_filter( 'et_core_get_third_party_components', array( $this, 'third_party_components_filter' ), 10, 2 );
		add_action( 'after_setup_theme', array( $this, 'wrap_providers' ), 12 );
	}

	public function third_party_components_filter( $components, $groups ) {
		if (
			( 'api/email' === $groups || empty( $groups ) || ( is_array( $groups ) && in_array( 'api/email', $groups, true ) ) )
			&& class_exists( '\\ET_Core_API_Email_Provider' )
			) {
			$components['convesioconvert'] = new Optin();
		}
		return $components;
	}

	public function wrap_providers() {
		// Bloom plugin support
		if ( isset( $GLOBALS['et_bloom'] ) ) {
			$GLOBALS['et_bloom']->providers = new Wrapper( $GLOBALS['et_bloom']->providers );
		}

		// Core API support
		if ( class_exists( '\\ET_Core_API_Email_Fields' ) && class_exists( '\\Closure' ) ) {
			$closure = function() {
				self::$_instance = new Wrapper( self::$_instance );
			};
			$bound   = $closure->bindTo( null, '\\ET_Core_API_Email_Providers' );
			$bound();
		}
	}
}
