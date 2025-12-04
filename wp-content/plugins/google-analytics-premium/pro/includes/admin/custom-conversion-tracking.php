<?php

class MonsterInsights_Custom_Conversion_Tracking {

	public function __construct() {
		add_action( 'wp_ajax_monsterinsights_conversion_tracking_mark_as_key_event', array( $this, 'mark_as_key_event' ) );

		add_action( 'monsterinsights_conversion_tracking_mark_as_key_event', array( $this, 'mark_as_key_event_handler' ), 10, 2 );
	}

	public function mark_as_key_event() {
		check_ajax_referer( 'monsterinsights_gutenberg_headline_nonce', 'nonce' );

		$event_name = isset( $_POST['eventName'] ) ? sanitize_text_field( $_POST['eventName'] ) : '';

		if ( empty( $event_name ) ) {
			wp_send_json_error( 'Event name is required' );
		}

		$this->schedule_mark_as_key_event( $event_name );

		wp_send_json_success( array( 'message' => __('Event marked as key event', 'google-analytics-premium' ) ) );
	}

	/**
	 * Schedule the mark as key event.
	 *
	 * @param string $event_name The name of the event to mark as key.
	 * @return void
	 */
	public function schedule_mark_as_key_event( $event_name ) {
		$args = array(
			'event_name' => $event_name,
			'attempt'    => 1
		);

		// Check if event is already scheduled
		$scheduled = wp_next_scheduled( 'monsterinsights_conversion_tracking_mark_as_key_event', $args );

		if ( ! $scheduled ) {
			wp_schedule_single_event( time(), 'monsterinsights_conversion_tracking_mark_as_key_event', $args );
		}
	}

	/**
	 * Background handler with timeout and retry logic.
	 *
	 * @param string $event_name The name of the event to mark as key.
	 * @param int $attempt The attempt number.
	 * @return void
	 */
	public function mark_as_key_event_handler( $event_name, $attempt ) {
		$api = new MonsterInsights_API_Request( 'analytics/mark-as-key-event', [], 'POST' );

		$response = $api->request([
			'event_name' => 'mi-' . $event_name
		]);

		// Check for errors and retry if needed
		if ( is_wp_error( $response ) && $attempt < 3 ) {
			// Retry with exponential backoff (60s, 120s, 240s)
			wp_schedule_single_event(
				time() + ( 60 * $attempt ),
				'monsterinsights_conversion_tracking_mark_as_key_event',
				array(
					'event_name' => $event_name,
					'attempt'    => $attempt + 1
				)
			);
		}
	}

}

new MonsterInsights_Custom_Conversion_Tracking();
