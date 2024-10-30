<?php

namespace ConvesioConvert\Admin;

class Feedback {

	public function __construct() {
		// Do not show feedback modal if not integrated yet.
		if ( ! get_option( 'convesioconvert_site_id', false ) ) {
			return;
		}

		add_action( 'current_screen', array( $this, 'init' ) );
		add_action( 'wp_ajax_convesioconvert_feedback', array( $this, 'send' ) );
	}

	public function init() {
		if ( ! $this->is_plugins_page() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enueue_scripts' ) );
		add_action( 'admin_footer', array( $this, 'render_modal' ) );
	}

	public function enueue_scripts() {
		wp_enqueue_script( 'convesioconvert-feedback' );
		wp_enqueue_style( 'convesioconvert-feedback' );
	}

	private function is_plugins_page() {
		// We are not showing feedback modal for network.
		return in_array( get_current_screen()->id, array( 'plugins' ), true );
	}

	public function render_modal() {
		$deactivation_reasons = array(
			// we dont need it anymore: 'not_using'              => __( 'Didn\'t use it', 'convesioconvert' ),
			'company_closed'         => __( 'Company Closed', 'convesioconvert' ),
			'better_solution'        => __( 'I found a better solution', 'convesioconvert' ),
			'too_difficult'          => __( 'Too difficult to figure out', 'convesioconvert' ),
			'lack_feature'           => __( 'Lack of features', 'convesioconvert' ),
			'meeting_my_needs'       => __( 'Doesn\'t meet my needs', 'convesioconvert' ),
			'cost'                   => __( 'Cost too much', 'convesioconvert' ),
			'lack_integration'       => __( 'Lack of integration with my tools', 'convesioconvert' ),
			'terms'                  => __( 'I am not down with your terms of service', 'convesioconvert' ),
			'temporary_deactivation' => __( 'It\'s a temporary deactivation', 'convesioconvert' ),
			'other'                  => __( 'Other', 'convesioconvert' ),
		);

		?>
		<div id="convesioconvert-modal-bg" style="display: none;">
			<div id="convesioconvert-feedback-modal">
				<div class="convesioconvert-feedback-modal-inner">
					<h2><?php esc_html_e( 'If you have a moment, please tell us why you are deactivating:', 'convesioconvert' ); ?></h2>

					<div class="convesioconvert-question-row">
						<h4>
							<?php esc_html_e( '1. Why did you cancel your account?' ); ?>
							<span class="convesioconvert-hint" style="display:none;"><?php esc_html_e( '(required)', 'convesioconvert' ); ?></span>
						</h4>
						<?php
						foreach ( $deactivation_reasons as $value => $label ) {
							?>
							<div class="option-row">
								<input type="checkbox" name="convesioconvert-deactivation-reason" id="<?php echo esc_attr( $value ); ?>" value="<?php echo esc_attr( $value ); ?>" style="margin: 0px">
								<label for="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></label>
							</div>
							<?php
						}
						?>
					</div>
					<div class="convesioconvert-question-row">
						<h4>
							<?php esc_html_e( '2. Can you explain more? What could we do to improve?', 'convesioconvert' ); ?>
							<span class="convesioconvert-explanation"><?php esc_html_e( '(optional)', 'convesioconvert' ); ?></span>
							<div class="alert alert-danger convesioconvert-err-note" style="display:none;">Please tell us why:</div>
						</h4>
						<textarea name="convesioconvert-deactivation-note" id="" rows="4"></textarea>
					</div>
					<div class="convesioconvert-question-row">
						<input type="checkbox" name="convesioconvert-deactivation-contact" id="convesioconvert-deactivation-contact">
						<label for="convesioconvert-deactivation-contact"><?php esc_html_e( 'Can we reach out to you later and discuss why you canceled your account?', 'convesioconvert' ); ?></label>
					</div>
					<div class="convesioconvert-question-row">
						<button id="convesioconvert-send-modal" type="button" class="button button-secondary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'convesioconvert_feedback' ) ); ?>">
							<?php esc_html_e( 'Submit and deactivate', 'convesioconvert' ); ?>
						</button>
						<button id="convesioconvert-discard-modal" type="button" class="button button-primary">
							<?php esc_html_e( 'Discard', 'convesioconvert' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<script>

		</script>
		<?php
	}

	public function send() {

		if ( ! isset( $_POST['n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['n'] ) ), 'convesioconvert_feedback' ) ) {
			wp_send_json_error();
		}

		$reasons = isset( $_POST['reasons'] ) ? $_POST['reasons'] : null; // phpcs:ignore
		$note    = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : null;
		$email   = null;

		if ( isset( $_POST['contact'] ) && 'false' != sanitize_text_field( wp_unslash( $_POST['contact'] ) ) ) { // phpcs:ignore
			$email = wp_get_current_user()->user_email;
		}

		$variables_types = array(
			'siteId'  => 'ID!',
			'reasons' => '[String!]',
			'note'    => 'String',
			'contact' => 'String',
		);

		$data = array(
			'siteId'  => get_option( 'convesioconvert_site_id', null ),
			'reasons' => $reasons,
			'note'    => $note,
			'contact' => $email,
		);

		// We don't prevent user from deactivating plugin on failure.
		\ConvesioConvert\GraphQL_Client::make()->make_mutation( 'deactivateFeedbacks', $variables_types, 'success', $data )->execute();

		wp_send_json_success();
	}
}
