<?php

/**
 * Handles ´category´ content type replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 */

namespace Replicast\Handler;

use GuzzleHttp\Exception\RequestException;

/**
 * Handles ´category´ content type replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class CategoryHandler extends Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \WP_Term    $term    Term object.
	 */
	public function __construct( \WP_Term $term ) {
		$this->rest_base  = 'categories';
		$this->object     = $term;
		$this->attributes = array( 'context' => 'embed' );
		$this->data       = $this->get_object_data();
	}

	/**
	 * Get category from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function get( $site ) {}

	/**
	 * Create category on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function post( $site ) {
		return $this->do_request( Handler::CREATABLE, $site );
	}

	/**
	 * Update category on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function put( $site ) {
		return $this->do_request( Handler::EDITABLE, $site );
	}

	/**
	 * Delete category from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function delete( $site ) {}

}
