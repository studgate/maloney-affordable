<?php
/**
 * Plugin Name:     WP Engine Update Source Selector
 * Description:     Install or update WordPress core, plugins, and themes from the source that works best for your site and workflows.
 * License:         GPLv2+
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author:          WP Engine
 * Author URI:      https://wpengine.com
 * Update URI:      false
 * Network:         true
 * Text Domain:     wpe-update-source-selector
 * Domain Path:     /languages
 * Version:         1.1.5
 *
 * @package         wpe-update-source-selector
 */

namespace WPE_Update_Source_Selector;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( __NAMESPACE__ . '\wpe_uss_path' ) ) {
	/**
	 * Get the path to the plugin's files.
	 *
	 * @param string $plugin_file Path to plugin's entry point file.
	 *
	 * @return string
	 */
	function wpe_uss_path( string $plugin_file ) {
		$abspath    = wp_normalize_path( dirname( $plugin_file ) );
		$mu_path    = $abspath . '/wpe-update-source-selector';
		$core_class = '/classes/wpe-update-source-selector.php';

		// Is entry point file in directory above where plugin's files are?
		if ( file_exists( $mu_path . $core_class ) ) {
			$abspath = $mu_path;
		}

		return trailingslashit( $abspath );
	}
}

if ( ! defined( 'WPE_USS_FILE' ) ) {
	define( 'WPE_USS_FILE', __FILE__ );
}

if ( ! defined( 'WPE_USS_PATH' ) ) {
	define( 'WPE_USS_PATH', wpe_uss_path( __FILE__ ) );
}

if ( ! function_exists( __NAMESPACE__ . '\wpe_uss_init' ) && defined( 'WPE_USS_PATH' ) && is_string( WPE_USS_PATH ) ) {
	/**
	 * Instantiate the plugin's main class.
	 *
	 * @handles init
	 *
	 * @return void
	 */
	function wpe_uss_init() {
		if ( class_exists( __NAMESPACE__ . '\WPE_Update_Source_Selector' ) ) {
			return;
		}

		if ( ! file_exists( WPE_USS_PATH . 'classes/autoloader.php' ) ) {
			return;
		}

		global $wpe_uss;

		// Initiate our autoloader.
		require_once WPE_USS_PATH . 'classes/autoloader.php';
		new Autoloader( __NAMESPACE__, WPE_USS_PATH );

		// Load the main entry class for the plugin.
		$wpe_uss = new WPE_Update_Source_Selector( 'wpe-update-source-selector', WPE_USS_FILE, WPE_USS_PATH );
	}

	add_action( 'init', __NAMESPACE__ . '\wpe_uss_init' );
}
