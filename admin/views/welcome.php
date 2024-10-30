<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<div class="convesioconvert-card">
		<h3 class="convesioconvert-card-title"><?php esc_html_e( 'Integrate with ConvesioConvert', 'convesioconvert' ); ?></h3>
		<p>
			<?php
			if ( \ConvesioConvert\Admin\Integration::had_integration() ) {
				esc_html_e( 'Connect your site again to continue executing rules and data collection.', 'convesioconvert' );
			} else {
				printf(
					/* translators: %1$s is a link to the site, %2$s is a link that reads 'Learn more'. */
					esc_html__( 'Please create an account on %1$s, then connect your site to start tracking your website\'s guests, leads and customers as well as executing automation rules. %2$s about creating an account and integrating your site with ConvesioConvert.', 'convesioconvert' ),
					'<a href="https://convesio.com/convert/" target="_blank">ConvesioConvert</a>',
					'<a href="https://convesio.com/knowledgebase/article/how-to-register-and-create-an-account-in-convesioconvert/" target="_blank">' . esc_html__( 'Learn more', 'convesioconvert' ) . '</a>'
				);
			}
			?>
		</p>
		<div class="convesioconvert-card-buttons">
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<?php wp_nonce_field( 'convesioconvert_integrate_post', 'nonce' ); ?>
				<input type="hidden" name="action" value="convesioconvert_integrate">
				<button class="button button-primary">
					<?php
					if ( \ConvesioConvert\Admin\Integration::had_integration() ) {
						esc_html_e( 'Reintegrate', 'convesioconvert' );
					} else {
						esc_html_e( 'Connect & Activate', 'convesioconvert' );
					}
					?>
				</button>
			</form>
		</div>

		<?php if ( \ConvesioConvert\Admin\Data_Manager::plugin_data_exists() ) : ?>
			<p class="convesioconvert-card-row">
				<span><?php esc_html_e( 'Erase ConvesioConvert data from WordPress.', 'convesioconvert' ); ?></span>
				<button class="button button-secondary convesioconvert-destroy-data-confirm"><?php esc_html_e( 'Erase ConvesioConvert Data', 'convesioconvert' ); ?></button>
			</p>

			<div class="convesioconvert-dialog" id="convesioconvert-destroy-data-dialog">
				<div class="convesioconvert-dialog-header">
					<h3 class="convesioconvert-dialog-title"><?php esc_html_e( 'Erase all ConvesioConvert data?', 'convesioconvert' ); ?></h3>
				</div>
				<div class="convesioconvert-dialog-content">
					<?php
						printf(
							'<p>%s</p><p style="margin-bottom: 6px;">%s</p><ul style="margin-top: 6px;"><li>&bullet; %s</li><li>&bullet; %s</li></ul><p>%s<br>%s</p>',
							esc_html__( 'Are you sure you want to erase ConvesioConvert plugin data from WordPress?', 'convesioconvert' ),
							esc_html__( 'Some analytics information will be deleted permanently, including, but not limited to: ', 'convesioconvert' ),
							esc_html__( 'User details in Customer Journey', 'convesioconvert' ),
							esc_html__( 'Purchase details in Customer Journey', 'convesioconvert' ),
							esc_html__( 'Use this feature for \'factory reset\' purposes.', 'convesioconvert' ),
							esc_html__( 'Would you like to continue?', 'convesioconvert' )
						);
					?>
				</div>
				<div class="convesioconvert-dialog-footer">
					<button class="button button-primary convesioconvert-destroy-data"><?php esc_html_e( 'Erase Data', 'convesioconvert' ); ?></button>
					<button class="button button-secondary convesioconvert-dialog-close"><?php esc_html_e( 'Cancel', 'convesioconvert' ); ?></button>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
