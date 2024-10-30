<?php

namespace ConvesioConvert\EDD;

/**
 * Checks for main class of Easy Digital Downloads plugin.
 *
 * @return bool True if main class of plugin exists.
 */
function is_edd_active() {
	return class_exists( 'Easy_Digital_Downloads' )
		&& version_compare( EDD_VERSION, '3.0', '>=' );
}
