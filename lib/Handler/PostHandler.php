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
use Replicast\Handler;

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

		$obj = \get_post_type_object( $post->post_type );

		$this->rest_base   = ! empty( $obj->rest_base ) ? $obj->rest_base : $obj->name;
		$this->object      = $post;
		$this->object_type = $post->post_type;
		$this->data        = $this->get_object_data();

	}

	/**
	 * Prepare page for create, update or delete.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared page data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified page data.
	 */
	public function prepare_page( $data, $site ) {

		// Unset page template if empty
		if ( empty( $data['template'] ) ) {
			unset( $data['template'] );
		}

		return $data;
	}

	/**
	 * Prepare attachment for create, update or delete.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared attachment data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified attachment data.
	 */
	public function prepare_attachment( $data, $site ) {

		// Force attachment status to be 'publish'
		// FIXME: review this later on
		if ( ! empty( $data['status'] ) && $data['status'] === 'inherit' ) {
			$data['status'] = 'publish';
		}

		// Update the "uploaded to" post ID with the associated remote post ID, if exists
		if ( $data['type'] !== 'attachment' && ! empty( $data['post'] ) ) {

			// Get replicast info
			$replicast_info = API::get_replicast_info( \get_post( $data['post'] ) );

			// Update object ID
			$data['post'] = '';

			if ( ! empty( $replicast_info ) ) {
				$data['post'] = $replicast_info[ $site->get_id() ]['id'];
			}

		}

		return $data;
	}

	/**
	 * Prepare terms.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared page data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified terms.
	 */
	public function prepare_terms( $data, $site ) {

		// Unset default categories and tags data structures
		unset( $data['categories'] );
		unset( $data['tags'] );

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['term'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['term'] as $key => $term ) {

			// Get replicast info
			$replicast_info = API::get_replicast_info( $term );

			// Update object ID
			$term->term_id = '';

			if ( ! empty( $replicast_info ) ) {
				$term->term_id = $replicast_info[ $site->get_id() ]['id'];
			}

			$data['replicast']['term'][ $key ] = $term;

			// Check if term has children
			if ( empty( $term->children ) ) {
				continue;
			}

			$this->prepare_child_terms( $term->term_id, $data['replicast']['term'][ $key ]->children, $site );

		}

		return $data;
	}

	/**
	 * Prepare child terms.
	 *
	 * @since     1.0.0
	 * @param     int                  $parent_id    The parent term ID.
	 * @param     array                $terms        The term data.
	 * @param     \Replicast\Client    $site         Site object.
	 * @return    array                              Possibly-modified child terms.
	 */
	private function prepare_child_terms( $parent_id, &$terms, $site ) {

		foreach ( $terms as $key => $term ) {

			// Get replicast info
			$replicast_info = API::get_replicast_info( $term );

			// Update object ID's
			$term->term_id = '';
			$term->parent  = '';

			if ( ! empty( $replicast_info ) ) {
				$term->term_id = $replicast_info[ $site->get_id() ]['id'];
				$term->parent  = $parent_id;
			}

			$terms[ $key ] = $term;

			// Check if term has children
			if ( empty( $term->children ) ) {
				continue;
			}

			$this->prepare_child_terms( $term->term_id, $terms[ $key ]->children, $site );

		}

	}

	/**
	 * Prepare post featured media.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared post data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified post data.
	 */
	public function prepare_featured_media( $data, $site ) {

		$attachment_id = $data['featured_media'];

		// Unset default featured media data structure
		unset( $data['featured_media'] );

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['media'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['media']['featured_media'] ) ) {
			return $data;
		}

		// Get replicast info
		$replicast_info = API::get_replicast_info( \get_post( $attachment_id ) );

		// Update object ID
		$data['replicast']['media']['featured_media']['id'] = '';

		if ( ! empty( $replicast_info ) ) {
			$data['replicast']['media']['featured_media']['id'] = $replicast_info[ $site->get_id() ]['id'];
		}

		return $data;
	}

	/**
	 * Update object with remote ID.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	public function update_object( $site_id, $data = null ) {
		API::update_replicast_info( $this->object, $site_id, $data );
	}

	/**
	 * Update terms with remote IDs.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	public function update_terms( $site_id, $data = null ) {

		if ( empty( $data->replicast ) ) {
			return;
		}

		if ( empty( $data->replicast->term ) ) {
			return;
		}

		foreach ( $data->replicast->term as $term_data ) {

			// Get term object
			$term = \get_term_by( 'name', $term_data->name, $term_data->taxonomy );

			if ( ! $term ) {
				return;
			}

			// Update replicast info
			API::update_replicast_info( $term, $site_id, $term_data );

		}

	}

	/**
	 * Update media with remote IDs.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	public function update_media( $site_id, $data = null ) {

		if ( empty( $data->replicast ) ) {
			return;
		}

		if ( empty( $data->replicast->media ) ) {
			return;
		}

		foreach ( $data->replicast->media as $key => $media_data ) {

			// Get media object
			$media = \get_post( $media_data->id );

			if ( ! $media ) {
				return;
			}

			// Update replicast info
			API::update_replicast_info( $media, $site_id, $media_data );

		}

	}

}
