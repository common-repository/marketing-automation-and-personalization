<?php

namespace ConvesioConvert\Controller;

class Terms_Controller {

	public function index( $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['taxonomy'] ) ) {
			return null;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $params['taxonomy'],
				'orderby'    => 'id',
				'hide_empty' => false,
				'count'      => true,
			)
		);

		if ( $terms instanceof \WP_Error ) {
			// We could return empty array in case of `'invalid_taxonomy' === $terms->get_error_code()`; but didn't try.
			// `invalid_taxonomy` happens when wrong taxonomy, e.g for a non-existent platform is called, or in case a
			// taxonomy is hidden, or not registered yet; but we haven't seen examples of any of the latter two yet.

			// Deliberately invalid JSON to throw error in the other side.
			return 'error: ' . $terms->get_error_code();
		}

		return array_map(
			/** @param \WP_Term $term */
			function ( $term ) {
				return array(
					'id'     => $term->term_taxonomy_id,
					'slug'   => $term->slug,
					'name'   => $term->name,
					'count'  => $term->count,
					'parent' => $term->parent,
				);
			},
			$terms
		);
	}
}
