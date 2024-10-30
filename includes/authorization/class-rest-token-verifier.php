<?php

namespace ConvesioConvert\Authorization;

use WP_Error;

class REST_Token_Verifier {

	/** @var string */
	private $token;

	/**
	 * @return true|WP_Error
	 */
	public function verify_token() {
		if ( CONVESIOCONVERT_TOKEN_VERIFY_DEBUG === 'debug' ) {
			return true;
		}

		$this->parse_token_from_header();
		if ( ! $this->token ) {
			return $this->token_error();
		}

		if ( $this->is_known_authorized_token() ) {
			return true;
		}

		$token_site_verifier = new REST_Token_Site_Verifier( $this->token );
		if ( ! $token_site_verifier->check_token_issued_for_integrated_site_id() ) {
			return REST_Token_Site_Verifier::site_id_error();
		}

		$response = $this->remote_call_verify_token();
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $this->token_verification_failed( $response, $body ) ) {
			return $this->token_error();
		}

		$this->save_as_known_authorized_token( $body );

		return true;
	}

	private function parse_token_from_header() {
		$this->token = '';

		if ( isset( $_SERVER['HTTP_X_CONVESIOCONVERT_TOKEN'] ) ) {
			$this->token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_CONVESIOCONVERT_TOKEN'] ) );
		}
	}

	private function is_known_authorized_token() {
		return get_transient( 'convesioconvert_token' ) === $this->token;
	}

	private function save_as_known_authorized_token( $body ) {
		if ( isset( $body['ttls'] ) ) {
			$ttls = min( 5 * MINUTE_IN_SECONDS, sanitize_text_field( $body['ttls'] ) );
			set_transient( 'convesioconvert_token', $this->token, $ttls );
		}
	}

	public static function expire_token() {
		delete_transient( 'convesioconvert_token' );
	}

	private function remote_call_verify_token() {
		$url = CONVESIOCONVERT_API_URL . '/v1/webhook/verify-token';

		return wp_remote_post(
			$url,
			array(
				'method'      => 'POST',
				'timeout'     => 15,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(),
				'body'        => array( 'token' => $this->token ),
				'cookies'     => array(),
			)
		);
	}

	private function token_verification_failed( $response, $body ) {
		return is_wp_error( $response ) ||
			200 !== $response['response']['code'] ||
			empty( $body['success'] ) ||
			'true' !== $body['success'];
	}

	private function token_error() {
		return new WP_Error(
			'invalid_token',
			esc_html__( 'Token not valid', 'convesioconvert' ),
			array( 'status' => 400 )
		);
	}
}
