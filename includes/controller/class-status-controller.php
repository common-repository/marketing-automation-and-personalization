<?php

namespace ConvesioConvert\Controller;

/**
 * Class to get essential site information, such as plugin status, versions, and other information necessary for site
 * syncs and health checks.
 *
 * Important: This class must not execute heavy logic, as it is called from the backend on multiple occasions.
 *            Any non-essential information (e.g suggestion requirements) must be collected by Extra_Data_Controller.
 *
 * Important: Collecting any additional data may be subject to regulations in the Privacy Policy and Terms of Use that
 *            users accept upon registration.
 */
class Status_Controller {
	public function status( $request ) {
		global $wp_version;

		return array(
			'active'            => true,
			'paused'            => \ConvesioConvert\Admin\Integration::is_paused(),
			'wp_version'        => $wp_version,
			'plugin_version'    => CONVESIOCONVERT_VERSION,
			'site_locale'       => get_locale(),
			'ecommerce_plugins' => apply_filters( 'convesioconvert_ecommerce_status_data', array() ),
			'meta'              => array(
				'wp_admin_url'        => admin_url(),
				'php_version'         => phpversion(),
				'permalink_structure' => get_option( 'permalink_structure' ),
				'terms_modified_at'   => \ConvesioConvert\Modification_Handler::get_terms_last_modification(),

				'wp_is_multisite'     => (bool) is_multisite(),
			),
		);
	}
}
