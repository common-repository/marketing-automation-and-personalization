<?php

namespace ConvesioConvert\Form_Integration;

class Init {

	public function __construct() {

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			new Elementor\Form();
		}

		// Raven itself loads based on plugins_loaded action,
		// so here it may not be loaded properly yet and we should call it with priority of 20 on "plugins_loaded".
		add_action(
			'plugins_loaded',
			function () {
				// First check if Raven support third party actions, and then add ConvesioConvert to the list. (older versions of Raven doesn't).
				if ( class_exists( 'JupiterX_Core\Raven\Modules\Forms\Module' ) ) {
					if ( method_exists( 'JupiterX_Core\Raven\Modules\Forms\Module', 'register_custom_action' ) ) {
						new Raven\Raven_Handler();
					}
				}
			},
			20
		);

		if ( defined( 'WPCF7_PLUGIN' ) ) {
			new CF7\Form();
		}

		if ( class_exists( 'GFForms' ) ) {
			new Gravityforms\Form();
		}

		if ( class_exists( 'Ninja_Forms' ) ) {
			new Ninja_Forms\Form();
		}

		// WPForms has a pro version, we check for a common function.
		if ( function_exists( 'wpforms' ) ) {
			new WPForms\Form();
		}

		// Checks are included in the class.
		Divi\Loader::instantiate();

	}
}
