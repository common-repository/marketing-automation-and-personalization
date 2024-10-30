<?php

use ConvesioConvert\Authorization\REST_Token_Verifier;

add_action( 'rest_api_init', 'convesioconvert_register_api_routes' );

function convesioconvert_register_api_routes() {
	$token_verifier_callback = array( new REST_Token_Verifier(), 'verify_token' );
	$base_route              = 'convesioconvert/v1';

	register_rest_route(
		$base_route,
		'sync/users',
		array(
			'methods'             => 'GET',
			'callback'            => array( new ConvesioConvert\Controller\User_Controller(), 'index' ),
			'permission_callback' => $token_verifier_callback,
		)
	);

	register_rest_route(
		$base_route,
		'sync/pages',
		array(
			'methods'             => 'GET',
			'callback'            => array( new ConvesioConvert\Controller\Page_Controller(), 'index' ),
			'permission_callback' => $token_verifier_callback,
		)
	);

	register_rest_route(
		$base_route,
		'sync/posts',
		array(
			'methods'             => 'GET',
			'callback'            => array( new ConvesioConvert\Controller\Post_Controller(), 'index' ),
			'permission_callback' => $token_verifier_callback,
		)
	);

	register_rest_route(
		$base_route,
		'sync/terms',
		array(
			'methods'             => 'GET',
			'callback'            => array( new ConvesioConvert\Controller\Terms_Controller(), 'index' ),
			'permission_callback' => $token_verifier_callback,
		)
	);

	register_rest_route(
		$base_route,
		'sync/status',
		array(
			'methods'             => 'POST',
			'callback'            => array( new ConvesioConvert\Controller\Status_Controller(), 'status' ),
			'permission_callback' => $token_verifier_callback,
		)
	);

	register_rest_route(
		$base_route,
		'sync/extra',
		array(
			'methods'             => 'POST',
			'callback'            => array( new ConvesioConvert\Controller\Extra_Data_Controller(), 'index' ),
			'permission_callback' => $token_verifier_callback,
		)
	);
}
