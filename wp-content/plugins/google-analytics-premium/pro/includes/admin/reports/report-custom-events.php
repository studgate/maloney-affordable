<?php
/**
 * Custom Events Report class
 *
 * @package MonsterInsights
 * @subpackage Reports
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_Custom_Events extends MonsterInsights_Report {

	public $class = 'MonsterInsights_Report_Custom_Events';
	public $name  = 'custom_events';
	public $level = 'pro';

	protected $api_path = 'custom-events';

	/**
	 * Primary class constructor.
	 */
	public function __construct() {
		$this->title = __( 'Custom Events', 'google-analytics-premium' );

		parent::__construct();
	}

}
