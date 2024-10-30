<?php

namespace ConvesioConvert\Authorization;

use WP_Error;

class REST_Token_Site_Verifier {

	/** @var string */
	private $token;

	public function __construct( $token ) {
		$this->token = $token;
	}

	/**
	 * Verifies if the site_id inside the token matches the current integrated site id.
	 *
	 * @return bool
	 */
	public function check_token_issued_for_integrated_site_id() {
		$token_array     = $this->parse_jwt_token_body();
		$token_site_id   = isset( $token_array['sub'] ) ? (int) $token_array['sub'] : 0;
		$current_site_id = (int) get_option( 'convesioconvert_site_id' );

		// Ensure $token_site_id is not empty for additional security.
		// Already cast to int to ignore invalid IDs and invalid strings such as "null" or "0".
		return $token_site_id && $token_site_id === $current_site_id;
	}

	private function parse_jwt_token_body() {
		$token_parts  = explode( '.', $this->token );
		$token_middle = isset( $token_parts[1] ) ? $token_parts[1] : '';

		return json_decode( $this->base64_decode_url_friendly( $token_middle ), true );
	}

	private function base64_decode_url_friendly( $data ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( str_replace( array( '-', '_', '~' ), array( '+', '/', '=' ), $data ), true );
	}

	public static function site_id_error() {
		return new WP_Error(
			'site_id_not_valid',
			esc_html__( 'Invalid Site ID', 'convesioconvert' ),
			array( 'status' => 403 )
		);
	}
}
