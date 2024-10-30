<?php
namespace ConvesioConvert\Form_Integration;

class Integration {

	public static $instance;

	private static $tags              = array();
	private static $basic_attributes  = array();
	private static $custom_attributes = array();

	private function __construct() {
		$result = self::get_attributes_and_tags();
		if ( ! is_wp_error( $result ) ) {
			self::$tags              = array_column( $result['tags'], 'value', 'key' );
			self::$basic_attributes  = array_column( $result['basicAttributes'], 'value', 'key' );
			self::$custom_attributes = array_column( $result['customAttributes'], 'value', 'key' );
		}
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private static function get_attributes_and_tags() {
		$schema = '
			basicAttributes {
				key
				value
			}
			customAttributes {
				key
				value
			}
			tags {
				key
				value
			}
		';

		$response = \ConvesioConvert\GraphQL_Client::make()->make_query( 'siteAttributes', array(), $schema )->execute();

		return $response;
	}

	public static function get_all_attributes() {
		// Get both basic and custom attributes, sort them and then merge them into one array.
		ksort( self::$basic_attributes );
		asort( self::$custom_attributes );
		$all = self::$basic_attributes + self::$custom_attributes;

		$attributes = array();

		foreach ( $all as $key => $val ) {
			$attributes[ 'convesioconvert-' . $key ] = $val;
		}

		return $attributes;
	}

	public static function get_custom_attributes() {
		return self::$custom_attributes;
	}

	public static function get_basic_attributes() {
		return self::$basic_attributes;
	}

	public static function get_tags() {
		return self::$tags;
	}

	/**
	 * This functions get the ID of an attribute and return wheter its basic or not
	 */
	public function get_attribute_type( $field_id = '' ) {
		if ( $field_id && is_numeric( $field_id ) ) {
			// Check if it exists inside basic fileds.
			return array_key_exists( $field_id, self::$basic_attributes ) ? 'basic' : 'custom';
		}
	}

	/**
	 * Use this function to send form data to our backend after submit
	 *
	 * @param array $form_data
	 * @param array $variables_types (specify what is the type of each field)
	 *
	 * @return \ConvesioConvert\GraphQL_Client response
	 */
	public static function form_submission( $form_data = array() ) {
		if ( ! isset( $form_data ) ) {
			return;
		}

		$variables_types = array(
			'siteId'   => 'ID!',
			'formName' => 'String!',
			'tags'     => '[ID]',
			'formData' => '[userAttributeInput]!',
		);

		$form_data['formData'] = array_filter(
			$form_data['formData'],
			function( $data ) {
				return $data['value'];
			}
		);

		if ( empty( $form_data['formData'] ) ) {
			return array( 'success' => false );
		}

		return \ConvesioConvert\GraphQL_Client::make()->make_mutation( 'formSubmission', $variables_types, 'success', $form_data )->execute();
	}

}
