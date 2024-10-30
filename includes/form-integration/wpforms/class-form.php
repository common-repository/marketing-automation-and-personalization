<?php

namespace ConvesioConvert\Form_Integration\WPForms;

use ConvesioConvert\Form_Integration\Integration;
use ConvesioConvert\Form_Integration\Utils;

class Form {

	public function __construct() {
		add_action( 'wpforms_process_complete', array( $this, 'after_submission' ), 10, 4 );
		add_action( 'wpforms_field_options_bottom_basic-options', array( $this, 'add_settings' ), 10, 2 );
	}

	public function get_convesioconvert_fields() {
		$fields = array();

		$fields['none'] = __( '-None-', 'convesioconvert' );

		$all_attributes = Integration::instance()->get_all_attributes();

		foreach ( $all_attributes as $field_key => $label ) {
			$fields[ $field_key ] = $label;
		}

		return $fields;
	}

	public function add_settings( $field, $instance ) {

		$exluded_field_types = array(
			'divider',
			'file-upload',
			'password',
			'pagebreak',
			'captcha',
		);

		if ( empty( $field['type'] ) || in_array( $field['type'], $exluded_field_types, true ) ) {
			return;
		}

		// Field label.
		$convesioconvert_field_label = $instance->field_element(
			'label',
			$field,
			array(
				'slug'    => 'convesioconvert',
				'value'   => esc_html__( 'Map to a ConvesioConvert field', 'convesioconvert' ),
				'tooltip' => esc_html__( 'Map this field to a ConvesioConvert field.', 'convesioconvert' ),
			),
			false
		);

		// Field Select box.
		$convesioconvert_field_setting = $instance->field_element(
			'select',
			$field,
			array(
				'slug'    => 'convesioconvert',
				'value'   => ! empty( $field['convesioconvert'] ) ? esc_attr( $field['convesioconvert'] ) : 'none',
				'desc'    => esc_html__( 'Map to a ConvesioConvert field', 'convesioconvert' ),
				'options' => $this->get_convesioconvert_fields(),
			),
			false
		);
		$args                          = array(
			'slug'    => 'convesioconvert',
			'content' => $convesioconvert_field_label . $convesioconvert_field_setting,
		);
		$instance->field_element( 'row', $field, $args );

	}

	public function after_submission( array $fields, array $entry, array $form, int $entry_id ) {

		$form_data = array();

		$form_data['siteId']   = get_option( 'convesioconvert_site_id' );
		$form_data['formName'] = $form['settings']['form_title'];
		$form_data['tags']     = array();
		$form_data['formData'] = array();

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['convesioconvert'] ) && 'none' !== $field['convesioconvert'] ) {
				$convesioconvert_field = str_replace( 'convesioconvert-', '', $field['convesioconvert'] );
				if ( in_array( $convesioconvert_field, array( '012', '013' ), true ) ) {
					$fields[ $field['id'] ]['value'] = Utils::guess_consent_type( $fields[ $field['id'] ]['value'] );
				}
				$form_data['formData'][] = array(
					'key'   => $convesioconvert_field,
					'value' => $fields[ $field['id'] ]['value'],
					'type'  => Integration::instance()->get_attribute_type( $convesioconvert_field ),
				);
			}
		}

		$response = Integration::instance()->form_submission( $form_data );

		if ( is_wp_error( $response ) ) {
			error_log( __( 'ConvesioConvert: Internal server error.', 'convesioconvert' ) ); // phpcs:ignore
		} elseif ( isset( $response['errors'] ) && isset( $response['error']['api_error'] ) ) {
			error_log( (string) $response['error']['api_error'][0] ); // phpcs:ignore
		}
	}
}
