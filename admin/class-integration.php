<?php

namespace ConvesioConvert\Admin;

use ConvesioConvert\GraphQL_Client;
use function \ConvesioConvert\verify_get_nonce;
use function \ConvesioConvert\verify_post_nonce;

class Integration {
	public function __construct() {
		add_action( 'admin_post_convesioconvert_integrate', array( $this, 'integrate' ) );
		add_action( 'load-' . Init::HOOK_SUFFIX, array( $this, 'perform_backend_actions' ), 20 );
		add_action( 'wp_ajax_convesioconvert_remove_integration', array( $this, 'remove_integration' ) );

		add_action( 'wp_ajax_convesioconvert_pause_integration', array( $this, 'pause_integration' ) );
		add_action( 'wp_ajax_convesioconvert_resume_integration', array( $this, 'resume_integration' ) );

		add_action( 'load-' . Init::HOOK_SUFFIX, array( $this, 'emit_important_notices' ), 30 );
	}

	/**
	 * Does a quick check to find out whether a site is successfully integrated.
	 *
	 * @return bool
	 */
	public static function is_integrated() {
		return (int) get_option( 'convesioconvert_site_id', false );
	}

	/**
	 * Starts a (re-)integration process. Sets up variables and redirects to Onboarding start page.
	 */
	public function integrate() {
		if ( ! verify_post_nonce( 'convesioconvert_integrate_post' ) ) {
			Notices::next_request_notice( __( 'Request blocked due to security reasons. Please try again.', 'convesioconvert' ) );
			wp_safe_redirect( Init::convesio_convert_wp_admin_url() );
			exit;
		}

		$query_params = array(
			'trigger'     => 'wp-plugin-connect',
			'site-url'    => $this->basic_site_url(),
			'return-url'  => Init::convesio_convert_wp_admin_url(),
			'consent-key' => $this->create_consent_key(),
			'nonce'       => wp_create_nonce( 'integrate-wordpress' ),
		);

		// Can't use add_query_arg; need to escape the values
		$integrate_start_url = CONVESIOCONVERT_APP_URL . '/onboarding?' . http_build_query( $query_params );

		wp_redirect( $integrate_start_url ); // phpcs:ignore
		exit;
	}

	/**
	 * Processes pending actions that need to be done before loading our Settings page in wp-admin.
	 */
	public function perform_backend_actions() {
		$nonce_verified = verify_get_nonce( 'integrate-wordpress' );

		if ( ! isset( $_GET['gm-response-type'] ) ) {
			return;
		}

		if ( ! $nonce_verified ) {
			Notices::onetime_notice( __( 'Integration failed due to security reasons. Please try again.', 'convesioconvert' ) );
			return;
		}

		$response_type = sanitize_key( wp_unslash( $_GET['gm-response-type'] ) );

		if ( 'integrate-result' === $response_type ) {
			if ( isset(
				$_GET['consent-key'],
				$_GET['site-token'],
				$_GET['site-id'],
				$_GET['site-url'],
				$_GET['user-email'],
				$_GET['site-verification-secret']
			) ) {
				$consent_key = sanitize_text_field( wp_unslash( $_GET['consent-key'] ) );
				$site_token  = sanitize_text_field( wp_unslash( $_GET['site-token'] ) );
				$site_id     = sanitize_text_field( wp_unslash( $_GET['site-id'] ) );
				$site_url    = esc_url_raw( wp_unslash( $_GET['site-url'] ) );
				$user_email  = sanitize_email( wp_unslash( $_GET['user-email'] ) );

				$verification_secret = sanitize_text_field( wp_unslash( $_GET['site-verification-secret'] ) );

				$this->verify_and_finalize_integration( $consent_key, $site_token, $site_id, $site_url, $user_email, $verification_secret );
				return;
			}
		} elseif ( 'integrate-cancel-auth' === $response_type ) {
			self::expire_consent_key();
			Notices::onetime_notice( __( 'Integration failed due to cancelling the authorization.', 'convesioconvert' ) );
			return;
		}

		// Show an error if request was not processed.
		Notices::onetime_notice( __( 'Malformed request, please try again.', 'convesioconvert' ) );
	}

	/**
	 * Verifies and finalizes a (re-)integration request.
	 *
	 * @param $consent_key
	 * @param $site_token
	 * @param $site_id
	 * @param $site_url
	 * @param $user_email
	 */
	private function verify_and_finalize_integration( $consent_key, $site_token, $site_id, $site_url, $user_email, $verification_secret = '' ) {
		if ( ! $this->verify_consent_key( $consent_key ) ) {
			Notices::onetime_notice( __( 'Request authenticity could not be verified. Please retry the integration.', 'convesioconvert' ) );
			return;
		}

		$this->mark_had_integration();

		update_option( 'convesioconvert_site_token', $site_token );
		update_option( 'convesioconvert_verification_secret', $verification_secret );
		update_option( 'convesioconvert_site_id', $site_id );
		update_option( 'convesioconvert_site_url', $site_url );
		update_option( 'convesioconvert_user_email', $user_email );

		Notices::enable_integrated_notice();

		// Additional redirect to clear the query params
		wp_safe_redirect( Init::convesio_convert_wp_admin_url() );
		exit;
	}

	/**
	 * Removes site data saved upon integration.
	 *
	 * It does not remove the metadata set on WordPress entities by us.
	 */
	public static function remove_integration_data() {
		// Remove any pre-integration data
		self::expire_consent_key();

		// Remove data set by integration
		delete_option( 'convesioconvert_site_token' );
		delete_option( 'convesioconvert_verification_secret' );
		delete_option( 'convesioconvert_site_id' );
		delete_option( 'convesioconvert_site_url' );
		delete_option( 'convesioconvert_user_email' );

		// Remove data set by integration-related parts
		delete_option( 'convesioconvert_pause' );
	}

	public function remove_integration() {
		if ( ! verify_post_nonce( 'convesioconvert_ajax' ) ) {
			wp_send_json_error();
		}

		// Remove site information
		self::remove_integration_data();

		// Remove any data related to integration
		Health_Check::remove_health_check_data();

		// Remove any pending notices, including the 'Integrated' notice.
		Notices::remove_notices();

		// The 'Integration Removed' notice will be emitted by `emit_important_notices` hook.

		wp_send_json_success();
	}

	/**
	 * Pause the integration
	 */
	public function pause_integration() {
		update_option( 'convesioconvert_pause', 'paused' );

		GraphQL_Client::make()
			->site_pause()
			->set( 'pause', true )
			->execute();

		// Run health check to detect and show Paused notice.
		Health_Check::initiate_health_check( true );

		wp_send_json_success();
	}

	/**
	 * Un-pause the integration
	 */
	public function resume_integration() {
		delete_option( 'convesioconvert_pause' );

		GraphQL_Client::make()
			->site_pause()
			->set( 'pause', false )
			->execute();

		// Run health check to remove the Paused notice.
		Health_Check::initiate_health_check( true );

		wp_send_json_success();
	}

	/**
	 * Returns whether integration is paused
	 *
	 * @return bool
	 */
	public static function is_paused() {
		return 'paused' === get_option( 'convesioconvert_pause' );
	}

	/**
	 * Emits persistent notices on our Settings page in wp-admin.
	 */
	public function emit_important_notices() {
		if ( ! self::is_integrated() && self::had_integration() ) {
			Notices::onetime_notice(
				__( 'You have successfully removed the integration of ConvesioConvert from your site. Data synchronization and rule execution is stopped. Click on the Reintegrate button if you would like to integrate your site again.', 'convesioconvert' ),
				array(
					'title' => __( 'Integration Removed', 'convesioconvert' ),
					'level' => 'warning',
				)
			);
		}
	}

	/**
	 * Sets a flag that shows a successful integration has been done at least once.
	 *
	 * Note: We don't differentiate between data existence and having had an integration. But if there will be data
	 * before integration, integration should set its own variables AND call Data_Manager::mark_plugin_data_existence.
	 */
	private function mark_had_integration() {
		Data_Manager::mark_plugin_data_existence();
	}

	/**
	 * Returns whether there has been a successful integration at least once.
	 *
	 * @return bool
	 */
	public static function had_integration() {
		return Data_Manager::plugin_data_exists();
	}

	/**
	 * Get the site scheme and domain, removing any port number or path if present.
	 *
	 * @return string
	 */
	private function basic_site_url() {
		$url = wp_parse_url( site_url() );
		return $url['scheme'] . '://' . $url['host'];
	}

	/**
	 * Creates a cryptographically-safe random consent_key and stores it as a transient.
	 *
	 * @return string
	 */
	private function create_consent_key() {
		$consent_key = wp_generate_password( 64, false );

		set_transient( 'convesioconvert_consent_key', $consent_key, HOUR_IN_SECONDS );

		return $consent_key;
	}

	/**
	 * Verifies the passed $consent_key to ensure it is the same as the stored consent_key transient. If verification
	 * succeeds, expires the consent key.
	 *
	 * @param $test_consent_key
	 *
	 * @return bool
	 */
	private function verify_consent_key( $test_consent_key ) {
		$consent_key = get_transient( 'convesioconvert_consent_key' );

		if ( $consent_key === $test_consent_key ) {
			self::expire_consent_key();
			return true;
		}

		return false;
	}

	/**
	 * Removes the consent key to not be usable again.
	 */
	private static function expire_consent_key() {
		delete_transient( 'convesioconvert_consent_key' );
	}
}
