<?php

/**
 * Handles ´category´ content type replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Request
 */

namespace Replicast\Request;

use Replicast\Request;
use GuzzleHttp\Exception\RequestException;

/**
 * Handles ´category´ content type replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Request
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Category extends Request {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \WP_Term|array    $term    Term object.
	 */
	public function __construct( \WP_Term $term ) {
		$this->rest_base = 'categories';
		$this->object    = $term;
	}

	/**
	 * Get category from a site.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Model\Site    $site    Site object.
	 */
	public function get( $site ) {}

	/**
	 * Create category on a site.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Model\Site    $site    Site object.
	 */
	public function post( $site ) {}

	/**
	 * Update category on a site.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Model\Site    $site    Site object.
	 */
	public function put( $site ) {}

	/**
	 * Delete category from a site.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Model\Site    $site    Site object.
	 */
	public function delete( $site ) {}

}
