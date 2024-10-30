<?php

namespace ConvesioConvert\Ecommerce;

abstract class User_Order_Manager {

	abstract public function get_user_order_count( $user_id );

	abstract public function user_has_ordered_by_user( $user_id );

	abstract public function list_of_purchased_product_ids();

	abstract public function get_last_order();

	abstract public function get_total_purchased_items();

	/**#@+
	 * Functions for working with logged-in users' commerce cache.
	 */

	protected function has_user_cache( $user_id, $key ) {
		return get_user_meta( $user_id, static::META_PREFIX . $key, true ) !== '';
	}

	protected function get_user_cache( $user_id, $key ) {
		return get_user_meta( $user_id, static::META_PREFIX . $key, true );
	}

	protected function set_user_cache( $user_id, $key, $value ) {
		update_user_meta( $user_id, static::META_PREFIX . $key, $value );
	}

	public static function invalidate_user_cache( $user_id ) {
		if ( ! $user_id ) {
			return; // Be safe
		}

		global $wpdb;

		$wpdb->query(
			"DELETE FROM $wpdb->usermeta WHERE user_id = " . esc_sql( $user_id ) .
			" AND meta_key LIKE '" . static::META_PREFIX . "%'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}
	/**#@-*/

	/**#@+
	 * Functions for working with guest customers' commerce cache.
	 */
	protected function has_guest_cache( $email, $key ) {
		return get_transient( static::OPTION_PREFIX . "$key:$email" ) !== false;
	}

	protected function get_guest_cache( $email, $key ) {
		return get_transient( static::OPTION_PREFIX . "$key:$email" );
	}

	protected function set_guest_cache( $email, $key, $value ) {
		set_transient( static::OPTION_PREFIX . "$key:$email", $value, WEEK_IN_SECONDS );
	}

	public static function invalidate_guest_cache( $email ) {
		global $wpdb;
		$pattern = "'%" . static::OPTION_PREFIX . '%' . esc_sql( $email ) . "%'";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE $pattern" );
	}

	public static function remove_data() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%" . static::OPTION_PREFIX . "%'" );
	}
	/**#@-*/
}
