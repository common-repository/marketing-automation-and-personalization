<?php

namespace ConvesioConvert\EDD2;

use EDD_Payment;
use WP_Error;

/**
 * Provide Guest Customers to our sync system. Done once on first integration.
 *
 * - Request query params: {@see Guest_Customers_Sync::get_guest_payments()}.
 * - Note: Due to implementation details, `offset` must always be an integer coefficient of `limit`.
 *   Arbitrary offsets are not allowed.
 * - Response: {@see Guest_Customers_Sync::index()}
 *
 * EDD Specific: There are TWO METHODS to get Guest Customers list from EDD.
 *
 * 1. From wp_posts and wp_postmeta table:
 *    Returns EDD_Payment objects containing ->ID and ->email fields.
 *          array_map(
 *              fn($p) => $p->customer_id,
 *              edd_get_payments(['user' => '0', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 200, 'page' => 1, 'output' => 'payments'])
 *          )
 *
 * 2. From wp_edd_customers table:
 *    Returns table rows as stdClass objects containing ->payment_ids CSV and ->email and ->purchase_count fields.
 *          array_map(
 *              fn ($p) => $p->user_id,
 *              (new EDD_Customer_Query)->query(['number' => 200, 'offset' => 0, 'orderby' => 'id', 'order' => 'ASC', 'users_include' => [0]])
 *          )
 *
 * Comparison: Both data seem to be mostly in sync:
 * 1. Removing an EDD Payment from admin removes it from wp_edd_customers.payment_ids;
 * 2. Removing an EDD Customer sets the _edd_payment_customer_id postmeta to 0;
 * 3. Removing a WP user sets wp_edd_customers.user_id to 0; but doesn't set _edd_payment_user_id postmeta to 0.
 *    Therefore, when there are deleted users, the postmeta data can , because only guest users will have a
 *    _edd_payment_user_id = 0 postmeta.
 *
 * The EDD_Customer_Query is the preferred method for retrieving guest customers because:
 * 1. Fact #3 in the comparison above: that postmeta can differentiate deleted vs guest customers;
 * 2. The fact that output from EDD_Customers may contain customers with 0 purchase_count that have to be filtered out,
 *    as well as transform the CSV values to make customers for each order.
 * 3. In addition, the payments query also gives us the order dates, while EDD_Customer only has the order IDs.
 */
class Guest_Customers_Sync {

	const LIMIT = 100;

	private $has_more = false;

	public function index( $request ) {
		$orders = $this->get_guest_payments( $request );

		$guest_customers = $this->get_guest_customers_from_orders( $orders );

		return array(
			'guest_customers' => $guest_customers,
			'has_more'        => $this->has_more,
		);
	}

	/**
	 * @param $request
	 *
	 * @return EDD_Payment[]
	 */
	private function get_guest_payments( $request ) {
		$params = $request->get_params();
		$limit  = isset( $params['limit'] ) ? (int) $params['limit'] : self::LIMIT;
		$offset = isset( $params['offset'] ) ? (int) $params['offset'] : 0;

		$orders = edd_get_payments(
			array(
				'user'    => '0',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => $limit,
				'page'    => ( (int) ( $offset / $limit ) ) + 1,
				'output'  => 'payments',
			)
		);

		$this->has_more = count( $orders ) > 0;

		return $orders;
	}

	/**
	 * @param EDD_Payment[] $orders
	 *
	 * @return array[]
	 */
	private function get_guest_customers_from_orders( $orders ) {
		return array_map(
			/** @param EDD_Payment $order */
			function ( $order ) {
				return array(
					'email'      => $order->email,
					'first_name' => $order->first_name,
					'last_name'  => $order->last_name,
					'address'    => $this->get_address( $order ),
					'phone'      => null,
					'country'    => $order->address['country'] ?? null,
					'region'     => $order->address['state'] ?? null,
					'city'       => $order->address['city'] ?? null,
					'order_id'   => $order->ID,
					'order_date' => $order->date,
				);
			},
			$orders
		);
	}

	/**
	 * @param EDD_Payment $order
	 *
	 * @return string|null The address for EDD is usually empty, i.e ',,,,' and we'll return null in such cases.
	 */
	private function get_address( $order ) {
		$address = implode( ', ', $order->address );

		if ( '' === trim( str_replace( ',', '', $address ) ) ) {
			return null;
		} else {
			return $address;
		}
	}

}
