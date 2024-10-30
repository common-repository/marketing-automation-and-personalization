<?php

namespace ConvesioConvert\Admin;

class Init {

	/** @var string  */
	const HOOK_SUFFIX = 'settings_page_convesioconvert-admin';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'activation_redirect' ) );
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'feedback' ) );
	}

	public function feedback() {
		new \ConvesioConvert\Admin\Feedback();
	}

	public function add_options_page() {
		add_options_page(
			esc_html__( 'ConvesioConvert Settings', 'convesioconvert' ),
			esc_html__( 'ConvesioConvert', 'convesioconvert' ),
			'manage_options',
			'convesioconvert-admin',
			array( $this, 'render_option_page' )
		);
	}

	/** @define "CONVESIOCONVERT_ADMIN_PATH" "" */
	public function render_option_page() {

		if ( Integration::is_integrated() ) {
			include_once CONVESIOCONVERT_ADMIN_PATH . 'views/integrated.php';
		} else {
			include_once CONVESIOCONVERT_ADMIN_PATH . 'views/welcome.php';
		}
	}

	public function enqueue_assets( $hook ) {
		// WordPress admin dashboard AND our Settings page in wp-admin
		if ( self::HOOK_SUFFIX === $hook || 'index.php' === $hook ) {
			wp_enqueue_script( 'convesioconvert-wordpress-dashboard' );

			$params = array(
				'nonce' => wp_create_nonce( 'convesioconvert_ajax' ),
			);

			wp_localize_script( 'convesioconvert-wordpress-dashboard', 'convesioconvert', $params );

		}

		wp_enqueue_script( 'convesioconvert-smart-rating' );

		// Our Settings page in wp-admin
		if ( self::HOOK_SUFFIX === $hook ) {
			wp_enqueue_script( 'convesioconvert-admin' );
			wp_enqueue_style( 'convesioconvert-admin' );

			$params = array(
				'nonce'           => wp_create_nonce( 'convesioconvert_ajax' ),
				'success_message' => __( 'Saved!', 'convesioconvert' ),
				'error_message'   => __( 'Error!', 'convesioconvert' ),
			);

			wp_localize_script( 'convesioconvert-admin', 'convesioconvert', $params );
		}
		wp_enqueue_style( 'convesioconvert-smart-rating' );
	}

	public function activation_redirect() {
		if ( ! get_transient( 'convesioconvert_activation_redirect' ) ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		delete_transient( 'convesioconvert_activation_redirect' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		wp_safe_redirect( self::convesio_convert_wp_admin_url() );

		exit;
	}

	/**
	 * Returns the URL of our Settings page in wp-admin.
	 *
	 * @return string
	 */
	public static function convesio_convert_wp_admin_url() {
		return admin_url( 'options-general.php?page=convesioconvert-admin' );
	}
}
