<?php
/**
 * eCommerce Cart Abandonment
 *
 * Ensures all the reports have a uniform class with helper functions.
 *
 * @since 9.2.0
 *
 * @package MonsterInsights
 * @subpackage Reports
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_Cart_Abandonment extends MonsterInsights_Report {

	public $class = 'MonsterInsights_Report_Cart_Abandonment';
	public $name  = 'cart_abandonment';
	public $level = 'pro';

	protected $api_path = 'cart-abandonment';

	/**
	 * Primary class constructor.
	 */
	public function __construct() {
		$this->title = __( 'Cart Abandonment', 'ga-premium' );

		parent::__construct();
	}

	/**
	 * Prepare report-specific data for output.
	 *
	 * @param array $data The data from the report before it gets sent to the frontend.
	 *
	 * @return mixed
	 */
	public function prepare_report_data( $data ) {
		// Add GA links.
		if ( ! empty( $data['data']['cart_abandonment_by_day'] ) ) {
			foreach( $data['data']['cart_abandonment_by_day'] as $key => $row ) {
				if ( ! empty( $row['date'] ) ) {
					$date = date_parse( $data['data']['cart_abandonment_by_day'][ $key ]['date'] );
					$data['data']['cart_abandonment_by_day'][ $key ]['date'] = $date["year"] . '-' .  $date["month"] . '-' . $date["day"];
				}
			}
		}

		return apply_filters( 'monsterinsights_report_traffic_sessions_chart_data', $data, $this->start_date, $this->end_date );
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
			$text = __( 'Please %s the MonsterInsights eCommerce addon to view Cart Abandonment reports.', 'ga-premium' );

			if ( monsterinsights_can_install_plugins() ) {
				return $text;
			} else {
				return sprintf( $text, __( 'install', 'ga-premium' ) );
			}
		}

		return $error;
	}

}
