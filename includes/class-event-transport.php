<?php

namespace ConvesioConvert;

/**
 * Contains functions for reporting events to collector in the backend.
 */
class Event_Transport {
	/**
	 * @var string
	 */
	private $platform_key;

	/**
	 * The site id
	 *
	 * @var mixed $site_id
	 */
	public $site_id;

	/**
	 * The session manager.
	 *
	 * @var Session_Manager $session
	 */
	private $session;

	/**
	 * The user type; users from other blogs will be considered guest. Plus it picks up user type overrides.
	 *
	 * @var string $user_type
	 */
	public $user_type;

	/**
	 * The real user type; users from other blogs will be considered guest. It does not pick up user type overrides.
	 *
	 * @var string $user_type_real
	 */
	public $user_type_real;

	/**
	 * The global siteUserId; only available via overrideUser.
	 *
	 * @var mixed $site_user_id
	 */
	public $site_user_id;

	/**
	 * The WordPress user id; users from other blogs will be null.
	 *
	 * @var mixed $user_id
	 */
	public $user_id;

	/**
	 * Event_Transport constructor.
	 *
	 * @param string $platform_key
	 */
	public function __construct( $platform_key ) {
		$this->platform_key = $platform_key;

		$this->site_id = get_option( 'convesioconvert_site_id' );
		$this->session = new Session_Manager();

		// Use this function to exclude Aliens from current blog's users.
		$this->user_type      = get_user_type();
		$this->user_type_real = $this->user_type;

		$user_id       = get_current_user_id();
		$this->user_id = ( $user_id && 'guest' !== $this->user_type ) ? $user_id : null;

		$effective_site_user_id = $this->session->get_effective_user_property( 'siteUserId' );
		$effective_user_id      = $this->session->get_effective_user_property( 'userId' );
		$effective_user_type    = $this->session->get_effective_user_property( 'type' );

		if (
			( $effective_site_user_id || $effective_user_id ) &&
			$effective_user_type &&
			'guest' === $this->user_type &&
			'guest' !== $effective_user_type
		) {
			$this->site_user_id = $effective_site_user_id;
			$this->user_id      = $effective_user_id;
			$this->user_type    = $effective_user_type;
		}
	}

	/**
	 * Sends an event.
	 *
	 * @param string $event_name The event name, negotiated with the backend.
	 * @param array  $event_data Additional event data.
	 *
	 * @return array|null
	 */
	public function send_event( $event_name, $event_data = array() ) {
		if ( ! $this->site_id ) {
			return null;
		}

		$url = CONVESIOCONVERT_API_URL . '/v1/outstand';

		$payload = array(
			'event'    => array(
				'id'      => wp_generate_uuid4(),
				'type'    => $event_name,
				'version' => 1,
			),
			'platform' => array(
				'platformKey'   => $this->platform_key,
				'pluginVersion' => CONVESIOCONVERT_VERSION,
			),
			'session'  => array(
				'clientId'  => $this->session->get_client_id(),
				'sessionId' => $this->session->get_session_id(),
			),
			'site'     => array(
				'id' => $this->site_id,
			),
			'user'     => array(
				'siteUserId'    => $this->site_user_id,
				'userId'        => $this->user_id,
				'effectiveType' => $this->user_type,
				'realType'      => $this->user_type_real,
			),
			'wpData'   => $event_data,
		);

		$raw_response = wp_remote_post(
			$url,
			array(
				'method'      => 'POST',
				'timeout'     => 15,
				'redirection' => 5,
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'        => wp_json_encode( $payload ),
				'cookies'     => array(),
			)
		);

		return json_decode( wp_remote_retrieve_body( $raw_response ), true );
	}
}
