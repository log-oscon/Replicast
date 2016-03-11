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

		$post_type         = \get_post_type_object( $post->post_type );

		$this->rest_base   = ! empty( $post_type->rest_base ) ? $post_type->rest_base : $post_type->name;
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
		// @see https://github.com/WP-API/WP-API/blob/develop/lib/endpoints/class-wp-rest-posts-controller.php#L1553
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

			$data['post'] = $replicast_info[ $site->get_id() ]['id'];

		} else {
			$data['post'] = '';
		}

		return $data;
	}

	/**
	 * Prepare post terms.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared page data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified terms.
	 */
	public function prepare_post_terms( $data, $site ) {

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

			$this->prepare_post_child_terms( $term->term_id, $data['replicast']['term'][ $key ]->children, $site );

		}

		return $data;
	}

	/**
	 * Prepare post child terms.
	 *
	 * @since     1.0.0
	 * @param     int                  $parent_id    The parent term ID.
	 * @param     array                $terms        The term data.
	 * @param     \Replicast\Client    $site         Site object.
	 * @return    array                              Possibly-modified child terms.
	 */
	private function prepare_post_child_terms( $parent_id, &$terms, $site ) {

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

			$this->prepare_post_child_terms( $term->term_id, $terms[ $key ]->children, $site );

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

		// Update the "featured image" post ID with the associated remote post ID, if exists
		if ( ! empty( $data['featured_media'] ) ) {

			// Get replicast info
			$replicast_info = API::get_replicast_info( \get_post( $data['featured_media'] ) );

			$data['featured_media'] = $replicast_info[ $site->get_id() ]['id'];

		}

		return $data;
	}

	/**
	 * Update post info.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	public function update_post_info( $site_id, $data = null ) {

		// Update replicast info
		API::update_replicast_info( $this->object, $site_id, $data );

	}

	/**
	 * Update post terms.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	public function update_post_terms( $site_id, $data ) {

		if ( empty( $data->replicast ) ) {
			return;
		}

		if ( empty( $data->replicast->term ) ) {
			return;
		}

		foreach ( $data->replicast->term as $term_data ) {

			// Get term object
			$term = \get_term_by( 'id', $term_data->term_id, $term_data->taxonomy );

			if ( ! $term ) {
				return;
			}

			// Update replicast info
			API::update_replicast_info( $term, $site_id, $term_data );

		}

	}

	/**
	 * Prepare post meta (ACF).
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared post data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified post data.
	 */
	public function prepare_post_meta( $data, $site ) {

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['meta'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['meta'] as $key => $meta ) {

			if ( empty( $meta['raw'] ) ) {
				continue;
			}

			$field_value = \acf_extract_var( $meta['raw'], 'value' );

			if ( empty( $field_value ) ) {
				continue;
			}

			$meta_value  = array();
			$field_type  = \acf_extract_var( $meta['raw'], 'type' );

			if ( $field_type === 'text' ) {
				$meta_value = $field_value;
			}

			if ( $field_type === 'relationship' ) {

				foreach ( $field_value as $related_object ) {

					if ( is_numeric( $related_object ) ) {
						$related_object = \get_post( $related_object );
					}

					// Get replicast info
					$replicast_info = API::get_replicast_info( $related_object );

					// Update object ID
					if ( ! empty( $replicast_info ) ) {
						$meta_value[] = $replicast_info[ $site->get_id() ]['id'];
					}

				}

				if ( ! empty( $meta_value ) ) {
					$meta_value = maybe_serialize( $meta_value );
				}

			}

			unset( $data['replicast']['meta'][ $key ] );

			if ( empty( $meta_value ) ) {
				continue;
			}

			$data['replicast']['meta'][ $key ][] = $meta_value;

		}

		return $data;
	}

}
