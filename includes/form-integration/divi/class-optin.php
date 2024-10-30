<?php

namespace ConvesioConvert\Form_Integration\Divi;

use ConvesioConvert\Form_Integration\Integration;

class Optin extends \ET_Core_API_Email_Provider {

	public $custom_fields_scope = 'account';

	public $uses_oauth = false;

	public function __construct( $owner = '', $account_name = '', $api_key = '' ) {

		$this->name = 'ConvesioConvert';
		$this->slug = 'convesioconvert';

		parent::__construct( 'ConvesioConvert', $account_name, $api_key );
	}

	public function get_account_fields() {
		return array();
	}

	public function subscribe( $args, $url = '' ) {
		$form_data = array();

		$form_data['siteId']   = get_option( 'convesioconvert_site_id' );
		$form_data['formName'] = 'Divi - optin module';
		$form_data['tags']     = array();

		$form_data['formData'][] = array(
			'key'   => '001',
			'value' => $args['email'],
			'type'  => 'basic',
		);

		$form_data['formData'][] = array(
			'key'   => '003',
			'value' => $args['name'],
			'type'  => 'basic',
		);

		if ( ! empty( $args['last_name'] ) ) {
			$form_data['formData'][] = array(
				'key'   => '004',
				'value' => $args['last_name'],
				'type'  => 'basic',
			);
		}

		$result = Integration::instance()->form_submission( $form_data );

		return $result['success'] ? 'success' : __( 'Error! Please try again.', 'convesioconvert' );
	}
}
