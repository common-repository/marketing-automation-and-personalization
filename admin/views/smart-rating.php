<?php
$nonce = wp_create_nonce( 'convesioconvert_feedback_notification_bar_nonce' );
?>
<div data-nonce="<?php echo esc_attr( $nonce ); ?>" class="convesioconvert-feedback-notification-bar-notice notice notice-info is-dismissible">
	<div class="convesioconvert-feedback-notification-bar-notice-inner">
		<div class="convesioconvert-feedback-notification-bar-notice-logo">
			<img src="<?php echo esc_url( CONVESIOCONVERT_ADMIN_URL . '/assets/img/notice-logo.png' ); ?>" alt="<?php esc_html_e( 'ConvesioConvert', 'convesioconvert' ); ?>" />
		</div>
		<div class="convesioconvert-feedback-notification-bar-notice-content">
			<!-- STEP 1 -->
			<div class="convesioconvert-feedback-notification-bar-notice-step" data-step="1">
				<p><strong><?php esc_html_e( 'Love using ConvesioConvert?', 'convesioconvert' ); ?></strong></p>
				<div class="jupiterx-feedback-notification-bar-notice-step-actions">
					<button class="button button-primary" data-step="2"><?php esc_html_e( 'Yes, I am', 'convesioconvert' ); ?></button>
					<button class="button-secondary" data-step="3"><?php esc_html_e( 'No, I\'m not', 'convesioconvert' ); ?></button>
				</div>
			</div>
			<!-- STEP 2 -->
			<div class="convesioconvert-feedback-notification-bar-notice-step hidden" data-step="2">
				<p><strong><?php esc_html_e( 'Please support us by rating ConvesioConvert', 'convesioconvert' ); ?></strong></p>
				<div class="convesioconvert-feedback-notification-bar-notice-step-actions">
					<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/marketing-automation-and-personalization/reviews/' ); ?>" class="button button-primary" target="_blank"><?php esc_html_e( 'Sure, I\'d love to rate', 'convesioconvert' ); ?></a>
					<button class="button-secondary"><?php esc_html_e( 'No thanks', 'convesioconvert' ); ?> </button>
				</div>
			</div>
			<!-- STEP 3 -->
			<div class="convesioconvert-feedback-notification-bar-notice-step hidden" data-step="3">
				<p><strong><?php esc_html_e( 'Would you like to share the problem you are having with ConvesioConvert?', 'convesioconvert' ); ?></strong></p>
				<div class="convesioconvert-feedback-notification-bar-notice-step-actions">
					<a href="<?php echo esc_url( 'mailto:support@convesio.com' ); ?>" class="button button-primary" target="_blank"><?php esc_html_e( 'Contact support', 'convesioconvert' ); ?></a>
					<a href="<?php echo esc_url( 'mailto:info@convesio.com' ); ?>" class="button-secondary" target="_blank"><?php esc_html_e( 'Report a bug', 'convesioconvert' ); ?></a>
				</div>
			</div>
		</div>
	</div>
</div>
