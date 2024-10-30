<?php

namespace ConvesioConvert\Controller;

use function ConvesioConvert\starts_with;

class User_Controller {

	public function index( $request ) {
		$params = $request->get_params();

		if ( isset( $params['type'] ) ) {

			switch ( $params['type'] ) {
				case 'new':
					return $this->get_new_users( $params );
				case 'modified':
					return $this->get_modified_users( $params );
				default:
					break;
			}
		}

		if ( isset( $params['ids'] ) ) {
			return $this->get_users_by_id( $params['ids'] );
		}
	}

	public function get_new_users( $args ) {

		$last_id = isset( $args['last_id'] ) ? $args['last_id'] : 0;

		$users = $this->get_users( $last_id );
		$users = $this->attach_metadata( $users );

		return $users;
	}

	public function get_modified_users( $args ) {

		global $wpdb;

		$first_synced_id = isset( $args['first_synced_id'] ) ? $args['first_synced_id'] : 0;
		$last_id         = isset( $args['last_id'] ) ? $args['last_id'] : 0;
		$limit           = 1000;
		// Multi-site queries work on single-site too, but we try to run lighter queries on single-site.
		if ( is_multisite() ) {
			$blog_prefix = $wpdb->get_blog_prefix( get_current_blog_id() );

			$query =
				"SELECT m1.user_id AS id, m1.meta_value AS ts
				FROM {$wpdb->usermeta} m1, {$wpdb->usermeta} m2
				WHERE m1.user_id = m2.user_id
				AND m1.meta_key = '_convesioconvert_user_last_modification'
				AND m2.meta_key = '{$blog_prefix}capabilities'
				AND m1.user_id BETWEEN %d AND %d
				ORDER BY id
				LIMIT %d";
		} else {
			$query =
				"SELECT user_id AS id, meta_value AS ts
				FROM $wpdb->usermeta
				WHERE meta_key = '_convesioconvert_user_last_modification' AND user_id BETWEEN %d AND %d
				ORDER BY id
				LIMIT %d";
		}

		$sql   = $wpdb->prepare( $query, $last_id, $first_synced_id, $limit ); // phpcs:ignore
		$users = $wpdb->get_results( $sql ); // phpcs:ignore

		return $users;
	}

	public function get_users_by_id( $ids ) {

		$ids      = explode( ',', $ids );
		$user_ids = array_slice( $ids, 0, 100, true );

		$fields = array(
			'id' => 'id',
			'display_name',
			'user_email',
			'user_registered',
			'user_status',
			'user_login',
		);

		$args = array(
			'include' => $user_ids,
			'fields'  => $fields,
		);

		$users = get_users( $args );
		$users = $this->attach_metadata( $users );

		return $users;
	}

	protected function get_users( $last_id = 0, $limit = 100 ) {

		global $wpdb;

		// Multi-site queries work on single-site too, but we try to run lighter queries on single-site.
		if ( is_multisite() ) {
			$blog_prefix = $wpdb->get_blog_prefix( get_current_blog_id() );

			$query =
				"SELECT user_id, user_id AS id, display_name, user_email, user_registered, user_status, user_login
				FROM {$wpdb->users}, {$wpdb->usermeta}
				WHERE {$wpdb->users}.id = {$wpdb->usermeta}.user_id
				AND meta_key = '{$blog_prefix}capabilities'
				AND id > %d
				ORDER BY {$wpdb->usermeta}.user_id
				LIMIT %d";
		} else {
			$query = "
				SELECT id, display_name, user_email, user_registered, user_status, user_login
				FROM $wpdb->users
				WHERE id > %d
				ORDER BY id
				LIMIT %d";
		}

		$sql = $wpdb->prepare( $query, $last_id, $limit ); // phpcs:ignore

		return $wpdb->get_results( $sql ); // phpcs:ignore
	}

	protected function attach_metadata( $users ) {
		if ( ! is_array( $users ) ) {
			return $users;
		}

		return array_map(
			function ( $user ) {
				$user_email_marketing_consent_meta = get_user_meta( $user->id, 'convesioconvert_email_consent', true );

				$user->first_name  = get_user_meta( $user->id, 'first_name', true );
				$user->last_name   = get_user_meta( $user->id, 'last_name', true );
				$user->ecommerce   = apply_filters( 'convesioconvert_attach_user_ecommerce_data', array(), $user->id, true );
				$user->modified_at = $this->get_user_modified_at( $user->id );
				if ( ! empty( $user_email_marketing_consent_meta ) ) {
					$user->marketing_email_consent = $user_email_marketing_consent_meta;
					delete_user_meta( $user->id, 'convesioconvert_email_consent' );
				}
				return $user;
			},
			$users
		);
	}

	protected function get_user_modified_at( $user_id ) {
		$modification = get_user_meta( $user_id, '_convesioconvert_user_last_modification', true );

		if ( empty( $modification ) ) {
			$modification = time();
			update_user_meta( $user_id, '_convesioconvert_user_last_modification', $modification );
		}

		return $modification;
	}
}
