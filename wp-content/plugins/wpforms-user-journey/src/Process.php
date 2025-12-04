<?php

namespace WPFormsUserJourney;

/**
 * User Journey processing.
 *
 * @since 1.0.0
 */
class Process {

	/**
	 * Initialize.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Process hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() {

		add_action( 'wpforms_process_entry_saved', [ $this, 'process_entry_meta' ], 10, 4 );
	}

	/**
	 * Check if user journey data present and process.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $fields    Final/sanitized submitted field data.
	 * @param array  $entry     Copy of original $_POST.
	 * @param array  $form_data Form data and settings.
	 * @param string $entry_id  Entry ID.
	 */
	public function process_entry_meta( $fields, $entry, $form_data, $entry_id ) {

		// Check if form has entries disabled.
		if ( isset( $form_data['settings']['disable_entries'] ) ) {
			return;
		}

		if ( ! isset( $_COOKIE['_wpfuj'] ) ) {
			return;
		}

		$journey = json_decode( wp_unslash( $_COOKIE['_wpfuj'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Clean up the cookie if the JSON is invalid.
		if ( ! is_array( $journey ) || empty( $journey ) ) {
			$this->cleanup_cookie();

			return;
		}

		$this->save_journey( $journey, $form_data, $entry_id );
		$this->cleanup_cookie();
	}

	/**
	 * Save journey to the entry.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $journey   List of records.
	 * @param array  $form_data Form data and settings.
	 * @param string $entry_id  Entry ID.
	 */
	private function save_journey( $journey, $form_data, $entry_id ) {

		$count          = 1;
		$timestamp_prev = 0;

		foreach ( $journey as $timestamp => $record ) {

			$item = $this->get_record_data( $record, $timestamp, $count );

			$count ++;

			if ( empty( $item ) ) {
				continue;
			}

			$item['entry_id'] = absint( $entry_id );
			$item['form_id']  = absint( $form_data['id'] );
			$item['duration'] = ! empty( $timestamp_prev ) ? absint( $timestamp ) - absint( $timestamp_prev ) : 0;

			wpforms_user_journey()->db->add( $item );
			$timestamp_prev = $timestamp;
		}
	}

	/**
	 * Replace cookie with empty one.
	 *
	 * @since 1.1.0
	 */
	private function cleanup_cookie() {

		setcookie( '_wpfuj', '', time() - 3600, '/' );
	}

	/**
	 * Get record from the string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $record    Record string.
	 * @param int    $timestamp Timestamp.
	 * @param int    $step      Current step.
	 *
	 * @return array
	 */
	private function get_record_data( $record, $timestamp, $step ) {

		if ( empty( $record ) || strpos( $record, '|#|' ) === false ) {
			return [];
		}

		$parts = explode( '|#|', $record );
		$url   = esc_url_raw( strtok( $parts[0], '?' ) );

		if ( 1 !== $step && false === strpos( $url, home_url() ) ) {
			return [];
		}

		$item = [
			'post_id'    => ! empty( $parts[2] ) ? absint( $parts[2] ) : 0,
			'url'        => $url,
			'parameters' => '',
			'title'      => ! empty( $parts[1] ) ? sanitize_text_field( $parts[1] ) : '',
			'external'   => strpos( $parts[0], home_url() ) === false,
			'step'       => $step,
			'date'       => gmdate( 'Y-m-d H:i:s', absint( $timestamp ) ),
		];

		$query_component = wp_parse_url( $parts[0], PHP_URL_QUERY );

		if ( ! empty( $query_component ) ) {
			parse_str( $query_component, $params );
		}

		if ( ! empty( $params ) ) {
			$parameters = [];
			foreach ( $params as $key => $value ) {
				$parameters[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
			$item['parameters'] = wp_json_encode( $parameters );
		}

		if ( $step === 1 && strpos( $item['title'], '{ReferrerPageTitle}' ) !== false ) {
			$title         = $this->get_html_page_title( $url );
			$item['title'] = ! empty( $title )
				? sanitize_text_field( $title )
				: str_replace( '{ReferrerPageTitle}', __( 'Referrer', 'wpforms-user-journey' ), $item['title'] );
		}

		return $item;
	}

	/**
	 * Get the page <title> from a given URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Page URL.
	 *
	 * @return string
	 */
	public function get_html_page_title( $url ) {

		if ( ! apply_filters( 'wpforms_user_journey_process_referrer_page_title', true ) ) {
			return '';
		}

		$request = wp_remote_get( $url );

		if ( 'OK' !== wp_remote_retrieve_response_message( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return '';
		}

		$response = wp_remote_retrieve_body( $request );

		preg_match( '/<title>(.*)<\/title>/i', $response, $matches );

		return ! empty( $matches[1] ) ? trim( $matches[1] ) : '';
	}
}
