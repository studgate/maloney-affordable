<?php

/**
 * Add notification when search console report is not connected
 * Recurrence: 15 Days
 *
 * @since 7.12.3
 */
final class MonsterInsights_Notification_Connect_Search_Console extends MonsterInsights_Notification_Event {

	public $notification_id       = 'monsterinsights_notification_connect_search_console';
	public $notification_interval = 15; // in days
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
		$report = $this->get_report( 'queries', $this->report_start_from, $this->report_end_to );
		$is_em  = function_exists( 'ExactMetrics' );

		if ( isset( $report['success'] ) && false === $report['success'] && ! empty( $report['error'] ) ) {
			$notification['title'] = __( 'The Google Search Console report is not properly set up', 'google-analytics-premium' );
			if ( ! $is_em ) {
				/* translators: Placeholders add a link to an article. */
				$notification['content'] = sprintf( __( 'Are you interested in what keywords bring you the most traffic from Google? You can get that information directly in your MonsterInsights Reports area by connecting your Google Search Console account with Google Analytics. <br><br>Follow our %1$sstep-by-step guide%2$s to get started and find out where to focus your attention.', 'google-analytics-premium' ), '<a href="' . $this->build_external_link( 'https://www.monsterinsights.com/docs/how-to-connect-google-search-console-to-google-analytics/' ) . '" target="_blank">', '</a>' );
			} else {
				$notification['content'] = esc_html__( 'Are you interested in what keywords bring you the most traffic from Google? You can get that information directly in your MonsterInsights Reports area by connecting your Google Search Console account with Google Analytics.', 'google-analytics-premium' );
			}

			if ( ! $is_em ) {
				$notification['btns'] = array(
					'learn_more' => array(
						'url'         => $this->build_external_link( 'https://www.monsterinsights.com/docs/how-to-connect-google-search-console-to-google-analytics/' ),
						'text'        => __( 'Learn More', 'google-analytics-premium' ),
						'is_external' => true,
					),
				);
			}

			return $notification;
		}

		return false;
	}

}

// initialize the class
new MonsterInsights_Notification_Connect_Search_Console();
