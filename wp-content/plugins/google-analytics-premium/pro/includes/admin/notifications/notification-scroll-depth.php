<?php

/**
 * Add notification when scroll depth option is not enabled
 * Recurrence: 40 Days
 *
 * @since 7.12.3
 */
final class MonsterInsights_Notification_Scroll_Depth extends MonsterInsights_Notification_Event {

	public $notification_id       = 'monsterinsights_notification_scroll_depth';
	public $notification_interval = 40; // in days
	public $notification_type     = array( 'basic', 'master', 'plus', 'pro' );
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
		$data                 = array();
		$report               = $this->get_report( 'publisher', $this->report_start_from, $this->report_end_to );
		$data['scroll_depth'] = isset( $report['data']['scroll']['average'] ) ? $report['data']['scroll']['average'] : 0;
		$is_em                = function_exists( 'ExactMetrics' );

		if ( ! empty( $data ) && ( $data['scroll_depth'] > 0 && $data['scroll_depth'] < 60 ) ) {
			/* translators: Placeholder adds the scroll depth value. */
			$notification['title'] = sprintf( __( 'Your Website Scroll Depth is %s', 'google-analytics-premium' ), $data['scroll_depth'] . '%' );
			if ( ! $is_em ) {
				/* translators: Placeholders add a link to an article. */
				$notification['content'] = sprintf( __( 'Tracking user scroll activity helps you understand how engaged your users are with your content, and lets you optimize your pages for a better experience. %1$sIn this article%2$s, you can see how to use scroll tracking in WordPress with google analytics.', 'google-analytics-premium' ), '<a href="' . $this->build_external_link( 'https://www.monsterinsights.com/how-to-use-scroll-tracking-in-wordpress-with-google-analytics/' ) . '" target="_blank">', '</a>' );
			} else {
				$notification['content'] = esc_html__( 'Tracking user scroll activity helps you understand how engaged your users are with your content, and lets you optimize your pages for a better experience.', 'google-analytics-premium' );
			}

			$notification['btns'] = array(
				'view_report' => array(
					'url'  => $this->get_view_url( 'monsterinsights-report-scroll-depth', 'monsterinsights_reports', 'publishers' ),
					'text' => __( 'View Report', 'google-analytics-premium' ),
				),
			);

			if ( ! $is_em ) {
				$notification['btns']['learn_more'] = array(
					'url'         => $this->build_external_link( 'https://www.monsterinsights.com/how-to-use-scroll-tracking-in-wordpress-with-google-analytics/' ),
					'text'        => __( 'Learn More', 'google-analytics-premium' ),
					'is_external' => true,
				);
			}

			return $notification;
		}

		return false;
	}

}

// initialize the class
new MonsterInsights_Notification_Scroll_Depth();
