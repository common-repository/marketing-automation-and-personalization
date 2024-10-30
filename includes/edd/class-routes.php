<?php

namespace ConvesioConvert\EDD;

use ConvesioConvert\Authorization\REST_Token_Verifier;

class Routes {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$base_route              = 'convesioconvert/v1';
		$token_verifier_callback = array( new REST_Token_Verifier(), 'verify_token' );

		register_rest_route(
			$base_route,
			'sync/products/edd',
			array(
				'methods'             => 'GET',
				'callback'            => array( new Downloads(), 'index' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'sync/orders/edd',
			array(
				'methods'             => 'GET',
				'callback'            => array( new Orders(), 'index' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'sync/guest-customers/edd',
			array(
				'methods'             => 'GET',
				'callback'            => array( new Guest_Customers_Sync(), 'index' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'coupons/edd',
			array(
				'methods'             => 'POST',
				'callback'            => array( new Discounts(), 'index' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'coupons/edd/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( new Discounts(), 'add' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'coupons/edd/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( new Discounts(), 'add' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'coupons/edd/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( new Discounts(), 'remove' ),
				'permission_callback' => $token_verifier_callback,
			)
		);
	}

}
