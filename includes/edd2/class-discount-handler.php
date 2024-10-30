<?php

namespace ConvesioConvert\EDD2;

use ConvesioConvert\GraphQL_Client;
use function ConvesioConvert\meta_site_prefix;

class Discount_Handler {

	public function __construct() {
		add_action( 'edit_post_edd_discount', array( $this, 'inform_discount_update' ) );
		add_action( 'edd_pre_delete_discount', array( $this, 'inform_discount_update' ) );

		// EDD table doesn't allow us to show our icon in coupon name, we add it to row actions instead.
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// add_action( 'manage_download_page_edd-discounts_columns', array( $this, 'custom_coupon_column' ), 5, 2 );
		add_filter( 'edd_discount_row_actions', array( $this, 'custom_coupon_column' ), 5, 2 );
	}

	public function inform_discount_update( $discount_id ) {
		$prefix = meta_site_prefix();

		$is_managed_discount = (bool) get_post_meta( $discount_id, "{$prefix}_discount", true );

		if ( ! $is_managed_discount ) {
			return;
		}

		try {
			$site_id  = get_option( 'convesioconvert_site_id' );
			$e_prefix = "{$prefix}_entity";
			$args     = array(
				'siteId'     => $site_id,
				'platform'   => 'edd',
				'couponId'   => $discount_id,
				'entityType' => get_post_meta( $discount_id, "{$e_prefix}_type", true ),
				'entityId'   => get_post_meta( $discount_id, "{$e_prefix}_id", true ),
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
	 * Hook. Adds our icon beside the coupons managed by us in discounts list admin page.
	 *
	 * @param $row_actions
	 * @param $discount
	 */
	public function custom_coupon_column( $row_actions, $discount ) {
		$prefix = meta_site_prefix();

		$is_managed_coupon = (bool) get_post_meta( $discount->ID, "{$prefix}_discount", true );
		if ( ! $is_managed_coupon ) {
			return $row_actions;
		}

		$indicator = '<img src="' . esc_url( CONVESIOCONVERT_ADMIN_URL . 'assets/img/favicon.png' ) . '"' .
			'style="width: 1.2em; height: 1.2em; margin: 0; vertical-align: top;" ' .
			'alt="Managed by ConvesioConvert" title="Managed by ConvesioConvert">&nbsp;';
		return array_merge(
			array( 'convesioconvert' => $indicator ),
			$row_actions
		);
	}
}
