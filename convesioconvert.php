<?php

/**
 * Plugin Name:       ConvesioConvert - Marketing Automation and Personalization
 * Plugin URI:        https://convesio.com/convert/
 * Description:       Marketing automation assistant to help you understand your users’ behavior, personalize their experience across their journey and help you acquire, nurture, convert and retain them.
 * Version:           3.2.1
 * Author:            Convesio
 * Author URI:        https://convesio.com
 * License:           GPL-3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       convesioconvert
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/** @define "plugin_dir_path( __FILE__ )" "" */
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

register_activation_hook( __FILE__, 'convesioconvert_activation' );
function convesioconvert_activation() {
	set_transient( 'convesioconvert_activation_redirect', true, MINUTE_IN_SECONDS );
}

add_action( 'plugins_loaded', 'convesioconvert_load' );

function convesioconvert_load() {
	new ConvesioConvert\Admin\Init();
	new ConvesioConvert\Admin\Notices();
	new ConvesioConvert\Admin\Integration();
	new ConvesioConvert\Admin\Data_Manager();
	new ConvesioConvert\Admin\Smart_Rating();
	new ConvesioConvert\Assets_Manager();

	if ( ! ConvesioConvert\Admin\Integration::is_integrated() ) {
		return;
	}

	new ConvesioConvert\Admin\Email_Consent();
	new ConvesioConvert\Admin\Health_Check();
	new ConvesioConvert\Front\Init();
	new ConvesioConvert\Coupon_Handler();
	new ConvesioConvert\Form_Integration\Init();
	new ConvesioConvert\Woocommerce\Init();
	new ConvesioConvert\EDD\Init();
	new ConvesioConvert\EDD2\Init();
	new ConvesioConvert\Event_Handler();

	// Init after Ecommerce platforms register their filters.
	new ConvesioConvert\Modification_Handler();
}
