<?php

namespace WPFormsGeolocation;

use WPForms\SmartTags\SmartTag\SmartTag;

/**
 * Class EntryGeolocation.
 *
 * @since 2.11.0
 */
class EntryGeolocation extends SmartTag {

	/**
	 * Get smart tag value.
	 *
	 * @since 2.11.0
	 *
	 * @param array  $form_data Form data.
	 * @param array  $fields    List of fields.
	 * @param string $entry_id  Entry ID.
	 *
	 * @return string
	 */
	public function get_value( $form_data, $fields = [], $entry_id = '' ): string {

		$location = $this->get_location( (int) $entry_id, (array) $form_data );

		if ( empty( $location ) ) {
			return '';
		}

		if ( in_array( $this->context, [ 'email', 'notification' ], true ) ) {
			return $this->email_location( $location );
		}

		return $this->get_formatted_location_value( $location );
	}


	/**
	 * Receive entry location.
	 *
	 * @since 2.11.0
	 *
	 * @param int   $entry_id  Entry ID.
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function get_location( int $entry_id, array $form_data ): array {

		if ( ! wpforms_is_collecting_ip_allowed( $form_data ) ) {
			return [];
		}

		if ( empty( $entry_id ) ) {
			$user_ip = wpforms_get_ip();

			return $user_ip ? $this->retrieve_from_api( $user_ip ) : [];
		}

		$entry_meta_handler = wpforms()->obj( 'entry_meta' );

		if ( ! $entry_meta_handler ) {
			return [];
		}

		$location = $entry_meta_handler->get_meta(
			[
				'entry_id' => $entry_id,
				'type'     => 'location',
				'number'   => 1,
			]
		);

		if ( ! empty( $location[0] ) && property_exists( $location[0], 'data' ) ) {
			return (array) json_decode( $location[0]->data, true );
		}

		return $this->retrieve_entry_location( $entry_id, $form_data );
	}

	/**
	 * Retrieve location from API.
	 *
	 * @since 2.11.0
	 *
	 * @param int   $entry_id  Entry ID.
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function retrieve_entry_location( int $entry_id, array $form_data ): array {

		$entry_handler      = wpforms()->obj( 'entry' );
		$entry_meta_handler = wpforms()->obj( 'entry_meta' );

		if ( ! $entry_handler || ! $entry_meta_handler ) {
			return [];
		}

		$entry      = $entry_handler->get( $entry_id );
		$ip_address = $entry->ip_address ?? '';

		if ( empty( $ip_address ) ) {
			return [];
		}

		$location = $this->retrieve_from_api( $ip_address );

		if ( ! $location ) {
			return [];
		}

		$form_id = $form_data['id'] ?? 0;

		$entry_meta_handler->add(
			[
				'entry_id' => absint( $entry_id ),
				'form_id'  => absint( $form_id ),
				'type'     => 'location',
				'data'     => wp_json_encode( $location ),
			],
			'entry_meta'
		);

		return $location;
	}

	/**
	 * Retrieve location from API.
	 *
	 * @since 2.11.0
	 *
	 * @param string $ip_address IP address.
	 *
	 * @return array
	 */
	private function retrieve_from_api( string $ip_address ): array {

		$retrieve_geo_data = new RetrieveGeoData();
		$location          = $retrieve_geo_data->get_location( $ip_address );

		return $location && is_array( $location ) ? $location : [];
	}

	/**
	 * Email location.
	 *
	 * @since 2.11.0
	 *
	 * @param array $location Location information.
	 *
	 * @return string
	 */
	private function email_location( array $location ): string {

		$process = wpforms()->obj( 'process' );

		if ( ! $process ) {
			return '';
		}

		$email_handler = $process->get_email_handler();

		if ( empty( $email_handler ) || ! is_object( $email_handler ) ) {
			return '';
		}

		if ( $this->context === 'email' ) {
			return $this->legacy_email_template( $location, $email_handler );
		}

		return $this->modern_email_template( $location, $email_handler );
	}

	/**
	 * Modern email template.
	 *
	 * @since 2.11.0
	 *
	 * @param array  $location      Location information.
	 * @param object $email_handler Email handler.
	 *
	 * @return string
	 */
	private function modern_email_template( array $location, $email_handler ): string {

		if ( ! method_exists( $email_handler, 'get_current_template' ) ) {
			return '';
		}

		// Determine the location text based on the template name.
		if ( $email_handler->get_current_template() === 'none' ) {
			return "\r\n\r\n" . $this->plain_email_value( $location );
		}

		if ( ! method_exists( $email_handler, 'get_current_field_template' ) ) {
			return '';
		}

		$field_type = 'geolocation-addon';
		$field_name = esc_html__( 'Entry Geolocation', 'wpforms-geolocation' );
		$field_val  = $this->get_formatted_location_value( $location );

		// Replace placeholders in the email field template with actual values.
		return str_replace(
			[ '{field_type}', '{field_name}', '{field_value}' ],
			[ $field_type, $field_name, $field_val ],
			$email_handler->get_current_field_template()
		);
	}

	/**
	 * Legacy email template.
	 *
	 * @since 2.11.0
	 *
	 * @param array  $location      Location information.
	 * @param object $email_handler Email handler.
	 *
	 * @return string
	 */
	private function legacy_email_template( array $location, $email_handler ): string {

		if ( ! method_exists( $email_handler, 'get_content_type' ) ) {
			return '';
		}

		if ( $email_handler->get_content_type() === 'text/plain' ) {
			return $this->plain_email_value( $location );
		}

		if ( ! method_exists( $email_handler, 'get_template' ) ) {
			return '';
		}

		ob_start();
		$email_handler->get_template_part( 'field', $email_handler->get_template() );

		$geo   = ob_get_clean();
		$geo   = str_replace( '{field_name}', esc_html__( 'Entry Geolocation', 'wpforms-geolocation' ), $geo );
		$value = $this->get_formatted_location_value( $location );

		return (string) str_replace( '{field_value}', $value, $geo );
	}

	/**
	 * Entry geolocation for plain/text content type mail.
	 *
	 * @since 2.11.0
	 *
	 * @param array $location Location information.
	 *
	 * @return string
	 */
	private function plain_email_value( array $location ): string {

		$geo = '--- ' . esc_html__( 'Entry Geolocation', 'wpforms-geolocation' ) . " ---\r\n\r\n";

		$geo .= $this->get_formatted_location_value( $location );

		return $geo . "\r\n\r\n";
	}

	/**
	 * Get formatted location value.
	 *
	 * @since 2.11.0
	 *
	 * @param array $location Location data.
	 *
	 * @return string
	 */
	private function get_formatted_location_value( array $location ): string {

		$value = implode(
			', ',
			array_filter(
				[
					! empty( $location['city'] ) ? $location['city'] : '',
					! empty( $location['region'] ) ? $location['region'] : '',
					! empty( $location['country'] ) ? $location['country'] : '',
				]
			)
		);

		if ( ! empty( $value ) ) {
			$value .= "\r\n";
		}

		if ( ! empty( $location['latitude'] ) && ! empty( $location['longitude'] ) ) {
			$value .= $location['latitude'] . ', ' . $location['longitude'];
		}

		return $value;
	}
}
