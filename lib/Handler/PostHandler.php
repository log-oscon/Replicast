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

use Replicast\Handler\CategoryHandler;
use Replicast\Handler\TagHandler;
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
	 * Handle object terms.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function handle_terms( $site ) {

		if ( empty( $this->data['_embedded'] ) ) {
			// TODO: return message
			return new FulfilledPromise( \__( '', 'replicast' ) );
		}

		if ( empty( $this->data['_embedded']['https://api.w.org/term'] ) ) {
			// TODO: return message
			return new FulfilledPromise( \__( '', 'replicast' ) );
		}

		foreach ( $this->data['_embedded']['https://api.w.org/term'] as $term_link ) {
			foreach ( $term_link as $term_data ) {

				// Get term object
				$term = \get_term( $term_data['id'], $term_data['taxonomy'] );

				if ( ! $term instanceof \WP_Term ) {
					continue;
				}

				// TODO: add filter to exclude some term slugs from being synced?
				if ( in_array( $term->slug, array( 'uncategorized' ) ) ) {
					continue;
				}

				if ( $term->taxonomy === 'category' ) {
					$handler = new CategoryHandler( $term );
					return $handler->handle_update( $site );

				} elseif ( $term->taxonomy === 'post_tag' ) {
					$handler = new TagHandler( $term );
					return $handler->handle_update( $site );
				}

			}
		}

		// TODO: return message
		return new FulfilledPromise( \__( '', 'replicast' ) );
	}

}
