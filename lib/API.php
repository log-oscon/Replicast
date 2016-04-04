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
	 * Registers a new field on a set of existing object types.
	 *
	 * @since    1.0.0
	 */
	public function register_rest_fields() {

		if ( ! function_exists( 'register_rest_field' ) ) {
			return;
		}

		foreach ( Admin\SiteAdmin::get_post_types() as $post_type ) {
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
		return array(
			'meta'  => static::get_object_meta( $object, $request ),
			'term'  => static::get_object_term( $object, $request ),
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
		 * @param     array     Object meta.
		 * @param     string    The object meta type.
		 * @param     int       Object ID.
		 * @return    array     Possibly-modified object meta.
		 */
		$prepared_data = \apply_filters( "replicast_get_{$meta_type}_meta", $prepared_data, $meta_type, $object['id'] );

		/**
		 * Extend object meta.
		 *
		 * @since     1.0.0
		 * @param     array    Object meta.
		 * @param     int      Object ID.
		 * @return    array    Possibly-modified object meta.
		 */
		$prepared_data = \apply_filters( 'replicast_get_object_meta', $prepared_data, $object['id'] );

		// Add remote object info
		$prepared_data[ Plugin::REPLICAST_OBJECT_INFO ] = array( \maybe_serialize( array(
			'object_id' => $object['id'],
			'edit_link' => \get_edit_post_link( $object['id'] ),
			'rest_url'  => \rest_url( $request->get_route() ),
		) ) );

		return $prepared_data;
	}

	/**
	 * Retrieve object terms.
	 *
	 * @since     1.0.0
	 * @param     array               $object     Details of current content object.
	 * @param     \WP_REST_Request    $request    Current \WP_REST_Request request.
	 * @return    array                           Object terms.
	 */
	public static function get_object_term( $object, $request ) {

		// Get a list of registered taxonomies
		$taxonomies = \get_taxonomies();

		/**
		 * Filter for suppressing taxonomies.
		 *
		 * @since     1.0.0
		 * @param     array    Name(s) of the suppressed taxonomies.
		 * @param     array    List of registered taxonomies.
		 * @param     int      The object ID.
		 * @return    array    Possibly-modified name(s) of the suppressed taxonomies.
		 */
		$suppressed_taxonomies = \apply_filters( 'replicast_suppress_object_taxonomies', array(), $taxonomies, $object['id'] );

		$prepared_data = array();
		foreach ( $taxonomies as $taxonomy_key => $taxonomy_key ) {

			if ( in_array( $taxonomy_key, array( Plugin::TAXONOMY_SITE ) ) ) {
				continue;
			}

			if ( in_array( $taxonomy_key, $suppressed_taxonomies ) ) {
				continue;
			}

			$prepared_data[ $taxonomy_key ] = $taxonomy_key;

		}

		// Get a hierarchical list of object terms
		$terms = static::get_object_terms_hierarchical( $object['id'], $prepared_data );

		/**
		 * Extend object terms.
		 *
		 * @since     1.0.0
		 * @param     array    Hierarchical list of object terms.
		 * @param     int      Object ID.
		 * @return    array    Possibly-modified object terms.
		 */
		return \apply_filters( 'replicast_get_object_term', $terms, $object['id'] );
	}

	/**
	 * Retrieves the terms associated with the given object in the supplied
	 * taxonomies, hierarchically structured.
	 *
	 * @see \wp_get_object_terms()
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     int      $object_id     The ID of the object to retrieve.
	 * @param     array    $taxonomies    The taxonomies to retrieve terms from.
	 * @return    array                   Hierarchical list of object terms
	 */
	private static function get_object_terms_hierarchical( $object_id, $taxonomies ) {

		// FIXME: we should soft cache this

		$hierarchical_terms = array();

		$terms = \wp_get_object_terms( $object_id, $taxonomies );

		if ( empty( $terms ) ) {
			return array();
		}

		foreach ( $terms as $term ) {

			if ( $term->parent > 0 ) {
				continue;
			}

			if ( in_array( $term->slug, array( 'uncategorized', 'untagged' ) ) ) {
				continue;
			}

			$hierarchical_terms[ $term->term_id ] = $term;
			$hierarchical_terms[ $term->term_id ]->children = static::get_child_terms( $term->term_id, $terms );
		}

		return $hierarchical_terms;
	}

	/**
	 * Retrieves a list of child terms.
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

			$children[ $term->term_id ] = $term;
			$children[ $term->term_id ]->children = static::get_child_terms( $term->term_id, $terms );
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

		/**
		 * Extend object media.
		 *
		 * @since     1.0.0
		 * @param     array    Object media.
		 * @param     int      Object ID.
		 * @return    array    Possibly-modified object media.
		 */
		$prepared_data = \apply_filters( 'replicast_get_object_media', array(), $object['id'] );

		// Get object featured media
		if ( ! empty( $object['featured_media'] ) ) {
			$prepared_data['featured_media'] = static::get_media( $object['featured_media'] );
		}

		return $prepared_data;
	}

	/**
	 * Prepares a media object.
	 *
	 * @since     1.0.0
	 * @param     int    $object_id    The object ID.
	 * @return    array                Prepared media object.
	 */
	public static function get_media( $object_id ) {

		/**
		 * Filter for suppressing image sizes.
		 *
		 * @since     1.0.0
		 * @param     array    Name(s) of the suppressed image sizes.
		 * @return    array    Possibly-modified name(s) of the suppressed image sizes.
		 */
		$suppressed_image_sizes = \apply_filters( 'replicast_suppress_image_sizes', array() );

		// Get metadata
		$metadata = \get_post_meta( $object_id, '_wp_attachment_metadata', true );

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

		return array(
			'id'                          => $object_id, // This object ID is going to be replaced by the remote ID
			'mime-type'                   => \get_post_mime_type( $object_id ),
			'metadata'                    => $metadata,
			Plugin::REPLICAST_OBJECT_INFO => \maybe_serialize( array(
				'object_id' => $object_id, // Save original object ID
				'permalink' => \get_attachment_link( $object_id ),
				'edit_link' => \get_edit_post_link( $object_id ),
				'rest_url'  => \rest_url( sprintf( '/wp/v2/media/%s', $object_id ) ),
			) ),
		);
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
		if ( ! empty( $values['term'] ) ) {
			static::update_object_term( $values['term'], $object );
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
		 * @param    string    The object meta type.
		 * @param    int       The object ID.
		 */
		\do_action( "replicast_update_object_{$meta_type}_meta", $meta, $meta_type, $object->ID );

	}

	/**
	 * Update object terms.
	 *
	 * @since     1.0.0
	 * @param     array     $terms     The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_term( $terms, $object ) {

		$prepared_ids = array();

		// Update terms
		foreach ( $terms as $term_data ) {

			// Check if taxonomy exists
			if ( ! \taxonomy_exists( $term_data['taxonomy'] ) ) {
				continue;
			}

			if ( $term_data['parent'] > 0 ) {
				continue;
			}

			// Get term
			$term = static::get_term( $term_data );

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

		foreach ( $prepared_ids as $taxonomy => $ids ) {
			\wp_set_object_terms(
				$object->ID,
				$ids,
				$taxonomy
			);
		}

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

		foreach ( $terms as $term_data ) {

			// Get term
			$term = static::get_term( $term_data, $parent_id );

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
	 * Get term object.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $term_data    The term data.
	 * @param     int      $parent_id    The parent term ID.
	 * @return    array                  An array containing, at least, the term_id and term_taxonomy_id.
	 */
	private static function get_term( $term_data, $parent_id = 0 ) {

		$term = \wp_insert_term( $term_data['name'], $term_data['taxonomy'], array(
			'description' => $term_data['description'],
			'parent'      => $parent_id,
		) );

		if ( \is_wp_error( $term ) ) {

			if ( ! \is_numeric( $term->get_error_data() ) ) {
				return array();
			}

			$term = \get_term_by( 'id', $term->get_error_data(), $term_data['taxonomy'], 'ARRAY_A' );
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

		// Update object featured media
		if ( ! empty( $media['featured_media'] ) ) {
			$attachment_id = static::update_media( $media['featured_media'] );

			// Assign featured media to post
			\set_post_thumbnail( $object->ID, $attachment_id );
		}

		/**
		 * Fires immediately after object media is updated.
		 *
		 * @since    1.0.0
		 * @param    array     The values of the field.
		 * @param    int       The object ID.
		 */
		\do_action( 'replicast_update_object_media', $media, $object->ID );

	}

	/**
	 * Updates a media object.
	 *
	 * @since     1.0.0
	 * @param     array    $image    The values of the field.
	 * @return    int                The media object ID.
	 */
	public static function update_media( $image ) {

		$image_id = ! empty( $image['id'] ) ? $image['id'] : '';

		// Create an attachment if no ID was given
		if ( empty( $image_id ) ) {

			$file = \esc_url( $image['metadata']['file'] );

			// Set attachment data
			$attachment = array(
				'post_mime_type' => $image['mime-type'],
				'post_title'     => \sanitize_file_name( basename( $file ) ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);

			// Create the attachment
			$image_id = \wp_insert_attachment( $attachment, $file );

			// Assign metadata to attachment
			\wp_update_attachment_metadata( $image_id, $image['metadata'] );

		}

		// Save remote object info
		\update_post_meta( $image_id, Plugin::REPLICAST_OBJECT_INFO, $image[ Plugin::REPLICAST_OBJECT_INFO ] );

		return $image_id;
	}

	/**
	 * Get object ID.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    string                     The object ID.
	 */
	public static function get_object_id( $object ) {

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
	 * Retrieve remote id from object.
	 *
	 * @since     1.0.0
	 * @param  [type] $object [description]
	 * @param     \Replicast\Client    $site           Site object.
	 * @return [type]            [description]
	 */
	public static function get_replicast_id( $object, $site ) {

		if ( ! is_numeric( $object ) ) {
			$object = \get_post( $object );
		}

		if ( ! empty( $replicast_info = static::get_replicast_info( $object ) ) ) {
			return $replicast_info[ $site->get_id() ]['id'];
		}

		return '';
	}

	/**
	 * Retrieve remote info from object.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    array                      The replicast info meta data.
	 */
	public static function get_replicast_info( $object ) {

		$replicast_info = \get_metadata(
			static::get_meta_type( $object ),
			static::get_object_id( $object ),
			Plugin::REPLICAST_REMOTE_IDS,
			true
		);

		if ( ! $replicast_info ) {
			return array();
		}

		if ( ! is_array( $replicast_info ) ) {
			$replicast_info = (array) $replicast_info;
		}

		return $replicast_info;
	}

	/**
	 * Update object with remote info.
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
	public static function update_replicast_info( $object, $site_id, $remote_data = null ) {

		// Get replicast object info
		$replicast_info = static::get_replicast_info( $object );

		// Save or delete the remote object info
		if ( $remote_data ) {
			$replicast_info[ $site_id ] = array(
				'id'     => static::get_object_id( $remote_data ),
				'status' => isset( $remote_data->status ) ? $remote_data->status : '',
			);
		}
		else {
			unset( $replicast_info[ $site_id ] );
		}

		return \update_metadata(
			static::get_meta_type( $object ),
			static::get_object_id( $object ),
			Plugin::REPLICAST_REMOTE_IDS,
			$replicast_info
		);
	}

}
