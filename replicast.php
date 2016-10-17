<?php

/**
 * @link              http://log.pt/
 * @since             1.0.0
 * @package           Replicast
 *
 * @wordpress-plugin
 * Plugin Name:       Replicast
 * Plugin URI:        http://log.pt/
 * Description:       Replicate content across WordPress installs via the WP REST API.
 * Version:           1.2.0
 * Author:            log.OSCON, Lda.
 * Author URI:        http://log.pt/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       replicast
 * Domain Path:       /languages
 */

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
\add_action( 'plugins_loaded', function () {
	$plugin = new \Replicast\Plugin( 'replicast', '1.2.0' );
	$plugin->run();
} );
