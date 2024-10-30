<?php


namespace ConvesioConvert;

use EDD_Customer;
use ConvesioConvert\Controller\User_Order_Controller;
use function ConvesioConvert\Woocommerce\is_woocommerce_active;
use function ConvesioConvert\EDD\is_edd_active;
use function ConvesioConvert\EDD2\is_edd_active as is_edd_2_active;

function get_user_type() {
	if ( ! is_user_logged_in() ) {
		return 'guest';
	}

	if ( is_multisite() ) {
		if ( ! is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) ) {
			return 'guest';
		}
	}

	$user_types = array();

	if ( is_woocommerce_active() ) {
		$orders = new User_Order_Controller();

		$user_types[] = $orders->get_user_order_count( get_current_user_id() ) > 0 ? 'customer' : 'lead';
	}

	if ( is_edd_active() || is_edd_2_active() ) {
		$customer     = new EDD_Customer( get_current_user_id(), true );
		$user_types[] = ( $customer->purchase_count > 0 ) ? 'customer' : 'lead';
	}

	return in_array( 'customer', $user_types, true ) ? 'customer' : 'lead';

}

/**
 * Constructs a user identity token using the site verification secret.
 *
 * @param int|string $user_id (string emails are also accepted)
 * @return string
 */
function get_user_identity_token( $user_id ) {
	$verification_secret = get_option( 'convesioconvert_verification_secret' );

	return ( $user_id && $verification_secret )
		? hash_hmac( 'sha256', $user_id, $verification_secret )
		: '';
}

function starts_with( $haystack, $needle ) {
	return substr( $haystack, 0, strlen( $needle ) ) === $needle;
}

function error_log( $log ) {
	if ( is_array( $log ) || is_object( $log ) ) {
		\error_log( print_r( $log, true ) ); // phpcs:ignore
		return;
	}

	\error_log( $log ); // phpcs:ignore
}

/**
 * Checks whether $search is an array and all $keys exist in it, whether null or non-null.
 *
 * @param array $keys
 * @param array $search
 *
 * @return bool
 */
function array_keys_exist( $keys, $search ) {
	if ( ! is_array( $search ) ) {
		return false;
	}

	$count = 0;
	foreach ( $keys as $key ) {
		$count += ( isset( $search[ $key ] ) || array_key_exists( $key, $search ) ) ? 1 : 0;
	}

	return count( $keys ) === $count;
}

/**
 * Returns the meta key prefix as a string for ConvesioConvert meta entries in places where the meta must only be
 * available for a specific site and must not be synced to other sites. As an example if tenant re-integrates with a new
 * site id, we don't want to use the old site's purchase session ids with the new site. Therefore append site_id to
 * order metas and other metas which should not survive on changing sites.
 *
 * @return string
 */
function meta_site_prefix() {
	$site_id = get_option( 'convesioconvert_site_id' );
	return "_convesioconvert_{$site_id}";
}

function get_relative_permalink( $post ) {
	$permalink = get_permalink( $post );
	$url       = str_replace( home_url(), '', $permalink );

	return rtrim( $url, '/' );
}

function is_screen( $screen_id ) {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}

	return $screen_id === $screen->id;
}

/**
 * Utility function to verify nonces without causing warnings in WPCS.
 *
 * @param string $action
 *
 * @param string $field_name
 *
 * @return bool
 */
function verify_get_nonce( $action, $field_name = 'nonce' ) {
	if ( ! isset( $_GET[ $field_name ] ) ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! wp_verify_nonce( wp_unslash( $_GET[ $field_name ] ), $action ) ) {
		return false;
	}

	return true;
}

/**
 * Utility function to verify nonces without causing warnings in WPCS.
 *
 * @param string $action
 *
 * @param string $field_name
 *
 * @return bool
 */
function verify_post_nonce( $action, $field_name = 'nonce' ) {
	if ( ! isset( $_POST[ $field_name ] ) ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! wp_verify_nonce( wp_unslash( $_POST[ $field_name ] ), $action ) ) {
		return false;
	}

	return true;
}

/**
 * A function to report events to ConvesioConvert event handler.
 *
 * @param string $event_type
 * @param int $user_id
 */
function transfer_an_event( $event_type, $user_id, $event_data = null ) {
	$variables_types = array(
		'siteId'    => 'ID!',
		'siteUser'  => 'SiteUserInputType',
		'eventType' => 'String!',
		'eventData' => 'JsonType',
		'platform'  => 'PlatformEnum!',
	);

	$data = array(
		'siteId'    => get_option( 'convesioconvert_site_id' ),
		'siteUser'  => array(
			'userId'        => $user_id,
			'identityToken' => get_user_identity_token( $user_id ),
		),
		'eventType' => $event_type,
		'eventData' => wp_json_encode( $event_data ),
		'platform'  => 'wordpress',
	);

	return \ConvesioConvert\GraphQL_Client::make()
		->make_mutation( 'externallyTriggerEvents', $variables_types, 'success', $data )
		->execute();
}
