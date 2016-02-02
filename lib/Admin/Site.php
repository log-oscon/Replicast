<?php

/**
 * Site taxonomy
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Admin
 */

namespace Replicast\Admin;

/**
 * Site taxonomy.
 *
 * This class defines all code necessary to run during the custom taxonomy registration.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Admin
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Site {

	/**
	 * Plugin instance.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @var       \Replicast\Plugin    Plugin instance.
	 */
	private $plugin;

	/**
	 * Taxonomy name.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @var       string    Taxonomy name.
	 */
	private $name;

	/**
	 * Taxonomy object.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @var       object    Taxonomy object.
	 */
	private $taxonomy;

	/**
	 * Taxonomy meta fields.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @var       array    Taxonomy meta fields.
	 */
	private $fields = array();

	/**
	 * Name(s) of the supported object type(s).
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       string|array    The current supported object type(s).
	 */
	protected $object_types;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Plugin    $plugin    Plugin instance.
	 * @param    string               $name      Taxonomy name.
	 */
	public function __construct( $plugin, $name ) {
		$this->plugin = $plugin;
		$this->name   = $name;

		/**
		 * Filter the available object type(s) for \Replicast\Admin\Site.
		 *
		 * @see    https://codex.wordpress.org/Post_Type
		 * @see    https://codex.wordpress.org/Post_Types#Custom_Types
		 *
		 * @since    1.0.0
		 * @param    array|string    Name(s) of the object type(s) for \Replicast\Admin\Site.
		 */
		$this->object_types = \apply_filters( 'replicast_site_object_type', array(
			'post',
			'page',
			'attachment'
		) );

	}

	/**
	 * Register taxonomy meta fields.
	 *
	 * @since    1.0.0
	 */
	public function register_fields() {

		$this->fields = array(
			'site_url' => array(
				'name'          => 'site_url',
				'label'         => \__( 'Site URL', 'replicast' ),
				'type'          => 'url',
				'instructions'  => \__( 'The site\'s main address.', 'replicast' ),
				'default_value' => '',
				'placeholder'   => \__( 'http://example.com', 'replicast' )
			),
			'api_url' => array(
				'name'          => 'api_url',
				'label'         => \__( 'REST API URL', 'replicast' ),
				'type'          => 'text',
				'instructions'  => \__( 'The site\'s base REST API address.', 'replicast' ),
				'default_value' => '',
				'placeholder'   => \__( '/wp-json/', 'replicast' )
			),
			'api_key' => array(
				'name'          => 'api_key',
				'label'         => \__( 'REST API Key', 'replicast' ),
				'type'          => 'text',
				'instructions'  => \__( 'A REST API authentication key that allows posting privileges to the remote site.', 'replicast' ),
				'default_value' => '',
				'placeholder'   => ''
			),
			'api_secret' => array(
				'name'          => 'api_secret',
				'label'         => \__( 'REST API Secret', 'replicast' ),
				'type'          => 'text',
				'instructions'  => \__( 'A REST API authentication secret that is the counterpart to the REST API key.', 'replicast' ),
				'default_value' => '',
				'placeholder'   => ''
			),
		);

	}

	/**
	 * Get the taxonomy name.
	 *
	 * @since     1.0.0
	 * @return    string    Taxonomy name (slug).
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the taxonomy object.
	 *
	 * @since     1.0.0
	 * @return    object    Taxonomy object.
	 */
	public function get_taxonomy() {
		return $this->taxonomy;
	}

	/**
	 * Get the current supported object type(s).
	 *
	 * @since     1.0.0
	 * @return    array    Supported object type(s).
	 */
	public function get_object_types() {
		return $this->object_types;
	}

	/**
	 * Register a taxonomy to aggregate site objects.
	 *
	 * @since    1.0.0
	 */
	public function register() {

		$labels = array(
			'add_new_item'      => \__( 'Add New Site', 'replicast' ),
			'all_items'         => \__( 'All Sites', 'replicast' ),
			'edit_item'         => \__( 'Edit Site', 'replicast' ),
			'name'              => \__( 'Sites', 'replicast' ),
			'new_item_name'     => \__( 'New Site', 'replicast' ),
			'not_found'         => \__( 'No Sites found.', 'replicast' ),
			'parent_item'       => \__( 'Parent Site', 'replicast' ),
			'parent_item_colon' => \__( 'Parent Site:', 'replicast' ),
			'search_items'      => \__( 'Search Sites', 'replicast' ),
			'singular_name'     => \__( 'Site', 'replicast' ),
			'update_item'       => \__( 'Update Site', 'replicast' ),
			'view_item'         => \__( 'View Site', 'replicast' ),
		);

		$this->taxonomy = \register_taxonomy(
			$this->name,
			$this->get_object_types(),
			array(
				'label'              => \__( 'Sites', 'replicast' ),
				'labels'             => $labels,
				'description'        => '',
				'public'             => false,
				'show_ui'            => true,
				'show_in_nav_menus'  => false,
				'show_tagcloud'      => false,
				'show_in_quick_edit' => false,
				'show_admin_column'  => true,
				'hierarchical'       => true,
			)
		);

	}

	/**
	 * Output all the taxonomy fields on the add screen.
	 *
	 * @since    1.0.0
	 */
	public function add_fields() {
		foreach ( $this->fields as $field ) {
			$this->add_form_field( $field );
		}
	}

	/**
	 * Output a taxonomy field on the add screen.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $atts    Field attributes.
	 */
	private function add_form_field( $atts ) {

		\wp_nonce_field( basename( __FILE__ ), $this->get_nonce_key( $atts['name'] ) );

		printf(
			'<div class="form-field term-%s-wrap">',
			$atts['name']
		);
		printf(
			'<label for="tag-%s">%s *</label>',
			$atts['name'],
			$atts['label']
		);
		printf(
			'<input id="tag-%1$s" name="%1$s" type="%2$s" value="%3$s" placeholder="%4$s">',
			$atts['name'],
			$atts['type'],
			$atts['default_value'],
			$atts['placeholder']
		);
		printf(
			'<p>%s</p>',
			$atts['instructions']
		);
		echo '</div>';

	}

	/**
	 * Output all the taxonomy fields on the edit screen.
	 *
	 * @since    1.0.0
	 * @param    \WP_Term    $term    Term object.
	 */
	public function edit_fields( $term ) {
		foreach ( $this->fields as $field ) {
			$this->edit_form_field( $term, $field );
		}
	}

	/**
	 * Output a taxonomy field on the edit screen.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $atts    Field attributes.
	 */
	private function edit_form_field( $term, $atts ) {

		$value = \get_term_meta( $term->term_id, $atts['name'], true );

		\wp_nonce_field( basename( __FILE__ ), $this->get_nonce_key( $atts['name'] ) );

		printf(
			'<tr class="form-field term-%s-wrap">',
			$atts['name']
		);
		printf(
			'<th scope="row"><label for="tag-%s">%s *</label></th>',
			$atts['name'],
			$atts['label']
		);
		echo '<td>';
		printf(
			'<input id="tag-%1$s" name="%1$s" type="%2$s" value="%3$s" placeholder="%4$s">',
			$atts['name'],
			$atts['type'],
			isset( $value ) ? \esc_attr( $value ) : $atts['default_value'],
			$atts['placeholder']
		);
		printf(
			'<p>%s</p>',
			$atts['instructions']
		);
		echo '</td>';
		echo '</tr>';

	}

	/**
	 * Update all the taxonomy field values.
	 *
	 * @since    1.0.0
	 * @param    int    $term_id    Term ID.
	 */
	public function update_fields( $term_id ) {
		foreach ( $this->fields as $field ) {

			$name = $field['name'];

			// If the field in nowhere to be found, jump to the next.
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}

			$this->update_form_field( $term_id, $name );
		}
	}

	/**
	 * Update a taxonomy field value.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     int       $term_id    Term ID.
	 * @param     string    $name       Field name.
	 */
	private function update_form_field( $term_id, $name ) {

		if ( ! isset( $_POST[ $this->get_nonce_key( $name ) ] ) ) {
			return;
		}

		if ( ! \wp_verify_nonce( $_POST[ $this->get_nonce_key( $name ) ], basename( __FILE__ ) ) ) {
			return;
		}

		$old_value = \get_term_meta( $term_id, $name, true );
		$new_value = \sanitize_text_field( $_POST[ $name ] );

		if ( $old_value && $new_value === '' ) {
			\delete_term_meta( $term_id, $name );
		} elseif ( $old_value !== $new_value ) {
			\update_term_meta( $term_id, $name, $new_value );
		}

	}

	/**
	 * Clear site cache after it's been deleted.
	 *
	 * @since    1.0.0
	 * @param    int    $term_id    Term taxonomy ID.
	 */
	public function on_deleted_term( $term_id ) {
		\wp_cache_delete( $term_id, 'replicast_sites' );
	}

	/**
	 * Get the nonce key for a taxonomy field.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     string    $name    Field name.
	 * @return    string             Nonce key identifier.
	 */
	private function get_nonce_key( $name ) {
		return 'replicast_' . $name . '_nonce';
	}

	/**
	 * Registers a new field on a set of existing object types.
	 *
	 * @since    1.0.0
	 */
	public function register_rest_fields() {

		foreach ( $this->get_object_types() as $object_type ) {
			\register_rest_field(
				$object_type,
				'replicast',
				array(
					'get_callback'    => array( $this, 'get_rest_fields' ),
					'update_callback' => array( $this, 'update_rest_fields' ),
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
	 * @return    mixed                              Custom fields.
	 */
	public function get_rest_fields( $object, $field_name, $request ) {
		return array(
			'meta' => $this->get_object_meta( $object ),
		);
	}

	/**
	 * Get custom fields for a post type.
	 *
	 * @since     1.0.0
	 * @param     array     $value     The value of the field.
	 * @param     object    $object    The object from the response.
	 * @return    mixed                Returns true on success and false on failure.
	 */
	public function update_rest_fields( $value, $object ) {

		// Update meta
		if ( ! empty( $value['meta'] ) ) {
			$this->update_object_meta( $value['meta'], $object );
		}

		return;
	}

	/**
	 * Retrieve metadata for the specified object.
	 *
	 * @access    private
	 * @since     1.0.0
	 * @param     array    $object    Details of current content object.
	 * @return    mixed               Single metadata value, or array of values. If the $meta_type
	 *                                or $object_id parameters are invalid, false is returned.
	 *                                If the meta value isn't set, an empty string or array is returned,
	 *                                respectively.
	 */
	private function get_object_meta( $object ) {

		$prepared_metadata = array();
		$metadata          = \get_metadata( $object['type'], $object['id'] );

		if ( ! $metadata ) {
			return $prepared_metadata;
		}

		if ( ! is_array( $metadata ) ) {
			$metadata = (array) $metadata;
		}

		foreach ( $metadata as $meta_key => $meta_value ) {

			if ( \is_protected_meta( $meta_key ) ) {
				continue;
			}

			$prepared_metadata[ $meta_key ] = $meta_value;
		}

		return $prepared_metadata;
	}

	/**
	 * Update metadata for the specified object.
	 *
	 * @access    private
	 * @since     1.0.0
	 * @param     array    $object    Details of current content object.
	 */
	private function update_object_meta( $values, $object ) {

		// TODO: should this be returning any kind of success/failure information?

		foreach ( $values as $meta_key => $meta_value ) {

			$meta_value = array_map( 'sanitize_text_field', $meta_value );

			\update_metadata(
				$object->post_type,
				$object->ID,
				\sanitize_key( $meta_key ),
				\maybe_serialize( $meta_value )
			);

		}

	}

}
