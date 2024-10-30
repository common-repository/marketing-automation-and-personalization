<?php

namespace ConvesioConvert\Admin;

use function ConvesioConvert\EDD\is_edd_active;
use function ConvesioConvert\EDD2\is_edd_active as is_edd_2_active;

/**
 * EDD Documentation for showing and saving additional order fields:
 * - https://easydigitaldownloads.com/docs/custom-checkout-fields/
 */
class Email_Consent {

	public function __construct() {
		$this->add_default_settings();
		add_action( 'wp_ajax_convesioconvert_save_settings', array( $this, 'save_settings' ) );

		$options = get_option( 'convesioconvert_consents' );

		if ( $options['signup'] ) {
			// WP Registration form.
			add_action( 'register_form', array( $this, 'add_registration_form_field' ) );
			add_action( 'user_register', array( $this, 'save_registration_form_field' ) );
			add_action( 'login_enqueue_scripts', array( $this, 'styles' ) );
		}

		if ( $options['wc_signup'] ) {
			add_action( 'woocommerce_register_form', array( $this, 'add_wc_register_form_field' ) );
			add_action( 'woocommerce_created_customer', array( $this, 'save_wc_register_form_field' ) );
		}

		if ( $options['wc_checkout'] ) {
			add_filter( 'woocommerce_order_button_html', array( $this, 'add_wc_checkout_field' ), 10, 1 );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_wc_checkout_field' ) );
		}

		if ( ! empty( $options['edd_checkout'] ) ) {
			add_action( 'edd_purchase_form_user_info_fields', array( $this, 'add_edd_checkout_field' ) );

			if ( is_edd_active() ) {
				add_action( 'edd_built_order', array( $this, 'save_edd_order_fields' ), 999, 2 );
			} elseif ( is_edd_2_active() ) {
				add_filter( 'edd_payment_meta', array( $this, 'save_edd_checkout_field' ), 999 );
			}
		}
	}

	public function save_settings() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'convesioconvert_ajax' ) ) {
			wp_send_json_error();
		}

		$options = array(
			'signup'       => (int) filter_var( $_POST['convesioconvert_consent_signup'], FILTER_VALIDATE_BOOLEAN ), //phpcs:ignore
			'wc_signup'    => (int) filter_var( $_POST['convesioconvert_consent_wc_signup'], FILTER_VALIDATE_BOOLEAN ), //phpcs:ignore
			'wc_checkout'  => (int) filter_var( $_POST['convesioconvert_consent_wc_checkout'], FILTER_VALIDATE_BOOLEAN ), //phpcs:ignore
			'edd_checkout' => (int) filter_var( $_POST['convesioconvert_consent_edd_checkout'], FILTER_VALIDATE_BOOLEAN ), //phpcs:ignore
			'consent_text' => sanitize_text_field( wp_unslash( $_POST['convesioconvert_consent_statement'] ) ), //phpcs:ignore
		);

		$options['consent_text'] = str_replace( '{{sitename}}', get_bloginfo( 'name' ), $options['consent_text'] );

		update_option( 'convesioconvert_consents', $options );

		wp_send_json_success();
	}

	// Add EDD.
	public function add_edd_checkout_field() {
		$this->show_checkbox( 'convesioconvert_consent_edd_checkout' );
	}

	/** Save consent data into the EDD order meta */
	public function save_edd_order_fields( $order_id, $order_data ) {
		if ( ! did_action( 'edd_pre_process_purchase' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$consent = isset( $_POST['convesioconvert_consent_edd_checkout'] ) && 'on' === $_POST['convesioconvert_consent_edd_checkout']
			? 'granted'
			: 'denied';

		edd_update_order_meta( $order_id, '_convesioconvert_email_consent', $consent );

		if ( isset( $order_data['user_info']['id'] ) ) {
			update_user_meta( $order_data['user_info']['id'], 'convesioconvert_email_consent', $consent );
		}
	}

	/** EDD < 3.x : Save consent data into the EDD payment meta */
	public function save_edd_checkout_field( $payment_meta ) {
		if ( did_action( 'edd_pre_process_purchase' ) && ! empty( $payment_meta ) ) {
			$payment_meta['convesioconvert_email_consent'] =
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				isset( $_POST['convesioconvert_consent_edd_checkout'] ) && 'on' === $_POST['convesioconvert_consent_edd_checkout']
					? 'granted'
					: 'denied';

			if ( isset( $payment_meta['user_info']['id'] ) ) {
				update_user_meta( $payment_meta['user_info']['id'], 'convesioconvert_email_consent', $payment_meta['convesioconvert_email_consent'] );
			}
		}

		return $payment_meta;
	}

	// Add WC checkout.
	public function add_wc_checkout_field( $html ) {
		return $this->show_checkbox( 'convesioconvert_consent_wc_checkout' ) . $html;
	}

	// Save WC checkout.
	public function save_wc_checkout_field( $order_id ) {

		$order   = new \WC_Order( $order_id );
		$user_id = $order->get_user_id();
		$consent =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			isset( $_POST['convesioconvert_consent_wc_checkout'] ) && 'on' === $_POST['convesioconvert_consent_wc_checkout']
				? 'granted'
				: 'denied';

		$order->update_meta_data( 'convesioconvert_email_consent', $consent );
		$order->save();

		if ( $user_id > 0 ) {
			update_user_meta( $user_id, 'convesioconvert_email_consent', $consent );
		}
	}

	// Add WC register.
	public function add_wc_register_form_field() {
		$this->show_checkbox( 'convesioconvert_consent_wc_signup' );
	}

	// Save WC register.
	public function save_wc_register_form_field( $user_id ) {
		if ( isset( $_POST['convesioconvert_consent_wc_signup'] ) && 'on' === $_POST['convesioconvert_consent_wc_signup'] ) { //phpcs:ignore
			update_user_meta( $user_id, 'convesioconvert_email_consent', 'granted' );
		} else {
			update_user_meta( $user_id, 'convesioconvert_email_consent', 'denied' );
		}
	}

	// Add WP Signup.
	public function add_registration_form_field() {
		$this->show_checkbox( 'convesioconvert_consent_signup' );
	}

	// Save WP Signup.
	public function save_registration_form_field( $user_id ) {
		if ( isset( $_POST['convesioconvert_consent_signup'] ) && 'on' === $_POST['convesioconvert_consent_signup'] ) { // phpcs:ignore
			update_user_meta( $user_id, 'convesioconvert_email_consent', 'granted' );
		} else {
			update_user_meta( $user_id, 'convesioconvert_email_consent', 'denied' );
		}
	}

	// Avoids multiple checks for option existence.
	public function add_default_settings() {
		$defaults = array(
			'signup'       => 0,
			'wc_signup'    => 0,
			'wc_checkout'  => 0,
			'edd_checkout' => 0,
			'consent_text' => __( 'I\'d like to subscribe to {{sitename}} newsletter to get product updates & news, weekly digest, and more.', 'convesioconvert' ),
		);

		add_option( 'convesioconvert_consents', $defaults );
	}

	/**
	 * Note: As of now we don't remove leftover 'convesioconvert_email_consent' in edd_payment_meta in EDD 2, or EDD 3 sites
	 * that upgraded from 2.
	 * The new code for EDD 3 stores these meta with an underscore (_) prefix, and they are removed properly though.
	 */
	public static function remove_consent_settings_and_data() {
		delete_option( 'convesioconvert_consents' );

		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key = 'convesioconvert_email_consent'" );
	}

	// Generate checkbox.
	private function show_checkbox( $name = '' ) {
		$options = get_option( 'convesioconvert_consents' );
		?>
		<p class="convesioconvert-consent">
			<label for="<?php echo esc_attr( $name ); ?>">
				<input
				type="checkbox"
				id="<?php echo esc_attr( $name ); ?>"
				name="<?php echo esc_attr( $name ); ?>">
				<?php echo esc_html( $options['consent_text'] ); ?>
			</label>
		</p>
		<?php
	}

	public function styles() {
		echo '
		<style type="text/css">
		#registerform {
			display: flex;
			flex-direction: column;
		}
		p.submit {
			order: 90;
		}
		p.convesioconvert-consent {
			order: 80;
		}
		</style>
		';
	}

}
