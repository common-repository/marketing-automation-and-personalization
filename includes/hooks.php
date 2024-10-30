<?php

// Load text domain.
add_action( 'init', 'convesioconvert_i18n' );

function convesioconvert_i18n() {
	load_plugin_textdomain( 'convesioconvert', false, CONVESIOCONVERT_PATH . 'languages' );
}

// Fix hidden links in emails.
add_filter( 'wp_new_user_notification_email', 'convesioconvert_modify_new_user_notification_email', 99, 3 );

function convesioconvert_modify_new_user_notification_email( $email, $user, $blogname ) {
	$email['message'] = preg_replace( '/<(' . preg_quote( network_site_url(), '/' ) . '[^>]*)>/', '\1', $email['message'] );
	return $email;
}

// Add custom data attribute to if-then script.
add_filter( 'script_loader_tag', 'convesioconvert_script_custom_attrs', 10, 3 );

function convesioconvert_script_custom_attrs( $tag, $handle ) {
	if ( 'convesioconvert-if-then' === $handle || 'convesioconvert-automations' === $handle ) {
		$tag = str_replace( ' src=', ' data-minify="0" async src=', $tag ); //phpcs:ignore
	}
	return $tag;
}

// Exclude our scripts from Autoptimize.
add_filter( 'autoptimize_filter_js_exclude', 'convesioconvert_compatibility_autoptimize' );

function convesioconvert_compatibility_autoptimize( $excluded_js_files ) {
	$convesioconvert_files = 'convesioconvert-public.js, if-then.min.js, _convesioconvert, automations.js';
	return $excluded_js_files . ', ' . $convesioconvert_files;
}

add_filter( 'litespeed_optimize_js_excludes', 'convesioconvert_compatibility_litespeed_cache' );
/**
 * Exclude our scripts from LiteSpeed Cache.
 *
 * @link https://github.com/litespeedtech/lscache_wp/blob/ec2d7a26272a7e65f8ebac3084343323506e5b40/src/optimize.cls.php#L805-L882
 */
function convesioconvert_compatibility_litespeed_cache( $files ) {
	$files[] = 'convesioconvert-public.js';
	$files[] = 'if-then.min.js';
	$files[] = 'automations.js';
	return $files;
}

// Exclude our inline scripts from WP Rocket.
add_filter( 'rocket_excluded_inline_js_content', 'convesioconvert_compatibility_wp_rocket_inline_scripts' );

function convesioconvert_compatibility_wp_rocket_inline_scripts( array $excluded_files ) {
	$excluded_files[] = '_convesioconvert';
	return $excluded_files;
}

// Exclude our external scripts from WP Rocket.
add_filter( 'rocket_minify_excluded_external_js', 'convesioconvert_compatibility_wp_rocket' );

function convesioconvert_compatibility_wp_rocket( array $excluded_files ) {
	$excluded_files[] = CONVESIOCONVERT_SUFFIX;
	return $excluded_files;
}
