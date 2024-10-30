<?php

namespace ConvesioConvert;

use ConvesioConvert\Controller\Orders_HPOS_Controller;
use ConvesioConvert\EDD\Orders;
use EDD\Orders\Order;
use EDD_Payment;
use WC_Order;
use function ConvesioConvert\EDD\is_edd_active;
use function ConvesioConvert\Woocommerce\is_woocommerce_active;
use function ConvesioConvert\Woocommerce\woocommerce_uses_hpos;

class Modification_Handler {

	const USER_META_FIELDS = array(
		// From WooCommerce, to detect Guest Checkout by a Guest Subscriber (i.e automatic order association)
		'_order_count',
		'convesioconvert_eu_consent',
		'convesioconvert_email_consent',
	);

	const POST_TYPES = array(
		'post',
		'page',
		'product',
		'shop_order',
	);

	const TAXONOMIES = array(
		'category',
		'post_tag',
		'product_cat',
		'product_tag',
	);

	private $user_meta_fields;
	private $post_types;
	private $taxonomies;

	public function __construct() {

		$this->user_meta_fields = apply_filters( 'convesioconvert_modification_user_meta_fields', self::USER_META_FIELDS );
		$this->post_types       = apply_filters( 'convesioconvert_modification_post_types', self::POST_TYPES );
		$this->taxonomies       = apply_filters( 'convesioconvert_modification_taxonomies', self::TAXONOMIES );

		add_action( 'user_register', array( $this, 'set_user_last_modification_time' ) ); // User registration.
		add_action( 'profile_update', array( $this, 'set_user_last_modification_time' ) ); // General db updates in user info.
		add_action( 'personal_options_update', array( $this, 'set_user_last_modification_time' ) );  // User updates profile.
		add_action( 'edit_user_profile_update', array( $this, 'set_user_last_modification_time' ) ); // User updates another user's profile.

		$this->add_actions( $this->post_types );
		$this->add_meta_actions( 'user' );
		$this->add_terms_actions( $this->taxonomies );

		// For WooCommerce rating and review meta fields on product
		add_action( 'wp_insert_comment', array( $this, 'handle_insert_comment' ), 20, 2 );

		// Associating orders with existing users, e.g on `wc_update_new_customer_past_orders` call or from wp-admin
		// order edit page.
		add_action( 'updated_post_meta', array( $this, 'set_post_meta_last_modification_time' ), 20, 4 );

		if ( is_woocommerce_active() ) {
			add_action( 'woocommerce_before_trash_order', array( $this, 'woocommerce_before_trash_order' ), 20, 2 );
			add_action( 'woocommerce_untrash_order', array( $this, 'woocommerce_untrash_order' ), 20 );

			if ( woocommerce_uses_hpos() ) {
				// Note: There are some discussion on the proper hook to use on GH, and the documentation is going to be
				// improved. For now, we opt to use `woocommerce_update_order`.
				// https://github.com/woocommerce/woocommerce/issues/35814
				add_action( 'woocommerce_update_order', array( $this, 'woocommerce_updated_order' ), 20, 2 );
			}
		}

		if ( is_edd_active() ) { // Applies to EDD 3+ only
			add_action( 'edd_updated_edited_purchase', array( $this, 'edd_updated_edited_purchase' ), 20 );
		}
	}

	public function add_actions( $post_types ) {
		foreach ( $post_types as $post_type ) {
			add_action( "save_post_{$post_type}", array( $this, 'set_last_modification_time' ), 10, 3 );
		}
	}

	/**
	 * Adds the hooks for create, update, delete actions in terms.
	 *
	 * This does not detect count/amount change for terms as that is only available in 'edited_term_taxonomy' hook that
	 * needs manual taxonomy check.
	 */
	public function add_terms_actions( $taxonomies ) {
		foreach ( $taxonomies as $taxonomy ) {
			add_action( "created_{$taxonomy}", array( $this, 'handle_taxonomy_update' ), 10, 2 );
			add_action( "edited_{$taxonomy}", array( $this, 'handle_taxonomy_update' ), 10, 2 );
			add_action( "delete_{$taxonomy}", array( $this, 'handle_taxonomy_delete' ), 10, 4 );
		}
	}

	public function add_meta_actions( $meta_type ) {
		add_action( "added_{$meta_type}_meta", array( $this, "set_{$meta_type}_meta_last_modification_time" ), 20, 4 );
		add_action( "updated_{$meta_type}_meta", array( $this, "set_{$meta_type}_meta_last_modification_time" ), 20, 4 );
		add_action( "deleted_{$meta_type}_meta", array( $this, "set_{$meta_type}_meta_last_modification_time" ), 20, 4 );
	}

	public function set_last_modification_time( $post_id, $post, $update ) {
		update_post_meta( $post_id, "_convesioconvert_{$post->post_type}_last_modification", time() );  // Timestamp, GMT.
	}

	public static function set_post_last_modification_time( $post_id, $post_type ) {
		update_post_meta( $post_id, "_convesioconvert_{$post_type}_last_modification", time() );  // Timestamp, GMT.
	}

	public function set_post_meta_last_modification_time( $meta_id, $post_id, $meta_key, $meta_value ) {
		$post_type = get_post_type( $post_id );

		// WooCommerce without HPOS
		if ( '_customer_user' === $meta_key && 'shop_order' === $post_type ) {
			self::set_post_last_modification_time( $post_id, 'shop_order' );
			return;
		}

		// EDD 2: Edd changes multiple meta keys on update customer process. _edd_payment_meta is a safe one to check.
		if ( '_edd_payment_meta' === $meta_key && 'edd_payment' === $post_type ) {
			self::set_post_last_modification_time( $post_id, 'edd_payment' );
		}

		// EDD 3+: This method is not called; a different action `updated_edd_order_meta` must be used (the built-in
		//   WordPress hook for meta changes of type `edd_order`). That being said, EDD 3+ stores important order fields
		//   in its dedicated order table and most meta are no longer relevant, so don't watch meta changes in EDD 3+,
		//   see `edd_updated_edited_purchase()` method defined next.
	}

	/**
	 * @param int $order_id
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function woocommerce_updated_order( $order_id, $order ) {
		// Set save = false, to not cause an infinite loop here.
		Orders_HPOS_Controller::update_order_modified_at( $order, false );
	}

	public function woocommerce_before_trash_order( $order_id, $order ) {
		if ( woocommerce_uses_hpos() ) {
			Orders_HPOS_Controller::update_order_modified_at( $order );
		} else {
			self::set_post_last_modification_time( $order->get_id(), 'shop_order' );
		}
	}

	public function woocommerce_untrash_order( $order_id ) {
		if ( woocommerce_uses_hpos() ) {
			$order = new WC_Order( $order_id );
			Orders_HPOS_Controller::update_order_modified_at( $order );
		} else {
			self::set_post_last_modification_time( $order_id, 'shop_order' );
		}
	}

	/**
	 * This is the best hook we can find for EDD 3+ to detect order updates.
	 * It only fires when order details are updated from EDD's admin UI though, but detects most changes.
	 * See the comment in {@see set_post_meta_last_modification_time()} for why we use it instead of the order meta.
	 */
	public function edd_updated_edited_purchase( $order_id ) {
		Orders::update_order_modified_at( $order_id );
	}

	public function set_user_last_modification_time( $user_id ) {
		update_user_meta( $user_id, '_convesioconvert_user_last_modification', time() );  // Timestamp, GMT.
	}

	public function set_user_meta_last_modification_time( $meta_id, $user_id, $meta_key, $meta_value ) {
		if ( $this->is_user_meta_interesting( $meta_key ) ) {
			$this->set_user_last_modification_time( $user_id );
		}
	}

	private function is_user_meta_interesting( $meta_key ) {
		// It is important that we don't return true for '_convesioconvert_user_last_modification'.
		return in_array( $meta_key, $this->user_meta_fields, true )
			|| starts_with( $meta_key, '_woocommerce_persistent_cart' )
			// For WooCommerce billing address change detection.
			|| starts_with( $meta_key, 'billing_' );
	}

	public function handle_insert_comment( $id, $comment ) {
		// EDD is not supporting comments for download post type by default. Uses it as download notes.
		// Updates the following meta on the product postmeta. We handle them here instead of on 'updated_post_meta'.
		// - _wc_average_rating
		// - _wc_rating_count
		// - _wc_review_count

		if ( 'review' === $comment->comment_type && 'product' === get_post_type( $comment->comment_post_ID ) ) {
			self::set_post_last_modification_time( $comment->comment_post_ID, 'product' );
		}
	}

	public function handle_taxonomy_update( $term_id, $tt_id ) {
		self::update_terms_last_modification();
	}

	public function handle_taxonomy_delete( $term, $tt_id, $deleted_term, $object_ids ) {
		self::update_terms_last_modification();
	}

	private static function update_terms_last_modification() {
		$last_modification = time(); // Timestamp, GMT.
		update_option( 'convesioconvert_terms_last_modification', $last_modification );
		return $last_modification;
	}

	public static function get_terms_last_modification() {
		$last_modification = get_option( 'convesioconvert_terms_last_modification' );

		if ( ! $last_modification ) {
			$last_modification = self::update_terms_last_modification();
		}

		return $last_modification;
	}

	/**
	 * The comments at the end of the method apply to all supported ecommerce platforms.
	 *
	 * @param WC_Order|EDD_Payment|Order $order
	 */
	public static function handle_checkout( $order ) {

		if ( $order instanceof EDD_Payment ) {
			//EDD 2
			foreach ( $order->downloads as $item ) {
				self::set_post_last_modification_time( $item['id'], 'download' );
			}

			self::set_post_last_modification_time( $order->ID, 'edd_payment' );
			return;
		} elseif ( $order instanceof Order ) {
			// EDD 3+
			foreach ( $order->get_items() as $item ) {
				self::set_post_last_modification_time( $item->product_id, 'download' );
			}

			Orders::update_order_modified_at( $order->id );
			return;
		}

		// elseif ( $order instanceof WC_Order ) :
		// WooCommerce:

		// Updates the 'total_sales' meta on the product postmeta. The mentioned meta changes cannot be detected with
		// the normal 'updated_post_meta' as WooCommerce changes database directly for updating it.

		foreach ( $order->get_items() as $item ) {
			self::set_post_last_modification_time( $item->get_product_id(), 'product' );
		}

		// Sometimes there is a delay between checkout and recording the session_ids in WooCommerce_Checkout_Controller;
		// either a few seconds or if things crash and the user has to refresh the thankyou page to see it. If sync runs
		// during this time the backend will get no session_ids. We try to prevent these edge cases by setting the order
		// modification time one more time.

		if ( woocommerce_uses_hpos() ) {
			$order->update_meta_data( '_convesioconvert_shop_order_last_modification', time() ); // Timestamp, GMT.
		} else {
			self::set_post_last_modification_time( $order->get_id(), 'shop_order' );
		}
	}

	public static function remove_data() {
		delete_option( 'convesioconvert_terms_last_modification' );
	}
}
