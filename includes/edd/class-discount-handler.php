<?php

namespace ConvesioConvert\EDD;

use EDD_Discount;
use ConvesioConvert\GraphQL_Client;
use function ConvesioConvert\meta_site_prefix;

/**
 * Does two separate things:
 * 1. Notify our backend of discount updates in order to apply them to templates.
 * 2. Rendering our logo for discounts managed by us.
 */
class Discount_Handler {

	public function __construct() {
		add_action( 'edd_post_update_discount', array( $this, 'inform_discount_update' ) );
		add_action( 'edd_pre_delete_discount', array( $this, 'inform_discount_update' ) );

		// EDD table doesn't allow us to show our icon in coupon name, we add it to row actions instead.
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// add_action( 'manage_download_page_edd-discounts_columns', array( $this, 'custom_coupon_column' ), 5, 2 );
		add_filter( 'edd_discount_row_actions', array( $this, 'custom_coupon_column' ), 5, 2 );
	}

	/** @param array $discount_data */
	public function inform_discount_update( $discount_data ) {
		if ( empty( $discount_data['id'] ) ) {
			// When checking out an order that uses a discount, this hook is called, but
			// $discount_data does not contain an id field. (even though this hook has a
			// second argument $discount_id, we're not using it in this method.)
			return;
		}

		$prefix = meta_site_prefix();

		$discount_id = is_array( $discount_data ) ? $discount_data['id'] : $discount_data;
		$discount    = edd_get_discount( $discount_id );

		$is_managed_discount = (bool) $discount->get_meta( "{$prefix}_discount" );

		if ( ! $is_managed_discount ) {
			return;
		}

		try {
			$site_id  = get_option( 'convesioconvert_site_id' );
			$e_prefix = "{$prefix}_entity";
			$args     = array(
				'siteId'     => $site_id,
				'platform'   => 'edd',
				'couponId'   => $discount->id,
				'entityType' => $discount->get_meta( "{$e_prefix}_type" ),
				'entityId'   => $discount->get_meta( "{$e_prefix}_id" ),
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
	 * @param EDD_Discount $discount
	 *
	 * @return array<string, string>
	 */
	public function custom_coupon_column( $row_actions, $discount ) {
		$prefix = meta_site_prefix();

		$is_managed_coupon = (bool) $discount->get_meta( "{$prefix}_discount" );
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
