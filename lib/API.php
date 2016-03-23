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
			'meta'           => static::get_object_meta( $object, $request ),
			'term'           => static::get_object_term( $object, $request ),
			'featured_media' => static::get_object_featured_media( $object, $request ),
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
		$whitelist = \apply_filters( 'replicast_expose_object_protected_meta', array(), $meta_type, $object['id'] );

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

			if ( \is_protected_meta( $meta_key ) && ! in_array( $meta_key, $whitelist ) ) {
				continue;
			}

			$prepared_data[ $meta_key ] = $meta_value;
		}

		/**
		 * Filter the obtained object meta.
		 *
		 * @since     1.0.0
		 * @param     array     Object meta.
		 * @param     string    The object meta type.
		 * @param     int       Object ID.
		 * @return    array     Possibly-modified object meta.
		 */
		$prepared_data = \apply_filters( "replicast_get_object_{$meta_type}_meta", $prepared_data, $meta_type, $object['id'] );

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
		$blacklist = \apply_filters( 'replicast_suppress_object_taxonomies', array(), $taxonomies, $object['id'] );

		$prepared_data = array();
		foreach ( $taxonomies as $taxonomy_key => $taxonomy_key ) {

			if ( in_array( $taxonomy_key, array( Plugin::TAXONOMY_SITE ) ) ) {
				continue;
			}

			if ( in_array( $taxonomy_key, $blacklist ) ) {
				continue;
			}

			$prepared_data[ $taxonomy_key ] = $taxonomy_key;

		}

		// Get a hierarchical list of object terms
		// FIXME: we should soft cache this
		$terms = static::get_object_terms_hierarchical( $object['id'], $prepared_data );

		/**
		 * Filter the obtained object terms.
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

		$hierarchical_terms = array();

		// FIXME: we should soft cache this
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
	 * Retrieve object featured media.
	 *
	 * @since     1.0.0
	 * @param     array               $object     Details of current content object.
	 * @param     \WP_REST_Request    $request    Current \WP_REST_Request request.
	 * @return    array                           Object featured media.
	 */
	public static function get_object_featured_media( $object, $request ) {

		if ( empty( $object['featured_media'] ) ) {
			return array();
		}

		$attachment_id = $object['featured_media'];
		$prepared_data = array();

		// Get image size information
		foreach ( static::get_image_sizes() as $size => $value ) {
			$prepared_data['sizes'][ $size ] = \wp_get_attachment_image_src( $attachment_id, $size );
		}

		// Get image metadata
		$attachment_metadata = \get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		if ( ! empty( $attachment_metadata['image_meta'] ) ) {
			$prepared_data['image_meta'] = $attachment_metadata['image_meta'];
		}

		// Get image filename
		$filename = basename( \get_attached_file( $attachment_id ) );
		if ( empty( $filename ) ) {
			$filename = \sanitize_title_with_dashes( $object['title']['rendered'] );
		}

		return array_merge(
			array(
				'id'        => $attachment_id,
				'edit_link' => \get_edit_post_link( $attachment_id ),
				'rest_url'  => \rest_url( sprintf( '/wp/v2/media/%s', $attachment_id ) ),
				'filename'  => $filename,
			),
			$prepared_data
		);
	}

	/**
	 * Get size information for all currently-registered image sizes.
	 *
	 * @global    $_wp_additional_image_sizes
	 * @uses      \get_intermediate_image_sizes()
	 *
	 * @since     1.0.0
	 * @access    private
	 * @return    array    Data for all currently-registered image sizes.
	 */
	private static function get_image_sizes() {

		global $_wp_additional_image_sizes;

		$sizes = array();
		foreach ( \get_intermediate_image_sizes() as $_size ) {

			if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {

				$sizes[ $_size ]['width']  = \get_option( "{$_size}_size_w" );
				$sizes[ $_size ]['height'] = \get_option( "{$_size}_size_h" );
				$sizes[ $_size ]['crop']   = (bool) \get_option( "{$_size}_crop" );

			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {

				$sizes[ $_size ] = array(
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
				);

			}

		}

		return $sizes;
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

		// Update featured media
		if ( ! empty( $values['featured_media'] ) ) {
			static::update_object_featured_media( $values['featured_media'], $object );
		}

	}

	/**
	 * Update object meta.
	 *
	 * @since     1.0.0
	 * @param     array     $values    The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_meta( $values, $object ) {

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		/**
		 * Filter for suppressing specific meta keys from update.
		 *
		 * @since     1.0.0
		 * @param     array     Name(s) of the suppressed meta keys.
		 * @param     array     The values of the field.
		 * @param     string    The object meta type.
		 * @param     int       The object ID.
		 * @return    array     Possibly-modified name(s) of the suppressed meta keys.
		 */
		$blacklist = \apply_filters( 'replicast_suppress_object_meta_from_update', array(), $values, $meta_type, $object->ID );

		// Update metadata
		foreach ( $values as $meta_key => $meta_values ) {

			if ( in_array( $meta_key, $blacklist ) ) {
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
		\do_action( "replicast_update_object_{$meta_type}_meta", $values, $meta_type, $object->ID );

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

		if ( ! empty( $prepared_ids ) ) {
			foreach ( $prepared_ids as $taxonomy => $ids ) {
				\wp_set_object_terms(
					$object->ID,
					$ids,
					$taxonomy
				);
			}
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
	 * Update object featured media.
	 *
	 * @since     1.0.0
	 * @param     array     $values    The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_featured_media( $values, $object ) {

		$attachment_id = ! empty( $values['id'] ) ? $values['id'] : '';

		// Create an attachment if no ID was given
		if ( empty( $attachment_id ) ) {

			$filename   = $values['filename'];
			$upload_dir = \wp_upload_dir();

			// Create a transparent 1x1 gif
			$image = hex2bin( '47494638396101000100900000ff000000000021f90405100000002c00000000010001000002020401003b' );

			// Check folder permission and define file location
			if( \wp_mkdir_p( $upload_dir['path'] ) ) {
				$file = implode( '/', array( $upload_dir['path'], $filename ) );
			} else {
				$file = implode( '/', array( $upload_dir['basedir'], $filename ) );
			}

			// Create the image  file on the server
			file_put_contents( $file, $image );

			// Check image file type
			$filetype = \wp_check_filetype( $filename, null );

			// Set attachment data
			$attachment = array(
				'post_mime_type' => $filetype['type'],
				'post_title'     => \sanitize_file_name( $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);

			// Create the attachment
			$attachment_id = \wp_insert_attachment( $attachment, $file, $object->ID );

			// Assign metadata to attachment
			\wp_update_attachment_metadata( $attachment_id, $values['image_meta'] );

		}

		// Assign featured media to post
		\set_post_thumbnail( $object->ID, $attachment_id );

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
		} elseif ( isset( $object->ID ) ) {
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
	 * Retrieve replicast info from object.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    array                      The replicast info meta data.
	 */
	public static function get_replicast_info( $object ) {

		$replicast_info = \get_metadata( static::get_meta_type( $object ), static::get_object_id( $object ), Plugin::REPLICAST_REMOTE_IDS, true );

		if ( ! $replicast_info ) {
			return array();
		}

		if ( ! is_array( $replicast_info ) ) {
			$replicast_info = (array) $replicast_info;
		}

		return $replicast_info;
	}

	/**
	 * Update object with replication info.
	 *
	 * This replication info consists in a pair <site_id, remote_object_id>.
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
