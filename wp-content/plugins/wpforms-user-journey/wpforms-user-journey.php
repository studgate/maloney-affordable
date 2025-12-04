<?php
/**
 * Plugin Name:       WPForms User Journey
 * Plugin URI:        https://wpforms.com
 * Description:       User Journey addon for WPForms.
 * Requires at least: 5.5
 * Requires PHP:      7.0
 * Author:            WPForms
 * Author URI:        https://wpforms.com
 * Version:           1.4.0
 * Text Domain:       wpforms-user-journey
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

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpDefineCanBeReplacedWithConstInspection */

use WPFormsUserJourney\Install;
use WPFormsUserJourney\Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @since 1.1.0
 */
const WPFORMS_USER_JOURNEY_VERSION = '1.4.0';

/**
 * Plugin file.
 *
 * @since 1.1.0
 */
const WPFORMS_USER_JOURNEY_FILE = __FILE__;

/**
 * Plugin path.
 *
 * @since 1.1.0
 */
define( 'WPFORMS_USER_JOURNEY_PATH', plugin_dir_path( WPFORMS_USER_JOURNEY_FILE ) );

/**
 * Check addon requirements.
 *
 * @since 1.0.5
 * @since 1.1.0 Renamed from wpforms_user_journey_required to wpforms_user_journey_load.
 * @since 1.1.0 Uses requirements feature.
 */
function wpforms_user_journey_load() {

	$requirements = [
		'file'    => WPFORMS_USER_JOURNEY_FILE,
		'wpforms' => '1.9.1',
	];

	if ( ! function_exists( 'wpforms_requirements' ) || ! wpforms_requirements( $requirements ) ) {
		return;
	}

	wpforms_user_journey();
}

add_action( 'wpforms_loaded', 'wpforms_user_journey_load' );

/**
 * Get the instance of the addon main class.
 *
 * @since 1.0.0
 *
 * @return Loader
 */
function wpforms_user_journey() {

	return Loader::get_instance();
}

require_once WPFORMS_USER_JOURNEY_PATH . 'vendor/autoload.php';

// Load installation things immediately for a reason how activation hook works.
new Install();
