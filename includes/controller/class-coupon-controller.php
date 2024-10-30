<?php

namespace ConvesioConvert\Controller;

use function ConvesioConvert\Woocommerce\is_woocommerce_active;

/**
 * Manages WooCommerce Coupon CRUD actions.
 */
class Coupon_Controller {
	/**
	 * Return errors if the request is not valid, i.e site not integrated or WooCommerce not active.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|null
	 */
	private function check_request_error( $request ) {
		if ( ! is_woocommerce_active() ) {
			return new \WP_Error( 'woocommerce_not_active', esc_html__( 'WooCommerce is not active', 'convesioconvert' ), array( 'status' => 404 ) );
		}

		return null;
	}

	/**
	 * Makes a response based on a coupon post.
	 *
	 * @param $coupon
	 * @param $status
	 * @return \WP_REST_Response
	 */
	private function coupon_response( $coupon, $status ) {
		$entity_prefix = \ConvesioConvert\meta_site_prefix() . '_entity';

		$coupon_data = array_merge(
			$coupon->to_array(),
			get_post_meta( $coupon->ID ),
			array(
				'entity_type' => get_post_meta( $coupon->ID, "{$entity_prefix}_type", true ),
				'entity_id'   => get_post_meta( $coupon->ID, "{$entity_prefix}_id", true ),
			)
		);

		$data = array( 'coupon' => $coupon_data );

		$response = new \WP_REST_Response( $data );

		$response->set_status( $status );

		return $response;
	}

	/**
	 * Retrieves a coupon post from the request after validating it.
	 *
	 * @param $request
	 * @return \WP_Error|\WP_Post|null
	 */
	private function retrieve_coupon( $request ) {
		$request_error = $this->check_request_error( $request );

		if ( $request_error ) {
			return $request_error;
		}

		$coupon = get_post( $request->get_param( 'id' ) );

		if ( ! $coupon || 'shop_coupon' !== $coupon->post_type || 'publish' !== $coupon->post_status ) {
			return new \WP_Error( 'invalid', 'invalid_coupon', array( 'status' => 404 ) );
		}

		return $coupon;
	}

	/**
	 * REST API route.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response|null
	 */
	public function retrieve( $request ) {
		$coupon = $this->retrieve_coupon( $request );
		if ( ! ( $coupon instanceof \WP_Post ) ) {
			return $coupon;
		}

		return $this->coupon_response( $coupon, 200 );
	}

	/**
	 * REST API route.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response|null
	 */
	public function create( $request ) {
		$request_error = $this->check_request_error( $request );

		if ( $request_error ) {
			return $request_error;
		}

		$coupon = $this->add_coupon( $request );

		return $this->coupon_response( $coupon, 201 );
	}

	/**
	 * Creates a coupon post.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Post
	 */
	protected function add_coupon( $request ) {

		$coupon_data = json_decode( $request->get_param( 'coupon_data' ) );

		$coupon = array(
			'post_title'   => $coupon_data->code,
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => 1000000000000,
			'post_type'    => 'shop_coupon',
		);

		$new_coupon_id = wp_insert_post( $coupon );

		// Up-to-date as of WooCommerce 3.8.
		$this->update_coupon_meta( $new_coupon_id, (array) $coupon_data->meta );

		return get_post( $new_coupon_id );
	}

	/**
	 * Update meta values for a coupon post.
	 *
	 * @param $coupon_id
	 * @param array $meta
	 */
	private function update_coupon_meta( $coupon_id, $meta ) {
		foreach ( $meta as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}
	}

	/**
	 * REST API route.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response|null
	 */
	public function update( $request ) {
		$coupon = $this->retrieve_coupon( $request );
		if ( ! ( $coupon instanceof \WP_Post ) ) {
			return $coupon;
		}

		$coupon = $this->update_coupon( $request, $coupon );

		return $this->coupon_response( $coupon, 200 );
	}

	/**
	 * Updates a coupon post.
	 *
	 * @param \WP_REST_Request $request
	 * @param $coupon
	 * @return \WP_Post
	 */
	protected function update_coupon( $request, $coupon ) {
		$coupon_data = json_decode( $request->get_param( 'coupon_data' ) );
		if ( $coupon->post_title !== $coupon_data->code ) {
			$coupon->post_title = $coupon_data->code;
			wp_update_post( $coupon );
		}

		$this->update_coupon_meta( $coupon->ID, $coupon_data->meta );

		return $coupon;
	}

	/**
	 * REST API route.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response|null
	 */
	public function delete( $request ) {
		$coupon = $this->retrieve_coupon( $request );
		if ( ! ( $coupon instanceof \WP_Post ) ) {
			return $coupon;
		}

		$response = $this->coupon_response( $coupon, 200 );

		$this->delete_coupon( $coupon );

		return $response;
	}

	/**
	 * Trashes or deletes a coupon post.
	 *
	 * @param $coupon
	 */
	protected function delete_coupon( $coupon ) {
		wp_delete_post( $coupon->ID );
	}
}
