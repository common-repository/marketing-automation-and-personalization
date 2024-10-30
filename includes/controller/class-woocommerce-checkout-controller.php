<?php
/**
 * Handles WooCommerce checkout action for both guest and logged-in users
 */
// check repeated thank you after refresh.

namespace ConvesioConvert\Controller;

use ConvesioConvert\Event_Transport;
use ConvesioConvert\Modification_Handler;
use ConvesioConvert\Session_Manager;
use ConvesioConvert\Woocommerce\Commerce_Data_Layer;
use ConvesioConvert\Woocommerce\Order_Status_Change_Hooks;

/**
 * Handles WooCommerce checkout action. Records session information and sends a checkout event to backend.
 */
class Woocommerce_Checkout_Controller {
	/**
	 * The key for order metadata specifying whether the Checkout was handled or not to prevent the action being handled
	 * and network requests multiple times. It is necessary as `woocommerce_thankyou` action may run more than once: e.g
	 * twice on initial checkout because of our old code, and then again user is able to refresh the thankyou page.
	 *
	 * @link https://github.com/woocommerce/woocommerce/issues/7787
	 */
	const CHECKOUT_HANDLED_META = '_convesioconvert_checkout_handled';

	/**
	 * @var \WC_Order $order
	 */
	private $order;

	/**
	 * @var Session_Manager $session
	 */
	private $session;

	/**
	 * @var \WP_User|false $user
	 */
	private $user;

	/**
	 * Handle_Wooommerce_Checkout constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'safe_handle_checkout' ), 10 );
	}

	/**
	 * Wrapper around handle_checkout that tries to shield WP from its exceptions
	 */
	public function safe_handle_checkout() {
		if ( ! is_checkout() || empty( is_wc_endpoint_url( 'order-received' ) ) ) {
			return;
		}

		global $wp;

		$order_id = $wp->query_vars['order-received'];
		// Don't proceed when order ID not found.
		if ( empty( $order_id ) ) {
			return;
		}

		try {
			$this->handle_checkout( $order_id );
		} catch ( \Throwable $ex ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Record $ex using Sentry
		}
	}

	/**
	 * Checks if checkout is not already handled then handles it
	 *
	 * @param mixed $order_id Order ID from Woocommerce.
	 */
	public function handle_checkout( $order_id ) {
		$this->order   = new \WC_Order( $order_id );
		$this->session = new Session_Manager();

		$this->find_existing_user()
			->record_order_meta_data()
			->reset_session_id()
			->override_user_type()
			->invalidate_ecommerce_cache()
			->fixup_data_layer_commerce_details();

		if ( ! $this->is_checkout_handled() ) {
			Modification_Handler::handle_checkout( $this->order );
			$this->send_checkout_event()
				->send_order_placed_event()
				->mark_checkout_handled();
		}
	}

	/**
	 * Finds and stores the existing user of the person that did a checkout.
	 *
	 * In case of aliens, WooCommerce adds them to the current blog before checkout (see `add_user_to_blog` in Woo
	 * source code). Therefore we do not need to use {@see \ConvesioConvert\get_user_type()} to detect aliens here.
	 * Simply getting the user from the order is enough; the user can be assumed to be registered on current blog.
	 *
	 * @return $this
	 */
	private function find_existing_user() {
		$this->user = $this->order->get_user();

		return $this;
	}

	/**
	 * Record session and client information for the order
	 *
	 * @return $this
	 */
	public function record_order_meta_data() {
		$meta_prefix = \ConvesioConvert\meta_site_prefix();

		if ( $this->order->get_meta( "{$meta_prefix}_session_id" ) ) {
			return $this;
		}

		$this->order->add_meta_data( "{$meta_prefix}_session_id", $this->session->get_session_id(), true );
		$this->order->add_meta_data( "{$meta_prefix}_client_id", $this->session->get_client_id(), true );
		$this->order->save();

		return $this;
	}

	/**
	 * Resets the session ID after purchase. The session ID will not change in this request.
	 *
	 * @return $this
	 */
	private function reset_session_id() {
		// We just want to reset the exact session the order was made in; So we must not use `$session->get_session_id`
		// because that will reset the session each time the user visits the thankyou page.
		$meta_prefix = \ConvesioConvert\meta_site_prefix();
		$session_id  = $this->order->get_meta( "{$meta_prefix}_session_id" );

		$this->session->reset_session_id( $session_id, 'checkout' );

		return $this;
	}

	/**
	 * In case of guest checkout, overrides the user type. This override will be picked up by FE.
	 *
	 * @return $this
	 */
	private function override_user_type() {
		if ( $this->user ) {
			return $this;
		}

		$meta_prefix = \ConvesioConvert\meta_site_prefix();

		$this->session->override_user_props(
			array(
				'type'         => 'customer',
				'email'        => $this->order->get_billing_email(),
				'username'     => $this->order->get_billing_email(),
				'firstName'    => $this->order->get_billing_first_name(),
				'lastName'     => $this->order->get_billing_last_name(),
				'registeredAt' => $this->order->get_date_created()->format( 'Y-m-d H:i:s' ),
				// Preserve the siteUserId override If the thank-you page is refreshed.
				'siteUserId'   => (string) $this->order->get_meta( "{$meta_prefix}_guest_site_user_id" ),
			)
		);

		$last_order = Commerce_Data_Layer::get_order_details_for_data_layer( $this->order );

		$this->session->override_commerce_props(
			'woo',
			array(
				'cartItems'          => array(),
				'hasCart'            => false,
				'lastOrder'          => $last_order,
				'noOfOrders'         => 1,
				'noOfPurchasedItems' => (int) $last_order['itemCount'],
				'products'           => array_map( 'strval', array_keys( $last_order['items'] ) ),
			)
		);

		return $this;
	}

	/**
	 * Invalidates the e-commerce cache for the user or guest customer.
	 *
	 * @return $this
	 */
	private function invalidate_ecommerce_cache() {
		if ( $this->user ) {
			User_Order_Controller::invalidate_user_cache( $this->user->ID );
		}

		User_Order_Controller::invalidate_guest_cache( $this->order->get_billing_email() );

		return $this;
	}

	/**
	 * Fixes the user products list and other details that is passed to the Front-end for the new purchase.
	 *
	 * Since this logic is executed after the footer script output (i.e. window._convesioconvert), we need to fix the
	 * value of window._convesioconvert.commerce.*.products that is passed to the front-end to make sure any newly
	 * purchased products appear in that list; which is exactly what this function does.
	 */
	public function fixup_data_layer_commerce_details() {
		$override_purchased_product_ids = function () {
			$orders = new User_Order_Controller();
			return $orders->list_of_purchased_product_ids();
		};
		$override_total_purchased_items = function () {
			$orders = new User_Order_Controller();
			return $orders->get_total_purchased_items();
		};

		add_filter( 'convesioconvert_user_purchased_product_ids_woo', $override_purchased_product_ids );
		add_filter( 'convesioconvert_user_total_purchased_items_woo', $override_total_purchased_items );
	}

	/**
	 * Reports a checkout event to backend.
	 *
	 * @return $this
	 */
	private function send_checkout_event() {
		// Select only main order data.
		$order_data = array_intersect_key( $this->order->get_data(), array_flip( $this->order->get_data_keys() ) );

		// Plus the id.
		$order_data['id']                      = $this->order->get_id();
		$order_data['marketing_email_consent'] = $this->order->get_meta( 'convesioconvert_email_consent' );

		// Prepare and send the event.
		$is_guest_checkout = ! $this->user;

		$event_name = $is_guest_checkout ? 'wpGuestCheckout' : 'wpUserCheckout';
		$event_data = array(
			'order' => $order_data,
		);

		$transport = new Event_Transport( 'woo' );
		$response  = $transport->send_event( $event_name, $event_data );

		if ( $is_guest_checkout && ! empty( $response['siteUserId'] ) ) {
			$this->session->override_user_props(
				array( 'siteUserId' => $response['siteUserId'] ),
				false
			);

			// This meta is only available since plugin v2.8.3.
			// Save the BE siteUserId on the order. Please note in case of merging site users, this will stop being
			// consistent and should be invalidated in the future, or not be relied upon too much.
			$meta_prefix = \ConvesioConvert\meta_site_prefix();
			$this->order->add_meta_data( "{$meta_prefix}_guest_site_user_id", $response['siteUserId'], true );
			$this->order->save();
		}

		return $this;
	}

	/**
	 * Sends an order placed event using the order status change sub-system.
	 *
	 * @return $this
	 */
	private function send_order_placed_event() {
		( new Order_Status_Change_Hooks() )->order_changed( $this->order->get_id(), '', 'placed' );

		return $this;
	}

	/**
	 * Checks if the checkout is handled.
	 *
	 * @return bool
	 */
	private function is_checkout_handled() {
		return (bool) $this->order->get_meta( self::CHECKOUT_HANDLED_META );
	}

	/**
	 * Sets metadata on the order specifying the checkout was handled.
	 *
	 * @return $this
	 */
	private function mark_checkout_handled() {
		$this->order->add_meta_data( self::CHECKOUT_HANDLED_META, '1', true );
		$this->order->save();

		return $this;
	}
}
