<?php

use ai\MonsterInsights_AI_Insights;

final class MonsterInsights_Notification_AI_Insight extends MonsterInsights_Notification_Event {

	public $notification_id = 'monsterinsights_notification_ai_insight';
	public $notification_interval = 3; // in days
	public $notification_type = array( 'master', 'pro' );
	public $notification_category = 'ai_insight';
	public $notification_icon = 'ai-insight';
	public $notification_priority = 1;

	private $insight_id;

	/**
	 * Initialize the class for a specific insight
	 * @param $insight_id
	 */
	public function __construct($insight_id)
	{
		$this->insight_id = $insight_id;
		//  Override id to make sure this notification can run for multiple insights at the same time.
		$this->notification_id = $this->notification_id . '_' . $insight_id;
		parent::__construct();
	}

	public static function create_notifications_for_insights()
	{
		$existing_insights_data = is_multisite() ?
			get_site_transient(MonsterInsights_AI_Insights::INSIGHTS_TRANSIENT_KEY) :
			get_transient(MonsterInsights_AI_Insights::INSIGHTS_TRANSIENT_KEY);

		if ( empty($existing_insights_data) || empty($existing_insights_data['insights']) ) {
			return;
		}

		$insights = $existing_insights_data['insights'];

		foreach ($insights as $insight) {
			new MonsterInsights_Notification_AI_Insight($insight['id']);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function prepare_notification_data( $notification ) {
		$existing_insights_data = is_multisite() ?
			get_site_transient(MonsterInsights_AI_Insights::INSIGHTS_TRANSIENT_KEY) :
			get_transient(MonsterInsights_AI_Insights::INSIGHTS_TRANSIENT_KEY);

		if ( empty($existing_insights_data) || empty($existing_insights_data['insights']) ) {
			return;
		}

		$notification_insight = null;

		foreach ( $existing_insights_data['insights'] as $insight ) {
			if ( $insight['id'] === $this->insight_id ) {
				$notification_insight = $insight;
				break;
			}
		}

		if ( empty($notification_insight) ) {
			return false;
		}

		$default_notification_title = __( 'New AI Insight', 'google-analytics-premium' );

		$notification['title'] = !empty( $notification_insight['title'] ) ? $notification_insight['title'] : $default_notification_title;
		$notification['content'] = $notification_insight['content'];
		$notification['btns'] = array(
			"view_insights" => array(
				'url'  => $this->get_view_url( '', 'monsterinsights_reports', 'ai-insights' ),
				'text' => __( 'View Insights', 'google-analytics-premium' )
			)
		);

		return $notification;
	}
}

// initialize the class

//  AI Insights
require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/admin/ai/class-monsterinsights-ai-insights.php';

MonsterInsights_Notification_AI_Insight::create_notifications_for_insights();
