<?php

namespace ConvesioConvert\Admin;

use function ConvesioConvert\verify_post_nonce;

class Data_Manager {
	public function __construct() {
		add_action( 'wp_ajax_convesioconvert_destroy_data', array( $this, 'destroy_data' ) );
	}

	/**
	 * Sets a flag that shows plugin-specific data exists on WordPress.
	 */
	public static function mark_plugin_data_existence() {
		update_option( 'convesioconvert_data_exists', 'yes' );
	}

	/**
	 * Returns whether plugin-specific data exists .
	 *
	 * @return bool
	 */
	public static function plugin_data_exists() {
		return 'yes' === get_option( 'convesioconvert_data_exists' );
	}

	/**
	 * Ajax handler that deletes all data after nonce verification.
	 */
	public function destroy_data() {
		if ( ! verify_post_nonce( 'convesioconvert_ajax' ) ) {
			wp_send_json_error();
		}

		self::delete_all_data();

		wp_send_json_success();
	}

	/**
	 * Removes any data (meta, option, transients, etc) set by us. We even remove transients as this can be used for:
	 * - Removing the data for regulation-compliance issues.
	 * - Restoring to a "factory default" state for debugging.
	 */
	private static function delete_all_data() {
		Email_Consent::remove_consent_settings_and_data();
		Integration::remove_integration_data();
		Health_Check::remove_health_check_data();
		Notices::remove_notices();
		Smart_Rating::remove_smart_rating_data();

		\ConvesioConvert\Modification_Handler::remove_data();
		\ConvesioConvert\Authorization\REST_Token_Verifier::expire_token();
		\ConvesioConvert\Controller\User_Order_Controller::remove_data();
		\ConvesioConvert\EDD\Discounts::remove_data();
		\ConvesioConvert\EDD\User_Orders::remove_data();
		\ConvesioConvert\EDD2\User_Orders::remove_data();

		self::remove_post_and_user_meta();

		delete_transient( 'convesioconvert_activation_redirect' );
		delete_option( 'convesioconvert_data_exists' );
	}

	/**
	 * Removes any post meta and user meta set by the system, for ANY site id.
	 *
	 * Includes:
	 * - Modification_Handler
	 * - Any meta set using `meta_site_prefix()`, including:
	 *   - Coupon_Controller::add_coupon
	 *   - Woocommerce_Checkout_Controller::record_order_meta_data
	 *   - Woocommerce_Checkout_Controller::mark_checkout_handled
	 *   - User_Order_Controller caches
	 */
	private static function remove_post_and_user_meta() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '\\_convesioconvert\\_%'" );
		$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '\\_convesioconvert\\_%'" );
	}
}
