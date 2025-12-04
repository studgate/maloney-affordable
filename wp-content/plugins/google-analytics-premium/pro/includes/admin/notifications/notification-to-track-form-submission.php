<?php

/**
 * Add notification when pro version activated & forms tracking option is disabled.
 * Recurrence: 20 Days
 *
 * @since 7.12.3
 */
final class MonsterInsights_Notification_To_Track_Form_Submission extends MonsterInsights_Notification_Event {

	public $notification_id = 'monsterinsights_notification_to_track_form_submission';
	public $notification_interval = 20; // in days
	public $notification_type = array( 'master', 'pro' );
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

		$forms_addon_active = class_exists( 'MonsterInsights_Forms' );

		if ( ! $forms_addon_active ) {

			$notification['title'] = __( 'Track Form Submissions in WordPress', 'google-analytics-premium' );
			// Translators: form submission notification content
			$notification['content'] = __( 'Enable form tracking by enabling our Forms addon', 'google-analytics-premium' );

			$notification['btns'] = array(
				"activate_addon" => array(
					'url'  => $this->get_view_url( 'monsterinsights-addon-forms', 'monsterinsights_settings', 'addons' ),
					'text' => __( 'Activate Addon', 'google-analytics-premium' ),
				),
			);

			return $notification;
		}

		return false;
	}

}

// initialize the class
new MonsterInsights_Notification_To_Track_Form_Submission();
