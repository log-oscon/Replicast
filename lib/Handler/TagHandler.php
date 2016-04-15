<?php

/**
 * Handles ´post_tag´ terms replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 */

namespace Replicast\Handler;

use Replicast\Handler;

/**
 * Handles ´post_tag´ terms replication.
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

		$this->rest_base   = 'tags';
		$this->object      = $term;
		$this->object_type = $term->taxonomy;
		$this->data        = $this->get_object_data();
		$this->attributes  = array( 'context' => 'embed' );

	}

}
