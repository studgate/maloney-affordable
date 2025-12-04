<?php
/**
 * eCommerce Coupons Report
 *
 * Ensures all the reports have a uniform class with helper functions.
 *
 * @since 8.17
 *
 * @package MonsterInsights
 * @subpackage Reports
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_eCommerce_Coupons extends MonsterInsights_Report {

	public $class = 'MonsterInsights_Report_eCommerce_Coupons';
	public $name  = 'ecommerce_coupons';
	public $level = 'pro';

	protected $api_path = 'ecommerce-coupons';

	/**
	 * Primary class constructor.
	 */
	public function __construct() {
		$this->title = __( 'eCommerce Coupons', 'google-analytics-premium' );

		parent::__construct();
	}

	/**
	 * Set eCommerce addon as a requirement of the eCommerce report.
	 *
	 * @param $error
	 * @param $args
	 * @param $name
	 *
	 * @return false|string
	 */
	public function requirements( $error = false, $args = array(), $name = '' ) {
		if ( ! empty( $error ) || $name !== $this->name ) {
			return $error;
		}

		if ( ! class_exists( 'MonsterInsights_eCommerce' ) ) {
			add_filter( 'monsterinsights_reports_handle_error_message', array( $this, 'add_error_addon_link' ) );

			// Translators: %s will be the action (install/activate) which will be filled depending on the addon state.
			$text = __( 'Please %s the MonsterInsights eCommerce addon to view Coupons reports.', 'google-analytics-premium' );

			if ( monsterinsights_can_install_plugins() ) {
				return $text;
			} else {
				return sprintf( $text, __( 'install', 'google-analytics-premium' ) );
			}
		}

		return $error;
	}

}
