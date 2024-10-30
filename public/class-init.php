<?php

namespace ConvesioConvert\Front;

use ConvesioConvert;
use ConvesioConvert\Admin\Integration;
use ConvesioConvert\Page_Content_Details;
use function ConvesioConvert\get_user_identity_token;

class Init {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'add_executor_if_then' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_inline_scripts' ), 9 );
	}

	public function add_executor_if_then() {
		wp_enqueue_script( 'convesioconvert-if-then' );
	}

	public function add_inline_scripts() {
		global $wp;
		$site_id      = get_option( 'convesioconvert_site_id' );
		$user_type    = ConvesioConvert\get_user_type();
		$current_user = ( 'guest' === $user_type ) ? null : wp_get_current_user();
		$page_type    = esc_html( Page_Content_Details::get_page_type() );

		$categories = array();
		if ( 'product' === $page_type ) {
			$terms = get_the_terms( get_the_ID(), 'product_cat' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[ $term->term_id ] = $term->name;
				}
			}
		}
		$data_layer = array(
			'load'        => true,
			'currentTime' => esc_html( date( 'Y-m-d H:i:s' ) ),
			'platform'    => array(
				'platformKey' => 'wordpress',
				'apiUrl'      => esc_url( CONVESIOCONVERT_API_URL ),
				'env'         => esc_html( CONVESIOCONVERT_APP_ENV ),
				'integration' => Integration::is_paused() ? 'paused' : 'active',
			),
			'siteId'      => esc_html( $site_id ),
			'site'        => array(
				'id'  => esc_html( $site_id ),
				'url' => esc_url( rtrim( site_url(), '/' ) ),
			),
			'page'        => array(
				'pageId'         => get_the_ID(),
				'pageType'       => $page_type,
				'pageCategories' => $categories,
				'platform'       => esc_html( Page_Content_Details::get_platform() ),
				'pageName'       => esc_html( Page_Content_Details::get_page_name() ),
				'url'            => esc_url( '/' . $wp->request ),
				'isSingle'       => is_single() || is_page(),
			),
			'user'        => array(
				'type'          => esc_html( $user_type ),
				'email'         => $current_user ? esc_html( $current_user->user_email ) : '',
				// 'siteUserId' => not available
				'userId'        => $current_user ? esc_html( $current_user->ID ) : '',
				'identityToken' => $current_user ? get_user_identity_token( $current_user->ID ) : '',
				'username'      => $current_user ? esc_html( $current_user->user_login ) : '',
				'firstName'     => $current_user ? esc_html( $current_user->first_name ) : '',
				'lastName'      => $current_user ? esc_html( $current_user->last_name ) : '',
				'registeredAt'  => $current_user ? esc_html( $current_user->user_registered ) : '',
			),
			'commerce'    => apply_filters( 'convesioconvert_data_layer_commerce_entry', array() ),
		);

		wp_localize_script( 'convesioconvert-if-then', '_convesioconvert', $data_layer );
	}

}
