<?php


class MonsterInsights_Notification_No_Addons extends MonsterInsights_Notification_Event {
	public $notification_id = 'monsterinsights_notification_no_addons';
	public $notification_interval = 7;
	public $notification_type = array( 'plus', 'pro' );
	public $notification_category = 'insight';
	public $notification_priority = 2;

	public function prepare_notification_data( $notification ) {

		$addons = monsterinsights_get_addons();
		if ( ! $addons || ! is_array( $addons['licensed'] ) ) {
			return false;
		}
		$licensed_addons = $addons['licensed'];

		$has_active_addons = false;

		foreach ( $licensed_addons as $addon ) {
			$slug            = 'monsterinsights-' . $addon->slug;
			$plugin_basename = monsterinsights_get_plugin_basename_from_slug( $slug );

			if ( is_multisite() && is_network_admin() ) {
				$active = is_plugin_active_for_network( $plugin_basename );
			} else {
				$active = is_plugin_active( $plugin_basename );
			}

			if ( $active ) {
				$has_active_addons = true;
				break;
			}
		}

		if ( ! $has_active_addons ) {
			$notification['title']   = __( "Remember to activate addons", "google-analytics-premium" );
			$notification['content'] = __( "Get the most of your subscription - enable some addons to help with site performance, reporting, and more", 'google-analytics-premium' );
			$notification['btns']    = array(
				'view_addons' => array(
					'url'  => $this->get_view_url( null, 'monsterinsights_settings', 'addons' ),
					'text' => __( 'View Addons', 'google-analytics-premium' ),
				)
			);

			return $notification;
		}

		return false;
	}
}

new MonsterInsights_Notification_No_Addons();
