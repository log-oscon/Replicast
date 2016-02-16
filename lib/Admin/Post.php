<?php

/**
 * Extend the ´post´ content type functionality
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Admin
 */

namespace Replicast\Admin;

/**
 * Extend the ´post´ content type functionality.
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Admin
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Post {

	/**
	 * The plugin's instance.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @var       \Replicast\Plugin    This plugin's instance.
	 */
	private $plugin;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Plugin    $plugin    This plugin's instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Prepare page for creation.
	 *
	 * @since     1.0.0
	 * @param     array    $data    Prepared post data.
	 * @return    array             Prepared post data.
	 */
	public function prepare_page_for_create( $data ) {
		$data = $this->page_template( $data );
		return $data;
	}

	/**
	 * Prepare page for update.
	 *
	 * @since     1.0.0
	 * @param     array    $data    Prepared post data.
	 * @return    array             Prepared post data.
	 */
	public function prepare_page_for_update( $data ) {
		$data = $this->fix_page_template( $data );
		return $data;
	}

	/**
	 * Fix page template for API requests.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $data    Prepared post data.
	 * @return    array             Prepared post data.
	 */
	private function fix_page_template( $data ) {

		// Unset page template if empty
		// @see https://github.com/WP-API/WP-API/blob/develop/lib/endpoints/class-wp-rest-posts-controller.php#L1553
		if ( empty( $data['template'] ) ) {
			unset( $data['template'] );
		}

		return $data;
	}

}
