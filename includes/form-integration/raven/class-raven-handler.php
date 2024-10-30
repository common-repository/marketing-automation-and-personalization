<?php

namespace ConvesioConvert\Form_Integration\Raven;

use JupiterX_Core\Raven\Modules\Forms\Module;
use Elementor\Utils as ElementorUtils;

class Raven_Handler {

	public function __construct() {
		add_action(
			'jupiterx_core_raven_init',
			function() {
				Module::register_custom_action( new Raven_Form_Action() );
			}
		);
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
	}

	public function enqueue_editor_scripts() {
		$suffix = ElementorUtils::is_script_debug() ? '' : '.min';

		wp_enqueue_script(
			'convesioconvert-raven-editor',
			CONVESIOCONVERT_ADMIN_ASSETS . 'js/elementor-editor' . $suffix . '.js',
			array( 'jquery' ),
			CONVESIOCONVERT_VERSION,
			true
		);
	}
}
