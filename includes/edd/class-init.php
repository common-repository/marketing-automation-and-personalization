<?php

namespace ConvesioConvert\EDD;

use ConvesioConvert\Page_Content_Details;

class Init {

	public function __construct() {
		// DO nothing if EDD is not active.
		if ( ! is_edd_active() ) {
			return;
		}

		new Checkout();
		new Commerce_Data_Layer();
		new Discount_Handler();
		new Routes();

		$this->add_hooks();
	}

	protected function add_hooks() {
		add_filter( 'convesioconvert_attach_user_ecommerce_data', array( $this, 'attach_customer_data' ), 10, 3 );
		add_filter( 'convesioconvert_ecommerce_status_data', array( $this, 'attach_ecommerce_status_data' ) );
		add_filter( 'convesioconvert_ecommerce_info', array( $this, 'attach_ecommerce_info' ) );
		add_filter( 'convesioconvert_modification_post_types', array( $this, 'add_post_type_modifications' ) );
		add_filter( 'convesioconvert_modification_taxonomies', array( $this, 'add_taxonomy_modifications' ) );
		add_filter( 'convesioconvert_modification_user_meta_fields', array( $this, 'add_user_meta_modifications' ) );

		add_action( 'convesioconvert_populate_page_content_details', array( $this, 'populate_page_content_details' ) );
	}

	public function attach_customer_data( $ecommerce_data, $user_id, $is_sync ) {
		$customer              = new Customer( $user_id );
		$ecommerce_data['edd'] = $customer->get_customer( $is_sync );
		return $ecommerce_data;
	}

	public function attach_ecommerce_status_data( $info ) {
		$edd_info    = new Info();
		$info['edd'] = $edd_info->status();
		return $info;
	}

	public function attach_ecommerce_info( $info ) {
		$edd_info    = new Info();
		$info['edd'] = $edd_info->general_information();
		return $info;
	}

	public function add_post_type_modifications( $post_types ) {
		$post_types[] = 'download';
		return $post_types;
	}

	public function add_taxonomy_modifications( $taxonomies ) {
		$taxonomies[] = 'download_category';
		$taxonomies[] = 'download_tag';
		return $taxonomies;
	}

	public function add_user_meta_modifications( $modifications ) {
		// info: _edd_user_address only works in EDD 2.x. In EDD 3 it's moved to EDD tables. However, we no longer track
		// user address updates for EDD 3. Based on use cases, probably we should. Check this commit's task for info.
		$modifications[] = '_edd_user_address';
		// info: edd_cart_token always updates or deletes right after edd_saved_cart.
		$modifications[] = 'edd_saved_cart';
		return $modifications;
	}

	public function populate_page_content_details() {

		if ( is_singular( array( 'download' ) ) ) {
			Page_Content_Details::set_platform( 'edd' );
			Page_Content_Details::set_page_type( Page_Content_Details::PRODUCT );
		}
	}

}
