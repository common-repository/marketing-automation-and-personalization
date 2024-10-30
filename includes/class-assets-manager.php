<?php

namespace ConvesioConvert;

class Assets_Manager {

	public $suffix = '.min';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ), 9 );
		add_action( 'wp_head', array( $this, 'prefetch_dns' ), 1 );

		if ( $this->is_script_debug() ) {
			$this->suffix = '';
		}
	}

	public static function is_script_debug() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	}

	public function register_public_assets() {
		$this->register_public_scripts();
	}

	public function register_public_scripts() {
		$site_id = get_option( 'convesioconvert_site_id', false );
		if ( ! $site_id ) {
			return;
		}

		$automations_url = CONVESIOCONVERT_API_URL . '/dynamic/sites/' . $site_id . '/automations.js';

		if ( 'guest' !== \ConvesioConvert\get_user_type() ) {
			$user_id = get_current_user_id();
			if ( $user_id ) {
				$automations_url = add_query_arg(
					array(
						'userId' => $user_id,
						'token'  => get_user_identity_token( $user_id ),
					),
					$automations_url
				);
			}
		}

		wp_register_script( 'convesioconvert-automations', $automations_url, array(), CONVESIOCONVERT_VERSION, false );
		wp_register_script( 'convesioconvert-if-then', CONVESIOCONVERT_RULE_CLIENT_URL, array( 'jquery', 'convesioconvert-automations' ), CONVESIOCONVERT_VERSION, false );
	}

	public function register_admin_assets() {
		$this->register_admin_scripts();
		$this->register_admin_styles();
	}

	public function register_admin_scripts() {
		wp_register_script(
			'convesioconvert-admin',
			CONVESIOCONVERT_ADMIN_ASSETS . 'js/convesioconvert-admin' . $this->suffix . '.js',
			array( 'jquery', 'jquery-ui-dialog', 'jquery-ui-tooltip' ),
			CONVESIOCONVERT_VERSION,
			true
		);

		wp_register_script(
			'convesioconvert-wordpress-dashboard',
			CONVESIOCONVERT_ADMIN_ASSETS . 'js/convesioconvert-wordpress-dashboard' . $this->suffix . '.js',
			array( 'jquery' ),
			CONVESIOCONVERT_VERSION,
			true
		);

		wp_register_script(
			'convesioconvert-smart-rating',
			CONVESIOCONVERT_ADMIN_ASSETS . 'js/convesioconvert-smart-rating' . $this->suffix . '.js',
			array( 'jquery' ),
			CONVESIOCONVERT_VERSION,
			true
		);

		wp_register_script(
			'convesioconvert-feedback',
			CONVESIOCONVERT_ADMIN_ASSETS . 'js/convesioconvert-feedback' . $this->suffix . '.js',
			array( 'jquery', 'jquery-ui-dialog' ),
			CONVESIOCONVERT_VERSION,
			true
		);
	}

	public function register_admin_styles() {
		wp_register_style(
			'convesioconvert-admin',
			CONVESIOCONVERT_ADMIN_ASSETS . 'css/convesioconvert-admin' . $this->suffix . '.css',
			array( 'wp-jquery-ui-dialog' ),
			CONVESIOCONVERT_VERSION,
			'all'
		);

		wp_register_style(
			'convesioconvert-smart-rating',
			CONVESIOCONVERT_ADMIN_ASSETS . 'css/convesioconvert-smart-rating' . $this->suffix . '.css',
			array( 'wp-jquery-ui-dialog' ),
			CONVESIOCONVERT_VERSION,
			'all'
		);

		wp_register_style(
			'convesioconvert-feedback',
			CONVESIOCONVERT_ADMIN_ASSETS . 'css/convesioconvert-feedback' . $this->suffix . '.css',
			array( 'wp-jquery-ui-dialog' ),
			CONVESIOCONVERT_VERSION,
			'all'
		);
	}

	public function prefetch_dns() {
		$site_id = get_option( 'convesioconvert_site_id', false );

		if ( $site_id ) {
			echo '<link rel="dns-prefetch" href="//api.' . esc_attr( CONVESIOCONVERT_SUFFIX ) . '" />';
		}
	}
}
