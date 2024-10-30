<?php


namespace ConvesioConvert\Admin;

use ConvesioConvert;
use function ConvesioConvert\verify_post_nonce;

class Smart_Rating {
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_show_smart_rating' ) );
		add_action( 'wp_ajax_convesioconvert_dismiss_smart_rating', array( $this, 'dismiss_smart_rating' ) );
	}

	/**
	 * Displays Smart Rating notice.
	 */
	public function maybe_show_smart_rating() {

		// This notice is shown on WP main dashboard page too, don't show to inappropriate users.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Integration::is_integrated() ) {
			return;
		}

		if ( get_option( 'convesioconvert_smart_rating_dismissed', false ) ) {
			return;
		}

		$last_fetch = (int) get_option( 'convesioconvert_smart_rating_last_fetch', false );

		if ( ! $last_fetch || ( time() - $last_fetch ) >= ( 3600 * 24 ) ) {
			$necessary_info = $this->get_smart_rating_necessary_info();
			update_option( 'convesioconvert_smart_rating_necessary_info', $necessary_info );
			update_option( 'convesioconvert_smart_rating_last_fetch', time() );
		} else {
			$necessary_info = get_option( 'convesioconvert_smart_rating_necessary_info', false );
		}

		if ( ! $necessary_info ) {
			return;
		}

		if ( ( time() - strtotime( $necessary_info['ownerVisitedAt'] ) ) >= 3600 * 24 * 30 ) {//30 days
			return;
		}

		if ( $necessary_info['numberOfRules'] < 3 ) {
			return;
		}

		/** @define "CONVESIOCONVERT_ADMIN_PATH" "" */
		include_once CONVESIOCONVERT_ADMIN_PATH . 'views/smart-rating.php';
	}


	public function get_smart_rating_necessary_info() {
		$rating_info = ConvesioConvert\GraphQL_Client::make()
			->make_query( 'siteRatingInfo', array(), 'ownerVisitedAt, numberOfRules' )
			->execute();

		if ( empty( $rating_info ) || is_wp_error( $rating_info ) ) {
			return false;
		}
		return $rating_info;
	}

	/**
	 * Dismisses the integration success notice for the current user only.
	 */
	public function dismiss_smart_rating() {
		if ( ! verify_post_nonce( 'convesioconvert_feedback_notification_bar_nonce' ) ) {
			wp_send_json_error();
		}

		if ( false === get_option( 'convesioconvert_smart_rating_dismissed' ) && false === update_option( 'convesioconvert_smart_rating_dismissed', false ) ) {
			add_option( 'convesioconvert_smart_rating_dismissed', true );
		}

		wp_send_json_success();
	}

	public static function remove_smart_rating_data() {
		delete_option( 'convesioconvert_smart_rating_dismissed' );
		delete_option( 'convesioconvert_smart_rating_last_fetch' );
		delete_option( 'convesioconvert_smart_rating_necessary_info' );
	}

}
