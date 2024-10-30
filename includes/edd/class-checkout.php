<?php

namespace ConvesioConvert\EDD;

use EDD\Orders\Order;
use EDD_Customer;
use ConvesioConvert\Event_Transport;
use ConvesioConvert\Modification_Handler;
use ConvesioConvert\Session_Manager;
use WP_User;
use function ConvesioConvert\get_user_type;
use function ConvesioConvert\meta_site_prefix;

class Checkout {

	const CHECKOUT_HANDLED_META = '_convesioconvert_checkout_handled';

	/** @var Session_Manager */
	private $session;

	/** @var WP_User|false */
	protected $user = false;

	/** @var Order */
	protected $order;

	/** @var EDD_Customer */
	protected $customer;


	public function __construct() {
		// Was not possible using `edd_complete_purchase` or `edd_payment_receipt_before` hooks.
		// Refer to the commit docs for the reason.
		add_action( 'template_redirect', array( $this, 'safe_handle_checkout' ) );
	}

	public function safe_handle_checkout() {
		if ( ! edd_is_success_page() ) {
			return;
		}

		$session  = edd_get_purchase_session();
		$order_id = 0;

		if ( $session ) {
			$payment_key = $session['purchase_key'];
			$order_id    = edd_get_purchase_id_by_key( $payment_key );
		}

		if ( ! $order_id ) {
			return;
		}

		$this->order    = $this->get_order_from_order_id( $order_id );
		$this->customer = new EDD_Customer( $this->order->customer_id );

		try {
			$this->handle_checkout();
		} catch ( \Throwable $ex ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Record $ex using Sentry
		}
	}

	/** @return Order|false */
	protected function get_order_from_order_id( $order_id ) {
		return edd_get_order( $order_id );
	}

	public function handle_checkout() {
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
				->mark_checkout_handled();
		}
	}

	/**
	 * Finds and stores the existing user of the person that did a checkout.
	 *
	 * We do the ConvesioConvert\get_user_type() check for two reasons:
	 * 1. In case of logged-out registered user checkout, EDD $order->user_id still contains the registered user_id!
	 *    But we'd like a Guest Checkout, like WooCommerce. So if WP user is not logged in, record a guest checkout.
	 * 2. In case of aliens, it's not researched if EDD will add them to the current blog. ConvesioConvert\get_user_type() has
	 *    code specific for excluding aliens, which is what we want to report a guest checkout.
	 *
	 * @return $this
	 */
	private function find_existing_user() {
		if ( 'guest' === get_user_type() ) {
			$this->user = false;
		} else {
			$this->user = get_user_by( 'id', $this->order->user_id );
		}

		return $this;
	}

	/**
	 * Record session and client information for the order
	 *
	 * @return $this
	 */
	public function record_order_meta_data() {
		$meta_prefix = meta_site_prefix();

		if ( $this->get_order_meta( "{$meta_prefix}_session_id" ) ) {
			return $this;
		}

		$this->add_unique_order_meta( "{$meta_prefix}_session_id", $this->session->get_session_id() );
		$this->add_unique_order_meta( "{$meta_prefix}_client_id", $this->session->get_client_id() );

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
		$meta_prefix = meta_site_prefix();
		$session_id  = $this->get_order_meta( "{$meta_prefix}_session_id" );

		$this->session->reset_session_id( $session_id, 'edd_checkout' );

		return $this;
	}

	/**
	 * Determine if this is a Guest Checkout.
	 *
	 * Use this function because our definition of 'guest' is different from a WordPress logged in user WRT users that
	 * are a member of another blog on multi-site, and an existing member that logs out and buys things with the same
	 * email.
	 *
	 * @return bool
	 */
	private function is_guest_checkout() {
		return ( false === $this->user );
	}

	/**
	 * Overrides the user type. This override will be picked up by FE.
	 *
	 * @return $this
	 */
	private function override_user_type() {
		// In this specific function do not use is_guest_checkout due to 593/594: When people* are logged out we'd like
		// to temporarily upgrade their current session anyway. (*People above includes own leads/customers and aliens.)
		if ( 'guest' !== get_user_type() ) {
			return $this;
		}

		$meta_prefix = meta_site_prefix();

		$this->session->override_user_props(
			array(
				'type'         => 'customer',
				'email'        => $this->customer->email,
				'username'     => $this->customer->email,
				'firstName'    => $this->customer->name,
				'lastName'     => $this->customer->name,
				'registeredAt' => $this->customer->date_created,
				// Preserve the siteUserId override If the thank-you page is refreshed.
				'siteUserId'   => (string) $this->get_order_meta( "{$meta_prefix}_guest_site_user_id" ),
			)
		);

		$last_order = $this->get_order_details_for_data_layer();

		$this->session->override_commerce_props(
			'edd',
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

	protected function get_order_details_for_data_layer() {
		return Commerce_Data_Layer::get_order_details_for_data_layer( $this->order );
	}

	/**
	 * Invalidates the e-commerce cache for the user or guest customer.
	 *
	 * @return $this
	 */
	private function invalidate_ecommerce_cache() {
		if ( $this->user ) {
			User_Orders::invalidate_user_cache( $this->user->ID );
		}

		// We consider some logged-in user checkouts as guest checkouts; nevertheless, they have a user_id on EDD.
		// See find_existing_user() for some explanation.
		/** @noinspection PhpCastIsUnnecessaryInspection EDD docs mistakenly specify int type for an actual string. */
		$order_user_id_reported_by_edd = (int) $this->order->user_id;
		if ( $order_user_id_reported_by_edd ) {
			User_Orders::invalidate_user_cache( $order_user_id_reported_by_edd );
		}

		User_Orders::invalidate_guest_cache( $this->customer->email );

		return $this;
	}

	public function fixup_data_layer_commerce_details() {
		$orders = new User_Orders();
		add_filter( 'convesioconvert_user_purchased_product_ids_edd', array( $orders, 'list_of_purchased_product_ids' ) );
		add_filter( 'convesioconvert_user_total_purchased_items_edd', array( $orders, 'get_total_purchased_items' ) );
	}

	/**
	 * Reports a checkout event to backend.
	 *
	 * @return $this
	 */
	private function send_checkout_event() {
		// Prepare and send the event.
		$is_guest_checkout = $this->is_guest_checkout();

		$event_name = $is_guest_checkout ? 'wpGuestCheckout' : 'wpUserCheckout';
		$event_data = array(
			'order' => $this->order_as_array(),
		);

		// If user is not logged in but her account already exists, it means she is now a customer and WooCommerce will
		// -or- already made her a member of the current blog in multi-site.  However Event_Transport still thinks of
		// her as a Guest, so we force the user type.
		$event_user_id   = $is_guest_checkout ? null : $this->user->ID;
		$event_user_type = $is_guest_checkout ? null : 'customer';

		$transport = new Event_Transport( 'edd' );
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
			$this->add_unique_order_meta( "{$meta_prefix}_guest_site_user_id", $response['siteUserId'] );
		}

		return $this;
	}

	/** @return object */
	protected function order_as_array() {
		$order_data                          = (object) $this->order->to_array();
		$order_data->edd3                    = true;
		$order_data->address                 = $this->order->address;
		$order_data->customer                = $this->customer;
		$order_data->marketing_email_consent = $this->get_order_meta( '_convesioconvert_email_consent' );

		return $order_data;
	}

	/**
	 * Checks if the checkout is handled.
	 *
	 * @return bool
	 */
	private function is_checkout_handled() {
		return (bool) $this->get_order_meta( self::CHECKOUT_HANDLED_META );
	}

	/**
	 * Sets metadata on the order specifying the checkout was handled.
	 *
	 * @return $this
	 */
	private function mark_checkout_handled() {
		$this->add_unique_order_meta( self::CHECKOUT_HANDLED_META, '1' );

		return $this;
	}

	protected function get_order_meta( $meta_key ) {
		return edd_get_order_meta( $this->order->id, $meta_key, true );
	}

	/**
	 * Important: Using add_meta INSTEAD OF update_meta ensure a previous session ID and client_id are NOT replaced
	 * by a new value when visiting a 'thank you' page.
	 */
	protected function add_unique_order_meta( $meta_key, $meta_value ) {
		return edd_add_order_meta( $this->order->id, $meta_key, $meta_value, true );
	}
}
