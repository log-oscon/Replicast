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

use Replicast\Admin\Site;

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

		foreach ( Site::get_post_types() as $post_type ) {
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
	 * Get custom fields for a object type.
	 *
	 * @since     1.0.0
	 * @param     array               $object        Details of current content object.
	 * @param     string              $field_name    Name of field.
	 * @param     \WP_REST_Request    $request       Current \WP_REST_Request request.
	 * @return    array                              Custom fields.
	 */
	public static function get_rest_fields( $object, $field_name, $request ) {
		return array(
			'meta' => static::get_object_meta( $object, $request->get_route() ),
		);
	}

	/**
	 * Retrieve metadata for the specified object.
	 *
	 * @since     1.0.0
	 * @param     array    $object    Details of current content object.
	 * @param     string   $route     Object REST route.
	 * @return    array               Object metadata.
	 */
	public static function get_object_meta( $object, $route ) {
		return static::get_metadata( $object, $route );
	}

	/**
	 * Get custom fields for a post type.
	 *
	 * @since     1.0.0
	 * @param     array     $value     The value of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_rest_fields( $value, $object ) {

		// Update meta
		if ( ! empty( $value['meta'] ) ) {
			static::update_object_meta( $value['meta'], $object );
		}

	}

	/**
	 * Update metadata for the specified object.
	 *
	 * @since     1.0.0
	 * @param     array     $value     The value of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_meta( $value, $object ) {

		// TODO: should this be returning any kind of success/failure information?

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		/**
		 * Filter for suppressing specific meta keys from update.
		 *
		 * @since     1.0.0
		 * @param     array                Name(s) of the suppressed meta keys.
		 * @param     array     $value     The value of the field.
		 * @param     object    $object    The object from the response.
		 * @return    array                Possibly-modified name(s) of the suppressed meta keys.
		 */
		$blacklist = \apply_filters( 'suppress_object_meta_from_update', array(), $value, $object );

		// Update metadata
		foreach ( $value as $meta_key => $meta_values ) {

			if ( in_array( $meta_key, $blacklist ) ) {
				continue;
			}

			\delete_metadata( $meta_type, $object->ID, $meta_key );
			foreach ( $meta_values as $meta_value ) {
				\add_metadata( $meta_type, $object->ID, $meta_key, \maybe_unserialize( $meta_value ) );
			}

		}

	}

	/**
	 * Retrieve metadata for the specified object.
	 *
	 * @access    private
	 * @since     1.0.0
	 * @param     array    $object    Details of current content object.
	 * @param     string   $route     Object REST route.
	 * @return    array               Object metadata.
	 */
	private static function get_metadata( $object, $route ) {

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		/**
		 * Filter for exposing specific protected meta keys.
		 *
		 * @since     1.0.0
		 * @param     array               Name(s) of the exposed meta keys.
		 * @param     array    $object    Details of current content object.
		 * @return    array               Possibly-modified name(s) of the exposed meta keys.
		 */
		$whitelist = \apply_filters( 'replicast_expose_object_protected_meta', array(), $object );

		$metadata = \get_metadata( $meta_type, $object['id'] );

		if ( ! $metadata ) {
			return array();
		}

		if ( ! is_array( $metadata ) ) {
			$metadata = (array) $metadata;
		}

		$prepared_metadata = array();
		foreach ( $metadata as $meta_key => $meta_value ) {

			if ( \is_protected_meta( $meta_key ) && ! in_array( $meta_key, $whitelist ) ) {
				continue;
			}

			$prepared_metadata[ $meta_key ] = $meta_value;

		}

		// Add object REST route to meta
		$prepared_metadata[ Plugin::REPLICAST_REMOTE ] = array( \maybe_serialize( array(
			'ID'        => $object['id'],
			'edit_link' => \get_edit_post_link( $object['id'] ),
			'rest_url'  => \rest_url( $route ),
		) ) );

		return $prepared_metadata;
	}

	/**
	 * Get object type.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The current object.
	 * @return    string                     The object type.
	 */
	public static function get_object_type( $object ) {

		if ( static::is_term( $object ) ) {
			return $object->taxonomy;
		}

		return $object->post_type;
	}

	/**
	 * Get meta type based on the object class or array data.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The current object.
	 * @return    string                     Possible values: user, comment, post, meta
	 */
	public static function get_meta_type( $object ) {

		// TODO: Support user and comment object types with an array structure

		if ( static::is_term( $object ) ) {
			return 'term';
		}

		if ( static::is_comment( $object ) ) {
			return 'comment';
		}

		if ( static::is_user( $object ) ) {
			return 'user';
		}

		return 'post';
	}

	/**
	 * Check if current object is a post/page.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The current object.
	 * @return    bool                       True if it's a post/page. False, otherwise.
	 */
	public static function is_post( $object ) {
		return $object instanceof \WP_Post;
	}

	/**
	 * Check if current object is a term.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The current object.
	 * @return    bool                       True if it's a term. False, otherwise.
	 */
	public static function is_term( $object ) {
		return $object instanceof \WP_Term || ( is_array( $object ) && isset( $object['taxonomy'] ) );
	}

	/**
	 * Check if current object is a comment.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The current object.
	 * @return    bool                       True if it's a comment. False, otherwise.
	 */
	public static function is_comment( $object ) {
		return $object instanceof \WP_Comment;
	}

	/**
	 * Check if current object is an user.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The current object.
	 * @return    bool                       True if it's an user. False, otherwise.
	 */
	public static function is_user( $object ) {
		return $object instanceof \WP_User;
	}

}
