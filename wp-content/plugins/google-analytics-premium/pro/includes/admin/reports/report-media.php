<?php
/**
 * Media Report
 *
 * Ensures all of the reports have a uniform class with helper functions.
 *
 * @since 8.9.0
 *
 * @package MonsterInsights
 * @subpackage Reports
 * @author Sourov
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_Media extends MonsterInsights_Report {

	public $class = 'MonsterInsights_Report_Media';
	public $name  = 'media';
	public $level = 'plus';

	/**
	 * Primary class constructor.
	 *
	 * @access public
	 * @since 8.9.0
	 */
	public function __construct() {
		$this->title = __( 'Media', 'google-analytics-premium' );

		parent::__construct();
	}

	public function requirements( $error = false, $args = array(), $name = '' ) {
		if ( ! empty( $error ) || $name !== $this->name ) {
			return $error;
		}

		if ( ! class_exists( 'MonsterInsights_Media' ) ) {
			add_filter( 'monsterinsights_reports_handle_error_message', array( $this, 'add_error_addon_link' ) );

			// Translators: %s will be the action (install/activate) which will be filled depending on the addon state.
			$text = __( 'Please %s the MonsterInsights Media addon to view media reports.', 'google-analytics-premium' );

			if ( monsterinsights_can_install_plugins() ) {
				return $text;
			} else {
				return sprintf( $text, __( 'install', 'google-analytics-premium' ) );
			}
		}

		return $error;
	}

}
