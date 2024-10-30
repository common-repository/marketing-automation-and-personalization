<?php

namespace ConvesioConvert;

/**
 * Handles coupon modifications and notifies backend. Also manages some UI tweaks.
 */
class Coupon_Handler {
	/**
	 * Coupon_Handler constructor.
	 */
	public function __construct() {
		add_action( 'edit_post_shop_coupon', array( $this, 'inform_coupon_update' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'inform_coupon_delete' ), 10, 1 );

		add_action( 'manage_shop_coupon_posts_custom_column', array( $this, 'custom_coupon_column' ), 5, 2 );
	}

	/**
	 * Hook. Notifies backend of the change.
	 *
	 * @param $post_id
	 * @param $post
	 */
	public function inform_coupon_update( $post_id, $post ) {
		$prefix = meta_site_prefix();

		$is_managed_coupon = (bool) get_post_meta( $post_id, "{$prefix}_coupon", true );
		if ( ! $is_managed_coupon ) {
			return;
		}

		try {
			$site_id  = get_option( 'convesioconvert_site_id' );
			$e_prefix = "{$prefix}_entity";
			$args     = array(
				'siteId'     => $site_id,
				'platform'   => 'woo',
				'couponId'   => $post_id,
				'entityType' => get_post_meta( $post_id, "{$e_prefix}_type", true ),
				'entityId'   => get_post_meta( $post_id, "{$e_prefix}_id", true ),
			);

			GraphQL_Client::make()
				->site_coupon_notify()
				->set( $args )
				->execute();
		} catch ( \Throwable $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Try not to crash
		}
	}

	/**
	 * Hook. Notifies backend of the change.
	 *
	 * @param $post_id
	 */
	public function inform_coupon_delete( $post_id ) {
		$post = get_post( $post_id );

		// Either not a coupon, or not published; in both cases we already don't have the coupon in the backend.
		if ( 'shop_coupon' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		$this->inform_coupon_update( $post_id, $post );
	}

	/**
	 * Hook. Adds our icon beside the coupons managed by us in coupons list admin page.
	 *
	 * @param $column
	 * @param $post_id
	 */
	public function custom_coupon_column( $column, $post_id ) {
		$prefix = meta_site_prefix();

		$is_managed_coupon = (bool) get_post_meta( $post_id, "{$prefix}_coupon", true );
		if ( ! $is_managed_coupon ) {
			return;
		}

		if ( 'coupon_code' === $column ) {
			echo '<img src="' . esc_url( CONVESIOCONVERT_ADMIN_URL . 'assets/img/favicon.png' ) . '"' .
				'style="width: 1.2em; height: 1.2em; margin: 0; vertical-align: top;" ' .
				'alt="Managed by ConvesioConvert" title="Managed by ConvesioConvert">&nbsp;';
		}
	}
}
