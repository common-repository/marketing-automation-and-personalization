<?php

namespace ConvesioConvert\Form_Integration\Gravityforms;

use ConvesioConvert\Form_Integration\Integration;
use ConvesioConvert\Form_Integration\Utils;
use GF_Field;

class Form {

	public function __construct() {
		add_action( 'gform_field_standard_settings', array( $this, 'add_settings' ), 10, 2 );
		add_action( 'gform_editor_js', array( $this, 'editor_script' ) );
		add_action( 'gform_after_submission', array( $this, 'after_submission' ), 10, 2 );
	}

	public function add_settings( $section_id, $form_id ) {
		if ( -1 !== $section_id ) {
			return;
		}

		$all_attributes = Integration::instance()->get_all_attributes();

		?>
		<li class="convesioconvert_setting field_setting">
			<label for="convesioconvert_field_mapping" class="section_label">
				<?php esc_html_e( 'Map to ConvesioConvert field', 'convesioconvert' ); ?>
			</label>
			<select id="convesioconvert_field_mapping" onchange="SetFieldProperty('convesioconvertField', jQuery(this).val());">
				<option value="none"><?php esc_html_e( '-None-', 'convesioconvert' ); ?></option>

				<?php
				foreach ( $all_attributes as $key => $label ) :
					?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php
				endforeach;
				?>
			</select>
		</li>

		<?php

	}

	public function editor_script() {
		?>
		<script type='text/javascript'>
			let excluded = [
				'list',
				'fileupload',
				'consent',
				'creditcard',
				'captcha',
				'repeater',
				'password',
				'calculation',
			];
			// Add our settings to all of the existing fields.
			for (let settings in fieldSettings) {
				if ( -1 === excluded.indexOf( settings ) ){
					fieldSettings[settings] += ', .convesioconvert_setting';
				}
			}

			// binding to the load field settings event to initialize the checkbox.
			jQuery(document).on('gform_load_field_settings', function(event, field, form){
				jQuery('#convesioconvert_field_mapping').val(field.convesioconvertField || 'none' );
			});
		</script>
		<?php
	}

	public function after_submission( $entry, $form ) {

		$form_data = array();

		$form_data['siteId']   = get_option( 'convesioconvert_site_id' );
		$form_data['formName'] = $form['title'];
		$form_data['tags']     = array();

		foreach ( $form['fields'] as $field ) {
			if ( ! empty( $field['convesioconvertField'] ) && 'none' !== $field['convesioconvertField'] ) {
				$field_code = str_replace( 'convesioconvert-', '', $field['convesioconvertField'] );
				$this->add_field_values( $form_data, $entry, $field, $field_code );
			}
		}

		$response = Integration::instance()->form_submission( $form_data );

		if ( is_wp_error( $response ) ) {
			error_log( __( 'ConvesioConvert: Internal server error.', 'convesioconvert' ) ); // phpcs:ignore
		} elseif ( isset( $response['errors'] ) && isset( $response['error']['api_error'] ) ) {
			error_log( (string) $response['error']['api_error'][0] ); // phpcs:ignore
		}

	}

	/**
	 * @param array $form_data The data to be sent to form submission mutation
	 * @param array $entry From GravityForms https://docs.gravityforms.com/entry-object/
	 * @param GF_Field $field A subclass of GF_Field from GravityForms https://docs.gravityforms.com/gf_field/
	 * @param string $field_code One of '001', '002', etc. fields coming from BE FORM_BASIC_USER_ATTRIBUTES.
	 */
	private function add_field_values( &$form_data, $entry, $field, $field_code ) {

		switch ( $field['type'] ) {
			case 'name':
				// https://docs.gravityforms.com/name/
				// https://docs.gravityforms.com/gf_field_name/

				$prefix = rgar( $entry, "$field->id.2", '' );
				$first  = rgar( $entry, "$field->id.3", '' );
				$middle = rgar( $entry, "$field->id.4", '' );
				$last   = rgar( $entry, "$field->id.6", '' );
				$suffix = rgar( $entry, "$field->id.8", '' );

				$first_name = implode( ' ', array_filter( array( $prefix, $first, $middle ) ) );
				$last_name  = implode( ' ', array_filter( array( $last, $suffix ) ) );
				$full_name  = implode( ' ', array_filter( array( $prefix, $first, $middle, $last, $suffix ) ) );

				// If this is mapped to a name field on our end, only extract the relevant data from the field.
				// Otherwise, send the full name.
				if ( '002' === $field_code ) {
					// full_name field in our backend
					// We actually don't try to send the '002' full_name field as GravityForms name field can
					// separate the first and last name parts for us.
					$form_data['formData'][] = Utils::form_data_entry( '003', $first_name );
					$form_data['formData'][] = Utils::form_data_entry( '004', $last_name );
				} elseif ( '003' === $field_code ) {
					// first_name field in our backend
					$form_data['formData'][] = Utils::form_data_entry( $field_code, $first_name );
				} elseif ( '004' === $field_code ) {
					// last_name field in our backend
					$form_data['formData'][] = Utils::form_data_entry( $field_code, $last_name );
				} else {
					// Mapped to a field other than name field in our backend; send the full name.
					$form_data['formData'][] = Utils::form_data_entry( $field_code, $full_name );
				}

				break;
			case 'address':
				// https://docs.gravityforms.com/address-field/
				// https://docs.gravityforms.com/gf_field_address/

				$line1   = rgar( $entry, "$field->id.1", '' );
				$line2   = rgar( $entry, "$field->id.2", '' );
				$city    = rgar( $entry, "$field->id.3", '' );
				$state   = rgar( $entry, "$field->id.4", '' );
				$zip     = rgar( $entry, "$field->id.5", '' );
				$country = rgar( $entry, "$field->id.6", '' );

				// As a special case, if an address field on GravityForms is mapped to any Address-related field on our
				// backend, we will send the individual parts separately, otherwise will send the merged full address.

				if ( in_array( $field_code, array( '005', '007', '008', '009' ), true ) ) {
					$address_lines           = implode( ', ', array_filter( array( $line1, $line2, $zip ) ) );
					$form_data['formData'][] = Utils::form_data_entry( '005', $address_lines );
					$form_data['formData'][] = Utils::form_data_entry( '007', $country );
					$form_data['formData'][] = Utils::form_data_entry( '008', $state );
					$form_data['formData'][] = Utils::form_data_entry( '009', $city );
				} else {
					$full_address            = implode( ', ', array( $line1, $line2, $city, $state, $zip, $country ) );
					$form_data['formData'][] = Utils::form_data_entry( $field_code, $full_address );
				}

				break;
			default:
				// https://docs.gravityforms.com/gf_field/

				$field_name  = isset( $field->inputs[0]['id'] ) ? $field->inputs[0]['id'] : $field->id;
				$field_value = rgar( $entry, (string) $field_name );

				// For eu_consent and marketing_email_consent, try to transform the value.
				if ( in_array( $field_code, array( '012', '013' ), true ) ) {
					$field_value = Utils::guess_consent_type( $field_value );
				}

				$form_data['formData'][] = Utils::form_data_entry( $field_code, $field_value );

				break;
		}
	}
}
