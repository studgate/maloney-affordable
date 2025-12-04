<?php

namespace WPFormsUserJourney;

/**
 * User Journey form frontend related functionality.
 *
 * @since 1.0.0
 */
class Frontend {

	/**
	 * Initialize.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Frontend hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() {

		add_action( 'wp_head', [ $this, 'enqueues' ] );
	}

	/**
	 * Frontend enqueues.
	 *
	 * @since 1.0.0
	 */
	public function enqueues() {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-user-journey',
			wpforms_user_journey()->url . "assets/js/wpforms-user-journey{$min}.js",
			[],
			WPFORMS_USER_JOURNEY_VERSION,
			false
		);

		$data = [
			'is_ssl' => is_ssl(),
		];

		if ( is_singular() ) {
			$data['page_id'] = get_the_ID();
		}

		wp_localize_script( 'wpforms-user-journey', 'wpforms_user_journey', $data );
	}
}
