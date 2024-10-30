<?php

namespace ConvesioConvert\Admin;

use ConvesioConvert\GraphQL_Client;
use function ConvesioConvert\array_keys_exist;
use function ConvesioConvert\verify_post_nonce;

class Health_Check {
	/**
	 * Health_Check constructor.
	 */
	public function __construct() {
		add_action( 'load-' . Init::HOOK_SUFFIX, array( $this, 'check_health_status' ), 30 );
		add_action( 'load-index.php', array( $this, 'emit_health_status_dashboard_notice' ), 30 );
		add_action( 'wp_ajax_convesioconvert_get_health_level', array( $this, 'get_health_level' ) );
	}

	/**
	 * Returns true if health check is in progress on server, OR we have not had a successful check since integration.
	 *
	 * @return bool
	 */
	public static function is_in_progress() {
		$health_status    = self::get_health_status();
		$backend_checking = $health_status['isInProgress'] || empty( $health_status['errorLevel'] );
		return $backend_checking;
	}

	/**
	 * Returns whether there are Errors in health check (does not include Warnings).
	 *
	 * @return bool
	 */
	public static function has_errors() {
		$health_status = self::get_health_status();
		return 'ERROR' === $health_status['errorLevel'];
	}

	/**
	 * Returns false if site has been deleted from backend.
	 *
	 * @return bool
	 */
	public static function site_exists() {
		$health_status = self::get_health_status();
		$message_key   = $health_status['messageKey'];
		return 'site-removed' !== $message_key && 'site-not-found' !== $message_key;
	}

	/**
	 * Removes any health check data.
	 */
	public static function remove_health_check_data() {
		delete_transient( 'convesioconvert_dashboard_health_status' );
		delete_transient( 'convesioconvert_health_status_fresh' );
		delete_transient( 'convesioconvert_health_status' );
	}

	/**
	 * AJAX call that returns health error level.
	 */
	public function get_health_level() {
		if ( ! verify_post_nonce( 'convesioconvert_ajax' ) ) {
			wp_send_json_error();
		}

		$health_status = self::get_health_status();

		$response = array(
			'success' => true,
			'level'   => $health_status['errorLevel'],
		);

		wp_send_json( $response );
	}

	/**
	 * Checks site health and installs notices if it found any problem.
	 */
	public function check_health_status() {
		self::emit_health_notice( self::get_health_status() );
	}

	/**
	 * Emits a onetime notice if health_status is ERROR, WARNING, or INFO.
	 *
	 * @param array $health_status
	 */
	private function emit_health_notice( $health_status ) {
		if ( 'sync-progress' === $health_status['messageKey'] ) {
			return; // Don't show this message
		}

		switch ( $health_status['errorLevel'] ) {
			case 'ERROR':
				$options = array(
					'level'  => 'error',
					'title'  => __( 'ConvesioConvert: Something went wrong' ),
					'escape' => false,
				);
				Notices::onetime_notice( $this->health_message( $health_status ), $options );
				break;
			case 'WARNING':
				$options = array(
					'level'  => 'warning',
					'title'  => __( 'ConvesioConvert: Warning' ),
					'escape' => false,
				);
				Notices::onetime_notice( $this->health_message( $health_status ), $options );
				break;
			case 'INFO':
				$options = array(
					'level'  => 'info',
					'escape' => false,
				);
				Notices::onetime_notice( $this->health_message( $health_status ), $options );
				break;
			case 'SUCCESS':
				break;
			case '':
			default:
				// In progress / error in response
		}
	}

	/**
	 * Retrieves
	 * @param array $health_status
	 *
	 * @return string
	 */
	private function health_message( $health_status ) {
		$message = $health_status['message'];

		if ( $health_status['messageKey'] ) {
			$message .= sprintf(
				' <a target="_blank" href="%s">%s</a>.',
				esc_url( 'https://convesio.com/knowledgebase/article/integration-troubleshooting/#' . $health_status['messageKey'] ),
				esc_html__( 'Learn more', 'convesioconvert' )
			);
		}

		return $message;
	}

	/**
	 * Retrieves the site health status from backend.
	 *
	 * Lightweight version of get_health_status for use in admin dashboard.
	 * Does not initiate a health check mutation, and caches the results for a longer time (1 minute).
	 */
	public function emit_health_status_dashboard_notice() {
		$health_status = get_transient( 'convesioconvert_dashboard_health_status' );

		if ( ! $health_status ) {
			$health_status = self::query_health_check();
			set_transient( 'convesioconvert_dashboard_health_status', $health_status, MINUTE_IN_SECONDS );
		}

		self::emit_health_notice( $health_status );
	}

	/**
	 * Retrieves the site health status from backend and caches it.
	 *
	 * @return array
	 */
	private static function get_health_status() {
		$health_status = get_transient( 'convesioconvert_health_status' );

		if ( ! $health_status ) {
			self::initiate_health_check();
			$health_status = self::query_health_check();

			self::adjust_health_check_freshness( $health_status['errorLevel'] );

			set_transient( 'convesioconvert_health_status', $health_status, 5 );
		}

		return $health_status;
	}

	/**
	 * Queries the site health status from backend and applies fix ups if error responses are received.
	 *
	 * @return array
	 */
	private static function query_health_check() {
		$health_status = GraphQL_Client::make()
			->site_health_status()
			->execute();

		// translators: %s is an error code like 'incompatible_data'.
		$error_message = esc_html__(
			'It seems like ConvesioConvert server is not accessible (%s). Please check your network.'
		);

		if ( $health_status instanceof \WP_Error ) {
			$backend_error_code = $health_status->get_error_message();

			switch ( $backend_error_code ) {
				case 'site_removed':
					$message     = esc_html__( 'You have deleted your site from ConvesioConvert dashboard and it has been scheduled for deletion. To bring back the site, contact ConvesioConvert support.', 'convesioconvert' );
					$message_key = 'site-removed';
					break;
				case 'site_not_found':
					$message     = esc_html__( 'Your site has been permanently deleted from ConvesioConvert.', 'convesioconvert' );
					$message_key = 'site-not-found';
					break;
				default:
					$message     = sprintf( $error_message, $health_status->get_error_code() );
					$message_key = 'backend-request-error';
					break;
			}

			$health_status = array(
				'errorLevel'   => 'ERROR',
				'message'      => $message,
				'messageKey'   => $message_key,
				'isInProgress' => false,
			);
		}

		$required_keys = array( 'errorLevel', 'message', 'messageKey', 'isInProgress' );
		if ( ! array_keys_exist( $required_keys, $health_status ) ) {
			$health_status = array(
				'errorLevel'   => 'ERROR',
				'message'      => sprintf( $error_message, 'incompatible_data' ),
				'messageKey'   => 'backend-request-error',
				'isInProgress' => false,
			);
		}

		return $health_status;
	}

	/**
	 * Calls the health check mutation on backend, throttling the requests.
	 *
	 * @param bool $force_now
	 */
	public static function initiate_health_check( $force_now = false ) {
		if ( $force_now ) {
			// Invalidate the query caches. Without these admin UI won't show the paused status for 1 minute.
			delete_transient( 'convesioconvert_dashboard_health_status' );
			delete_transient( 'convesioconvert_health_status' );
		}

		$fresh = get_transient( 'convesioconvert_health_status_fresh' );

		if ( 'yes' === $fresh && ! $force_now ) {
			return;
		}

		GraphQL_Client::make()
			->site_health_check()
			->set( 'forceNow', $force_now )
			->execute();

		set_transient( 'convesioconvert_health_status_fresh', 'yes', MINUTE_IN_SECONDS );
	}

	/**
	 * If health check is not successful, tenant may want to reload the page to trigger the health check mutation again,
	 * so we clear the freshness flag. We don't need to trigger the mutation if check was successful or already running.
	 *
	 * @param string $error_level
	 */
	private static function adjust_health_check_freshness( $error_level ) {
		if ( in_array( $error_level, array( 'WARNING', 'INFO', 'ERROR' ), true ) ) {
			delete_transient( 'convesioconvert_health_status_fresh' );
		}
	}
}
