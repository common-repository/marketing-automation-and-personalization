<div class="convesioconvert-notice convesioconvert-notice-onetime notice notice-<?php echo esc_attr( $notice['level'] ); ?>">
	<p>
		<?php
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		// All the variables are escaped before including
		printf(
			'%s%s%s',
			$notice['title'] ? '<strong>' . $notice['title'] . '</strong>' : '',
			$notice['title'] && $notice['text'] ? ' &#8211; ' : '',
			$notice['text'] ? $notice['text'] : ''
		);
		// phpcs:enable
		?>
	</p>
</div>
