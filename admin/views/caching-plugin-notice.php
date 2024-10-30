<div class="convesioconvert-notice convesioconvert-notice-caching-plugin notice notice-warning is-dismissible">
	<p>
		<?php
		printf(
			/* translators: %s Plugin name */
			esc_html__( '%1$s – A caching plugin that is not fully compatible with ConvesioConvert was detected on your site. %2$s.', 'convesioconvert' ),
			'<strong>ConvesioConvert</strong>',
			'<a href="https://convesio.com/knowledgebase/article/configuring-caching-plugins/" target="_blank">' . esc_html__( 'Learn more', 'convesioconvert' ) . '</a>'
		);
		?>
	</p>
</div>
