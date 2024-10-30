<?php

namespace ConvesioConvert\Controller;

class Page_Controller extends Post_Type_Sync {

	public function __construct() {
		$this->post_type = 'page';
		$this->fields    = array( 'ID', 'post_title' );
	}

	protected function prepare( $pages ) {
		return array_map(
			function ( $page ) {
				return array(
					'id'          => $page->ID,
					'url'         => \ConvesioConvert\get_relative_permalink( $page->ID ),
					'title'       => $page->post_title,
					'modified_at' => $this->get_post_type_modified_at( $page->ID ),
				);
			},
			$pages
		);
	}

}
