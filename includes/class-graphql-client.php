<?php

namespace ConvesioConvert;

/**
 * Simple client for ConvesioConvert Backend API. Does not offer any caching.
 */
class GraphQL_Client {

	private $query;

	private $operation;

	private $variables;

	/**
	 * GraphQL_Client constructor.
	 */
	public function __construct() {
		$this->reset();
	}

	/**
	 * Fluent syntax helper function.
	 *
	 * @return GraphQL_Client
	 */
	public static function make() {
		return new self();
	}

	/**
	 * Resets the state for a new query/mutation
	 *
	 * @return $this
	 */
	public function reset() {
		$this->query     = '';
		$this->operation = '';
		$this->variables = array();
		return $this;
	}

	/**
	 * Makes a GraphQL query/mutation string.
	 *
	 * @param string $query_name
	 * @param array $variables Input variables as a ['name' => 'type'] array; must not contain siteId
	 * @param string $outputs Outputs, must not contain __typename
	 * @param string $query_type Either 'query' or 'mutation'
	 *
	 * @return $this
	 */
	public function make_query( $query_name, $variables, $outputs, $query_type = 'query' ) {
		$all_variables = array_merge( $variables, array( 'siteId' => 'ID!' ) );
		$query_outputs = $outputs ? $outputs . ' __typename' : '';

		$variable_inputs = '';
		$query_inputs    = '';

		$separator = '';
		foreach ( $all_variables as $name => $type ) {
			$variable_inputs .= "{$separator}\${$name}: {$type}";
			$query_inputs    .= "{$separator}{$name}: \${$name}";
			$separator        = ', ';
		}

		$this->query  = "$query_type $query_name ($variable_inputs) { $query_name($query_inputs) ";
		$this->query .= $outputs ? "{ $query_outputs } }" : ' }';

		$this->operation = $query_name;

		return $this;
	}

	/**
	 * Helper function, see make_query.
	 *
	 * @param string $query_name
	 * @param array $variables Input variables; must not contain siteId
	 * @param string $outputs Outputs, must not contain __typename
	 *
	 * @return $this
	 */
	public function make_mutation( $query_name, $variables, $outputs, $query_variables = array() ) {
		$this->variables = $query_variables;
		return $this->make_query( $query_name, $variables, $outputs, 'mutation' );
	}

	/**
	 * Calls a sitePause mutation
	 *
	 * @return $this
	 */
	public function site_pause() {
		return $this->make_mutation( 'sitePause', array( 'pause' => 'Boolean!' ), 'success' );
	}

	/**
	 * Calls siteHealthCheck mutation
	 *
	 * @return $this
	 */
	public function site_health_check() {
		return $this->make_mutation( 'siteHealthCheck', array( 'forceNow' => 'Boolean' ), 'success' );
	}

	/**
	 * Calls siteHealthStatus query
	 *
	 * @return $this
	 */
	public function site_health_status() {
		return $this->make_query( 'siteHealthStatus', array(), 'errorLevel, message, messageKey, isInProgress' );
	}

	public function site_coupon_notify() {
		return $this->make_mutation(
			'siteCouponNotify',
			array(
				'siteId'     => 'ID!',
				'platform'   => 'PlatformEnum!',
				'couponId'   => 'ID!',
				'entityType' => 'String!',
				'entityId'   => 'ID!',
			),
			'success'
		);
	}

	/**
	 * Sets variables for the query.
	 *
	 * Can be called as `set( $name, $value )` to set a single variable or `set([$name => $value, ...]` to set multiple.
	 * Calling with any other signature will be ignored.
	 *
	 * @param string|array $variables Either a variable name, or a ['name' => 'value'] array. Should not contain siteId
	 *
	 * @param mixed $value If the first arg was a variable name, the value for that var. Ignored otherwise.
	 *
	 * @return $this
	 */
	public function set( $variables, $value = null ) {
		if ( is_array( $variables ) ) {
			$this->variables = array_merge( $this->variables, $variables );
		} elseif ( is_string( $variables ) ) {
			$variable_name   = $variables;
			$this->variables = array_merge( $this->variables, array( $variable_name => $value ) );
		}
		return $this;
	}

	/**
	 * Execute the query/mutation and return the result.
	 *
	 * After querying. class is also reset and can be used for another query.
	 *
	 * @return mixed|\WP_Error Returns the response, or \WP_Error in case of errors.
	 */
	public function execute() {
		$api_url    = CONVESIOCONVERT_API_URL . '/graphql';
		$site_id    = get_option( 'convesioconvert_site_id' );
		$site_token = get_option( 'convesioconvert_site_token' );

		$this->set( 'siteId', $site_id );

		$body = array(
			'operationName' => $this->operation,
			'variables'     => $this->variables,
			'query'         => $this->query,
		);

		$headers = array(
			'Authorization' => 'Bearer ' . $site_token,
			'Content-Type'  => 'application/json; charset=utf-8',
		);

		$response = null;

		try {
			$response = wp_remote_post(
				$api_url,
				array(
					'timeout' => 15,
					'headers' => $headers,
					'body'    => wp_json_encode( $body ),
				)
			);
		} catch ( \Throwable $ex ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Record $ex using Sentry
		}

		return $this->process_response( $response );
	}

	/**
	 * Processes a WordPress wp_remote_post GraphQL call response.
	 *
	 * For successful responses, extracts the resulting data.
	 * For failed responses, turns GQL errors into \WP_Errors,
	 *
	 * @param $response
	 *
	 * @return mixed|\WP_Error
	 */
	private function process_response( $response ) {
		if ( ! $response ) {
			return new \WP_Error( 'request_error', 'Exception while trying to reach ConvesioConvert server' );
		}

		if ( $response instanceof \WP_Error ) {
			return $response;
		}

		// GraphQL response must be a non-empty object containing 'data' and/or 'errors' fields.
		$gql = json_decode( $response['body'], true );

		if ( isset( $gql['errors'][0]['message'] ) ) {
			return new \WP_Error( 'api_error', $gql['errors'][0]['message'], $gql['errors'] );
		}

		// $gql['data'][ $this->operation ] i.e the query result may exist but be null, so check using array_key_exists.
		// Use isset as a performance hack before the array_key_exists calls.
		if (
			isset( $gql['data'][ $this->operation ] )
			|| (
				is_array( $gql ) &&
				\array_key_exists( 'data', $gql ) &&
				\array_key_exists( $this->operation, $gql['data'] )
			)
		) {
			return $gql['data'][ $this->operation ];
		}

		// Show a generic HTTP message when no clue from the GQL response
		$message = $response['response']['code'] . ' ' . $response['response']['message'];
		return new \WP_Error( 'api_http_error', $message, $gql );
	}
}
