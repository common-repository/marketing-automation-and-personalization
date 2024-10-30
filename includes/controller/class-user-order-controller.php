<?php

namespace ConvesioConvert\Controller;

use ConvesioConvert\Ecommerce\User_Order_Manager;
use ConvesioConvert\Session_Manager;
use ConvesioConvert\WooCommerce\Commerce_Data_Layer;
use function ConvesioConvert\Woocommerce\is_woocommerce_active;
use function ConvesioConvert\Woocommerce\woocommerce_uses_hpos;

/**
 * Any functionality related to WooCommerce orders management should be in this class.
 *
 * IMPORTANT: Due to 1001 in all parts all order statuses (whether paid or unpaid) must be considered in calculations
 * and determining the user type.
 */
class User_Order_Controller extends User_Order_Manager {
	const OPTION_PREFIX = 'convesioconvert_ecomcache_';
	const META_PREFIX   = '_' . self::OPTION_PREFIX;

	/**
	 * refer -> wc_customer_bought_product
	 *
	 * @param mixed $email
	 * @return bool
	 */
	public function user_has_ordered_by_email( $email ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return false;
		}

		return $this->user_has_ordered_by_user( $user->ID );
	}

	public function get_user_order_count( $user_id ) {
		if ( ! is_woocommerce_active() ) {
			return 0;
		}

		if ( ! $user_id ) {
			return 0;
		}

		/**
		 * Check if user has order or not.
		 *
		 * Has builtin cache (user meta).
		 * @link https://docs.woocommerce.com/wc-apidocs/source-class-WC_Customer_Data_Store.html#349-377
		 */
		return wc_get_customer_order_count( $user_id );
	}

	public function user_has_ordered_by_user( $user_id ) {
		$result = $this->get_user_order_count( $user_id );

		return $result > 0;
	}

	/**
	 * Returns a list of purchased product_ids for the current user, based on all order statuses.
	 *
	 * - If the current user is logged in, return based on her user id; however since we associate guest customers with
	 *   users this will effectively include guest checkouts. (After Unified Subscription we no longer associate and the
	 *   logic may need adjustment.)
	 * - If the current user is a guest customer (not logged-in), return guest checkouts based on billing email; which
	 *   effectively includes the purchases for the logged-in times.
	 *
	 * Inspired by:
	 * - wc_customer_bought_product
	 * - WC_Customer_Data_Store functions using _customer_user, especially get_total_spent
	 * - https://rudrastyh.com/woocommerce/display-purchased-products.html
	 * - We preferred double-joins over https://www.businessbloomer.com/woocommerce-display-products-purchased-user/
	 *
	 * @return array
	 */
	public function list_of_purchased_product_ids() {
		if ( ! is_woocommerce_active() ) {
			return array();
		}

		global $wpdb;

		$cache_key = 'products_list';

		$user_id = null;
		$email   = null;

		if ( 'guest' !== \ConvesioConvert\get_user_type() ) {
			$user_id = get_current_user_id();

			if ( $this->has_user_cache( $user_id, $cache_key ) ) {
				return $this->get_user_cache( $user_id, $cache_key ) ?: array();
			}

			$variable   = $user_id;
			$sql_filter = woocommerce_uses_hpos()
				? 'orders.customer_id = %s'
				: "ordermeta.meta_key = '_customer_user' AND ordermeta.meta_value = %s";
		} else {
			$session = new Session_Manager();
			$email   = $session->get_effective_user_property( 'email' );

			if ( ! $email ) {
				return array();
			}

			if ( $this->has_guest_cache( $email, $cache_key ) ) {
				return $this->get_guest_cache( $email, $cache_key ) ?: array();
			}

			$variable   = $email;
			$sql_filter = woocommerce_uses_hpos()
				? 'orders.billing_email = %s'
				: "ordermeta.meta_key = '_billing_email' AND ordermeta.meta_value = %s";
		}

		$statuses_list       = array_keys( wc_get_order_statuses() );
		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses_list ), '%s' ) );

		$sql_binds = array_merge( $statuses_list, array( $variable ) );

		$query = woocommerce_uses_hpos()
			? "
				SELECT      itemmeta.meta_value
				FROM        {$wpdb->prefix}woocommerce_order_itemmeta itemmeta
				INNER JOIN  {$wpdb->prefix}woocommerce_order_items items
				            ON itemmeta.order_item_id = items.order_item_id
				INNER JOIN  {$wpdb->prefix}wc_orders orders
				            ON orders.ID = items.order_id
				WHERE       orders.status IN ( {$status_placeholders} )
							AND itemmeta.meta_key = '_product_id'
				            AND {$sql_filter}
				ORDER BY    orders.date_created_gmt DESC
				"
			: "
				SELECT      itemmeta.meta_value
				FROM        {$wpdb->prefix}woocommerce_order_itemmeta itemmeta
				INNER JOIN  {$wpdb->prefix}woocommerce_order_items items
				            ON itemmeta.order_item_id = items.order_item_id
				INNER JOIN  {$wpdb->posts} orders
				            ON orders.ID = items.order_id
				INNER JOIN  {$wpdb->postmeta} ordermeta
				            ON orders.ID = ordermeta.post_id
				WHERE       orders.post_status IN ( {$status_placeholders} )
							AND itemmeta.meta_key = '_product_id'
				            AND {$sql_filter}
				ORDER BY    orders.post_date DESC
				";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared
		$purchased_products_ids = $wpdb->get_col( $wpdb->prepare( $query, $sql_binds ) );
		// phpcs:enable

		$products_list = array_values( array_unique( $purchased_products_ids ?: array() ) );

		// Front-end 'Purchased Product' rule execution expects these to be strings, ensure they are strings
		$products_list = array_map( 'strval', $products_list );

		if ( 'guest' !== \ConvesioConvert\get_user_type() ) {
			$this->set_user_cache( $user_id, $cache_key, $products_list );
		} else {
			$this->set_guest_cache( $email, $cache_key, $products_list );
		}

		return $products_list;
	}

	/**
	 * Get current user or guest last order payment method.
	 *
	 * @return array
	 */
	public function get_last_order() {
		$order = Commerce_Data_Layer::empty_order( 'woo' );

		if ( ! is_woocommerce_active() ) {
			return $order;
		}

		$cache_key = 'last_order';

		if ( 'guest' !== \ConvesioConvert\get_user_type() ) {
			$user_id = get_current_user_id();

			if ( $this->has_user_cache( $user_id, $cache_key ) ) {
				return $this->get_user_cache( $user_id, $cache_key ) ?: $order;
			}
		} else {
			// Effective user ID for guest-customer.
			$session = new Session_Manager();
			$user_id = $session->get_effective_user_property( 'userId' );
			$email   = $session->get_effective_user_property( 'email' );

			if ( ! $email ) {
				return $order;
			}
			if ( $this->has_guest_cache( $email, $cache_key ) ) {
				return $this->get_guest_cache( $email, $cache_key ) ?: $order;
			}
		}

		$last_order = wc_get_customer_last_order( $user_id );
		// Order didn't exists.
		if ( ! $last_order ) {
			return $order;
		}

		$order = Commerce_Data_Layer::get_order_details_for_data_layer( $last_order );

		if ( 'guest' !== \ConvesioConvert\get_user_type() ) {
			$this->set_user_cache( $user_id, $cache_key, $order );
		} else {
			$this->set_guest_cache( $email, $cache_key, $order );
		}

		return $order;
	}

	/**
	 * Get current user total purchased items from multiple orders.
	 *
	 * FIXME: Duplicate of $this->list_of_purchased_product_ids() method.
	 *
	 * @return integer
	 */
	public function get_total_purchased_items() {
		if ( ! is_woocommerce_active() ) {
			return 0;
		}

		global $wpdb;

		$cache_key = 'total_purchased_items';

		$user_id = null;
		$email   = null;

		if ( 'guest' !== \ConvesioConvert\get_user_type() ) {
			$user_id = get_current_user_id();

			if ( $this->has_user_cache( $user_id, $cache_key ) ) {
				return (int) $this->get_user_cache( $user_id, $cache_key ) ?: 0;
			}

			$variable   = $user_id;
			$sql_filter = woocommerce_uses_hpos()
				? 'orders.customer_id = %s'
				: "ordermeta.meta_key = '_customer_user' AND ordermeta.meta_value = %s";
		} else {
			$session = new Session_Manager();
			$email   = $session->get_effective_user_property( 'email' );

			if ( ! $email ) {
				return 0;
			}

			if ( $this->has_guest_cache( $email, $cache_key ) ) {
				return (int) $this->get_guest_cache( $email, $cache_key ) ?: 0;
			}

			$variable   = $email;
			$sql_filter = woocommerce_uses_hpos()
				? 'orders.billing_email = %s'
				: "ordermeta.meta_key = '_billing_email' AND ordermeta.meta_value = %s";
		}

		$statuses_list       = array_keys( wc_get_order_statuses() );
		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses_list ), '%s' ) );

		$sql_binds = array_merge( $statuses_list, array( $variable ) );

		$query = woocommerce_uses_hpos()
			? "
				SELECT      COUNT(itemmeta.meta_value)
				FROM        {$wpdb->prefix}woocommerce_order_itemmeta itemmeta
				INNER JOIN  {$wpdb->prefix}woocommerce_order_items items
				            ON itemmeta.order_item_id = items.order_item_id
				INNER JOIN  {$wpdb->prefix}wc_orders orders
				            ON orders.ID = items.order_id
				WHERE       orders.status IN ( {$status_placeholders} )
							AND itemmeta.meta_key = '_product_id'
				            AND {$sql_filter}
				ORDER BY    orders.date_created_gmt DESC
				"
			: "
				SELECT      COUNT(itemmeta.meta_value)
				FROM        {$wpdb->prefix}woocommerce_order_itemmeta itemmeta
				INNER JOIN  {$wpdb->prefix}woocommerce_order_items items
				            ON itemmeta.order_item_id = items.order_item_id
				INNER JOIN  {$wpdb->posts} orders
				            ON orders.ID = items.order_id
				INNER JOIN  {$wpdb->postmeta} ordermeta
				            ON orders.ID = ordermeta.post_id
				WHERE       orders.post_status IN ( {$status_placeholders} )
							AND itemmeta.meta_key = '_product_id'
				            AND {$sql_filter}
				ORDER BY    orders.post_date DESC
				";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared
		$total_purchased_items = (int) $wpdb->get_var( $wpdb->prepare( $query, $sql_binds ) );

		if ( 'guest' !== \ConvesioConvert\get_user_type() ) {
			$this->set_user_cache( $user_id, $cache_key, $total_purchased_items );
		} else {
			$this->set_guest_cache( $email, $cache_key, $total_purchased_items );
		}

		return (int) $total_purchased_items;
	}

	public static function remove_data() {
		parent::remove_data();

		if ( ! is_woocommerce_active() || ! woocommerce_uses_hpos() ) {
			return;
		}

		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE '_convesioconvert%'" );
	}
}
