<?php

namespace ConvesioConvert;

/**
 * Retrieves analytics session details from cookies.
 */
class Session_Manager {
	/**
	 * The parsed contents of the cookie. Defined `static` as this should be a singleton;
	 * shared among all instances of the class.
	 *
	 * The two keys overrideUser/overrideCommerce exist on the cookie in case of guest customers. If the plugin stores
	 * anything in those overrides, the FE executor script won't pick up and update self::$cookie['overrideUser'] or
	 * ['overrideCommerce'] until the next page refresh. (And the plugin does not write out the cookie changes to
	 * browser neither.)
	 * So in case the plugin overrides them, we store them inside the cookie in the same place the script would have
	 * stored them, for the plugin to have access to them for the duration of current request, to be able to read them
	 * via `get_effective_*` functions of this class.
	 * Normal access to WP_User object properties won't include the overridden props, e.g first name, etc.
	 *
	 * @var array Will be initialized in constructor and won't remain null.
	 */
	private static $cookie = null;

	public function __construct() {
		if ( null !== self::$cookie ) {
			// Treat it as a singleton, self::$cookie is shared among all callers and should not be re-initialized.
			return;
		}

		$this->get_analytics_cookie();

		if ( ! self::$cookie ) {
			// Cookie not found, or not sent from browser; should log to sentry.
			self::$cookie = array();
		}

		// To ensure reading from these values does not result in errors or warnings.
		if ( empty( self::$cookie['overrideUser'] ) ) {
			self::$cookie['overrideUser'] = array();
		}

		if ( empty( self::$cookie['overrideCommerce'] ) ) {
			self::$cookie['overrideCommerce'] = array();
		}
	}

	/**
	 * Get the permanent client id of the visitor
	 *
	 * @return string|null
	 */
	public function get_client_id() {
		return isset( self::$cookie['clientId'] ) ? self::$cookie['clientId'] : null;
	}

	/**
	 * Get the temporary session id of the visitor
	 *
	 * @return string|null
	 */
	public function get_session_id() {
		return isset( self::$cookie['sessionId'] ) ? self::$cookie['sessionId'] : null;
	}

	/**
	 * Put a reset session id request at the footer which will be picked up by the script.
	 *
	 * Does NOT change the session ID for the duration of the current request. But if FE is going to use it again,
	 * it should reset, like {@see self::$cookie}'s overrideUser/overrideCommerce.
	 *
	 * @param $session_id
	 * @param string $reason
	 */
	public function reset_session_id( $session_id, $reason ) {
		$session_reset = array(
			'reason'    => $reason,
			'sessionId' => $session_id,
		);

		$echo_reset = function () use ( $session_reset ) {
			wp_localize_script( 'convesioconvert-if-then', '_convesioconvertSessionReset', $session_reset );
		};

		add_action( 'wp_enqueue_scripts', $echo_reset, 8 );
	}

	/**
	 * Override the user properties of the visitor; e.g upgrade them to a Guest Customer
	 *
	 * @param array $props Properties to override; only non-empty props will be overridden.
	 * @param bool $reset If set to false, adds new properties to te override cookie without clearing it.
	 */
	public function override_user_props( $props, $reset = true ) {
		$user_override = array_filter( $props );

		if ( ! $reset ) {
			$user_override = array_merge( self::$cookie['overrideUser'], $user_override );
		}

		self::$cookie['overrideUser'] = $user_override;

		$echo_override = function () use ( $user_override ) {
			wp_localize_script( 'convesioconvert-if-then', '_convesioconvertUserOverride', $user_override );
		};

		add_action( 'wp_enqueue_scripts', $echo_override, 8 );
	}

	/**
	 * Override the commerce properties of the visitor; similar to {@see override_user_props()}
	 *
	 * @param string $platform_key
	 * @param array $props Properties to override; only non-empty props will be overridden.
	 * @param bool $reset If set to false, adds new properties to te override cookie without clearing it.
	 */
	public function override_commerce_props( $platform_key, $props, $reset = true ) {
		$commerce_override = array(
			// Do not use 'array_filter' in case of Ecommerce overrides;
			// all attributes have to be set for consistency.
			$platform_key => $props,
		);

		if ( ! $reset ) {
			$commerce_override = array_merge( self::$cookie['overrideCommerce'], $commerce_override );
		}

		self::$cookie['overrideCommerce'] = $commerce_override;

		$echo_override = function () use ( $commerce_override ) {
			wp_localize_script( 'convesioconvert-if-then', '_convesioconvertCommerceOverride', $commerce_override );
		};

		add_action( 'wp_enqueue_scripts', $echo_override, 8 );
	}

	/**
	 * Gets the effective overridden user property of Guest Subscribers or Guest Customers.
	 * This is set by the FE script on popup form subscription, or by the plugin on Guest Checkout.
	 *
	 * @param string $property
	 *
	 * @return mixed|null
	 */
	public function get_effective_user_property( $property ) {
		if ( isset( self::$cookie['overrideUser'][ $property ] ) ) {
			return self::$cookie['overrideUser'][ $property ];
		}

		return null;
	}

	/**
	 * Similar to {@see get_effective_user_property()}
	 *
	 * @param string $property
	 *
	 * @return mixed|null
	 */
	public function get_effective_commerce_property( $property ) {
		if ( isset( self::$cookie['overrideCommerce'][ $property ] ) ) {
			return self::$cookie['overrideCommerce'][ $property ];
		}

		return null;
	}

	/**
	 * Parses the analytics cookie
	 *
	 * @return void
	 */
	private function get_analytics_cookie() {
		self::$cookie = json_decode( filter_input( INPUT_COOKIE, 'outstand' ) ?: 'null', true );
	}
}
