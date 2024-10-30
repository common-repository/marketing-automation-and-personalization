<?php

namespace ConvesioConvert\Woocommerce;

use ConvesioConvert\Controller\Woocommerce_Checkout_Controller;
use ConvesioConvert\Page_Content_Details;

class Init {

	public function __construct() {
		if ( ! is_woocommerce_active() ) {
			return;
		}

		new Woocommerce_Checkout_Controller();
		new Commerce_Data_Layer();
		new Routes();
		new Order_Status_Change_Hooks();

		add_filter( 'convesioconvert_attach_user_ecommerce_data', array( $this, 'attach_customer_data' ), 10, 3 );
		add_filter( 'convesioconvert_ecommerce_status_data', array( $this, 'attach_ecommerce_status_data' ) );
		add_filter( 'convesioconvert_ecommerce_info', array( $this, 'attach_ecommerce_info' ) );

		add_action( 'convesioconvert_populate_page_content_details', array( $this, 'populate_page_content_details' ) );
	}

	public function attach_customer_data( $ecommerce_data, $user_id, $is_sync ) {
		$customer              = new \ConvesioConvert\Woocommerce\Customer( $user_id );
		$ecommerce_data['woo'] = $customer->get_customer( $is_sync );
		return $ecommerce_data;
	}

	public function attach_ecommerce_status_data( $info ) {
		$woo_info    = new \ConvesioConvert\Woocommerce\Info();
		$info['woo'] = $woo_info->status();
		return $info;
	}

	public function attach_ecommerce_info( $info ) {
		$woo_info    = new \ConvesioConvert\Woocommerce\Info();
		$info['woo'] = $woo_info->general_information();
		return $info;
	}

	public function populate_page_content_details() {
		if ( is_product() ) {
			Page_Content_Details::set_platform( 'woo' );
			Page_Content_Details::set_page_type( Page_Content_Details::PRODUCT );
		}

		$page_name = '';
		if ( is_checkout() && ! empty( is_wc_endpoint_url( 'order-received' ) ) ) {
			$page_name = 'thank-you';
		} elseif ( is_checkout() && ! is_wc_endpoint_url() ) {
			$page_name = 'checkout';
		} elseif ( is_singular() ) {
			$page_name = get_the_title( get_queried_object_id() );
		}
		Page_Content_Details::set_page_name( $page_name );
	}

}
