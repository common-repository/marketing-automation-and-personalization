<?php

namespace ConvesioConvert\Woocommerce;

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
			'sync/products/woo',
			array(
				'methods'             => 'GET',
				'callback'            => array( new \ConvesioConvert\Controller\Product_Controller(), 'index' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		$orders_controller_callback = woocommerce_uses_hpos()
			? array( new \ConvesioConvert\Controller\Orders_HPOS_Controller(), 'index' )
			: array( new \ConvesioConvert\Controller\Orders_Controller(), 'index' );

		register_rest_route(
			$base_route,
			'sync/orders/woo',
			array(
				'methods'             => 'GET',
				'callback'            => $orders_controller_callback,
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'sync/guest-customers/woo',
			array(
				'methods'             => 'GET',
				'callback'            => array( new \ConvesioConvert\Woocommerce\Guest_Customers_Sync(), 'index' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'coupons/woo',
			array(
				'methods'             => 'POST',
				'callback'            => array( new \ConvesioConvert\Controller\Coupon_Controller(), 'retrieve' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'coupons/woo/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( new \ConvesioConvert\Controller\Coupon_Controller(), 'create' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'coupons/woo/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( new \ConvesioConvert\Controller\Coupon_Controller(), 'update' ),
				'permission_callback' => $token_verifier_callback,
			)
		);

		register_rest_route(
			$base_route,
			'coupons/woo/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( new \ConvesioConvert\Controller\Coupon_Controller(), 'delete' ),
				'permission_callback' => $token_verifier_callback,
			)
		);
	}

}
