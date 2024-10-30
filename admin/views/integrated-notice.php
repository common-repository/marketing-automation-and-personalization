<div class="convesioconvert-notice convesioconvert-notice-integration-success notice notice-success is-dismissible">
	<p>
		<?php
		printf(
			'<strong>%s</strong> &#8211; %s',
			esc_html__( 'Integration completed', 'convesioconvert' ),
			esc_html__( 'Congratulations! You have successfully integrated ConvesioConvert with your website. Please be patient while we are synchronizing your website with ConvesioConvert platform.', 'convesioconvert' )
		);
		?>
	</p>
	<p>
		<?php if ( \ConvesioConvert\Admin\Health_Check::site_exists() ) : ?>
			<a class="button" id="convesioconvert-go-to-dashboard-button" href="<?php echo esc_url( sprintf( '%s/sites/%s/getting-started', CONVESIOCONVERT_APP_URL, get_option( 'convesioconvert_site_id' ) ) ); ?>">
				<?php esc_html_e( 'Continue to ConvesioConvert', 'convesioconvert' ); ?>
			</a>
		<?php else : ?>
			<button class="button" title="<?php esc_attr_e( 'Site has been removed.', 'convesioconvert' ); ?>" disabled>
				<?php esc_html_e( 'Continue to ConvesioConvert', 'convesioconvert' ); ?>
			</button>
		<?php endif; ?>
	</p>
</div>
