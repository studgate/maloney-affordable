<?php
/**
 * Engagement Pages Report
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

final class MonsterInsights_Report_Engagement_Pages extends MonsterInsights_Report {

	public $class = 'MonsterInsights_Report_Engagement_Pages';
	public $name  = 'engagement_pages';
	public $level = 'plus';

	protected $api_path = 'engagement-pages';

	/**
	 * Primary class constructor.
	 */
	public function __construct() {
		$this->title = __( 'Pages Report', 'google-analytics-premium' );

		parent::__construct();
	}

}
