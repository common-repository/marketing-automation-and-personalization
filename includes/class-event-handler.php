<?php

namespace ConvesioConvert;

class Event_Handler {

	public function __construct() {
		add_action( 'user_register', array( $this, 'user_created' ), 10, 2 );
		add_action( 'wp_login', array( $this, 'user_logged_in' ), 10, 2 );
		add_action( 'wp_insert_comment', array( $this, 'comment_created' ), 10, 2 );
		add_action( 'edit_comment', array( $this, 'comment_updated' ), 10, 2 );
	}

	public function user_created( $user_id, $user_data ) {
		if ( ( $user_data['role'] ?? null ) !== 'administrator' ) {
			unset( $user_data['user_pass'] );
			transfer_an_event( 'wp_user_created', $user_id, $user_data );
		}
	}

	public function user_logged_in( string $user_login, $user ) {
		if ( ! isset( $user->ID ) || current_user_can( 'administrator' ) ) {
			return;
		}
		transfer_an_event( 'wp_user_login', $user->ID );
	}

	public function comment_created( int $id, $comment ) {
		if ( isset( $comment->user_id ) ) {
			transfer_an_event( 'wp_comment_created', $comment->user_id );
		}
	}

	public function comment_updated( int $id, array $data ) {
		if ( isset( $data['user_id'] ) ) {
			transfer_an_event( 'wp_comment_updated', $data['user_id'] );
		}
	}
}
