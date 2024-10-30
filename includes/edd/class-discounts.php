<?php

namespace ConvesioConvert\EDD;

use EDD_Discount;
use WP_Error;
use WP_REST_Response;
use function ConvesioConvert\meta_site_prefix;

class Discounts {

	public function index( $request ) {

		$discount_id = $request->get_param( 'id' );
		return $this->get_discount( $discount_id );
	}

	/** @return EDD_Discount|WP_Error */
	private function retrieve_discount( $id ) {
		$discount = edd_get_discount( $id );

		if ( ! ( $discount instanceof EDD_Discount ) ) {
			return new WP_Error( 'invalid', 'invalid_coupon', array( 'status' => 404 ) );
		}
		return $discount;
	}

	/** @return WP_Error|WP_REST_Response */
	private function get_discount( $id ) {
		$gm_prefix     = meta_site_prefix();
		$entity_prefix = "{$gm_prefix}_entity";
		$discount      = $this->retrieve_discount( $id );

		if ( ! ( $discount instanceof EDD_Discount ) ) {
			return $discount;
		}

		$array_data    = $discount->to_array();
		$discount_data = array_merge(
			$array_data,
			array(
				'entity_type' => $discount->get_meta( "{$entity_prefix}_type" ),
				'entity_id'   => $discount->get_meta( "{$entity_prefix}_id" ),
			)
		);

		$data = array( 'coupon' => $discount_data );

		$response = new WP_REST_Response( $data );

		$response->set_status( 200 );

		return $response;

	}

	/** @return WP_Error|WP_REST_Response */
	public function add( $request ) {
		$existing_discount_id = null;

		if ( $request->get_param( 'id' ) ) {
			$existing_discount_id = $request->get_param( 'id' );
		}

		$coupon_data = json_decode( $request->get_param( 'coupon_data' ), true );
		$meta        = isset( $coupon_data['meta'] ) ? $coupon_data['meta'] : array();
		unset( $coupon_data['meta'] );

		if ( ! $existing_discount_id ) {
			$new_discount_id = edd_add_discount( $coupon_data );

			foreach ( $meta as $key => $value ) {
				edd_update_adjustment_meta( $new_discount_id, $key, $value );
			}
		} else {
			$new_discount_id = $existing_discount_id;

			edd_update_discount( $existing_discount_id, $coupon_data );
		}

		return $this->get_discount( $new_discount_id );
	}

	/** @return WP_Error|WP_REST_Response */
	public function remove( $request ) {
		$discount_id = $request->get_param( 'id' );

		$discount = $this->retrieve_discount( $discount_id );

		if ( ! ( $discount instanceof EDD_Discount ) ) {
			return $discount;
		}

		$response = $this->get_discount( $discount_id );

		edd_delete_discount( $discount_id );

		return $response;
	}

	public static function remove_data() {
		if ( ! is_edd_active() ) {
			return;
		}

		global $wpdb;
		$gm_prefix = meta_site_prefix();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM $wpdb->edd_adjustmentmeta WHERE meta_key LIKE '$gm_prefix%'" );
	}

}
