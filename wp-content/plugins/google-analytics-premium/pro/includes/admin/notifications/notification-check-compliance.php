<?php

/**
 * If Google Ads / PPC tracking  is enabled we will add this notification.
 */
final class MonsterInsights_Notification_Check_Compliance extends MonsterInsights_Notification_Event {

	public $notification_id = 'monsterinsights_notification_check_compliance';
	public $notification_interval = 7;
	public $notification_type = array( 'master', 'pro' );
	public $notification_category = 'alert';
	public $notification_icon = 'warning';
	public $notification_priority = 1;

	/**
	 * Everything about this notification content set here.
	 */
	public function prepare_notification_data( $notification ) {
		// If both of the ads plugin deactivated.
		if ( ! class_exists('MonsterInsights_Ads') && ! class_exists('MonsterInsights_PPC_Tracking_Premium') ) {
			return false;
		}

		$notification['title']   = __( 'EEA Compliance Update', 'google-analytics-premium' );
		$notification['content'] = __( 'New privacy regulations will soon require you to receive consent from website visitors located inside an EEA country in order to use Google Ads or interest, demographics or location data (Google Analytics Signals). Use the MonsterInsights compliance checker to see if your site requires consent.', 'google-analytics-premium' );

		$notification['btns'] = array(
			"check_now" => array(
				'url'  => $this->get_view_url( '', 'monsterinsights_settings', 'tools/eea-compliance' ),
				'text' => __( 'Check Now', 'google-analytics-premium' )
			),
		);

		return $notification;
	}

}

// Initialize the class
new MonsterInsights_Notification_Check_Compliance();
