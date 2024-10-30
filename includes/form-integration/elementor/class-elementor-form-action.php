<?php

namespace ConvesioConvert\Form_Integration\Elementor;

use ConvesioConvert\Form_Integration\Utils;
use ElementorPro\Modules\Forms\Classes\Action_Base;
use Elementor\Repeater;
use Elementor\Controls_Manager;
use ConvesioConvert\Form_Integration\Integration;

class Elementor_Form_Action extends Action_Base {

	private $convesioconvert_tags, $attributes_config, $form_fields_config; //phpcs:ignore
	private $all_attributes = array();

	public function get_name() {
		return 'convesioconvert';
	}

	public function get_label() {
		return __( 'ConvesioConvert', 'convesioconvert' );
	}

	public function update_convesioconvert_attributes() {
		$this->all_attributes       = Integration::instance()->get_all_attributes();
		$this->convesioconvert_tags = Integration::instance()->get_tags();
	}

	private function set_controls_config() {

		$this->attributes_config = array(
			'name'    => 'convesioconvert_attribute',
			'label'   => __( 'Attribute', 'convesioconvert' ),
			'type'    => Controls_Manager::SELECT,
			'options' => array( 'none' => __( '- None -', 'convesioconvert' ) ) + $this->all_attributes,
			'default' => 'none',
		);

		$this->form_fields_config = array(
			'name'    => 'convesioconvert_form_field',
			'label'   => __( 'Form Field', 'convesioconvert' ),
			'type'    => Controls_Manager::SELECT,
			'options' => array(// Will be updated by JS while changing form fields.
				'none' => __( '- None -', 'convesioconvert' ),
			),
			'default' => 'none',
		);
	}

	private function register_controls_by_add_control() {
		$repeater = new Repeater();

		$repeater->add_control(
			'convesioconvert_attribute',
			$this->attributes_config
		);

		$repeater->add_control(
			'convesioconvert_form_field',
			$this->form_fields_config
		);

		return $repeater->get_controls();
	}

	/**
	 * Add our settings section in Elementor form widget.
	 */
	public function register_settings_section( $element ) {
		$this->update_convesioconvert_attributes();
		$this->set_controls_config();

		$element->start_controls_section(
			'section_convesioconvert',
			array(
				'tab'       => 'content',
				'label'     => __( 'ConvesioConvert', 'convesioconvert' ),
				'condition' => array(
					'submit_actions' => $this->get_name(),
				),
			)
		);

		$element->add_control(
			'convesioconvert_form_name',
			array(
				'type'    => Controls_Manager::TEXT,
				'label'   => __( 'Form Name', 'convesioconvert' ),
				'default' => __( 'New Form', 'convesioconvert' ),
			)
		);

		$element->add_control(
			'convesioconvert_form_tags',
			array(
				'type'        => Controls_Manager::SELECT2,
				'label'       => __( 'Tags', 'convesioconvert' ),
				'description' => __( 'Add comma separated tags', 'convesioconvert' ),
				'multiple'    => true,
				'options'     => $this->convesioconvert_tags,
			)
		);

		$element->add_control(
			'convesioconvert_heading_field_mapping',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => __( 'Field Mapping', 'convesioconvert' ),
			)
		);

		$repeater_control_args = array(
			'name'          => 'convesioconvert_mapping_fields',
			'type'          => Controls_Manager::REPEATER,
			'title_field'   => '{{ convesioconvert_form_field }}',
			'prevent_empty' => true,
			'default'       => array(
				array(
					'convesioconvert_attribute'  => 'none',
					'convesioconvert_form_field' => 'none',
				),
			),
		);

		$repeater_control_args['fields'] = $this->register_controls_by_add_control();

		$element->add_control(
			'convesioconvert_mapping_fields',
			$repeater_control_args
		);

		$element->end_controls_section();
	}

	/**
	 * Run
	 *
	 * Runs the action after submit
	 *
	 * @access public
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run( $record, $ajax_handler ) {
		$this->update_convesioconvert_attributes();

		$settings   = $record->get( 'form_settings' );
		$raw_fields = $record->get( 'fields' );
		$all_fields = array();
		$site_id    = get_option( 'convesioconvert_site_id' );
		$tags       = $settings['convesioconvert_form_tags'];
		$form_name  = $settings['convesioconvert_form_name'];

		// Get value of default and custom mapped fields.
		foreach ( $settings['convesioconvert_mapping_fields'] as $field_key => $value ) {
			if ( ! isset( $value['convesioconvert_form_field'] ) ) {
				continue;
			}

			$field_id = $value['convesioconvert_form_field'];

			if ( $field_id ) {
				$convesioconvert_field = str_replace( 'convesioconvert-', '', $value['convesioconvert_attribute'] );

				if ( ! empty( $convesioconvert_field ) && 'none' !== $convesioconvert_field ) {
					if ( in_array( $convesioconvert_field, array( '012', '013' ), true ) ) {
						$raw_fields[ $field_id ]['value'] = Utils::guess_consent_type( $raw_fields[ $field_id ]['value'] );
					}

					if ( is_array( $raw_fields[ $field_id ]['value'] ) ) {
						continue;
					}

					$field = array(
						'key'   => $convesioconvert_field,
						'value' => $raw_fields[ $field_id ]['value'],
						'type'  => Integration::instance()->get_attribute_type( $convesioconvert_field ),
					);

					array_push( $all_fields, $field );
				}
			}
		}

		$form_data = array(
			'siteId'   => $site_id,
			'formName' => $form_name,
			'tags'     => $tags,
			'formData' => $all_fields,
		);

		$response = Integration::instance()->form_submission( $form_data );

		if ( is_wp_error( $response ) ) {
			$ajax_handler->add_admin_error_message( 'ConvesioConvert: Internal server error.' );
		} elseif ( ! $response['success'] ) {
			$ajax_handler->add_admin_error_message( 'ConvesioConvert: Could not send the form successfuly.' );
		}
	}

	/**
	 * On Export
	 *
	 * Clears form settings on export
	 *
	 * @access public
	 * @param array $element
	 */
	public function on_export( $element ) {
		foreach ( $this->convesioconvert_fields as $fields_key => $label ) {
			if ( isset( $element[ 'convesioconvert_mapping_fields_' . $fields_key ] ) ) {
				unset( $element[ 'convesioconvert_mapping_fields_' . $fields_key ] );
			}
		}
	}
}
