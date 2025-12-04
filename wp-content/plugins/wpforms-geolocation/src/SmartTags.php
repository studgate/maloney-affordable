<?php

namespace WPFormsGeolocation;

/**
 * Class SmartTags.
 *
 * @since 2.0.0
 */
class SmartTags {

	/**
	 * Hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks() {

		add_filter( 'wpforms_smart_tags', [ $this, 'register_tag' ] );
		add_filter( 'wpforms_smarttags_get_smart_tag_class_name', [ $this, 'register_smart_tag_class' ], 10, 2 );
	}

	/**
	 * Register the new {entry_geolocation} smart tag.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tags List of tags.
	 *
	 * @return array $tags List of tags.
	 */
	public function register_tag( $tags ) {

		$tags['entry_geolocation'] = esc_html__( 'Entry Geolocation', 'wpforms-geolocation' );

		return $tags;
	}

	/**
	 * Register smart tag class.
	 *
	 * @since 2.11.0
	 *
	 * @param string $class_name     Class name.
	 * @param string $smart_tag_name Smart tag name.
	 *
	 * @return string
	 */
	public function register_smart_tag_class( $class_name, $smart_tag_name ): string {

		if ( $smart_tag_name !== 'entry_geolocation' ) {
			return (string) $class_name;
		}

		return EntryGeolocation::class;
	}

	/**
	 * Process the {entry_geolocation} smart tag inside email messages.
	 *
	 * @since      2.0.0
	 * @deprecated 2.3.0
	 *
	 * @param string $message Theme email message.
	 * @param object $email   WPForms_WP_Emails.
	 *
	 * @return string
	 */
	public function entry_location( $message, $email ) {

		_deprecated_function( __METHOD__, '2.3.0 of the WPForms Geolocation addon', __CLASS__ . '::email_message()' );

		return $this->email_message( $message, $email );
	}

	/**
	 * Process the {entry_geolocation} smart tag inside email messages.
	 *
	 * Deprecated Note: This function has been deprecated without notice to
	 * prevent the generation of unintended logs for users who may revert to
	 * the "Legacy" email template for specific reasons.
	 *
	 * It is advised to exercise caution and consider future modifications
	 * and extensions, as this function will be removed and unhooked at some point in the future.
	 *
	 * @since      2.3.0
	 * @since      2.11.0 Added deprecation notice.
	 * @deprecated 2.7.0
	 *
	 * @param string $message Theme email message.
	 * @param object $email   WPForms_WP_Emails.
	 *
	 * @return string
	 */
	public function email_message( $message, $email ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		$location = $this->get_location( $email->entry_id );

		if ( empty( $location ) ) {
			return $this->replace_smart_tag( $message, '' );
		}

		$geo = $email->get_content_type() === 'text/plain'
			? $this->plain_entry_location( $location )
			: $this->html_entry_location( $location, $email );

		return $this->replace_smart_tag( $message, $geo );
	}

	/**
	 * Process the {entry_geolocation} smart tag inside email messages.
	 * This function uses the new extension class to determine the correct template assigned
	 * for notification emails and sending out emails.
	 *
	 * @since      2.7.0
	 * @deprecated 2.11.0
	 *
	 * @param string $message       Email message to be processed.
	 * @param string $template_name Template name selected for sending out notification emails.
	 * @param object $email         An instance of WPForms\Emails\Notifications.
	 *
	 * @return string
	 */
	public function notifications_message( $message, $template_name, $email ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		// Retrieve the location for the entry.
		$location = $this->get_location( $email->entry_id );

		// Check if the entry has a location detected, if not, return the original message.
		if ( empty( $location ) ) {
			return $this->replace_smart_tag( $message, '' );
		}

		// Determine the location text based on the template name.
		if ( $template_name === 'none' ) {
			return $this->replace_smart_tag( $message, $this->plain_entry_location( $location ) );
		}

		$field_type = 'geolocation-addon';
		$field_name = esc_html__( 'Entry Geolocation', 'wpforms-geolocation' );
		$field_val  = $this->html_entry_location_value( $location );

		// Replace placeholders in the email field template with actual values.
		$geo_text = str_replace(
			[ '{field_type}', '{field_name}', '{field_value}' ],
			[ $field_type, $field_name, $field_val ],
			$email->field_template
		);

		// Replace the {entry_geolocation} smart tag in the message with the location text.
		return $this->replace_smart_tag( $message, $geo_text );
	}

	/**
	 * Process the {entry_geolocation} smart tag inside confirmation messages.
	 *
	 * @since      2.3.0
	 * @deprecated 2.11.0
	 *
	 * @param string $confirmation_message Confirmation message.
	 * @param array  $form_data            Form data and settings.
	 * @param array  $fields               Sanitized field data.
	 * @param int    $entry_id             Entry ID.
	 *
	 * @return string
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function confirmation_message( $confirmation_message, $form_data, $fields, $entry_id ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		$location = $this->get_location( $entry_id );

		if ( empty( $location ) ) {
			return $this->replace_smart_tag( $confirmation_message, '' );
		}

		return $this->replace_smart_tag( $confirmation_message, $this->html_entry_location_value( $location ) );
	}

	/**
	 * Replace smart tags.
	 *
	 * @since      2.3.0
	 * @deprecated 2.11.0
	 *
	 * @param string $content Content.
	 * @param string $value   Smart tag value.
	 *
	 * @return string
	 */
	private function replace_smart_tag( $content, $value ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		if ( ! $this->has_smart_tag( $content ) ) {
			return $content;
		}

		return str_replace( '{entry_geolocation}', $value, $content );
	}

	/**
	 * Determine whether the content contains the {entry_geolocation} tag.
	 *
	 * @since 2.3.0
	 * @deprecated 2.11.0
	 *
	 * @param string $content Content.
	 *
	 * @return bool
	 */
	public function has_smart_tag( $content ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		return strpos( $content, '{entry_geolocation}' ) !== false;
	}

	/**
	 * Get location.
	 *
	 * @since      2.3.0
	 * @deprecated 2.11.0
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return array
	 */
	private function get_location( $entry_id ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		$location = wpforms()->obj( 'entry_meta' )->get_meta(
			[
				'entry_id' => $entry_id,
				'type'     => 'location',
				'number'   => 1,
			]
		);

		if ( empty( $location[0] ) || ! property_exists( $location[0], 'data' ) ) {
			return [];
		}

		return json_decode( $location[0]->data, true );
	}

	/**
	 * Entry geolocation for plain/text content type mail.
	 *
	 * @since      2.0.0
	 * @deprecated 2.11.0
	 *
	 * @param array $location   Location information.
	 * @param bool  $with_title Location information title.
	 *
	 * @return string
	 */
	private function plain_entry_location( $location, $with_title = true ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		$geo = $with_title ? '--- ' . esc_html__( 'Entry Geolocation', 'wpforms-geolocation' ) . " ---\r\n\r\n" : '';

		$geo .= $location['city'] . ', ' . $location['region'] . ', ' . $location['country'] . "\r\n";

		return $geo . $location['latitude'] . ', ' . $location['longitude'] . "\r\n\r\n";
	}

	/**
	 * Entry geolocation for HTML content type mail.
	 *
	 * @since      2.0.0
	 * @deprecated 2.11.0
	 *
	 * @param array  $location Location information.
	 * @param object $email    WPForms_WP_Emails.
	 *
	 * @return string
	 */
	private function html_entry_location( $location, $email ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		ob_start();
		$email->get_template_part( 'field', $email->get_template(), true );

		$geo   = ob_get_clean();
		$geo   = str_replace( '{field_name}', esc_html__( 'Entry Geolocation', 'wpforms-geolocation' ), $geo );
		$value = $this->html_entry_location_value( $location );

		return (string) str_replace( '{field_value}', $value, $geo );
	}

	/**
	 * Get entry location HTML value.
	 *
	 * @since      2.3.0
	 * @deprecated 2.11.0
	 *
	 * @param array $location Location data.
	 *
	 * @return string
	 */
	private function html_entry_location_value( $location ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

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

		if ( ! empty( $location['latitude'] ) && ! empty( $location['longitude'] ) ) {
			$value .= '<br>' . $location['latitude'] . ', ' . $location['longitude'];
		}

		return $value;
	}

	/**
	 * Process the {entry_geolocation} smart tag inside CSV attachment content.
	 *
	 * @since      2.5.0
	 * @deprecated 2.11.0
	 *
	 * @param array $content  Content.
	 * @param int   $entry_id Entry ID.
	 *
	 * @return array
	 */
	public function csv_attachment_content( $content, $entry_id ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation addon' );

		$location = $this->get_location( $entry_id );

		if ( empty( $location ) ) {

			foreach ( $content['body'] as $key => $csv_fields ) {
				$content['body'][ $key ] = $this->replace_smart_tag( $csv_fields, '' );
			}

			return $content;
		}

		$geo = $this->plain_entry_location( $location, false );

		foreach ( $content['body'] as $key => $csv_fields ) {
			$content['body'][ $key ] = $this->replace_smart_tag( $csv_fields, $geo );
		}

		return $content;
	}
}
