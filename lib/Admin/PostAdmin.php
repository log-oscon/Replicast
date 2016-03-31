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
	 * Show admin column contents.
	 *
	 * @since    1.0.0
	 * @param    string    $column       The name of the column to display.
	 * @param    int       $object_id    The current object ID.
	 */
	public function manage_custom_column( $column, $object_id ) {

		if ( $column !== 'replicast' ) {
			return;
		}

		$remote_info = $this->get_remote_info( $object_id );

		$html = sprintf(
			'<span class="dashicons dashicons-%s"></span>',
			$remote_info ? 'yes' : 'no'
		);

		if ( ! empty( $remote_info['edit_link'] ) ) {
			$html = sprintf(
				'<a href="%s" title="%s">%s</a>',
				\esc_url( $remote_info['edit_link'] ),
				\esc_attr__( 'Edit', 'replicast' ),
				$html
			);
		}

		/**
		 * Filter the column contents.
		 *
		 * @since     1.0.0
		 * @param     string      Column contents.
		 * @param     mixed       Single metadata value, or array of values.
		 *                        If the $meta_type or $object_id parameters are invalid, false is returned.
		 * @param     \WP_Post    The current object ID.
		 * @return    string      Possibly-modified column contents.
		 */
		echo \apply_filters( 'manage_custom_column_html', $html, $remote_info, $object_id );

	}

	/**
	 * Show admin column.
	 *
	 * @since     1.0.0
	 * @param     array     $columns      An array of column names.
	 * @param     string    $post_type    The post type slug.
	 * @return    array                   Possibly-modified array of column names.
	 */
	public function manage_columns( $columns, $post_type = 'page' ) {

		if ( ! in_array( $post_type, SiteAdmin::get_post_types() ) ) {
			return $columns;
		}

		/**
		 * Filter the column header title.
		 *
		 * @since     1.0.0
		 * @param     string    Column header title.
		 * @return    string    Possibly-modified column header title.
		 */
		$title = \apply_filters( 'replicast_manage_columns_title', \__( 'Replicast', 'replicast' ) );

		/**
		 * Filter the columns displayed.
		 *
		 * @since     1.0.0
		 * @param     array     An array of column names.
		 * @param     string    The object type slug.
		 * @return    array     Possibly-modified array of column names.
		 */
		return \apply_filters(
			'replicast_manage_columns',
			array_merge( $columns, array( 'replicast' => $title ) ),
			$post_type
		);
	}

	/**
	 * Dynamically filter a user's capabilities.
	 *
	 * @since     1.0.0
	 * @param     array       $allcaps    An array of all the user's capabilities.
	 * @param     array       $caps       Actual capabilities for meta capability.
	 * @param     array       $args       Optional parameters passed to has_cap(), typically object ID.
	 * @param     \WP_User    $user       The user object.
	 * @return    array                   Possibly-modified array of all the user's capabilities.
	 */
	public function hide_edit_link( $allcaps, $caps, $args, $user ) {

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return $allcaps;
		}

		// Bail out if we're not asking about a post
		if ( $args[0] !== 'edit_post' ) {
			return $allcaps;
		}

		// Check if the current object is an original or a duplicate
		if ( ! $this->get_remote_info( $args[2] ) ) {
			return $allcaps;
		}

		// Disable 'edit_posts', 'edit_published_posts' and 'edit_others_posts'
		if ( in_array( $cap, array( 'edit_posts', 'edit_published_posts', 'edit_others_posts' ) ) ) {
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

		// Check if the current object is an original or a duplicate
		if ( ! $remote_info = $this->get_remote_info( $object->ID ) ) {
			return $defaults;
		}

		/**
		 * Extend the list of unsupported row action links.
		 *
		 * @since     1.0.0
		 * @param     array       An array of row actions.
		 * @param     \WP_Post    The current object.
		 * @return    array       Possibly-modified array of row actions.
		 */
		$defaults = \apply_filters( 'replicast_hide_row_actions', $defaults, $object );

		// Force the removal of unsupported default actions
		unset( $defaults['edit'] );
		unset( $defaults['inline hide-if-no-js'] );
		unset( $defaults['trash'] );

		// New set of actions
		$actions = array();

		// 'Edit link' points to the object original location
		$actions['edit'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			\esc_url( $remote_info['edit_link'] ),
			\esc_attr__( 'Edit', 'replicast' ),
			\__( 'Edit', 'replicast' )
		);

		// Re-order actions
		foreach ( $defaults as $key => $value ) {
			$actions[ $key ] = $value;
		}

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

		// Get remote info
		$remote_info = $this->get_remote_info( $attachment_id );

		if ( empty( $remote_info ) ) {
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
			\esc_url( $remote_info['edit_link'] ),
			\esc_attr__( 'Edit', 'replicast' ),
			$thumb_html
		);
	}

	/**
	 * Delete remote info from post thumbnail.
	 *
	 * @since    1.0.0
	 * @param    array     $meta_ids     An array of deleted metadata entry IDs.
	 * @param    int       $object_id    Object ID.
	 * @param    string    $meta_key     Meta key.
	 */
	public function delete_post_thumbnail( $meta_ids, $object_id, $meta_key ) {

		if ( $meta_key !== '_thumbnail_id' ) {
			return;
		}

		$object_id = \get_post_thumbnail_id( $object_id );

		if ( ! $object_id ) {
			return;
		}

		\delete_post_meta( $object_id, Plugin::REPLICAST_REMOTE_IDS );

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
				'key'     => Plugin::REPLICAST_OBJECT_INFO,
				'compare' => 'NOT EXISTS',
			)
		);
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

							// Update replicast info
							$handler->update_post_info( $site_id, $remote_data );

							// Update featured media
							$handler->update_post_featured_media( $site_id, $remote_data );

							// Update post terms
							$handler->update_post_terms( $site_id, $remote_data );

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
			$replicast_info = API::get_replicast_info( $post );

			// Verify that the current object has been "removed" (aka unchecked) from any site(s)
			// FIXME: review this later on
			foreach ( $replicast_info as $site_id => $replicast_data ) {
				if ( ! array_key_exists( $site_id, $sites ) ) {

					$handler->handle_delete( $this->get_site( $site_id ), true )
						->then(
							function ( $response ) use ( $site_id, $handler ) {

								// Update replicast info
								$handler->update_post_info( $site_id );

								// TODO: build notices

							}
						)
						->wait();

				}
			}

		} catch ( \Exception $ex ) {
			// FIXME
			error_log( '---- on_trash_post ----' );
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

							// Update replicast info
							$handler->update_post_info( $site->get_id(), $remote_data );

							// TODO: build notices

						}
					)
					->wait();

			}

		} catch ( \Exception $ex ) {
			// FIXME
			error_log( '---- on_trash_post ----' );
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

							// Update replicast info
							$handler->update_post_info( $site->get_id() );

							// TODO: build notices

						}
					)
					->wait();

			}

		} catch ( \Exception $ex ) {
			// FIXME
			error_log( '---- on_delete_post ----' );
			error_log( print_r( $ex->getMessage(), true ) );
		}

		// Set admin notices
		if ( ! empty( $notices ) ) {
			$this->set_admin_notice( $notices );
		}

	}

}
