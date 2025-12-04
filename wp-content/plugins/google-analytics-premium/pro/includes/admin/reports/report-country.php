<?php
/**
 * Country Report
 *
 * Ensures all of the reports have a uniform class with helper functions.
 *
 * @since 9.2.0
 *
 * @package MonsterInsights
 * @subpackage Reports
 * @author  Andrei Lupu
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_Countries extends MonsterInsights_Report {

	public $title;
	public $class = 'MonsterInsights_Report_Countries';
	public $name = 'countries';
	public $level = 'plus';

	/**
	 * Primary class constructor.
	 *
	 * @access public
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->title = __( 'Country / Region', 'google-analytics-premium' );
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
		// Add flags to the countries report.
		if ( ! empty( $data['data']['countries'] ) ) {
			$country_names = monsterinsights_get_country_list( true );
			foreach ( $data['data']['countries'] as $key => $country ) {
				$data['data']['countries'][ $key ]['name'] = isset( $country_names[ $country['iso'] ] ) ? $country_names[ $country['iso'] ] : $country['iso'];
			}
		}


		return apply_filters( 'monsterinsights_report_countries_data', $data );
	}
}
