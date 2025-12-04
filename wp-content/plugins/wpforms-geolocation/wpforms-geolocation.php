<?php
/**
 * Plugin Name:       WPForms Geolocation
 * Plugin URI:        https://wpforms.com
 * Description:       Display geolocation details with WPForms.
 * Requires at least: 5.5
 * Requires PHP:      7.1
 * Author:            WPForms
 * Author URI:        https://wpforms.com
 * Version:           2.11.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpforms-geolocation
 * Domain Path:       /languages
 *
 * WPForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WPForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WPForms. If not, see <https://www.gnu.org/licenses/>.
 */

use WPFormsGeolocation\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @since 1.0.0
 */
const WPFORMS_GEOLOCATION_VERSION = '2.11.0';

/**
 * Plugin file.
 *
 * @since 1.0.0
 */
const WPFORMS_GEOLOCATION_FILE = __FILE__;

/**
 * Plugin path.
 *
 * @since 1.0.0
 */
define( 'WPFORMS_GEOLOCATION_PATH', plugin_dir_path( WPFORMS_GEOLOCATION_FILE ) );

/**
 * Plugin URL.
 *
 * @since 1.0.0
 */
define( 'WPFORMS_GEOLOCATION_URL', plugin_dir_url( WPFORMS_GEOLOCATION_FILE ) );

/**
 * Check addon requirements.
 *
 * @since 2.0.0
 * @since 2.5.0 Uses requirements feature.
 */
function wpforms_geolocation_load() {

	// Check requirements.
	$requirements = [
		'file'    => WPFORMS_GEOLOCATION_FILE,
		'wpforms' => '1.9.4',
	];

	if ( ! function_exists( 'wpforms_requirements' ) || ! wpforms_requirements( $requirements ) ) {
		return;
	}

	wpforms_geolocation();
}

add_action( 'wpforms_loaded', 'wpforms_geolocation_load' );

/**
 * Get the instance of the addon main class.
 *
 * @since 1.0.0
 * @since 2.3.0 Added deprecated file load.
 *
 * @return Plugin
 */
function wpforms_geolocation() {

	require_once WPFORMS_GEOLOCATION_PATH . 'vendor/autoload.php';

	return Plugin::get_instance();
}
