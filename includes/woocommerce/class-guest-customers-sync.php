<?php

namespace ConvesioConvert\Woocommerce;

use WC_Order;
use WP_Error;

/**
 * Provide Guest Customers to our sync system. Done once on first integration.
 *
 * - Request query params: {@see Guest_Customers_Sync::get_non_refunded_guest_orders()}.
 * - Response: {@see Guest_Customers_Sync::index()}
 */
class Guest_Customers_Sync {

	const LIMIT = 100;

	private $has_more = false;

	public function index( $request ) {
		$orders = $this->get_non_refunded_guest_orders( $request );

		$guest_customers = $this->get_guest_customers_from_orders( $orders );

		return array(
			'guest_customers' => $guest_customers,
			'has_more'        => $this->has_more,
		);
	}

	/**
	 * @param $request
	 *
	 * @return WC_Order[]
	 */
	private function get_non_refunded_guest_orders( $request ) {
		$params = $request->get_params();
		$limit  = isset( $params['limit'] ) ? (int) $params['limit'] : self::LIMIT;
		$offset = isset( $params['offset'] ) ? (int) $params['offset'] : 0;

		$orders = wc_get_orders(
			array(
				'customer_id' => '0',
				'orderby'     => 'id',
				'order'       => 'ASC',
				'limit'       => $limit,
				'offset'      => $offset,
			)
		);

		// Set this before filtering the items, because even though it's
		// possible that all the items be filtered out, and we return zero
		// items to the caller, more items may still available in the DB.
		// Only set to false when the query really returned no items.
		$this->has_more = count( $orders ) > 0;

		return array_filter(
			$orders,
			function ( $order ) {
				// In case of refunds, a \WC_Order_Refund may be returned from
				// the query which does not have e.g ->get_billing_email().
				return $order instanceof WC_Order;
			}
		);
	}

	/**
	 * @param WC_Order[] $orders
	 *
	 * @return array[]
	 */
	private function get_guest_customers_from_orders( $orders ) {
		return array_map(
			/** @param WC_Order $order */
			function ( $order ) {
				return array(
					'email'      => $order->get_billing_email(),
					'first_name' => $order->get_billing_first_name(),
					'last_name'  => $order->get_billing_last_name(),
					'address'    => $this->get_address( $order ),
					'phone'      => $order->get_billing_phone(),
					'country'    => $order->get_billing_country(),
					'region'     => $order->get_billing_state(),
					'city'       => $order->get_billing_city(),
					'order_id'   => $order->get_id(),
					'order_date' => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
				);
			},
			$orders
		);
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return string|null
	 */
	private function get_address( $order ) {
		return str_replace( array( '<br/>', '<br>' ), ', ', $order->get_formatted_billing_address() ) ?: null;
	}

}
