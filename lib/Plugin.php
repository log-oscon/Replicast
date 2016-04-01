<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the dashboard.
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, dashboard-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Plugin {

	/**
	 * The remote site taxonomy identifier.
	 *
	 * @since    1.0.0
	 * @var      \Replicast\Admin\SiteAdmin
	 */
	const TAXONOMY_SITE = 'remote_site';

	/**
	 * Identifies the meta variable that saves the information
	 * regarding the "to where" the object was replicated.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const REPLICAST_REMOTE_IDS = '_replicast_remote_ids';

	/**
	 * Identifies the meta variable that is sent to the remote site and
	 * that contains information regarding the remote object.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const REPLICAST_OBJECT_INFO = '_replicast_object_info';

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       \Replicast\Loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       string    The string used to uniquely identify this plugin.
	 */
	protected $name;

	/**
	 * The current version of the plugin.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       string    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string    $name       Plugin name.
	 * @param    string    $version    Plugin version.
	 */
	public function __construct( $name, $version ) {
		$this->name    = $name;
		$this->version = $version;
		$this->loader  = new Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since     1.0.0
	 * @access    private
	 */
	private function set_locale() {

		$plugin_i18n = new I18n();
		$plugin_i18n->set_domain( $this->get_name() );
		$plugin_i18n->load_plugin_textdomain();

	}

	/**
	 * Register all of the hooks related to the dashboard functionality.
	 *
	 * @since     1.0.0
	 * @access    private
	 */
	private function define_admin_hooks() {

		$admin = new Admin( $this );

		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_notices',         $admin, 'display_admin_notices' );

	}

	/**
	 * Register all of the hooks related to the \Admin\Post functionality.
	 *
	 * @since     1.0.0
	 * @access    private
	 */
	private function define_admin_post_hooks() {

		$post = new Admin\PostAdmin( $this );

		$this->loader->add_action( 'save_post',          $post, 'on_save_post', 10, 3 );
		$this->loader->add_action( 'attachment_updated', $post, 'on_save_post', 10, 3 );
		$this->loader->add_action( 'trashed_post',       $post, 'on_trash_post' );
		$this->loader->add_action( 'before_delete_post', $post, 'on_delete_post' );

		// Admin UI - Posts
		$this->loader->add_action( 'manage_posts_custom_column',   $post, 'manage_custom_column', 10, 2 );
		$this->loader->add_action( 'manage_pages_custom_column',   $post, 'manage_custom_column', 10, 2 );
		$this->loader->add_filter( 'manage_pages_columns',         $post, 'manage_columns', 10, 2 );
		$this->loader->add_filter( 'manage_posts_columns',         $post, 'manage_columns', 10, 2 );
		$this->loader->add_filter( 'user_has_cap',                 $post, 'hide_edit_link', 10, 4 );
		$this->loader->add_filter( 'post_row_actions',             $post, 'hide_row_actions', 99, 2 );
		$this->loader->add_filter( 'page_row_actions',             $post, 'hide_row_actions', 99, 2 );
		$this->loader->add_filter( 'wp_get_attachment_image_src',  $post, 'get_attachment_image_src', 10, 3 );
		$this->loader->add_filter( 'wp_get_attachment_url',        $post, 'get_attachment_url', 10, 2 );
		$this->loader->add_filter( 'wp_prepare_attachment_for_js', $post, 'prepare_attachment_for_js', 10, 3 );

		// Admin UI - Featured Image
		$this->loader->add_filter( 'admin_post_thumbnail_html',   $post, 'update_post_thumbnail', 10, 2 );
		$this->loader->add_filter( 'delete_post_meta',            $post, 'delete_post_thumbnail', 10, 3 );

		// Admin UI - Media Library
		$this->loader->add_filter( 'media_row_actions',           $post, 'hide_row_actions', 99, 2 );
		$this->loader->add_filter( 'ajax_query_attachments_args', $post, 'hide_attachments_on_grid_mode' );
		$this->loader->add_action( 'pre_get_posts',               $post, 'hide_attachments_on_list_mode' );

	}

	/**
	 * Register all of the hooks related to the \Admin\Site functionality.
	 *
	 * @since     1.0.0
	 * @access    private
	 */
	private function define_admin_site_hooks() {

		$site = new Admin\SiteAdmin( $this, static::TAXONOMY_SITE );

		$this->loader->add_action( 'init',                                      $site, 'register', 99 );
		$this->loader->add_action( 'init',                                      $site, 'register_fields' );
		$this->loader->add_action( static::TAXONOMY_SITE . '_add_form_fields',  $site, 'add_fields' );
		$this->loader->add_action( static::TAXONOMY_SITE . '_edit_form_fields', $site, 'edit_fields' );
		$this->loader->add_action( 'created_' . static::TAXONOMY_SITE,          $site, 'update_fields' );
		$this->loader->add_action( 'edited_' . static::TAXONOMY_SITE,           $site, 'update_fields' );
		$this->loader->add_action( 'delete_' . static::TAXONOMY_SITE,           $site, 'on_deleted_term' );

	}

	/**
	 * Register all of the hooks related to the API functionality.
	 *
	 * @since     1.0.0
	 * @access    private
	 */
	private function define_api_hooks() {

		$api = new API( $this );

		$this->loader->add_action( 'rest_api_init', $api, 'register_rest_fields' );

	}

	/**
	 * Register all of the hooks related to the ACF functionality.
	 *
	 * @since     1.0.0
	 * @access    private
	 */
	private function define_acf_hooks() {

		if ( ! class_exists( 'acf' ) ) {
			return;
		}

		$acf = new ACF( $this );

		$this->loader->add_action( 'init', $acf, 'register', 99 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * Load the dependencies, define the locale, and set the hooks for the Dashboard and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function run() {

		$this->set_locale();

		$this->define_api_hooks();
		$this->define_admin_hooks();
		$this->define_admin_post_hooks();
		$this->define_admin_site_hooks();

		$this->define_acf_hooks();

		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    \Replicast\Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
