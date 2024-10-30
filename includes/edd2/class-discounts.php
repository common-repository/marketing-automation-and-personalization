<?php

namespace ConvesioConvert\EDD2;

class Discounts {

	public function index( $request ) {

		$discount_id = $request->get_param( 'id' );
		return $this->get_discount( $discount_id );
	}

	private function retrieve_discount( $id ) {
		$discount = edd_get_discount( $id );

		if ( ! ( $discount instanceof \EDD_Discount ) ) {
			return new \WP_Error( 'invalid', 'invalid_coupon', array( 'status' => 404 ) );
		}
		return $discount;
	}

	private function get_discount( $id ) {
		$gm_prefix     = \ConvesioConvert\meta_site_prefix();
		$entity_prefix = "{$gm_prefix}_entity";
		$discount      = $this->retrieve_discount( $id );

		if ( ! ( $discount instanceof \EDD_Discount ) ) {
			return $discount;
		}

		$array_data    = $discount->to_array();
		$discount_data = array_merge(
			$array_data,
			array(
				'entity_type' => get_post_meta( $discount->ID, "{$entity_prefix}_type", true ),
				'entity_id'   => get_post_meta( $discount->ID, "{$entity_prefix}_id", true ),
			)
		);

		$data = array( 'coupon' => $discount_data );

		$response = new \WP_REST_Response( $data );

		$response->set_status( 200 );

		return $response;

	}

	public function add( $request ) {
		$existing_discount_id = null;

		if ( $request->get_param( 'id' ) ) {
			$existing_discount_id = $request->get_param( 'id' );
		}

		$coupon_data = json_decode( $request->get_param( 'coupon_data' ), true );
		$meta        = isset( $coupon_data['meta'] ) ? $coupon_data['meta'] : array();
		unset( $coupon_data['meta'] );

		// Only assign a name on coupon create; tenant may change the coupon name later so don't touch it on updates.
		if ( $existing_discount_id ) {
			$discount            = new \EDD_Discount( $existing_discount_id );
			$coupon_data['name'] = $discount->get_name();
		}

		$new_discount_id = edd_store_discount( $coupon_data, $existing_discount_id );

		if ( ! $existing_discount_id ) {
			foreach ( $meta as $key => $value ) {
				update_post_meta( $new_discount_id, $key, $value );
			}
		}

		return $this->get_discount( $new_discount_id );
	}

	public function remove( $request ) {
		$discount_id = $request->get_param( 'id' );

		$discount = $this->retrieve_discount( $discount_id );

		if ( ! ( $discount instanceof \EDD_Discount ) ) {
			return $discount;
		}

		$response = $this->get_discount( $discount_id );

		edd_remove_discount( $discount_id );

		return $response;
	}

}
