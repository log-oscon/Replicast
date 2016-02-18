<?php

/**
 * Handles ´tag´ content type replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 */

namespace Replicast\Handler;

use \GuzzleHttp\Exception\RequestException;

/**
 * Handles ´tag´ content type replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class TagHandler extends Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \WP_Term    $term    Term object.
	 */
	public function __construct( \WP_Term $term ) {
		$this->rest_base  = 'tags';
		$this->object     = $term;
		$this->attributes = array( 'context' => 'embed' );
		$this->data       = $this->get_object_data();
	}

	/**
	 * Get tag from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function get( $site ) {}

	/**
	 * Create tag on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function post( $site ) {}

	/**
	 * Update tag on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function put( $site ) {}

	/**
	 * Delete tag from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function delete( $site ) {}

}
