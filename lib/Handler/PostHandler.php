<?php

/**
 * Handles ´post´ content type replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 */

namespace Replicast\Handler;

use Replicast\API;
use GuzzleHttp\Promise\FulfilledPromise;

/**
 * Handles ´post´ content type replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class PostHandler extends Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \WP_Post    $post    Post object.
	 */
	public function __construct( \WP_Post $post ) {
		$post_type       = \get_post_type_object( $post->post_type );
		$this->rest_base = ! empty( $post_type->rest_base ) ? $post_type->rest_base : $post_type->name;
		$this->object    = $post;
		$this->data      = $this->get_object_data();
	}

	/**
	 * Handle post terms.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site           Site object.
	 * @param     object               $remote_data    Remote object data.
	 */
	public function handle_terms( $site, $remote_data ) {

		if ( empty( $this->data['_embedded'] ) ) {
			return;
		}

		if ( empty( $this->data['_embedded']['https://api.w.org/term'] ) ) {
			return;
		}

		if ( empty( $remote_data->replicast ) ) {
			return;
		}

		if ( empty( $remote_data->replicast->terms ) ) {
			return;
		}

		// Remote terms
		$remote_terms = $remote_data->replicast->terms;

		// TODO
		// - compare the list of local terms with the returned terms
		// - update the local term ids with the corresponding remote ids?

		// foreach ( $this->data['_embedded']['https://api.w.org/term'] as $term_link ) {
		// 	foreach ( $term_link as $term_data ) {

		// 		// Get term object
		// 		$term = \get_term( $term_data['id'], $term_data['taxonomy'] );

		// 		if ( ! API::is_term( $term ) ) {
		// 			continue;
		// 		}

		// 	}
		// }

	}

}
