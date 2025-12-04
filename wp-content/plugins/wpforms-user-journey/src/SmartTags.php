<?php

namespace WPFormsUserJourney;

/**
 * Class SmartTags.
 *
 * @since 1.0.3
 */
class SmartTags {

	/**
	 * Smart tag.
	 *
	 * @since 1.0.3
	 *
	 * @var string
	 */
	const SMART_TAG = 'entry_user_journey';

	/**
	 * Init.
	 *
	 * @since 1.0.3
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.0.3
	 */
	private function hooks() {

		add_filter( 'wpforms_email_message', [ $this, 'email_message' ], 10, 2 );
		add_filter( 'wpforms_frontend_confirmation_message', [ $this, 'confirmation_message' ], 10, 4 );
		add_filter( 'wpforms_smart_tags', [ $this, 'register_tag' ] );
		add_filter( 'wpforms_pro_admin_builder_notifications_advanced_entry_csv_attachment_content', [ $this, 'csv_attachment_content' ], 10, 2 );
		add_filter( 'wpforms_emails_notifications_message', [ $this, 'notifications_message' ], 10, 3 );
		add_filter( 'wpforms_process_smart_tags', [ $this, 'process_smart_tag_google_sheet' ], 10, 5 );
	}

	/**
	 * Process the {entry_user_journey} smart tag inside CSV attachment content.
	 *
	 * @since 1.1.0
	 *
	 * @param array $content  Content.
	 * @param int   $entry_id Entry ID.
	 *
	 * @return array
	 */
	public function csv_attachment_content( $content, $entry_id ) {

		$entry        = $this->get_entry_with_journey( $entry_id );
		$journey_text = $this->plain_email_entry_journey( $entry, false );

		foreach ( $content['body'] as $key => $csv_fields ) {
			$content['body'][ $key ] = $this->replace_smart_tag( $csv_fields, $journey_text );
		}

		return $content;
	}

	/**
	 * Register the new {entry_user_journey} smart tag.
	 *
	 * @since 1.0.3
	 *
	 * @param array $tags List of tags.
	 *
	 * @return array $tags List of tags.
	 */
	public function register_tag( $tags ) {

		$tags[ self::SMART_TAG ] = esc_html__( 'Entry User Journey', 'wpforms-user-journey' );

		return $tags;
	}

	/**
	 * Process the {entry_user_journey} smart tag inside email messages.
	 *
	 * Deprecated Note: This function has been deprecated without notice to
	 * prevent the generation of unintended logs for users who may revert to
	 * the "Legacy" email template for specific reasons.
	 *
	 * It is advised to exercise caution and consider future modifications
	 * and extensions, as this function will be removed and unhooked at some point in the future.
	 *
	 * @since 1.0.3
	 *
	 * @depecated 1.2.0
	 *
	 * @param string $message Theme email message.
	 * @param object $email   WPForms_WP_Emails.
	 *
	 * @return string
	 */
	public function email_message( $message, $email ) {

		$entry = $this->get_entry_with_journey( $email->entry_id );

		if ( empty( $entry->user_journey ) ) {
			return $this->replace_smart_tag( $message, '' );
		}

		$journey_text = $email->get_content_type() === 'text/plain'
			? $this->plain_email_entry_journey( $entry )
			: $this->html_email_entry_journey( $entry, $email );

		return $this->replace_smart_tag( $message, $journey_text );
	}

	/**
	 * Process the {entry_user_journey} smart tag inside email messages.
	 * This function uses the new extension class to determine the correct template assigned
	 * for notification emails and sending out emails.
	 *
	 * @since 1.2.0
	 *
	 * @param string $message       Email message to be processed.
	 * @param string $template_name Template name selected for sending out notification emails.
	 * @param object $email         An instance of WPForms\Emails\Notifications.
	 *
	 * @return string
	 */
	public function notifications_message( $message, $template_name, $email ) {

		// Retrieve the entry with the user journey.
		$entry = $this->get_entry_with_journey( $email->entry_id );

		// Check if the entry has a user journey, if not, return the original message.
		if ( empty( $entry->user_journey ) ) {
			return $this->replace_smart_tag( $message, '' );
		}

		// Determine the journey text based on the template name.
		if ( $template_name === 'none' ) {
			return $this->replace_smart_tag( $message, $this->plain_email_entry_journey( $entry ) );
		}

		$field_type = 'user-journey-addon';
		$field_name = esc_html__( 'Entry User Journey', 'wpforms-user-journey' );
		$field_val  = wpforms_user_journey()->view->get_entry_journey_table( $entry );

		// Replace placeholders in the email field template with actual values.
		$journey_text = str_replace(
			[ '{field_type}', '{field_name}', '{field_value}' ],
			[ $field_type, $field_name, $field_val ],
			$email->field_template
		);

		// Replace the {entry_user_journey} smart tag in the message with the journey text.
		return $this->replace_smart_tag( $message, $journey_text );
	}

	/**
	 * Process the {entry_user_journey} smart tag inside confirmation messages.
	 *
	 * @since 1.0.3
	 *
	 * @param string $confirmation_message Confirmation message.
	 * @param array  $form_data            Form data and settings.
	 * @param array  $fields               Sanitized field data.
	 * @param int    $entry_id             Entry ID.
	 *
	 * @return string
	 */
	public function confirmation_message( $confirmation_message, $form_data, $fields, $entry_id ) {

		$entry = $this->get_entry_with_journey( $entry_id );

		if ( empty( $entry->user_journey ) ) {
			return $this->replace_smart_tag( $confirmation_message, '' );
		}

		$output  = sprintf(
			'<h4>%s</h4>',
			esc_html__( 'Entry User Journey', 'wpforms-user-journey' )
		);
		$output .= wpforms_user_journey()->view->get_entry_journey_table( $entry, 'confirmation' );

		return $this->replace_smart_tag( $confirmation_message, $output );
	}

	/**
	 * Process smart tag in the Google Sheets.
	 *
	 * @since 1.4.0
	 *
	 * @param string|mixed $content   Content.
	 * @param array        $form_data Form data.
	 * @param array        $fields    List of fields.
	 * @param string       $entry_id  Entry ID.
	 * @param string       $context   Context.
	 *
	 * @return string
	 */
	public function process_smart_tag_google_sheet( $content, array $form_data, array $fields, string $entry_id, string $context = '' ): string {

		$content = (string) $content;

		if ( $context !== 'google-sheets-custom-value' ) {
			return $content;
		}

		$entry = $this->get_entry_with_journey( $entry_id );

		if ( empty( $entry->user_journey ) ) {
			return $this->replace_smart_tag( $content, '' );
		}

		$output = $this->plain_email_entry_journey( $entry, false );

		return $this->replace_smart_tag( $content, $output );
	}

	/**
	 * Replace smart tags.
	 *
	 * @since 1.0.3
	 *
	 * @param string $content Content.
	 * @param string $value   Smart tag value.
	 *
	 * @return string
	 */
	private function replace_smart_tag( $content, $value ) {

		return str_replace( '{' . self::SMART_TAG . '}', $value, $content );
	}

	/**
	 * Get journey.
	 *
	 * @since 1.0.3
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return object
	 */
	private function get_entry_with_journey( $entry_id ) {

		$entry = wpforms()->obj( 'entry' )->get( $entry_id );

		if ( empty( $entry ) ) {
			return $entry;
		}

		$journey = wpforms_user_journey()->db->get_rows( [ 'entry_id' => $entry_id ] );

		if ( ! empty( $journey ) ) {
			$entry->user_journey = $journey;
		}

		return $entry;
	}

	/**
	 * Entry user journey for plain/text content type mail.
	 *
	 * @since 1.0.3
	 * @since 1.1.0 Added $with_title parameter.
	 *
	 * @param object $entry      Entry with journey data.
	 * @param bool   $with_title Location information title.
	 *
	 * @return string
	 */
	private function plain_email_entry_journey( $entry, $with_title = true ) {

		$text  = $with_title ? '--- ' . esc_html__( 'Entry User Journey', 'wpforms-user-journey' ) . " ---\r\n\r\n" : '';
		$text .= wpforms_user_journey()->view->get_entry_journey_plain_text( $entry );

		return $text . "\r\n\r\n";
	}

	/**
	 * Entry user journey for HTML content type mail.
	 *
	 * @since 1.0.3
	 *
	 * @param object $entry Entry with journey data.
	 * @param object $email WPForms_WP_Emails.
	 *
	 * @return string
	 */
	private function html_email_entry_journey( $entry, $email ) {

		ob_start();
		$email->get_template_part( 'field', $email->get_template(), true );

		$html  = ob_get_clean();
		$html  = str_replace( '{field_name}', esc_html__( 'Entry User Journey', 'wpforms-user-journey' ), $html );
		$value = wpforms_user_journey()->view->get_entry_journey_table( $entry, 'email' );

		return (string) str_replace( '{field_value}', $value, $html );
	}
}
