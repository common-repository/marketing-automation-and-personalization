<?php

namespace ConvesioConvert\Controller;

/**
 * Class for collecting data useful for business logic and analysis.
 *
 * Important: Collecting any additional data must be in accordance with the Privacy Policy and Terms of Use that users
 *            accept upon registration.
 */
class Extra_Data_Controller {
	public function index( $request ) {

		$info = array();

		$info_sets = array(
			'theme_information',
			'option_information',
			'plugins_information',
			'ecommerce_information',
		);

		foreach ( $info_sets as $info_set ) {
			try {
				$info = array_merge( $info, $this->$info_set() );
			} catch ( \Throwable $throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Not handling the exception; do not include the info.
			}
		}

		return $info;
	}

	private function theme_information() {
		$info  = array();
		$theme = wp_get_theme();

		if ( $theme && $theme->exists() ) {
			$info = array(
				'theme_name'    => $theme->get( 'Name' ),
				'theme_version' => $theme->get( 'Version' ),
			);
		}

		$info['theme_has_404_page'] = $this->theme_has_404_page() ? 'yes' : 'no';

		return $info;
	}

	private function theme_has_404_page() {
		return file_exists( get_template_directory() . '/404.php' ) ||
			file_exists( get_stylesheet_directory() . '/404.php' );
	}

	private function option_information() {
		return array(
			'option_blog_public' => (string) 0 === (string) get_option( 'blog_public' ) ? 'no' : 'yes',
		);
	}

	private function plugins_information() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$network_active_plugins = array_keys( get_site_option( 'active_sitewide_plugins' ) ?: array() );
		$blog_active_plugins    = get_option( 'active_plugins' ) ?: array();

		$active_plugins = array_unique( array_merge( $network_active_plugins, $blog_active_plugins ) );
		$all_plugins    = get_plugins();

		$plugins = array_map(
			function ( $plugin_data, $plugin_file ) use ( $active_plugins ) {
				return array(
					'main'    => $plugin_file,
					'slug'    => strtok( $plugin_file, '/' ),
					'name'    => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : null,
					'version' => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : null,
					'active'  => in_array( $plugin_file, $active_plugins, true ),
				);
			},
			$all_plugins,
			array_keys( $all_plugins )
		);

		return compact( 'plugins' );
	}

	private function ecommerce_information() {
		return array(
			'ecommerce' => apply_filters( 'convesioconvert_ecommerce_info', array() ),
		);
	}

}
