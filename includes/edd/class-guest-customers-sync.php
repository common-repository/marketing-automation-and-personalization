<?php

namespace ConvesioConvert\EDD;

use EDD\Orders\Order;

/**
 * Provide Guest Customers to our sync system. Done once on first integration.
 *
 * - Request query params: {@see Guest_Customers_Sync::get_guest_orders()}.
 * - Response: {@see Guest_Customers_Sync::index()}
 *
 * EDD Specific: In EDD 2, there were two methods to get the guest customers list and there were some behavior quirks
 * with removing a WP user; blame this commit for the details.
 *
 * In EDD 3, the preferred method is using its standard Order query. It may be worth it to look into EDD_Customer-based
 * sync though, as guest customer sync with repetitive emails among multiple orders can be optimized in our backend.
 * We'll have to watch out EDD_Customers with 0 purchase_count that have to be filtered out though.
 */
class Guest_Customers_Sync {

	const LIMIT = 100;

	private $has_more = false;

	public function index( $request ) {
		$orders = $this->get_guest_orders( $request );

		$guest_customers = $this->get_guest_customers_from_orders( $orders );

		return array(
			'guest_customers' => $guest_customers,
			'has_more'        => $this->has_more,
		);
	}

	/**
	 * @param $request
	 *
	 * @return Order[]
	 */
	private function get_guest_orders( $request ) {
		$params = $request->get_params();
		$limit  = isset( $params['limit'] ) ? (int) $params['limit'] : self::LIMIT;
		$offset = isset( $params['offset'] ) ? (int) $params['offset'] : 0;

		$orders = edd_get_orders(
			array(
				'user'    => '0',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => $limit,
				'offset'  => $offset,
			)
		);

		$this->has_more = count( $orders ) > 0;

		return $orders;
	}

	/**
	 * @param Order[] $orders
	 *
	 * @return array[]
	 */
	private function get_guest_customers_from_orders( $orders ) {
		return array_map(
			function ( $order ) {
				list( $first_name, $last_name ) = $this->get_customer_name( $order );

				return array(
					'email'      => $order->email,
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'address'    => $this->get_address( $order ),
					'phone'      => null,
					'country'    => $order->address->country,
					'region'     => $order->address->region,
					'city'       => $order->address->city,
					'order_id'   => $order->id,
					'order_date' => $order->date_created,
				);
			},
			$orders
		);
	}

	/**
	 * @param Order $order
	 *
	 * @return array
	 */
	private function get_customer_name( $order ) {
		$customer = edd_get_customer( $order->customer_id );

		if ( ! $customer ) {
			return array( '', '' );
		}

		$full_name    = trim( $customer->name ?: '' );
		$space_offset = strpos( $full_name, ' ' );

		if ( $space_offset ) {
			return array(
				substr( $full_name, 0, $space_offset ),
				substr( $full_name, $space_offset + 1 ),
			);
		} else {
			return array( $full_name, '' );
		}
	}

	/**
	 * @param Order $order
	 *
	 * @return string|null The address for EDD is usually empty, i.e ',,,,' and we'll return null in such cases.
	 */
	private function get_address( $order ) {

		$address_array = (array) $order->address;

		unset( $address_array['id'] );
		unset( $address_array['order_id'] );
		unset( $address_array['first_name'] );
		unset( $address_array['last_name'] );

		$address = implode( ', ', $address_array );

		if ( '' === trim( str_replace( ',', '', $address ) ) ) {
			return null;
		} else {
			return $address;
		}
	}

}
