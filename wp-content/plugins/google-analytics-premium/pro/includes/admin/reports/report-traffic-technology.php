<?php
/**
 * Traffic Technology Report
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

final class MonsterInsights_Report_Traffic_Technology extends MonsterInsights_Report {

	public $class = 'MonsterInsights_Report_Traffic_Technology';
	public $name  = 'traffic_technology';
	public $level = 'plus';

	protected $api_path = 'traffic-technology';

	/**
	 * Primary class constructor.
	 */
	public function __construct() {
		$this->title = __( 'Traffic Technology', 'google-analytics-premium' );

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
		if ( isset( $data['data']['browser_breakdown'] ) && is_array( $data['data']['browser_breakdown'] ) ) {
			$data['data']['galinks'] = array(
				3 => sprintf(
					'https://analytics.google.com/analytics/web/#/a%1$sp%2$s/reports/explorer?params=_u..nav%%3Dmaui%%26_r.explorerCard..selmet%%3D%%5B%%22activeUsers%%22%%5D%%26_r.explorerCard..seldim%%3D%%5B%%22browser%%22%%5D&collectionId=user&r=user-technology-detail',
					MonsterInsights()->auth->get_accountid(),
					MonsterInsights()->auth->get_propertyid()
				),
			);
		}

		return $data;
	}
}
