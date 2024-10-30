<?php

namespace ConvesioConvert\Form_Integration\Ninja_Forms;

use ConvesioConvert\Form_Integration\Integration;
use ConvesioConvert\Form_Integration\Utils;

class Form {


	public function __construct() {
		add_filter( 'ninja_forms_field_load_settings', array( $this, 'add_settings' ), 10, 3 );
		add_action( 'ninja_forms_after_submission', array( $this, 'after_submission' ) );
	}

	public function get_convesioconvert_fields() {
		$fields = array();

		$fields[] = array(
			'label' => __( '-None-', 'convesioconvert' ),
			'value' => 'none',
		);

		$all_attributes = Integration::instance()->get_all_attributes();

		foreach ( $all_attributes as $field_key => $label ) {
			$fields[] = array(
				'label' => $label,
				'value' => $field_key,
			);
		}

		return $fields;
	}

	public function add_settings( $settings, $name, $parent_group ) {

		// Don't add ConvesioConvert option to the following field types.
		$excluded = array(
			'hr',
			'submit',
			'unknown',
			'recaptcha',
			'recaptcha_v3',
			'password',
			'passwordconfirm',
			'html',
			'listcheckbox',
			'listimage',
			'listmultiselect',
			'repeater',
		);

		if ( in_array( $name, $excluded, true ) ) {
			return $settings;
		}

		$settings['convesioconvert'] = array(
			'name'    => 'convesioconvert',
			'type'    => 'select',
			'label'   => esc_html__( 'Map to ConvesioConvert field', 'convesioconvert' ),
			'options' => $this->get_convesioconvert_fields(),
			'width'   => 'one-half',
			'group'   => 'primary',
			'value'   => 'none',
			'help'    => esc_html__( 'Map this field to a ConvesioConvert field.', 'convesioconvert' ),

		);

		return $settings;
	}

	public function after_submission( $form ) {

		$form_data = array();

		$form_data['siteId']   = get_option( 'convesioconvert_site_id' );
		$form_data['formName'] = $form['settings']['title'];
		$form_data['tags']     = array();

		foreach ( $form['fields'] as $index => $field ) {
			if ( 'repeater' === $field['type'] ) {
				foreach ( $field['fields'] as $repeater_field ) {
					if ( ! isset( $field['value'][ $repeater_field['id'] ]['value'] ) ) {
						continue;
					}

					$repeater_field['value'] = $field['value'][ $repeater_field['id'] ]['value'];
					$form['fields'][]        = $repeater_field;
				}

				unset( $form['fields'][ $index ] );
			}
		}

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['convesioconvert'] ) && 'none' !== $field['convesioconvert'] ) {
				$convesioconvert_field = str_replace( 'convesioconvert-', '', $field['convesioconvert'] );

				if ( in_array( $convesioconvert_field, array( '012', '013' ), true ) ) {
					$field['value'] = Utils::guess_consent_type( $field['value'] );
				}

				$value = $field['value'];
				$type  = $field['type'];

				if ( is_array( $value ) ) {
					if ( 'date' === $type ) {
						$value = isset( $value['date'] ) ? $value['date'] : '';
					} elseif ( isset( $value[0] ) ) {
						$value = $value[0];
					} else {
						continue;
					}
				}

				$form_data['formData'][] = array(
					'key'   => $convesioconvert_field,
					'value' => $value,
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
