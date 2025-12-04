<?php

/**
 * Add notification for high EU Traffic
 * Recurrence: 30 Days
 *
 * @since 7.12.3
 */
final class MonsterInsights_Notification_Pro_EU_Traffic extends MonsterInsights_Notification_Event {

	public $notification_id = 'monsterinsights_notification_pro_eu_traffic';
	public $notification_interval = 30; // in days
	public $notification_type = array( 'master', 'plus', 'pro' );
	public $notification_icon = 'star';
	public $notification_category = 'insight';
	public $notification_priority = 2;

	/**
	 * Build Notification
	 *
	 * @return array $notification notification is ready to add
	 *
	 * @since 7.12.3
	 */
	public function prepare_notification_data( $notification ) {

		$is_eu_addon_active = class_exists( 'MonsterInsights_EU_Compliance' );

		if ( $is_eu_addon_active ) {
			return false;
		}

		$eu_countries = [
			'AT',
			'BE',
			'BG',
			'HR',
			'CY',
			'CZ',
			'DK',
			'EE',
			'FI',
			'FR',
			'DE',
			'GR',
			'HU',
			'IE',
			'IT',
			'LU',
			'MT',
			'NL',
			'PL',
			'PT',
			'RO',
			'SK',
			'SI',
			'ES',
			'SE'
		];

		$report = $this->get_report();

		$sessions      = isset( $report['data']['infobox']['sessions']['value'] ) ? $report['data']['infobox']['sessions']['value'] : 0;
		$all_countries = isset( $report['data']['countries'] ) ? $report['data']['countries'] : [];

		$eu_sessions = 0;

		foreach ( $all_countries as $country ) {
			if ( in_array( $country['iso'], $eu_countries ) ) {
				$eu_sessions += intval( $country['sessions'] );
			}
		}

		if ( empty( $sessions ) ) {
			return false;
		}

		$eu_sessions_percentage = $eu_sessions / $sessions * 100;

		if ( $eu_sessions_percentage < 1 ) {
			return false;
		}

		$notification['title']   = __( 'Install and activate our EU Privacy add-on', 'google-analytics-premium' );
		$notification['content'] = __( 'Your site is receiving traffic from the EU. Help your site become more compliant by enabling the EU Privacy add-on.', 'google-analytics-premium' );
		$notification['btns']    = array(
			"activate_addon" => array(
				'url'  => $this->get_view_url( 'monsterinsights-addon-eu-compliance', 'monsterinsights_settings', 'addons' ),
				'text' => __( 'Activate Addon', 'google-analytics-premium' ),
			),
		);

		return $notification;
	}
}

new MonsterInsights_Notification_Pro_EU_Traffic();
