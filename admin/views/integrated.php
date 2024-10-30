<?php

$options = get_option( 'convesioconvert_consents', array() );

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<div class="convesioconvert-card">
		<h3 class="convesioconvert-card-title">
			<span>
				<?php esc_html_e( 'Status: ', 'convesioconvert' ); ?>
				<?php if ( \ConvesioConvert\Admin\Integration::is_paused() ) : ?>
					<em class="convesioconvert-text-warning"><?php esc_html_e( 'Paused', 'convesioconvert' ); ?></em>
				<?php else : ?>
					<em class="convesioconvert-text-success"><?php esc_html_e( 'Connected', 'convesioconvert' ); ?></em>
				<?php endif; ?>
			</span>
			<?php if ( \ConvesioConvert\Admin\Health_Check::site_exists() ) : ?>
				<a class="button button-secondary" target="_blank" href="<?php echo esc_url( sprintf( '%s/sites/%s/getting-started', CONVESIOCONVERT_APP_URL, get_option( 'convesioconvert_site_id' ) ) ); ?>">
					<?php esc_html_e( 'View Dashboard', 'convesioconvert' ); ?>
				</a>
			<?php else : ?>
				<button class="button button-secondary" title="<?php esc_attr_e( 'Site has been removed.', 'convesioconvert' ); ?>" disabled>
					<?php esc_html_e( 'View Dashboard', 'convesioconvert' ); ?>
				</button>
			<?php endif; ?>
		</h3>
		<h4><?php esc_html_e( 'Integration details:', 'convesioconvert' ); ?></h4>
		<p>
			<strong><?php esc_html_e( 'Sync Status:', 'convesioconvert' ); ?></strong>
			<?php if ( \ConvesioConvert\Admin\Health_Check::is_in_progress() ) : ?>
				<em id="admin-sync-status" class="convesioconvert-text-secondary"><?php esc_html_e( 'In Progress', 'convesioconvert' ); ?></em>
			<?php elseif ( \ConvesioConvert\Admin\Health_Check::has_errors() ) : ?>
				<em id="admin-sync-status" class="convesioconvert-text-danger"><?php esc_html_e( 'Error', 'convesioconvert' ); ?></em>
			<?php else : ?>
				<em id="admin-sync-status" class="convesioconvert-text-success"><?php esc_html_e( 'Success', 'convesioconvert' ); ?></em>
			<?php endif; ?>

		</p>
		<p>
			<strong><?php esc_html_e( 'ConvesioConvert Account:', 'convesioconvert' ); ?></strong>
			<?php echo esc_html( get_option( 'convesioconvert_user_email', __( '[Unknown]', 'convesioconvert' ) ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Site URL:', 'convesioconvert' ); ?></strong>
			<?php /* translators: %s is a URL. */ ?>
			<?php echo esc_html( get_option( 'convesioconvert_site_url', sprintf( __( '%s (Reported by WordPress)', 'convesioconvert' ), get_site_url() ) ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Site ID:', 'convesioconvert' ); ?></strong>
			<?php echo esc_html( get_option( 'convesioconvert_site_id', __( '[Unknown]', 'convesioconvert' ) ) ); ?>
		</p>
		<?php if ( \ConvesioConvert\Admin\Integration::is_paused() ) : ?>
			<p class="convesioconvert-card-row">
				<span><?php esc_html_e( 'Resume the integration to continue executing rules.', 'convesioconvert' ); ?></span>
				<button class="button button-secondary convesioconvert-resume-integration"><?php esc_html_e( 'Resume', 'convesioconvert' ); ?></button>
			</p>
		<?php else : ?>
			<p class="convesioconvert-card-row">
				<?php /* translators: %s is a Learn More link. */ ?>
				<span><?php esc_html_e( 'Temporarily pause the integration. Rules will not be executed. Analytics and data gathering will keep running.', 'convesioconvert' ); ?></span>
				<button class="button button-secondary convesioconvert-pause-integration"><?php esc_html_e( 'Pause', 'convesioconvert' ); ?></button>
			</p>
		<?php endif; ?>
		<p class="convesioconvert-card-row">
			<?php /* translators: %s is a Learn More link. */ ?>
			<span><?php esc_html_e( 'Stop synchronizing your site and rule execution.', 'convesioconvert' ); ?></span>
			<button class="button convesioconvert-button-danger convesioconvert-disconnect-confirm"><?php esc_html_e( 'Remove Integration', 'convesioconvert' ); ?></button>
		</p>
		<div class="convesioconvert-dialog" id="convesioconvert-disconnect-dialog">
			<div class="convesioconvert-dialog-header">
				<h3 class="convesioconvert-dialog-title"><?php esc_html_e( 'Disconnect ConvesioConvert?', 'convesioconvert' ); ?></h3>
			</div>
			<div class="convesioconvert-dialog-content">
				<p><?php esc_html_e( 'Are you sure you want to disconnect your site?', 'convesioconvert' ); ?></p>
				<p><?php esc_html_e( 'Data synchronization will be stopped and the rules will stop executing. You can pause the synchronization instead.', 'convesioconvert' ); ?></p>
			</div>
			<div class="convesioconvert-dialog-footer">
				<button class="button button-primary convesioconvert-remove-integration"><?php esc_html_e( 'Remove Integration', 'convesioconvert' ); ?></button>
				<button class="button button-secondary convesioconvert-dialog-close"><?php esc_html_e( 'Cancel', 'convesioconvert' ); ?></button>
			</div>
		</div>
	</div>
	<div class="convesioconvert-card">
		<h3 class="convesioconvert-card-title">
			<span><?php esc_html_e( 'Settings', 'convesioconvert' ); ?></span>
		</h3>
		<p>
			<h4>
				<span>
					<?php esc_html_e( 'Marketing Email Consent', 'convesioconvert' ); ?>
				</span>
				<span
					class="convesioconvert-help-icon"
					title="<?php esc_attr_e( 'If you want to send marketing emails to your users, you should collect their consent when they sign up to your website. In this section you can define which forms to show the consent checkbox and its content.', 'convesioconvert' ); ?>"
				>
					<span class="dashicons dashicons-editor-help"></span>
				</span>
			</h4>
			<div class="convesioconvert-setting-field">
				<input type="checkbox" id="convesioconvert-consent-signup" name="convesioconvert_consent_signup" <?php echo $options['signup'] ? 'checked' : ''; ?> >
				<label for="convesioconvert-consent-signup">
				<?php esc_html_e( 'Show in WordPress native signup form', 'convesioconvert' ); ?>
				</label>
			</div>
			<div class="convesioconvert-setting-field">
				<input type="checkbox" id="convesioconvert-consent-wc-signup" name="convesioconvert_consent_wc_signup" <?php echo $options['wc_signup'] ? 'checked' : ''; ?>>
				<label for="convesioconvert-consent-wc-signup">
					<?php esc_html_e( 'Show in WooCommerce signup form', 'convesioconvert' ); ?>
				</label>
			</div>
			<div class="convesioconvert-setting-field">
				<input type="checkbox" id="convesioconvert-consent-wc-checkout" name="convesioconvert_consent_wc_checkout" <?php echo $options['wc_checkout'] ? 'checked' : ''; ?>>
				<label for="convesioconvert-consent-wc-checkout">
				<?php esc_html_e( 'Show in WooCommerce Checkout page', 'convesioconvert' ); ?>
				</label>
			</div>
			<div class="convesioconvert-setting-field">
				<input type="checkbox" id="convesioconvert-consent-edd-checkout" name="convesioconvert_consent_edd_checkout" <?php echo ! empty( $options['edd_checkout'] ) ? 'checked' : ''; ?>>
				<label for="convesioconvert-consent-edd-checkout">
				<?php esc_html_e( 'Show in Easy Digital Downloads Checkout page', 'convesioconvert' ); ?>
				</label>
			</div>
			</p>
		<p>
			<h4>
				<?php esc_html_e( 'Marketing Email Consent Statement', 'convesioconvert' ); ?>
			</h4>
			<?php
			$consent_text = __( 'I\'d like to subscribe to {{sitename}} newsletter to get product updates & news, weekly digest, and more.', 'convesioconvert' );

			if ( $options['consent_text'] ) {
				$consent_text = $options['consent_text'];
			}
			?>
			<textarea name="convesioconvert_statement" id="convesioconvert_consent_statement" cols="70" rows="3"><?php echo esc_html( $consent_text ); ?></textarea>
			<div class="convesioconvert-hint">
				<small>
					<?php esc_html_e( 'You can use {{sitename}} and it will be replaced by your sitename automatically.', 'convesioconvert' ); ?>
				</small>
			</div>
		</p>
		<p>
			<div class="convesioconvert-dialog-footer">
				<?php wp_nonce_field( 'convesioconvert_save_settings', 'convesioconvert_settings_nonce' ); ?>
				<button id="convesioconvert-save-settings" class="button button-primary"><?php esc_html_e( 'Save', 'convesioconvert' ); ?></button>
			</div>
		</p>
	</div>
</div>

<?php
