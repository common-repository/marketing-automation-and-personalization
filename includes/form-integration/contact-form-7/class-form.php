<?php

namespace ConvesioConvert\Form_Integration\CF7;

use ConvesioConvert\Form_Integration\Integration;
use ConvesioConvert\Form_Integration\Utils;

class Form {

	public function __construct() {
		add_action( 'wpcf7_submit', array( $this, 'send' ), 10, 2 );
	}

	public function map( $posted_data ) {
		$mapped_data = array();

		if ( isset( $posted_data['your-email'] ) ) {
			$mapped_data[] = array(
				'key'   => '001',
				'value' => $posted_data['your-email'],
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['your-first-name'] ) ) {
			$mapped_data[] = array(
				'key'   => '003',
				'value' => $posted_data['your-first-name'],
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['your-last-name'] ) ) {
			$mapped_data[] = array(
				'key'   => '004',
				'value' => $posted_data['your-last-name'],
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['your-address'] ) ) {
			$mapped_data[] = array(
				'key'   => '005',
				'value' => $posted_data['your-address'],
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['your-phone'] ) ) {
			$mapped_data[] = array(
				'key'   => '006',
				'value' => $posted_data['your-phone'],
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['your-country'] ) ) {
			$country = $posted_data['your-country'];

			$mapped_data[] = array(
				'key'   => '007',
				'value' => is_array( $country ) ? $country[0] : $country,
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['your-region'] ) ) {
			$region = $posted_data['your-region'];

			$mapped_data[] = array(
				'key'   => '008',
				'value' => is_array( $region ) ? $region[0] : $region,
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['your-city'] ) ) {
			$city = $posted_data['your-city'];

			$mapped_data[] = array(
				'key'   => '009',
				'value' => is_array( $city ) ? $city[0] : $city,
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['your-birthdate'] ) ) {
			$mapped_data[] = array(
				'key'   => '014',
				'value' => $posted_data['your-birthdate'],
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['eu-consent'] ) ) {
			$mapped_data[] = array(
				'key'   => '012',
				'value' => Utils::guess_consent_type( $posted_data['eu-consent'][0] ),
				'type'  => 'basic',
			);
		}

		if ( isset( $posted_data['marketing-email-consent'] ) ) {
			$mapped_data[] = array(
				'key'   => '013',
				'value' => Utils::guess_consent_type( $posted_data['marketing-email-consent'][0] ),
				'type'  => 'basic',
			);
		}

		return $mapped_data;
	}

	public function send( $cf7_obj, $submit_results ) {

		$form_data = array();

		if ( isset( $submit_results['status'] ) && 'mail_sent' === $submit_results['status'] ) {

			$submission      = \WPCF7_Submission::get_instance();
			$submission_data = (array) $submission->get_posted_data();

			$form_data['siteId']   = get_option( 'convesioconvert_site_id' );
			$form_data['formName'] = $cf7_obj->title();
			$form_data['formData'] = $this->map( $submission_data );
			$form_data['tags']     = array();

			$response = Integration::instance()->form_submission( $form_data );

			if ( is_wp_error( $response ) ) {
				error_log( __( 'ConvesioConvert: Internal server error.', 'convesioconvert' ) ); // phpcs:ignore
			} elseif ( isset( $response['errors'] ) && isset( $response['error']['api_error'] ) ) {
				error_log( (string) $response['error']['api_error'][0] ); // phpcs:ignore
			}
		}
	}

}
