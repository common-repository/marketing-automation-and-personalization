<?php
namespace ConvesioConvert\Form_Integration\Raven;

use ConvesioConvert\Form_Integration\Utils;
use JupiterX_Core\Raven\Modules\Forms\Actions\Action_Base;
use Elementor\Repeater;
use Elementor\Controls_Manager;
use ConvesioConvert\Form_Integration\Integration;

class Raven_Form_Action extends Action_Base {

	private $convesioconvert_tags, $attributes_config, $form_fields_config; //phpcs:ignore
	private $all_attributes = array();

	public function __construct() {
		add_action( 'elementor/element/raven-form/section_settings/after_section_end', array( $this, 'update_controls' ) );
	}

	public function get_name() {
		return 'convesioconvert';
	}

	public function get_title() {
		return __( 'ConvesioConvert', 'jupiterx-core' );
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

	public function update_controls( $widget ) {
		$this->update_convesioconvert_attributes();
		$this->set_controls_config();

		$widget->start_controls_section(
			'section_convesioconvert',
			array(
				'label'     => __( 'ConvesioConvert', 'jupiterx-core' ),
				'condition' => array(
					'actions' => $this->get_name(),
				),
			)
		);

		$widget->add_control(
			'convesioconvert_form_name',
			array(
				'type'    => 'text',
				'label'   => __( 'Form Name', 'convesioconvert' ),
				'default' => __( 'New Form', 'convesioconvert' ),
			)
		);

		$widget->add_control(
			'convesioconvert_form_tags',
			array(
				'type'        => 'select2',
				'label'       => __( 'Tags', 'convesioconvert' ),
				'description' => __( 'Add comma separated tags', 'convesioconvert' ),
				'multiple'    => true,
				'options'     => $this->convesioconvert_tags,
			)
		);

		$widget->add_control(
			'convesioconvert_heading_field_mapping',
			array(
				'type'  => 'heading',
				'label' => __( 'Field Mapping', 'convesioconvert' ),
			)
		);

		$repeater_control_args = array(
			'name'          => 'convesioconvert_mapping_fields',
			'type'          => 'repeater',
			'prevent_empty' => true,
			'default'       => array(
				array(
					'convesioconvert_attribute'  => 'none',
					'convesioconvert_form_field' => 'none',
				),
			),
		);

		$repeater_control_args['fields'] = $this->register_controls_by_add_control();

		$widget->add_control(
			'convesioconvert_mapping_fields',
			$repeater_control_args
		);

		$widget->end_controls_section();
	}

	public static function run( $ajax_handler ) {
		$form     = $ajax_handler->form;
		$settings = $form['settings'];

		$all_fields = array();
		$site_id    = get_option( 'convesioconvert_site_id' );
		$tags       = isset( $settings['convesioconvert_form_tags'] ) ? $settings['convesioconvert_form_tags'] : '';
		$form_name  = isset( $settings['convesioconvert_form_name'] ) ? $settings['convesioconvert_form_name'] : '';

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
						$ajax_handler->record['fields'][ $field_id ] = Utils::guess_consent_type( $ajax_handler->record['fields'][ $field_id ] );
					}

					$field = array(
						'key'   => $convesioconvert_field,
						'value' => $ajax_handler->record['fields'][ $field_id ],
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
			error_log( __( 'ConvesioConvert: Internal server error.', 'convesioconvert' ) ); // phpcs:ignore
		} elseif ( isset( $response['errors'] ) && isset( $response['error']['api_error'] ) ) {
			error_log( (string) $response['error']['api_error'][0] ); // phpcs:ignore
		}
	}
}
