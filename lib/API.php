<?php

/**
 * Extend the API functionality
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

use Replicast\Admin;

/**
 * Extend the API functionality.
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class API {

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
	 * Register hooks.
	 *
	 * @since    1.0.0
	 */
	public function register() {

		foreach ( Admin\SiteAdmin::get_post_types() as $post_type ) {

			if ( function_exists( 'register_rest_field' ) ) {
				\register_rest_field(
					$post_type,
					'replicast',
					array(
						'get_callback'    => array( __CLASS__, 'get_rest_fields' ),
						'update_callback' => array( __CLASS__, 'update_rest_fields' ),
						'schema'          => null,
					)
				);
			}

		}

	}

	/**
	 * Retrieve the field value.
	 *
	 * @since     1.0.0
	 * @param     array               $object        Details of current content object.
	 * @param     string              $field_name    Name of field.
	 * @param     \WP_REST_Request    $request       Current \WP_REST_Request request.
	 * @return    array                              Custom fields.
	 */
	public static function get_rest_fields( $object, $field_name, $request ) {

		if ( $field_name !== 'replicast' ) {
			return array();
		}

		return array(
			'meta'  => static::get_object_meta( $object, $request ),
			'terms' => static::get_object_terms( $object, $request ),
			'media' => static::get_object_media( $object, $request ),
		);
	}

	/**
	 * Retrieve object meta.
	 *
	 * @since     1.0.0
	 * @param     array               $object     Details of current content object.
	 * @param     \WP_REST_Request    $request    Current \WP_REST_Request request.
	 * @return    array                           Object meta.
	 */
	public static function get_object_meta( $object, $request ) {

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		/**
		 * Filter for exposing specific protected meta keys.
		 *
		 * @since     1.0.0
		 * @param     array     Name(s) of the exposed meta keys.
		 * @param     string    The object meta type.
		 * @param     int       The object ID.
		 * @return    array     Possibly-modified name(s) of the exposed meta keys.
		 */
		$exposed_meta = \apply_filters( 'replicast_expose_object_protected_meta', array(), $meta_type, $object['id'] );

		// Get object metadata
		$meta = \get_metadata( $meta_type, $object['id'] );

		if ( ! $meta ) {
			return array();
		}

		if ( ! is_array( $meta ) ) {
			$meta = (array) $meta;
		}

		$prepared_data = array();
		foreach ( $meta as $meta_key => $meta_value ) {

			if ( \is_protected_meta( $meta_key ) && ! in_array( $meta_key, $exposed_meta ) ) {
				continue;
			}

			$prepared_data[ $meta_key ] = $meta_value;
		}

		/**
		 * Extend object meta by meta type.
		 *
		 * @since     1.0.0
		 * @param     array    Object meta.
		 * @param     array    Details of current content object.
		 * @return    array    Possibly-modified object meta.
		 */
		$prepared_data = \apply_filters( "replicast_get_object_{$meta_type}_meta", $prepared_data, $object );

		/**
		 * Extend object meta.
		 *
		 * @since     1.0.0
		 * @param     array     Object meta.
		 * @param     array     Details of current content object.
		 * @param     string    The object meta type.
		 * @return    array     Possibly-modified object meta.
		 */
		return \apply_filters( 'replicast_get_object_meta', $prepared_data, $object, $meta_type );
	}

	/**
	 * Retrieve object terms.
	 *
	 * @since     1.0.0
	 * @param     array               $object     Details of current content object.
	 * @param     \WP_REST_Request    $request    Current \WP_REST_Request request.
	 * @return    array                           Object terms.
	 */
	public static function get_object_terms( $object, $request ) {

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		// Get a hierarchical list of object terms
		$prepared_data = static::get_terms_hierarchical( $object );

		/**
		 * Extend object terms by meta type.
		 *
		 * @since     1.0.1
		 * @param     array    Hierarchical list of object terms.
		 * @param     array    Details of current content object.
		 * @return    array    Possibly-modified object terms.
		 */
		$prepared_data = \apply_filters( "replicast_get_object_{$meta_type}_terms", $prepared_data, $object );

		/**
		 * Extend object terms.
		 *
		 * @since     1.0.0
		 * @param     array    Hierarchical list of object terms.
		 * @param     array    Details of current content object.
		 * @return    array    Possibly-modified object terms.
		 */
		return \apply_filters( 'replicast_get_object_terms', $prepared_data, $object );
	}

	/**
	 * Retrieve the terms for an object.
	 *
	 * @see \wp_get_object_terms()
	 *
	 * @since     1.0.0
	 * @param     int       $object_id      The object ID.
	 * @param     string    $object_type    The object type.
	 * @return    array                     An array of taxonomy terms, or empty array if no terms are found.
	 */
	public static function get_terms( $object_id, $object_type ) {

		// FIXME: we should soft cache this

		// Get a list of object taxonomies
		$taxonomies = static::get_taxonomies( $object_id, $object_type );

		$terms = \wp_get_object_terms( $object_id, array_keys( $taxonomies ) );

		if ( empty( $terms ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Returns all the taxonomies for an object.
	 *
	 * @see \get_object_taxonomies()
	 *
	 * @since     1.0.0
	 * @param     int       $object_id      The object ID.
	 * @param     string    $object_type    The object type.
	 * @return    array                     All taxonomy names or objects for the given object.
	 */
	public static function get_taxonomies( $object_id, $object_type ) {

		// FIXME: we should soft cache this

		$taxonomies = \get_object_taxonomies( $object_type, 'objects' );

		/**
		 * Filter for suppressing taxonomies.
		 *
		 * @since     1.0.0
		 * @param     array    Name(s) of the suppressed taxonomies.
		 * @param     array    List of registered taxonomies.
		 * @param     int      The object ID.
		 * @return    array    Possibly-modified name(s) of the suppressed taxonomies.
		 */
		$suppressed_taxonomies = \apply_filters( 'replicast_suppress_object_taxonomies', array(), $taxonomies, $object_id );

		$prepared_taxonomies = array();
		foreach ( $taxonomies as $taxonomy_name => $taxonomy ) {

			if ( in_array( $taxonomy_name, array( Plugin::TAXONOMY_SITE ) ) ) {
				continue;
			}

			if ( in_array( $taxonomy_name, $suppressed_taxonomies ) ) {
				continue;
			}

			$prepared_taxonomies[ $taxonomy_name ] = $taxonomy;

		}

		return $prepared_taxonomies;
	}

	/**
	 * Retrieves the terms associated with the given object in the supplied
	 * taxonomies, hierarchically structured.
	 *
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $object    Details of current content object.
	 * @return    array               Hierarchical list of object terms
	 */
	private static function get_terms_hierarchical( $object ) {

		// Retrieve the terms
		$terms = static::get_terms( $object['id'], $object['type'] );

		$hierarchical_terms = array();
		foreach ( $terms as $term ) {

			if ( $term->parent > 0 ) {
				continue;
			}

			$term_id   = $term->term_id;
			$source_id = static::get_source_id( $term_id, 'term' );

			$hierarchical_terms[ $source_id ] = $term;

			$child_terms = static::get_child_terms( $term_id, $terms );
			if ( ! empty( $child_terms ) ) {
				$hierarchical_terms[ $source_id ]->children = $child_terms;
			}

		}

		return $hierarchical_terms;
	}

	/**
	 * Retrieves a list of child terms.
	 *
	 * Recursive function.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     int      $parent_id    The parent term ID.
	 * @param     array    $terms        The term data.
	 * @return    array                  List of child terms.
	 */
	private static function get_child_terms( $parent_id, $terms ) {

		$children = array();
		foreach ( $terms as $term ) {

			if ( $term->parent !== $parent_id ) {
				continue;
			}

			$term_id   = $term->term_id;
			$source_id = static::get_source_id( $term_id, 'term' );

			$children[ $source_id ] = $term;

			$child_terms = static::get_child_terms( $term_id, $terms );
			if ( ! empty( $child_terms ) ) {
				$children[ $source_id ]->children = $child_terms;
			}

		}

		return $children;
	}

	/**
	 * Retrieves object media.
	 *
	 * This method is used to build a data structure that contains all the information needed
	 * to create a virtual media object on the remote site.
	 *
	 * Its basic use is to prepare the featured media object that is attached to an object, like a post.
	 *
	 * @since     1.0.0
	 * @param     array               $object     Details of current content object.
	 * @param     \WP_REST_Request    $request    Current \WP_REST_Request request.
	 * @return    array                           Object media.
	 */
	public static function get_object_media( $object, $request ) {

		$prepared_data = array();

		// Retrieve featured media
		if ( ! empty( $object['featured_media'] )  ) {

			$source_id = static::get_source_id( $object['featured_media'] );

			$relations = array(
				'post' => array(
					$object['id'] => array(
						'featured_media' => 'featured_media',
					),
				),
			);

			$prepared_data[ $source_id ] = static::get_media( $source_id, $object['featured_media'], $relations, $prepared_data );
		}

		// Retrieve galleries media
		if ( static::is_post( $object ) && ! empty( $object['content']['raw'] )  ) {

			// Get galleries
			$galleries = static::get_galleries( $object['content']['raw'] );

			foreach ( $galleries as $gallery ) {

				$atts = \shortcode_parse_atts( $gallery[3] );

				if ( empty( $atts['ids'] ) ) {
					continue;
				}

				$ids = explode( ',', $atts['ids'] );

				foreach ( $ids as $id ) {

					$source_id = static::get_source_id( $id );

					$relations = array(
						'post' => array(
							$object['id'] => array(
								'gallery_shortcode' => 'gallery_shortcode',
							),
						),
					);

					$prepared_data[ $source_id ] = static::get_media( $source_id, $id, $relations, $prepared_data );
				}

			}

		}

		/**
		 * Extend object media.
		 *
		 * @since     1.0.0
		 * @param     array    Object media.
		 * @param     array    Details of current content object.
		 * @return    array    Possibly-modified object media.
		 */
		return \apply_filters( 'replicast_get_object_media', $prepared_data, $object );
	}

	/**
	 * Retrieves a media object.
	 *
	 * If the object ID does not exists it adds media information for creation purposes.
	 * Otherwise, adds the ID and the field type in the structure that "saves" the
	 * relation between the local IDs and the IDs on the remote site.
	 *
	 * @since     1.0.0
	 * @param     int      $source_id    The source object ID.
	 * @param     int      $object_id    The object ID.
	 * @param     mixed    $relations    Object relations.
	 * @param     array    $data         Object media.
	 * @return    array                  Prepared media object.
	 */
	public static function get_media( $source_id, $object_id, $relations = array(), $data ) {

		// Add media information for creation purposes
		if ( ! array_key_exists( $object_id, $data ) ) {

			/**
			 * Filter for suppressing image sizes.
			 *
			 * @since     1.0.0
			 * @param     array    Name(s) of the suppressed image sizes.
			 * @return    array    Possibly-modified name(s) of the suppressed image sizes.
			 */
			$suppressed_image_sizes = \apply_filters( 'replicast_suppress_image_sizes', array() );

			// Get metadata
			$metadata = \wp_get_attachment_metadata( $object_id, true );

			// Replace relative url with absolute url
			$metadata['file'] = \wp_get_attachment_url( $object_id );

			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size => $value ) {

					if ( in_array( $size, $suppressed_image_sizes ) ) {
						unset( $metadata['sizes'][ $size ] );
						continue;
					}

					// Replace relative url with absolute url
					$metadata['sizes'][ $size ]['file'] = \wp_get_attachment_image_src( $object_id, $size )[0];

				}
			}

			$attachment = \get_post( $object_id );

			return array(
				'id'           => $object_id,
				'post_title'   => $attachment->post_title,
				'post_excerpt' => $attachment->post_excerpt,
				'post_content' => $attachment->post_content,
				'mime-type'    => \get_post_mime_type( $object_id ),
				'metadata'     => $metadata,
				'_relations'   => $relations,
			);
		}

		$data[ $source_id ]['_relations'] = array_merge_recursive( $data[ $source_id ]['_relations'], $relations );

		return $data[ $source_id ];
	}

	/**
	 * Retrieves galleries from an object.
	 *
	 * @uses  \get_shortcode_regex()
	 *
	 * @since     1.0.0
	 * @param     string    $content    Object content.
	 * @return    array                 Parsed galleries.
	 */
	public static function get_galleries( $content ) {
		preg_match_all( '/' . \get_shortcode_regex( array( 'gallery' ) ) . '/', $content, $galleries, PREG_SET_ORDER );
		return $galleries;
	}

	/**
	 * Set and update the field value.
	 *
	 * @since     1.0.0
	 * @param     array     $values    The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_rest_fields( $values, $object ) {

		// Update object meta
		if ( ! empty( $values['meta'] ) ) {
			static::update_object_meta( $values['meta'], $object );
		}

		// Update object terms
		if ( ! empty( $values['terms'] ) ) {
			static::update_object_terms( $values['terms'], $object );
		}

		// Update object media
		if ( ! empty( $values['media'] ) ) {
			static::update_object_media( $values['media'], $object );
		}

	}

	/**
	 * Update object meta.
	 *
	 * @since     1.0.0
	 * @param     array     $meta      The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_meta( $meta, $object ) {

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		/**
		 * Filter for suppressing specific meta keys.
		 *
		 * @since     1.0.0
		 * @param     array     Name(s) of the suppressed meta keys.
		 * @param     array     The values of the field.
		 * @param     string    The object meta type.
		 * @param     int       The object ID.
		 * @return    array     Possibly-modified name(s) of the suppressed meta keys.
		 */
		$suppressed_meta = \apply_filters( 'replicast_suppress_object_meta', array(), $meta, $meta_type, $object->ID );

		// Update metadata
		foreach ( $meta as $meta_key => $meta_values ) {

			if ( in_array( $meta_key, $suppressed_meta ) ) {
				continue;
			}

			\delete_metadata( $meta_type, $object->ID, $meta_key );
			foreach ( $meta_values as $meta_value ) {
				\add_metadata( $meta_type, $object->ID, $meta_key, \maybe_unserialize( $meta_value ) );
			}

		}

		/**
		 * Fires immediately after object meta of a specific type is updated.
		 *
		 * @since    1.0.0
		 * @param    array     The values of the field.
		 * @param    object    The object from the response.
		 */
		\do_action( "replicast_update_object_{$meta_type}_meta", $meta, $object );

		/**
		 * Fires immediately after object meta is updated.
		 *
		 * @since    1.0.0
		 * @param    array     The values of the field.
		 * @param    object    The object from the response.
		 * @param    string    The object meta type.
		 */
		\do_action( "replicast_update_object_meta", $meta, $object, $meta_type );

	}

	/**
	 * Update object terms.
	 *
	 * @since     1.0.0
	 * @param     array     $terms     The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_terms( $terms, $object ) {

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		$prepared_ids = array();

		// Update terms
		foreach ( $terms as $source_id => $term_data ) {

			// Check if taxonomy exists
			if ( ! \taxonomy_exists( $term_data['taxonomy'] ) ) {
				continue;
			}

			if ( $term_data['parent'] > 0 ) {
				continue;
			}

			// Update term
			$term = static::update_term( $term_data );

			if ( empty( $term ) ) {
				continue;
			}

			$terms[ $source_id ]['term_id']          = $term['term_id'];
			$terms[ $source_id ]['term_taxonomy_id'] = $term['term_taxonomy_id'];

			// Save term id for post insertion
			$prepared_ids[ $term_data['taxonomy'] ][] = $term['term_id'];

			// Check if term has children
			if ( empty( $term_data['children'] ) ) {
				continue;
			}

			$prepared_ids = array_merge_recursive(
				$prepared_ids,
				static::update_child_terms( $term['term_id'], $term_data['children'] )
			);

		}

		// Get a list of object taxonomies
		$taxonomies = array_keys( static::get_taxonomies( $object->ID, $object->post_type ) );

		// Relates an object to a term, or a set of terms, and taxonomy type
		foreach ( $taxonomies as $taxonomy ) {

			$ids = array();
			if ( array_key_exists( $taxonomy, $prepared_ids ) ) {
				$ids = $prepared_ids[ $taxonomy ];
			}

			/**
			 * If this was coming from the database or another source, we would need to make sure
			 * these were integers.
			 */
			$ids = array_map( 'intval', $ids );
			$ids = array_unique( $ids );

			\wp_set_object_terms( $object->ID, $ids, $taxonomy, false );
		}

		/**
		 * Fires immediately after object terms of a specific type are updated.
		 *
		 * @since    1.0.1
		 * @param    array     The values of the field.
		 * @param    object    The object from the response.
		 */
		\do_action( "replicast_update_object_{$meta_type}_terms", $terms, $object );

		/**
		 * Fires immediately after object terms are updated.
		 *
		 * @since    1.0.0
		 * @param    array     The values of the field.
		 * @param    object    The object from the response.
		 */
		\do_action( 'replicast_update_object_terms', $terms, $object );

	}

	/**
	 * Updates a list of child terms.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     int      $parent_id    The parent term ID.
	 * @param     array    $terms        The term data.
	 * @return    array                  List of child terms.
	 */
	private static function update_child_terms( $parent_id, $terms ) {

		$prepared_ids = array();

		foreach ( $terms as $source_id => $term_data ) {

			// Update term
			$term = static::update_term( $term_data, $parent_id );

			if ( empty( $term ) ) {
				continue;
			}

			$terms[ $source_id ]['term_id']          = $term['term_id'];
			$terms[ $source_id ]['term_taxonomy_id'] = $term['term_taxonomy_id'];

			// Save term id for post insertion
			$prepared_ids[ $term_data['taxonomy'] ][] = $term['term_id'];

			// Check if term has children
			if ( empty( $term_data['children'] ) ) {
				continue;
			}

			$prepared_ids = array_merge_recursive(
				$prepared_ids,
				static::update_child_terms( $term['term_id'], $term_data['children'] )
			);

		}

		return $prepared_ids;
	}

	/**
	 * Update term object.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $term_data    The term data.
	 * @param     int      $parent_id    The parent term ID.
	 * @return    array                  An array containing, at least, the term_id and term_taxonomy_id.
	 */
	private static function update_term( $term_data, $parent_id = 0 ) {

		$term     = ! empty( $term_data['term_id'] ) ? $term_data['term_id'] : $term_data['name'];
		$taxonomy = $term_data['taxonomy'];

		$values = array(
			'name'        => $term_data['name'],
			'description' => $term_data['description'],
			'parent'      => $parent_id,
		);

		$term = \term_exists( $term, $taxonomy, $parent_id );

		// Insert term if does not exists
		if ( $term === 0 || $term === null ) {
			$term = \wp_insert_term( $term_data['name'], $taxonomy, $values );
		}

		if ( ! is_array( $term ) ) {
			return array();
		}

		// Update term
		\wp_update_term( $term['term_id'], $taxonomy, $values );

		// Save remote object info
		if ( ! empty( $term_data['meta'] ) ) {
			\update_term_meta( $term['term_id'], Plugin::REPLICAST_SOURCE_INFO, $term_data['meta'][ Plugin::REPLICAST_SOURCE_INFO ] );
		}

		return $term;
	}

	/**
	 * Update object media.
	 *
	 * @since     1.0.0
	 * @param     array     $media     The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_media( $media, $object ) {

		$gallery_media_ids = array();

		// Create media or update media
		foreach ( $media as $source_id => $media_data ) {

			// Update media
			$media[ $source_id ]['id'] = static::update_media( $media_data );

			if ( empty( $media_data['_relations']['post'] ) ) {
				continue;
			}

			// Update media relations
			foreach ( $media_data['_relations']['post'] as $relations ) {

				// Update the featured media into the database
				if ( array_key_exists( 'featured_media', $relations ) ) {
					\set_post_thumbnail( $object->ID, $media[ $source_id ]['id'] );
				}

				if ( array_key_exists( 'gallery_shortcode', $relations ) ) {
					$gallery_media_ids[ $source_id ] = $media[ $source_id ]['id'];
				}

			}

		}

		// Update galleries media
		if ( static::is_post( $object ) && ! empty( $object->post_content ) ) {

			// Get galleries
			$galleries = static::get_galleries( $object->post_content );

			foreach ( $galleries as $gallery ) {

				$atts         = \shortcode_parse_atts( $gallery[3] );
				$ids          = explode( ',', $atts['ids'] );
				$prepared_ids = array();

				foreach ( $ids as $id ) {
					if ( ! array_key_exists( $id, $gallery_media_ids ) ) {
						continue;
					}
					$prepared_ids[] = $gallery_media_ids[ $id ];
				}

				$pattern     = '/(\\[gallery(?:.*)ids=)\"(' . $atts['ids'] . ')\"((?:.*)\\])/';
				$replacement = '';

				// If there's no association between any of the local and remote media,
				// it's better remove the shortcode
				if ( ! empty( $prepared_ids ) ) {
					$replacement = '${1}"' . implode( ',', $prepared_ids ) . '"$3';
				}

				$object->post_content = preg_replace( $pattern, $replacement, $object->post_content );

			}

			// Update the post into the database
			\wp_update_post( $object );

		}

		/**
		 * Fires immediately after object media is updated.
		 *
		 * @since    1.0.0
		 * @param    array     The values of the field.
		 * @param    object    The object from the response.
		 */
		\do_action( 'replicast_update_object_media', $media, $object );

	}

	/**
	 * Get the object ID.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    string                     The object ID.
	 */
	public static function get_id( $object ) {

		if ( isset( $object->term_id ) ) {
			return $object->term_id;
		}

		if ( isset( $object->ID ) ) {
			return $object->ID;
		}

		return $object->id;
	}

	/**
	 * Get meta type based on the object class or array data.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    string                     Possible values: user, comment, post, meta
	 */
	public static function get_meta_type( $object ) {

		// FIXME: revisit later

		if ( static::is_term( $object ) ) {
			return 'term';
		}

		return 'post';
	}

	/**
	 * Check if object is a post/page.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    bool                       True if it's a post/page. False, otherwise.
	 */
	public static function is_post( $object ) {

		// TODO: continuous improvement

		if ( $object instanceof \WP_Post ) {
			return true;
		}

		if ( is_object( $object ) && isset( $object->post_type ) ) {
			return true;
		}

		if ( is_array( $object ) && isset( $object['type'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if object is a term.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    bool                       True if it's a term. False, otherwise.
	 */
	public static function is_term( $object ) {

		// TODO: continuous improvement

		if ( $object instanceof \WP_Term ) {
			return true;
		}

		if ( is_object( $object ) && isset( $object->term_id ) ) {
			return true;
		}

		if ( is_array( $object ) && isset( $object['term_id'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieve source object ID.
	 *
	 * @since     1.0.0
	 * @param     int       $object_id    The object ID.
	 * @param     string    $meta_type    The object meta type.
	 * @return    int                     The source object ID.
	 */
	public static function get_source_id( $object_id, $meta_type = 'post' ) {

		// Get source object info
		$source_info = static::get_source_info( $object_id, $meta_type );

		if ( ! empty( $source_info ) && ! empty( $source_info['object_id'] ) ) {
			return $source_info['object_id'];
		}

		return $object_id;
	}

	/**
	 * Retrieve source object info.
	 *
	 * @since     1.0.0
	 * @param     int       $object_id    The object ID.
	 * @param     string    $meta_type    The object meta type.
	 * @return    mixed                   Single metadata value, or array of values.
	 *                                    If the $meta_type or $object_id parameters are invalid, false is returned.
	 */
	public static function get_source_info( $object_id, $meta_type = 'post' ) {

		if( empty( $metadata = \get_metadata( $meta_type, $object_id, Plugin::REPLICAST_SOURCE_INFO, true ) ) ) {
			return $metadata;
		}

		return \maybe_unserialize( $metadata );
	}

	/**
	 * Retrieve remote object info.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    array                      The remote object info.
	 */
	public static function get_remote_info( $object ) {

		$remote_info = \get_metadata(
			static::get_meta_type( $object ),
			static::get_id( $object ),
			Plugin::REPLICAST_REMOTE_INFO,
			true
		);

		if ( ! $remote_info ) {
			return array();
		}

		if ( ! is_array( $remote_info ) ) {
			$remote_info = (array) $remote_info;
		}

		return $remote_info;
	}

	/**
	 * Update remote object info.
	 *
	 * This remote info consists in a pair <site_id, remote_object_id>.
	 *
	 * @since     1.0.0
	 * @param     object|array         $object                       The object.
	 * @param     int                  $site_id                      Site ID.
	 * @param     object|null          $remote_data    (optional)    Remote object data. Null if it's for permanent delete.
	 * @return    mixed                                              Returns meta ID if the meta doesn't exist, otherwise
	 *                                                               returns true on success and false on failure.
	 */
	public static function update_remote_info( $object, $site_id, $remote_data = null ) {

		// Get replicast object info
		$remote_info = static::get_remote_info( $object );

		// Save or delete the remote object info
		if ( $remote_data ) {

			$remote_info[ $site_id ] = array(
				'id' => static::get_id( $remote_data ),
			);

			if ( static::is_post( $object ) ) {
				$remote_info[ $site_id ]['status'] = $remote_data->status;
			}

			if ( static::is_term( $object ) ) {
				$remote_info[ $site_id ]['term_taxonomy_id'] = $remote_data->term_taxonomy_id;
			}

		}
		else {
			unset( $remote_info[ $site_id ] );
		}

		return \update_metadata(
			static::get_meta_type( $object ),
			static::get_id( $object ),
			Plugin::REPLICAST_REMOTE_INFO,
			$remote_info
		);
	}

	/**
	 * Updates a media object.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $media_data    The values of the field.
	 * @return    int                     The media object ID.
	 */
	private static function update_media( $media_data ) {

		$attachment_id = ! empty( $media_data['id'] ) ? $media_data['id'] : '';

		// Create an attachment if no ID was given
		if ( empty( $attachment_id ) ) {

			$file  = \esc_url( $media_data['metadata']['file'] );
			$title = ! empty( $media_data['post_title'] ) ? \sanitize_text_field( $media_data['post_title'] ) : \sanitize_file_name( basename( $file ) );

			// Set attachment data
			$attachment = array(
				'post_mime_type' => $media_data['mime-type'],
				'post_title'     => $title,
				'post_excerpt'   => \sanitize_text_field( $media_data['post_excerpt'] ),
				'post_content'   => \sanitize_text_field( $media_data['post_content'] ),
				'post_status'    => 'inherit'
			);

			// Create the attachment
			$attachment_id = \wp_insert_attachment( $attachment, $file );

			// Assign metadata to attachment
			\wp_update_attachment_metadata( $attachment_id, $media_data['metadata'] );

		}

		// Save remote object info
		\update_post_meta( $attachment_id, Plugin::REPLICAST_SOURCE_INFO, $media_data[ Plugin::REPLICAST_SOURCE_INFO ] );

		return $attachment_id;
	}

}
