<?php

namespace ConvesioConvert\Controller;

class Post_Type_Sync {

	protected $post_type;
	protected $fields;
	protected $post_status = array( 'publish' );

	public function index( $request ) {
		$params = $request->get_params();
		if ( isset( $params['type'] ) ) {
			switch ( $params['type'] ) {
				case 'new':
					return $this->get_new( $params );
				case 'modified':
					return $this->get_modified( $params );
				default:
					break;
			}
		}

		if ( isset( $params['ids'] ) ) {
			return $this->get_by_id( $params['ids'] );
		}
	}

	protected function get_new( $args ) {
		global $wpdb;

		$last_id = isset( $args['last_id'] ) ? $args['last_id'] : 0;
		$limit   = 100;

		$sql_statuses = implode( ', ', array_fill( 0, count( $this->post_status ), '%s' ) );
		$sql_fields   = implode( ', ', $this->fields );

		$values = array_merge( array( $this->post_type ), $this->post_status, array( $last_id ), array( $limit ) );
		$query  = "
			SELECT $sql_fields
			FROM $wpdb->posts
			WHERE post_type = %s AND post_status IN ($sql_statuses) AND ID > %d
			ORDER BY id
			LIMIT %d";

		$sql = $wpdb->prepare( $query, $values ); // phpcs:ignore

		$posts = $wpdb->get_results( $sql ); // phpcs:ignore
		$posts = $this->prepare( $posts );

		return $posts;
	}

	public function get_modified( $args ) {
		global $wpdb;

		$first_synced_id = isset( $args['first_synced_id'] ) ? $args['first_synced_id'] : 0;
		$last_id         = isset( $args['last_id'] ) ? $args['last_id'] : 0;
		$limit           = 1000;

		$query = "
			SELECT post_id AS id, meta_value AS ts
			FROM $wpdb->postmeta
			WHERE meta_key = %s AND post_id BETWEEN %d AND %d
			ORDER BY id
			LIMIT %d";

		$sql = $wpdb->prepare(
			$query, // phpcs:ignore
			"_convesioconvert_{$this->post_type}_last_modification",
			$last_id,
			$first_synced_id,
			$limit
		);

		return $wpdb->get_results( $sql ); // phpcs:ignore
	}

	public function get_by_id( $ids ) {
		$ids      = explode( ',', $ids );
		$post_ids = array_slice( $ids, 0, 100, true );

		$args = array(
			'post_type'   => $this->post_type,
			'include'     => $post_ids,
			'post_status' => $this->post_status,
		);

		$posts = get_posts( $args );
		$posts = $this->prepare( $posts );

		return $posts;
	}

	protected function prepare( $posts ) {

	}

	public function get_post_type_modified_at( $post_id ) {
		$modification = get_post_meta( $post_id, "_convesioconvert_{$this->post_type}_last_modification", true );

		if ( empty( $modification ) ) {
			$modification = time();
			update_post_meta( $post_id, "_convesioconvert_{$this->post_type}_last_modification", $modification );
		}

		return $modification;
	}

}
