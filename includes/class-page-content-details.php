<?php

namespace ConvesioConvert;

class Page_Content_Details {

	/**#@+
	 * These 'page content types' are values recognized by ConvesioConvert, not from WordPress or WooCommerce.
	 */
	const PAGE    = 'page';
	const POST    = 'post';
	const PRODUCT = 'product';
	/**#@-*/

	/**
	 * @var string|null
	 */
	private static $platform = null;

	/**
	 * @var string|null
	 */
	private static $page_type = null;
	private static $page_name = '';

	/**
	 * The real function that handles the page content details getting.
	 */
	private static function populate_page_content_details() {
		// phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
		self::$platform = 'wordpress';

		self::$page_type = get_post_type() === 'post'
			? self::POST
			: self::PAGE;

		do_action( 'convesioconvert_populate_page_content_details' );
	}

	/**
	 * To be called only by those that handle the action populate_page_content_details.
	 *
	 * @param $platform
	 */
	public static function set_platform( $platform ) {
		self::$platform = $platform;
	}

	/**
	 * To be called only by those that handle the action populate_page_content_details.
	 *
	 * @param $page_type
	 */
	public static function set_page_type( $page_type ) {
		self::$page_type = $page_type;
	}

	/**
	 * To be called only by those that handle the action populate_page_content_details.
	 *
	 * @param $page_name
	 */
	public static function set_page_name( $page_name ) {
		self::$page_name = $page_name;
	}

	/**
	 * For some known page types, returns the platform that renders the page (i.e the 'responsible' platform).
	 *
	 * If the current has a known type to the platform, then it has to have a platform key in this filter as well;
	 * however, pages that do not have a special content type yet (such as product list) do not need to return the
	 * correct platform.
	 *
	 * @return string
	 */
	public static function get_platform() {
		if ( is_null( self::$platform ) ) {
			self::populate_page_content_details();
		}

		return self::$platform;
	}

	/**
	 * Returns the page content type, one of the constants defined in this class.
	 *
	 * Just returns the post types ConvesioConvert knows, does not reveal WP post types.
	 *
	 * @return string
	 */
	public static function get_page_type() {
		if ( is_null( self::$page_type ) ) {
			self::populate_page_content_details();
		}

		return self::$page_type;
	}

	/**
	 * Returns the page name, can be thank-you or checkout or...
	 *
	 * Just returns the post names ConvesioConvert knows or uses.
	 *
	 * @return string
	 */
	public static function get_page_name() {
		if ( empty( self::$page_name ) ) {
			self::populate_page_content_details();
		}

		return self::$page_name;
	}
}
