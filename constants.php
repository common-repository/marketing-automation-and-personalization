<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONVESIOCONVERT_VERSION', '3.2.1' );
define( 'CONVESIOCONVERT_SLUG', 'convesioconvert' );

define( 'CONVESIOCONVERT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONVESIOCONVERT_URL', plugin_dir_url( __FILE__ ) );

define( 'CONVESIOCONVERT_ADMIN_PATH', CONVESIOCONVERT_PATH . 'admin/' );
define( 'CONVESIOCONVERT_ADMIN_URL', CONVESIOCONVERT_URL . 'admin/' );

define( 'CONVESIOCONVERT_PUBLIC_ASSETS', CONVESIOCONVERT_URL . 'public/assets/dist/' );
define( 'CONVESIOCONVERT_ADMIN_ASSETS', CONVESIOCONVERT_ADMIN_URL . 'assets/dist/' );

if ( ! defined( 'CONVESIOCONVERT_SUFFIX' ) ) {
	define( 'CONVESIOCONVERT_SUFFIX', 'convert.convesio.com' );
}

if ( ! defined( 'CONVESIOCONVERT_API_URL' ) ) {
	define( 'CONVESIOCONVERT_API_URL', 'https://api.' . CONVESIOCONVERT_SUFFIX );
}

if ( ! defined( 'CONVESIOCONVERT_APP_URL' ) ) {
	define( 'CONVESIOCONVERT_APP_URL', 'https://' . CONVESIOCONVERT_SUFFIX );
}

if ( ! defined( 'CONVESIOCONVERT_RULE_CLIENT_URL' ) ) {
	define( 'CONVESIOCONVERT_RULE_CLIENT_URL', '//executor.' . CONVESIOCONVERT_SUFFIX . '/if-then.min.js' );
}

if ( ! defined( 'CONVESIOCONVERT_TOKEN_VERIFY_DEBUG' ) ) {
	define( 'CONVESIOCONVERT_TOKEN_VERIFY_DEBUG', 'no' );
}

if ( ! defined( 'CONVESIOCONVERT_APP_ENV' ) ) {
	define( 'CONVESIOCONVERT_APP_ENV', 'prod' );
}
