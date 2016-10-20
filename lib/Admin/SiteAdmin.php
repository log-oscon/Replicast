<?php
/**
 * The dashboard-specific functionality of the `remote_site` taxonomy
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Admin
 */

namespace Replicast\Admin;

use Replicast\Plugin;

/**
 * The dashboard-specific functionality of the `remote_site` taxonomy.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Admin
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class SiteAdmin {

	/**
	 * Plugin instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    \Replicast\Plugin
	 */
	private $plugin;

	/**
	 * Taxonomy name.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $name;

	/**
	 * Taxonomy object.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    object
	 */
	private $taxonomy;

	/**
	 * Taxonomy meta fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $fields = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \Replicast\Plugin $plugin Plugin instance.
	 * @param string            $name Taxonomy name.
	 */
	public function __construct( $plugin, $name ) {
		$this->plugin = $plugin;
		$this->name   = $name;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {

		$this->register_taxonomy();
		$this->register_fields();

		\add_action( $this->get_name() . '_add_form_fields',  array( $this, 'add_fields' ) );
		\add_action( $this->get_name() . '_edit_form_fields', array( $this, 'edit_fields' ) );

		\add_action( 'created_' . $this->get_name(), array( $this, 'update_fields' ) );
		\add_action( 'edited_' . $this->get_name(),  array( $this, 'update_fields' ) );
		\add_action( 'delete_' . $this->get_name(),  array( $this, 'on_deleted_term' ) );

		\add_action( 'restrict_manage_posts', array( $this, 'get_filter_dropdown' ) );
		\add_action( 'pre_get_posts',         array( $this, 'filter_posts' ) );
	}

	/**
	 * Get the taxonomy name.
	 *
	 * @since  1.0.0
	 * @return string Taxonomy name (slug).
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the taxonomy filter key.
	 *
	 * @since  1.0.0
	 * @return string Taxonomy filter key.
	 */
	public function get_filter_key() {
		return 'replicast_' . $this->name . '_filter';
	}

	/**
	 * Get the taxonomy object.
	 *
	 * @since  1.0.0
	 * @return object Taxonomy object.
	 */
	public function get_taxonomy() {
		return $this->taxonomy;
	}

	/**
	 * Get the current supported post type(s).
	 *
	 * @since  1.0.0
	 * @return array Supported post type(s).
	 */
	public static function get_post_types() {

		/**
		 * Filter the available post type(s).
		 *
		 * @see https://codex.wordpress.org/Post_Type
		 * @see https://codex.wordpress.org/Post_Types#Custom_Types
		 *
		 * @since 1.0.0
		 * @param array|string Name(s) of the post type(s).
		 */
		return \apply_filters( 'replicast_site_post_types', \get_post_types( array(
			'public' => true,
		) ) );
	}

	/**
	 * Get the current supported post status(es).
	 *
	 * @since  1.0.0
	 * @return array Supported post status(es).
	 */
	public static function get_post_status() {

		/**
		 * Filter the available post status(es).
		 *
		 * @see https://codex.wordpress.org/Post_Status
		 * @see https://codex.wordpress.org/Post_Status#Custom_Status
		 *
		 * @since 1.0.0
		 * @param array|string Name(s) of the post status(es).
		 */
		return \apply_filters( 'replicast_site_post_status', array_keys( \get_post_stati() ) );
	}

	/**
	 * Register a taxonomy to aggregate site objects.
	 *
	 * @since 1.0.0
	 */
	public function register_taxonomy() {

		/**
		 * Filter for showing the taxonomy managing UI in the admin.
		 *
		 * @since  1.0.0
		 * @param  bool True if the taxonomy managing UI is visible in the admin. False, otherwise.
		 * @return bool True if the taxonomy managing UI is visible in the admin. False, otherwise.
		 */
		$show_ui = \apply_filters( 'replicast_site_show_ui', true );

		/**
		 * Filter for making the taxonomy available for selection in navigation menus.
		 *
		 * @since  1.0.0
		 * @param  bool True if the taxonomy is available for selection in navigation menus. False, otherwise.
		 * @return bool True if the taxonomy is available for selection in navigation menus. False, otherwise.
		 */
		$show_in_nav_menus = \apply_filters( 'replicast_site_show_in_nav_menus', false );

		/**
		 * Filter for showing the taxonomy columns on associated post-types.
		 *
		 * @since  1.0.0
		 * @param  bool True if the taxonomy columns are visible on associated post-types. False, otherwise.
		 * @return bool True if the taxonomy columns are visible on associated post-types. False, otherwise.
		 */
		$show_admin_column = \apply_filters( 'replicast_site_show_admin_column', true );

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

		$capabilities = array(
			'manage_terms' => 'manage_sites',
			'edit_terms'   => 'manage_sites',
			'delete_terms' => 'manage_sites',
			'assign_terms' => 'manage_sites',
		);

		$this->taxonomy = \register_taxonomy(
			$this->name,
			static::get_post_types(),
			array(
				'label'              => \__( 'Sites', 'replicast' ),
				'labels'             => $labels,
				'description'        => '',
				'public'             => false,
				'show_ui'            => $show_ui,
				'show_in_nav_menus'  => $show_in_nav_menus,
				'show_tagcloud'      => false,
				'show_in_quick_edit' => false,
				'show_admin_column'  => $show_admin_column,
				'hierarchical'       => true,
				'capabilities'       => $capabilities,
			)
		);
	}

	/**
	 * Register taxonomy meta fields.
	 *
	 * @since 1.0.0
	 */
	public function register_fields() {
		$this->fields = array(
			'api_url' => array(
				'name'          => 'api_url',
				'label'         => \__( 'REST API URL', 'replicast' ),
				'type'          => 'text',
				'instructions'  => \__( 'The remote REST API address.', 'replicast' ),
				'default_value' => '',
				'placeholder'   => \__( 'http://example.com/wp-json/wp/v2/', 'replicast' ),
			),
			'api_key' => array(
				'name'          => 'api_key',
				'label'         => \__( 'REST API Key', 'replicast' ),
				'type'          => 'text',
				'instructions'  => \__( 'A REST API authentication key that allows posting privileges to the remote site.', 'replicast' ),
				'default_value' => '',
				'placeholder'   => '',
			),
			'api_secret' => array(
				'name'          => 'api_secret',
				'label'         => \__( 'REST API Secret', 'replicast' ),
				'type'          => 'text',
				'instructions'  => \__( 'A REST API authentication secret that is the counterpart to the REST API key.', 'replicast' ),
				'default_value' => '',
				'placeholder'   => '',
			),
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
	 * Output all the taxonomy fields on the edit screen.
	 *
	 * @since 1.0.0
	 * @param \WP_Term $term Term object.
	 */
	public function edit_fields( $term ) {
		foreach ( $this->fields as $field ) {
			$this->edit_form_field( $term, $field );
		}
	}

	/**
	 * Update all the taxonomy field values.
	 *
	 * @since 1.0.0
	 * @param int $term_id Term ID.
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
	 * Clear site cache after it's been deleted.
	 *
	 * @since 1.0.0
	 * @param int $term_id Term taxonomy ID.
	 */
	public function on_deleted_term( $term_id ) {
		\wp_cache_delete( $term_id, 'replicast_sites' );
	}

	/**
	 * Generates a taxonomy dropdown filter for the supported post type(s).
	 *
	 * @since 1.0.0
	 */
	public function get_filter_dropdown() {
		global $post_type;

		if ( ! in_array( $post_type, static::get_post_types() ) ) {
			return;
		}

		\wp_dropdown_categories( array(
			'show_option_all' => \__( 'Show All Sites', 'replicast' ),
			'name'            => $this->get_filter_key(),
			'taxonomy'        => $this->get_name(),
			'selected'        => isset( $_GET[ $this->get_filter_key() ] ) ? \sanitize_text_field( $_GET[ $this->get_filter_key() ] ) : 0,
			'hide_empty'      => 0,
			'hide_if_empty'   => 1,
		) );
	}

	/**
	 * Filter the supported post type(s).
	 *
	 * @since 1.0.0
	 * @param \WP_Query $query \WP_Query object.
	 */
	public function filter_posts( $query ) {
		global $post_type, $pagenow;

		if ( ! \is_admin() ) {
			return;
		}

		// If we aren't currently on the edit screen, bail.
		if ( $pagenow !== 'edit.php' ) {
			return;
		}

		if ( ! in_array( $post_type, static::get_post_types() ) ) {
			return;
		}

		$tax_query = array();

		// Add selected filter to query.
		if ( ! empty( $_GET[ $this->get_filter_key() ] ) ) {
			$tax_query[] = array(
				'taxonomy' => $this->get_name(),
				'field'    => 'term_id',
				'terms'    => array( \sanitize_text_field( $_GET[ $this->get_filter_key() ] ) )
			);
		}

		if ( count( $tax_query ) > 0 ) {
			$tax_query['relation'] = 'AND';
			$query->query_vars['tax_query'] = $tax_query;
		}
	}

	/**
	 * Get the nonce key for a taxonomy field.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $name Field name.
	 * @return string       Nonce key identifier.
	 */
	private function get_nonce_key( $name ) {
		return 'replicast_site_admin_' . $name . '_nonce';
	}

	/**
	 * Output a taxonomy field on the add screen.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param  array $atts Field attributes.
	 */
	private function add_form_field( $atts ) {

		\wp_nonce_field( basename( __FILE__ ), $this->get_nonce_key( $atts['name'] ) );

		printf(
			'<div class="form-field term-%s-wrap">',
			\esc_attr( $atts['name'] )
		);
		printf(
			'<label for="tag-%s">%s *</label>',
			\esc_attr( $atts['name'] ),
			\esc_html( $atts['label'] )
		);
		printf(
			'<input id="tag-%1$s" name="%1$s" type="%2$s" value="%3$s" placeholder="%4$s">',
			\esc_attr( $atts['name'] ),
			\esc_attr( $atts['type'] ),
			\sanitize_text_field( $atts['default_value'] ),
			\esc_attr( $atts['placeholder'] )
		);
		printf(
			'<p>%s</p>',
			\esc_html( $atts['instructions'] )
		);
		echo '</div>';
	}

	/**
	 * Output a taxonomy field on the edit screen.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  \WP_Term $term Term object.
	 * @param  array    $atts Field attributes.
	 */
	private function edit_form_field( $term, $atts ) {

		$value = \get_term_meta( $term->term_id, $atts['name'], true );

		\wp_nonce_field( basename( __FILE__ ), $this->get_nonce_key( $atts['name'] ) );

		printf(
			'<tr class="form-field term-%s-wrap">',
			\esc_attr( $atts['name'] )
		);
		printf(
			'<th scope="row"><label for="tag-%s">%s</label></th>',
			\esc_attr( $atts['name'] ),
			\esc_html( $atts['label'] )
		);
		echo '<td>';
		printf(
			'<input id="tag-%1$s" name="%1$s" type="%2$s" value="%3$s" placeholder="%4$s">',
			\esc_attr( $atts['name'] ),
			\esc_attr( $atts['type'] ),
			\sanitize_text_field( isset( $value ) ? $value : $atts['default_value'] ),
			\esc_attr( $atts['placeholder'] )
		);
		printf(
			'<p>%s</p>',
			\esc_html( $atts['instructions'] )
		);
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Update a taxonomy field value.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int    $term_id Term ID.
	 * @param  string $name    Field name.
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
}
