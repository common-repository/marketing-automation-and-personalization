<?php

namespace ConvesioConvert\Admin;

use function ConvesioConvert\verify_post_nonce;

/**
 * Manages showing 3 types of admin notices: persistent 'Integrated' notices dismissible for each user also shown on WP
 * dashboard, and one-time notices that can be displayed in current or next request on our Settings page in wp-admin.
 * Public API is provided by the `public static` functions.
 */
class Notices {

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'emit_integrated_notice' ) );
		add_action( 'admin_notices', array( $this, 'emit_onetime_notices' ) );
		add_action( 'admin_notices', array( $this, 'emit_caching_plugin_notice' ) );

		add_action( 'wp_ajax_convesioconvert_dismiss_integrated_notice', array( $this, 'dismiss_integrated_notice' ) );
		add_action( 'wp_ajax_convesioconvert_dismiss_caching_plugin_notice', array( $this, 'dismiss_caching_plugin_notice' ) );
	}

	/**
	 * Returns true for main dashboard and our Settings page in wp-admin.
	 *
	 * @return bool
	 */
	private static function is_suitable_screen() {
		return \ConvesioConvert\is_screen( 'dashboard' ) || \ConvesioConvert\is_screen( Init::HOOK_SUFFIX );
	}

	/**
	 * Removes all notices from WP transient/meta.
	 *
	 * Cannot remove onetime notices that are already set to be emitted.
	 */
	public static function remove_notices() {

		self::disable_integrated_notice();
		self::remove_onetime_notices();
	}

	/**
	 * Displays integration notice, taking into account its dismissal status.
	 */
	public function emit_integrated_notice() {
		if ( ! self::is_suitable_screen() ) {
			return;
		}

		// This notice is shown on WP main dashboard page too, don't show to inappropriate users.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$integration_id = get_transient( 'convesioconvert_integration_success_id' );
		if ( ! $integration_id ) {
			return;
		}

		$current_user_id = get_current_user_id();
		$transient_name  = "convesioconvert_integrated_notice_dismissed_{$integration_id}_{$current_user_id}";
		$user_dismissed  = get_transient( $transient_name );
		if ( 'dismissed' === $user_dismissed ) {
			return;
		}

		/** @define "CONVESIOCONVERT_ADMIN_PATH" "" */
		include_once CONVESIOCONVERT_ADMIN_PATH . 'views/integrated-notice.php';
	}

	/**
	 * Displays caching plugin compatibility notice.
	 */
	public function emit_caching_plugin_notice() {
		if ( ! self::is_suitable_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$integration_id = get_transient( 'convesioconvert_integration_success_id' );
		if ( ! $integration_id ) {
			return;
		}

		$current_user_id = get_current_user_id();
		$transient_name  = "convesioconvert_caching_plugin_notice_dismissed_{$integration_id}_{$current_user_id}";
		$user_dismissed  = get_transient( $transient_name );
		if ( 'dismissed' === $user_dismissed ) {
			return;
		}

		$show_notice = false;
		// Unfully supported plugins that requires to show notices when activated.
		$plugins = array(
			'wpsc_init'                  => 'WP Super Cache',
			'WP_Rocket\Plugin'           => 'WP Rocket Cache',
			'LiteSpeed\Core'             => 'LiteSpeed Cache',
			'W3TC\Root_Loader'           => 'W3 Total Cache',
			'WpFastestCache'             => 'WP Fastest Cache',
			'WP_Optimize'                => 'WP Optimize',
			'Hummingbird\WP_Hummingbird' => 'Hummingbird',
			'Cache_Enabler'              => 'Cache Enabler',
		);

		foreach ( $plugins as $instance => $plugin_name ) {
			if ( class_exists( $instance ) || function_exists( $instance ) ) {
				$show_notice = true;
				break;
			}
		}

		if ( ! $show_notice ) {
			return;
		}

		/** @define "CONVESIOCONVERT_ADMIN_PATH" "" */
		include CONVESIOCONVERT_ADMIN_PATH . 'views/caching-plugin-notice.php';
	}

	/**
	 * Show integration success message to all admin users.
	 * - Don't use user_meta, use transients for everything so they can be cleaned up easily (e.g by 'optimizer' plugins
	 *   that remove transients).
	 * - Set a flag with a unique integration_id, all users that have not 'dismissed' the message for that particular
	 *   integration_id will continue to see the message.
	 */
	public static function enable_integrated_notice() {
		$integration_id = time();
		set_transient( 'convesioconvert_integration_success_id', $integration_id );
	}

	/**
	 * Remove integration success message forever.
	 */
	public static function disable_integrated_notice() {
		delete_transient( 'convesioconvert_integration_success_id' );
	}

	/**
	 * Dismisses the integration success notice for the current user only.
	 */
	public function dismiss_integrated_notice() {
		if ( ! verify_post_nonce( 'convesioconvert_ajax' ) ) {
			wp_send_json_error();
		}

		$integration_id  = get_transient( 'convesioconvert_integration_success_id' );
		$current_user_id = get_current_user_id();
		$transient_name  = "convesioconvert_integrated_notice_dismissed_{$integration_id}_{$current_user_id}";
		set_transient( $transient_name, 'dismissed' );
		wp_send_json_success();
	}

	/**
	 * Dismisses the caching plugin warning notice for the current user only.
	 */
	public function dismiss_caching_plugin_notice() {
		if ( ! verify_post_nonce( 'convesioconvert_ajax' ) ) {
			wp_send_json_error();
		}

		$integration_id  = get_transient( 'convesioconvert_integration_success_id' );
		$current_user_id = get_current_user_id();
		$transient_name  = "convesioconvert_caching_plugin_notice_dismissed_{$integration_id}_{$current_user_id}";
		set_transient( $transient_name, 'dismissed' );
		wp_send_json_success();
	}

	/**
	 * Displays one-time notices registered using {@see Notices::next_request_notice} function.
	 *
	 * These will be only displayed on our Settings page in wp-admin.
	 */
	public function emit_onetime_notices() {
		if ( ! self::is_suitable_screen() ) {
			return;
		}

		$notices = get_transient( 'convesioconvert_onetime_notices' );

		if ( is_array( $notices ) ) {
			foreach ( $notices as $notice ) {
				$output = self::notice_outputter( $notice );
				$output();
			}
		}

		self::remove_onetime_notices();
	}

	private static function remove_onetime_notices() {
		delete_transient( 'convesioconvert_onetime_notices' );
	}

	/**
	 * Puts a notice into a queue to be shown on next request (or current request, if called before admin_notices).
	 *
	 * @param string $text
	 * @param array $options
	 */
	public static function next_request_notice( $text, $options = array() ) {
		$notices    = get_transient( 'convesioconvert_onetime_notices' );
		$new_notice = array_merge( $options, array( 'text' => $text ) );

		if ( is_array( $notices ) ) {
			$notices = array_merge( $notices, array( $new_notice ) );
		} else {
			$notices = array( $new_notice );
		}

		set_transient( 'convesioconvert_onetime_notices', $notices );
	}

	/**
	 * Display an admin notice in the current request. Must be called before admin_notices hook is run.
	 *
	 * @param string $text
	 * @param array $options Array consisting of the following keys:
	 *                       title: Plain (non-html) text.
	 *                       level: Either 'success', 'info', 'warning', 'error' (default)
	 *                       escape: Either 'esc_html' (default) or false, to apply escaping to text.
	 */
	public static function onetime_notice( $text, $options = array() ) {
		$all_options = array_merge( $options, array( 'text' => $text ) );
		add_action( 'admin_notices', self::notice_outputter( $all_options ) );
	}

	/**
	 * Returns a function that prints a notice.
	 *
	 * @param array $options
	 *
	 * @return \Closure
	 */
	private static function notice_outputter( $options ) {
		return function () use ( $options ) {
			if ( ! self::is_suitable_screen() ) {
				return;
			}

			$notice = array(
				'text'  => '',
				'title' => '',
				'level' => isset( $options['level'] ) ? $options['level'] : 'error',
			);

			if ( ! empty( $options['title'] ) ) {
				$notice['title'] = esc_html( $options['title'] );
			}
			if ( ! empty( $options['text'] ) ) {
				$allowed_html = array(
					'a'      => array(
						'href'   => true,
						'title'  => true,
						'target' => true,
					),
					'b'      => array(),
					'i'      => array(),
					'em'     => array(),
					'strong' => array(),
					'br'     => array(),
				);

				$notice['text'] = wp_kses( $options['text'], $allowed_html );
			}

			/** @define "CONVESIOCONVERT_ADMIN_PATH" "" */
			include CONVESIOCONVERT_ADMIN_PATH . 'views/onetime-notice.php';
		};
	}
}
