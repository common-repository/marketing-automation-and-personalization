<?php

namespace ConvesioConvert\EDD;

class Downloads extends \ConvesioConvert\Controller\Post_Type_Sync {

	public function __construct() {
		$this->post_type = 'download';
		$this->fields    = array( 'ID', 'post_content', 'post_excerpt', 'post_title', 'post_date_gmt' );
	}

	protected function prepare( $downloads ) {
		return array_map(
			function ( $post ) {
				// These return `false` when no term is found.
				$cats = get_the_terms( $post->ID, 'download_category' ) ?: array();
				$tags = get_the_terms( $post->ID, 'download_tag' ) ?: array();

				$download = edd_get_download( $post->ID );

				return array(
					'id'              => $post->ID,
					'content'         => $post->post_content,
					'excerpt'         => $post->post_excerpt,
					'url'             => \ConvesioConvert\get_relative_permalink( $post->ID ),
					'title'           => $post->post_title,
					'images'          => array( wp_get_attachment_url( get_post_thumbnail_id( $post->ID ), 'thumbnail' ) ),
					'price'           => (float) $download->get_price(),
					'variable_prices' => $download->get_prices(), // Different in WooCommerce
					'sku'             => $download->get_sku(),
					'total_sales'     => $download->get_sales(),
					'published_at'    => $post->post_date_gmt,
					'categories'      => wp_list_pluck( (array) $cats, 'term_taxonomy_id' ),
					'tags'            => wp_list_pluck( (array) $tags, 'term_taxonomy_id' ),
					'modified_at'     => $this->get_post_type_modified_at( $post->ID ),
				);
			},
			$downloads
		);
	}
}
