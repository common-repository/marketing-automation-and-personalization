<?php

namespace ConvesioConvert\Form_Integration\Elementor;

use Elementor\Utils as ElementorUtils;

class Form {

	public function __construct() {
		add_action( 'elementor_pro/init', array( $this, 'register_convesioconvert_action' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
	}

	public function register_convesioconvert_action() {
		$convesioconvert_action = new Elementor_Form_Action();

		\ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' )->add_form_action( $convesioconvert_action->get_name(), $convesioconvert_action );
	}

	/**
	 * Load needed editor scripts.
	 */
	public function enqueue_editor_scripts() {
		$suffix = ElementorUtils::is_script_debug() ? '' : '.min';

		wp_enqueue_script(
			'convesioconvert-elementor-editor',
			CONVESIOCONVERT_ADMIN_ASSETS . 'js/elementor-editor' . $suffix . '.js',
			array( 'jquery' ),
			CONVESIOCONVERT_VERSION,
			true
		);
	}
}
