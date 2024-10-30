<?php

namespace ConvesioConvert\Woocommerce;

use ConvesioConvert\GraphQL_Client;

use WC_Order;

use function ConvesioConvert\transfer_an_event;
use function ConvesioConvert\get_user_identity_token;

class Order_Status_Change_Hooks {

	private $convesioconvert_statuses = array(
		'placed'     => 'order_placed',
		'pending'    => 'order_pending',
		'on-hold'    => 'order_on_hold',
		'processing' => 'order_processing',
		'completed'  => 'order_completed',
		'cancelled'  => 'order_cancelled',
		'failed'     => 'order_failed',
		'refunded'   => 'order_refunded',
	);

	public function __construct() {
		if ( ! is_woocommerce_active() ) {
			return;
		}

		add_action( 'woocommerce_order_status_changed', array( $this, 'order_changed' ), 10, 3 );

		add_action( 'user_register', array( $this, 'customer_created' ), 10, 2 );
	}

	public function order_changed( $order_id, $status_from, $status_to ) {
		if ( ! in_array( $status_to, array_keys( $this->convesioconvert_statuses ), true ) ) {
			return;
		}

		$order = new WC_Order( $order_id );

		$user_id = $order->get_user_id();
		if ( $user_id ) {
			$site_user_input = array(
				'userId'        => $user_id,
				'identityToken' => get_user_identity_token( $user_id ),
			);
		} else {
			// Do not read $this->session->get_effective_user_property( 'siteUserId' ) as some order events are fired
			// much later in the admin panel. Do not rely on the order's '_guest_site_user_id' meta either, as backend
			// siteUserIds do change in some rare situations. The order email should be the reliable authorization tool.
			$user_email      = $order->get_billing_email();
			$site_user_input = array(
				'limitedEmailAuthorize' => $user_email,
				'identityToken'         => get_user_identity_token( $user_email ),
			);

			if ( 'order_placed' === $this->convesioconvert_statuses[ $status_to ] ) {
				$this->reset_guest_checkout_started_cart_id( $order );
			}
		}

		$variables_types = array(
			'siteId'      => 'ID!',
			'siteUser'    => 'SiteUserInputType',
			'orderStatus' => 'String!',
			'orderData'   => 'JsonType',
		);

		$items = array_map(
			function ( $item ) {
				return $item->get_data();
			},
			$order->get_items()
		);

		foreach ( $items as &$item ) {
			$image         = wp_get_attachment_image_src(
				get_post_thumbnail_id( $item['product_id'] ),
				'single-post-thumbnail'
			);
			$item['image'] = $image ? $image[0] : '';
		}

		$order_data = array(
			'order' => $order->get_data(),
			'items' => $items,
		);

		$data = array(
			'siteId'      => get_option( 'convesioconvert_site_id' ),
			'siteUser'    => $site_user_input,
			'orderStatus' => $this->convesioconvert_statuses[ $status_to ],
			'orderData'   => wp_json_encode( $order_data ),
		);

		GraphQL_Client::make()
			->make_mutation( 'orderStatusChangeTrigger', $variables_types, 'success', $data )
			->execute();
	}

	public function reset_guest_checkout_started_cart_id( $order ) {
		$variables_types = array(
			'siteId'   => 'ID!',
			'platform' => 'PlatformEnum!',
			'email'    => 'String',
			'orderId'  => 'String',
		);
		$email           = $order->get_billing_email();
		$data            = array(
			'siteId'   => get_option( 'convesioconvert_site_id' ),
			'email'    => $email,
			'platform' => 'woo',
			'orderId'  => (string) $order->get_id(),
		);

		GraphQL_Client::make()
			->make_mutation( 'siteUserCheckoutStartedTriggered', $variables_types, null, $data )
			->execute();
	}

	public function customer_created( $user_id, $user_data ) {
		if ( ( $user_data['role'] ?? null ) !== 'customer' ) {
			return;
		}
		unset( $user_data['user_pass'] );
		transfer_an_event( 'wp_customer_created', $user_id, $user_data );
	}
}
