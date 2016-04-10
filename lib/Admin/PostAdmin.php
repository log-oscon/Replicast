<?php

/**
 * The dashboard-specific functionality of the `post` taxonomy
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Admin
 */

namespace Replicast\Admin;

use Replicast\Admin;
use Replicast\API;
use Replicast\Handler\PostHandler;
use Replicast\Plugin;

/**
 * The dashboard-specific functionality of the `post` taxonomy.
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Admin
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class PostAdmin extends Admin {

	/**
	 * Register hooks.
	 *
	 * @since    1.0.0
	 */
	public function register() {

		\add_action( 'save_post',          array( $this, 'on_save_post' ), 10, 3 );
		\add_action( 'attachment_updated', array( $this, 'on_save_post' ), 10, 3 );
		\add_action( 'trashed_post',       array( $this, 'on_trash_post' ) );
		\add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );

		// Admin UI - Posts
		foreach ( SiteAdmin::get_post_types() as $post_type ) {

			if ( \is_post_type_hierarchical( $post_type ) ) {
				\add_filter( 'page_row_actions', array( $this, 'hide_row_actions' ), 10, 2 );
			} else {
				\add_filter( 'post_row_actions', array( $this, 'hide_row_actions' ), 10, 2 );
			}

			\add_filter( "manage_{$post_type}_posts_columns",       array( $this, 'manage_posts_columns' ), 10, 2 );
			\add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'manage_posts_custom_column' ), 10, 2 );
		}

		\add_filter( 'user_has_cap',                 array( $this, 'manage_posts_edit' ), 10, 3 );
		\add_filter( 'wp_get_attachment_image_src',  array( $this, 'get_attachment_image_src' ), 10, 3 );
		\add_filter( 'wp_get_attachment_url',        array( $this, 'get_attachment_url' ), 10, 2 );
		\add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ), 10, 3 );

		// Admin UI - Taxonomies
		foreach ( \get_taxonomies() as $taxonomy ) {
			\add_filter( "{$taxonomy}_row_actions",          array( $this, 'hide_row_actions' ), 10, 2 );
			\add_filter( "manage_edit-{$taxonomy}_columns",  array( $this, 'manage_taxonomies_columns' ) );
			\add_filter( "manage_{$taxonomy}_custom_column", array( $this, 'manage_taxonomies_custom_column' ), 10, 3 );
		}

		// Admin UI - Featured Image
		\add_filter( 'admin_post_thumbnail_html', array( $this, 'update_post_thumbnail' ), 10, 2 );

		// Admin UI - Media Library
		\add_filter( 'media_row_actions',           array( $this, 'hide_row_actions' ), 10, 2 );
		\add_filter( 'ajax_query_attachments_args', array( $this, 'hide_attachments_on_grid_mode' ) );
		\add_action( 'pre_get_posts',               array( $this, 'hide_attachments_on_list_mode' ) );

	}

	/**
	 * Filter posts columns.
	 *
	 * @since     1.0.0
	 * @param     array     $columns      An array of column names.
	 * @param     string    $post_type    The post type slug.
	 * @return    array                   Possibly-modified array of column names.
	 */
	public function manage_posts_columns( $columns, $post_type = 'page' ) {

		if ( ! in_array( $post_type, SiteAdmin::get_post_types() ) ) {
			return $columns;
		}

		/**
		 * Filter the posts columns.
		 *
		 * @since     1.0.0
		 * @param     array     An array of column names.
		 * @param     string    The object type slug.
		 * @return    array     Possibly-modified array of column names.
		 */
		return \apply_filters(
			'replicast_posts_columns',
			$this->manage_columns( $columns ),
			$post_type
		);
	}

	/**
	 * Filter taxonomies columns.
	 *
	 * @since     1.0.0
	 * @param     array     $columns      An array of column names.
	 * @param     string    $post_type    The post type slug.
	 * @return    array                   Possibly-modified array of column names.
	 */
	public function manage_taxonomies_columns( $columns ) {

		/**
		 * Filter the taxonomies columns.
		 *
		 * @since     1.0.0
		 * @param     array    An array of column names.
		 * @return    array    Possibly-modified array of column names.
		 */
		return \apply_filters(
			'replicast_taxonomies_columns',
			$this->manage_columns( $columns )
		);
	}

	/**
	 * Manage columns.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array     $columns    An array of column names.
	 * @return    array                 Possibly-modified array of column names.
	 */
	private function manage_columns( $columns ) {

		$sorted_columns = array();

		/**
		 * Filter the column header title.
		 *
		 * @since     1.0.0
		 * @param     string    Column header title.
		 * @return    string    Possibly-modified column header title.
		 */
		$replicast_title = \apply_filters( 'replicast_column_title', \__( 'Replicast', 'replicast' ) );

		$sorted_columns = array(
			'replicast' => sprintf(
				'<span class="screen-reader-text">%s</span>',
				\esc_attr( $replicast_title )
			)
		);

		foreach ( $columns as $column_key => $column_title ) {
			$sorted_columns[ $column_key ] = $column_title;
		}

		return $sorted_columns;
	}

	/**
	 * Renders the custom column contents for a supported post type.
	 *
	 * @since    1.0.0
	 * @param    string    $column_name    The name of the column to display.
	 * @param    int       $object_id      The current object ID.
	 */
	public function manage_posts_custom_column( $column_name, $object_id ) {

		if ( $column_name !== 'replicast' ) {
			return;
		}

		echo $this->manage_custom_column( $object_id, 'post' );
	}

	/**
	 * Renders the custom column contents for a taxonomy.
	 *
	 * @since     1.0.0
	 * @param     string    $content        The column contents.
	 * @param     string    $column_name    The name of the column to display.
	 * @param     int       $object_id      The current object ID.
	 * @return    string                    Possibly-modified column contents.
	 */
	public function manage_taxonomies_custom_column( $content, $column_name, $object_id ) {

		if ( $column_name !== 'replicast' ) {
			return $content;
		}

		return $this->manage_custom_column( $object_id, 'term' );
	}

	/**
	 * Custom column contents.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     int       $object_id    The current object ID.
	 * @param     string    $meta_type    Type of object metadata.
	 * @return    string                  Column contents.
	 */
	private function manage_custom_column( $object_id, $meta_type ) {

		$origin_info = API::get_origin_info( $object_id, $meta_type );

		$html = sprintf(
			'<span class="dashicons dashicons-%s"></span>',
			$origin_info ? 'yes' : 'no'
		);

		if ( ! empty( $origin_info['edit_link'] ) ) {
			$html = sprintf(
				'<a href="%s" title="%s">%s</a>',
				\esc_url( $origin_info['edit_link'] ),
				\esc_attr__( 'Edit', 'replicast' ),
				$html
			);
		}

		/**
		 * Filter the custom column contents.
		 *
		 * @since     1.0.0
		 * @param     string      Column contents.
		 * @param     mixed       Single metadata value, or array of values.
		 *                        If the $meta_type or $object_id parameters are invalid, false is returned.
		 * @param     \WP_Post    The current object ID.
		 * @return    string      Possibly-modified column contents.
		 */
		return \apply_filters( 'manage_column_html', $html, $origin_info, $object_id );
	}

	/**
	 * Manage posts editing.
	 *
	 * @since     1.0.0
	 * @param     array       $allcaps    All the capabilities of the user.
	 * @param     array       $caps       Required capability.
	 * @param     array       $args       [0] Requested capability
	 *                                    [1] User ID
	 *                                    [2] Associated object ID
	 * @return    array                   Possibly-modified array of all the user's capabilities.
	 */
	public function manage_posts_edit( $allcaps, $caps, $args ) {

		if ( ! \is_admin() ) {
			return $allcaps;
		}

		// Bail out if we're not asking about a post
		if ( $args[0] !== 'edit_post' ) {
			return $allcaps;
		}

		// Check if the current object is an original or a duplicate
		if ( ! empty( $args[2] ) && ! empty( API::get_origin_info( $args[2] ) ) ) {
			return $allcaps;
		}

		// Disable certain capabilities
		foreach ( $caps as $cap ) {
			if ( ! in_array( $cap, array( 'edit_posts', 'edit_published_posts', 'edit_others_posts' ) ) ) {
				continue;
			}
			$allcaps[ $cap ] = false;
		}

		return $allcaps;
	}

	/**
	 * Filter the list of row action links.
	 *
	 * @since     1.0.0
	 * @param     array       $defaults    An array of row actions.
	 * @param     \WP_Post    $object      The current object.
	 * @return    array                    Possibly-modified array of row actions.
	 */
	public function hide_row_actions( $defaults, $object ) {

		$object_id = API::get_id( $object );
		$meta_type = API::get_meta_type( $object );

		if ( empty( $origin_info = API::get_origin_info( $object_id, $meta_type ) ) ) {
			return $defaults;
		}

		$actions = array( 'view' => $defaults['view'] );

		/**
		 * Extend the list of supported row action links by meta type.
		 *
		 * @since     1.0.0
		 * @param     array    An array of row actions.
		 * @param     int      The object ID.
		 * @return    array    Possibly-modified array of row actions.
		 */
		$actions = \apply_filters( "replicast_hide_{$meta_type}_row_actions", $actions, $defaults, $object_id );

		/**
		 * Extend the list of supported row action links.
		 *
		 * @since     1.0.0
		 * @param     array    An array of row actions.
		 * @param     int      The object ID.
		 * @return    array    Possibly-modified array of row actions.
		 */
		$actions = \apply_filters( 'replicast_hide_row_actions', $actions, $defaults, $object_id );

		// 'Edit link' points to the object original location
		$actions['edit'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			\esc_url( $origin_info['edit_link'] ),
			\esc_attr__( 'Edit', 'replicast' ),
			\__( 'Edit', 'replicast' )
		);

		return $actions;
	}

	/**
	 * Update post thumbnail with the remote thumbnail image.
	 *
	 * @since     1.0.0
	 * @param     string    $content    Post thumbnail markup.
	 * @param     int       $post_id    Post ID.
	 * @return    string                Possibly-modified post thumbnail markup.
	 */
	public function update_post_thumbnail( $content, $post_id ) {

		$object_id = \get_post_thumbnail_id( $post_id );

		if ( empty( $object_id ) ) {
			return $content;
		}

		if ( empty( $origin_info = API::get_origin_info( $object_id ) ) ) {
			return $content;
		}

		// Get thumbnail metadata
		$metadata = \get_post_meta( $object_id, '_wp_attachment_metadata', true );

		if ( empty( $metadata ) ) {
			return $content;
		}

		$width  = $metadata['width'];
		$height = $metadata['height'];
		$file   = $metadata['file'];

		if ( ! empty( $metadata['sizes'] ) && ! empty( $metadata['sizes']['post-thumbnail'] ) ) {
			$width  = $metadata['sizes']['post-thumbnail']['width'];
			$height = $metadata['sizes']['post-thumbnail']['height'];
			$file   = $metadata['sizes']['post-thumbnail']['file'];
		}

		$thumb_html = sprintf(
			'<img width="%s" height="%s" src="%s" class="attachment-post-thumbnail size-post-thumbnail">',
			\esc_attr( $width ),
			\esc_attr( $height ),
			\esc_attr( $file )
		);

		return sprintf(
			'<p class="hide-if-no-js"><a href="%s" title="%s" id="set-post-thumbnail" class="thickbox">%s</a></p>',
			\esc_url( $origin_info['edit_link'] ),
			\esc_attr__( 'Edit', 'replicast' ),
			$thumb_html
		);
	}

	/**
	 * Hide remote attachments on media library grid mode.
	 *
	 * @since     1.0.0
	 * @param     array    $query_args    Query args.
	 * @return    array                   Possibly-modified query args.
	 */
	public function hide_attachments_on_grid_mode( $query_args ) {

		if ( ! \is_admin() ) {
			return $query_args;
		}

		if ( $query_args['post_type'] !== 'attachment' ) {
			return $query_args;
		}

		return array_merge(
			array( 'meta_query' => $this->hide_attachments() ),
			$query_args
		);
	}

	/**
	 * Hide remote attachments on media library list mode.
	 *
	 * @since    1.0.0
	 * @param    \WP_Query    $query    Query object.
	 */
	public function hide_attachments_on_list_mode( $query ) {

		if ( ! \is_admin() ) {
			return;
		}

		if ( \get_query_var( 'post_type' ) !== 'attachment' ) {
			return;
		}

		$query->set( 'meta_query', $this->hide_attachments() );

	}

	/**
	 * Meta query for hiding remote attachments.
	 *
	 * @since     1.0.0
	 * @access    private
	 */
	private function hide_attachments() {
		return array(
			array(
				'key'     => Plugin::REPLICAST_ORIGIN_INFO,
				'compare' => 'NOT EXISTS',
			)
		);
	}

	/**
	 * Filter the image src.
	 *
	 * @see  image_downsize
	 *
	 * @since     1.0.0
	 * @param     array|false     $image            Either array with src, width & height, icon src, or false.
	 * @param     int             $attachment_id    Image attachment ID.
	 * @param     string|array    $size             Size of image. Image size or array of width and height values
	 *                                              (in that order).
	 * @return    array                             Array with src, width & height, icon src.
	 */
	public function get_attachment_image_src( $image, $attachment_id, $size ) {

		if ( empty( API::get_origin_info( $attachment_id ) ) ) {
			return $image;
		}

        // Get attachment metadata
		$metadata = \get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$url             = $metadata['file'];
		$width           = $metadata['width'];
		$height          = $metadata['height'];
		$is_intermediate = false;

		if ( $intermediate = \image_get_intermediate_size( $attachment_id, $size ) ) {
			$url             = $intermediate['file'];
			$width           = $intermediate['width'];
			$height          = $intermediate['height'];
			$is_intermediate = true;
		} elseif( ! empty( $metadata['sizes'] ) && ! empty( $metadata['sizes'][ $size ] ) ) {
			$url    = $metadata['sizes'][ $size ]['file'];
			$width  = $metadata['sizes'][ $size ]['width'];
			$height = $metadata['sizes'][ $size ]['height'];
		}

		return array( $url, $width, $height, $is_intermediate );
	}

	/**
	 * Filter the attachment URL.
	 *
	 * @since     1.0.0
	 * @param     string    $url              URL for the given attachment.
	 * @param     int       $attachment_id    Attachment ID.
	 * @return                                Possibly-modified URL for the given attachment
	 */
	public function get_attachment_url( $url, $attachment_id ) {

		if ( empty( API::get_origin_info( $attachment_id ) ) ) {
			return $url;
		}

		// Get attachment metadata
		$metadata = \get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		return basename( $metadata['file'] );
	}

	/**
	 * Filter the attachment data prepared for JavaScript.
	 *
	 * @since     1.0.0
	 * @param     array         $response      Array of prepared attachment data.
	 * @param     int|object    $attachment    Attachment ID or object.
	 * @param     array         $meta          Array of attachment meta data.
	 * @return    array                        Possibly-modified array of prepared attachment data.
	 */
	public function prepare_attachment_for_js( $response, $attachment, $meta ) {

		$attachment_id = $attachment;
		if ( ! is_numeric( $attachment_id ) ) {
			$attachment_id = $attachment->ID;
		}

		if ( empty( $origin_info = API::get_origin_info( $attachment_id ) ) ) {
			return $response;
		}

		// Update links
		$response['link']     = $origin_info['permalink'];
		$response['editLink'] = $origin_info['edit_link'];

		// Remove unsupported actions
		unset( $response['nonces']['update'] );
		unset( $response['nonces']['delete'] );

		// if ( ! empty( $meta['sizes'] ) ) {
		// 	foreach ( $response['sizes'] as $size => $value ) {
		// 		$response['sizes'][ $size ]['url'] = $meta['sizes'][ $size ]['url'];
		// 	}
		// }

		return $response;
	}

	/**
	 * Triggered whenever a post is published, or if it is edited and
	 * the status is changed to publish.
	 *
	 * @since    1.0.0
	 * @param    int         $post_id                      The post ID.
	 * @param    \WP_Post    $post                         The \WP_Post object.
	 * @param    \WP_Post    $post_before    (optional)    The \WP_Post object before the update. Only for attachments.
	 */
	public function on_save_post( $post_id, \WP_Post $post, $post_before = null ) {

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return;
		}

		// If post is an autosave, return
		if ( \wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// If post is a revision, return
		if ( \wp_is_post_revision( $post_id ) ) {
			return;
		}

		// If current user can't edit posts, return
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// PostHandlers with trash status are processed in \Request\Admin on_trash_post
		if ( $post->post_status === 'trash' ) {
			return;
		}

		// Double check post status
		if ( ! in_array( $post->post_status, SiteAdmin::get_post_status() ) ) {
			return;
		}

		// Admin notices
		$notices = array();

		// Get sites
		$sites = $this->get_sites( $post );

		// Wrap the post
		$handler = new PostHandler( $post );

		try {

			foreach ( $sites as $site ) {

				$handler->handle_save( $site )
					->then(
						function ( $response ) use ( $site, $handler ) {

							// Get the remote object data
							$remote_data = json_decode( $response->getBody()->getContents() );

							if ( empty( $remote_data ) ) {
								continue;
							}

							$site_id = $site->get_id();

							// Update object
							$handler->update_object( $site_id, $remote_data );

							// Update terms
							$handler->update_terms( $site_id, $remote_data );

							// Update media
							$handler->update_media( $site_id, $remote_data );

							// TODO: build notices

						}
					)
					->wait();

			}

			// 			$notices[] = array(
			// 				'status_code'   => $response->getStatusCode(),
			// 				'reason_phrase' => $response->getReasonPhrase(),
			// 				'message'       => sprintf(
			// 					'%s %s',
			// 					sprintf(
			// 						$response->getStatusCode() === 201 ? \__( 'PostHandler published on %s.', 'replicast' ) : \__( 'PostHandler updated on %s.', 'replicast' ),
			// 						$site->get_name()
			// 					),
			// 					sprintf(
			// 						'<a href="%s" title="%s" target="_blank">%s</a>',
			// 						\esc_url( $remote_data->link ),
			// 						\esc_attr( $site->get_name() ),
			// 						\__( 'View post', 'replicast' )
			// 					)
			// 				)
			// 			);

			// 		}

			// 	} catch ( \Exception $ex ) {
			// 		if ( $ex->hasResponse() ) {
			// 			$notices[] = array(
			// 				'status_code'   => $ex->getResponse()->getStatusCode(),
			// 				'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
			// 				'message'       => $ex->getMessage()
			// 			);
			// 		}
			// 	}

			// Get replicast info
			$replicast_info = API::get_remote_info( $post );

			// Verify that the current object has been "removed" (aka unchecked) from any site(s)
			// FIXME: review this later on
			foreach ( $replicast_info as $site_id => $replicast_data ) {
				if ( ! array_key_exists( $site_id, $sites ) ) {

					$handler->handle_delete( $this->get_site( $site_id ), true )
						->then(
							function ( $response ) use ( $site_id, $handler ) {

								// Update object
								$handler->update_object( $site_id );

								// TODO: build notices

							}
						)
						->wait();

				}
			}

		} catch ( \Exception $ex ) {
			// FIXME
			error_log( '---- SAVE ----' );
			error_log( print_r( $ex->getMessage(), true ) );
		}

		// Set admin notices
		if ( ! empty( $notices ) ) {
			$this->set_admin_notice( $notices );
		}

	}

	/**
	 * Fired when a post (or page) is about to be trashed.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 */
	public function on_trash_post( $post_id ) {

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return;
		}

		// If current user can't delete posts, return
		if ( ! \current_user_can( 'delete_posts' ) ) {
			return;
		}

		// Retrieves post data given a post ID
		$post = \get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		// Double check post status
		if ( $post->post_status !== 'trash' ) {
			return;
		}

		// Admin notices
		$notices = array();

		// Get sites
		$sites = $this->get_sites( $post );

		// Wrap the post
		$handler = new PostHandler( $post );

		/**
		 * Filter for whether to bypass trash or force deletion.
		 *
		 * @since     1.0.0
		 * @param     bool    Flag for bypass trash or force deletion.
		 * @return    bool    Possibly-modified flag for bypass trash or force deletion.
		 */
		$force = \apply_filters( "replicast_force_{$post->post_type}_delete", false );

		try {

			foreach ( $sites as $site ) {

				$handler
					->handle_delete( $site, $force )
					->then(
						function ( $response ) use ( $site, $handler, $force ) {

							// Get the remote object data
							$remote_data = json_decode( $response->getBody()->getContents() );

							if ( empty( $remote_data ) ) {
								continue;
							}

							if ( $force ) {
								$remote_data = null;
							}

							// Update object
							$handler->update_object( $site->get_id(), $remote_data );

							// TODO: build notices

						}
					)
					->wait();

			}

		} catch ( \Exception $ex ) {
			// FIXME
			error_log( '---- TRASH ----' );
			error_log( print_r( $ex->getMessage(), true ) );
		}

			// 	$notices[] = array(
			// 			'status_code'   => $response->getStatusCode(),
			// 			'reason_phrase' => $response->getReasonPhrase(),
			// 			'message'       => sprintf(
			// 				'%s %s',
			// 				sprintf(
			// 					\__( 'PostHandler trashed on %s.', 'replicast' ),
			// 					$site->get_name()
			// 				),
			// 				sprintf(
			// 					'<a href="%s" title="%s" target="_blank">%s</a>',
			// 					\esc_url( $remote_data->link ),
			// 					\esc_attr( $site->get_name() ),
			// 					\__( 'View post', 'replicast' )
			// 				)
			// 			)
			// 		);

			// 	}

			// } catch ( \Exception $ex ) {
			// 	if ( $ex->hasResponse() ) {
			// 		$notices[] = array(
			// 			'status_code'   => $ex->getResponse()->getStatusCode(),
			// 			'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
			// 			'message'       => $ex->getMessage()
			// 		);
			// 	}
			// }

		// Set admin notices
		if ( ! empty( $notices ) ) {
			$this->set_admin_notice( $notices );
		}

	}

	/**
	 * Fired when a post, page or attachment is permanently deleted.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 */
	public function on_delete_post( $post_id ) {

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return;
		}

		// If current user can't delete posts, return
		if ( ! \current_user_can( 'delete_posts' ) ) {
			return;
		}

		// Retrieves post data given a post ID
		$post = \get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		// Admin notices
		$notices = array();

		// Get sites
		$sites = $this->get_sites( $post );

		// Wrap the post
		$handler = new PostHandler( $post );

		try {

			foreach ( $sites as $site ) {

				$handler
					->handle_delete( $site, true )
					->then(
						function ( $response ) use ( $site, $handler ) {

							// Update object
							$handler->update_object( $site->get_id() );

							// TODO: build notices

						}
					)
					->wait();

			}

		} catch ( \Exception $ex ) {
			// FIXME
			error_log( '---- DELETE ----' );
			error_log( print_r( $ex->getMessage(), true ) );
		}

		// Set admin notices
		if ( ! empty( $notices ) ) {
			$this->set_admin_notice( $notices );
		}

	}

}
