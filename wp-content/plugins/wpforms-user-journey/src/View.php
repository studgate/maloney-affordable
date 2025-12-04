<?php

namespace WPFormsUserJourney;

/**
 * Class View.
 *
 * @since 1.0.3
 */
class View {

	/**
	 * Inline styles of the User Journey table in emails.
	 *
	 * @since 1.0.3
	 *
	 * @var string
	 */
	const TABLE_STYLE = 'width: 100%; border-top: 1px solid #cccccc; border-left: 1px solid #cccccc; border-collapse: collapse;';

	/**
	 * Inline styles of the User Journey table cell in emails.
	 *
	 * @since 1.0.3
	 *
	 * @var string
	 */
	const CELL_STYLE = 'border-right: 1px solid #cccccc; border-bottom: 1px solid #cccccc; padding: 6px; vertical-align: top;';

	/**
	 * Get Entry User Journey table.
	 *
	 * @since 1.0.3
	 *
	 * @param object $entry   Entry data object.
	 * @param string $context Context. Values: [ 'entries' | 'confirmation' | 'email' ]. Default: 'email'.
	 *
	 * @return string
	 */
	public function get_entry_journey_table( $entry, $context = 'email' ) {

		$form       = wpforms()->obj( 'form' )->get( $entry->form_id );
		$form_title = ! empty( $form )
			? $form->post_title
			: sprintf( /* translators: %d - form id. */
				esc_html__( 'Form (#%d)', 'wpforms-user-journey' ),
				$entry->form_id
			);

		$timestamp_prev = false;
		$style          = '';

		if ( $context === 'email' ) {
			$style = ' style="' . self::TABLE_STYLE . '"';
		}

		$output = sprintf(
			'<table width="100%%" cellspacing="0" cellpadding="0"%s>',
			$style
		);

		foreach ( $entry->user_journey as $record ) {

			$output        .= $this->get_entry_journey_record( $record, $context, $timestamp_prev );
			$timestamp_prev = strtotime( $record->date );
		}

		$output .= $this->get_entry_journey_summary( $entry, $form_title, $context, $timestamp_prev );
		$output .= '</table>';

		return $output;
	}

	/**
	 * Get Entry User Journey record row.
	 *
	 * @since 1.0.3
	 *
	 * @param object   $record         Journey record data.
	 * @param string   $context        Context. Values: [ 'entries' | 'confirmation' | 'email' ]. Default: 'email'.
	 * @param int|bool $timestamp_prev Previous record timestamp. Default: false.
	 *
	 * @return string
	 */
	private function get_entry_journey_record( $record, $context = 'email', $timestamp_prev = false ) {

		$timestamp = isset( $record->date ) ? strtotime( $record->date ) : time();
		$tpl_dir   = sanitize_key( $context );
		$url       = isset( $record->url ) ? $record->url : '';

		$output  = $this->get_entry_journey_date( $timestamp, $context, $timestamp_prev );
		$output .= wpforms_render(
			$tpl_dir . '/journal-record',
			[
				'time'     => wpforms_time_format( $timestamp, '' , true ),
				'title'    => isset( $record->title ) ? $record->title : '',
				'url'      => $url,
				'path'     => str_replace( home_url(), '', $url ),
				'params'   => isset( $record->parameters ) ? json_decode( $record->parameters, true ) : [],
				'duration' => ! empty( $record->duration ) ? human_time_diff( $timestamp - $record->duration, $timestamp ) : 0,
				'status'   => 'visit',
			],
			true
		);

		return $output;
	}

	/**
	 * Get Entry User Journey record date row.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $timestamp      Record timestamp.
	 * @param string   $context        Context. Values: [ 'entries' | 'confirmation' | 'email' ]. Default: 'email'.
	 * @param int|bool $timestamp_prev Previous record timestamp. Default: false.
	 *
	 * @return string
	 */
	private function get_entry_journey_date( $timestamp, $context, $timestamp_prev = false ) {

		if ( ! empty( $timestamp_prev ) && gmdate( 'd', $timestamp ) === gmdate( 'd', $timestamp_prev ) ) {
			return '';
		}

		$style = '';

		if ( $context === 'email' ) {
			$style = ' style="' . self::CELL_STYLE . ' font-weight: bold;"';
		}

		return sprintf(
			'<tr>
				<td colspan="3" class="date"%1$s>%2$s</td>
			</tr>',
			$style,
			esc_html( wpforms_date_format( $timestamp, '', true ) )
		);
	}

	/**
	 * Get Entry User Journey summary record.
	 *
	 * @since 1.0.3
	 *
	 * @param object   $entry          Entry data object.
	 * @param string   $form_title     Form title.
	 * @param string   $context        Context. Values: [ 'entries' | 'confirmation' | 'email' ]. Default: 'email'.
	 * @param int|bool $timestamp_prev Previous record timestamp. Default: false.
	 *
	 * @return string
	 */
	private function get_entry_journey_summary( $entry, $form_title, $context = 'email', $timestamp_prev = false ) {

		$summary = sprintf(
			/* translators: %1$s - number of steps; %2$s - total time spent. */
			__( 'User took %1$s over %2$s', 'wpforms-user-journey' ),
			sprintf(
				/* translators: Total number of steps taken. */
				_n( '%s step', '%s steps', count( $entry->user_journey ), 'wpforms-user-journey' ),
				count( $entry->user_journey )
			),
			human_time_diff( strtotime( $entry->user_journey[0]->date ), strtotime( $entry->date ) )
		);
		$tpl_dir = sanitize_key( $context );

		return wpforms_render(
			$tpl_dir . '/journal-record',
			[
				'time'     => wpforms_time_format( $entry->date, '', true ),
				'title'    => sprintf(
					'%s %s',
					$form_title,
					$entry->status === 'abandoned' ? __( 'abandoned', 'wpforms-user-journey' ) : __( 'submitted', 'wpforms-user-journey' )
				),
				'url'      => '',
				'path'     => $summary,
				'params'   => [],
				'duration' => human_time_diff( $timestamp_prev, strtotime( $entry->date ) ),
				'status'   => $entry->status === 'abandoned' ? 'abandon' : 'submit',
			],
			true
		);
	}

	/**
	 * Get Entry User Journey plain text.
	 *
	 * @since 1.0.3
	 *
	 * @param object $entry Entry data object.
	 *
	 * @return string
	 */
	public function get_entry_journey_plain_text( $entry ) {

		if ( empty( $entry->user_journey ) ) {
			return '';
		}

		$form       = wpforms()->obj( 'form' )->get( $entry->form_id );
		$form_title = ! empty( $form )
			? $form->post_title
			: sprintf( /* translators: %d - form id. */
				esc_html__( 'Form (#%d)', 'wpforms-user-journey' ),
				$entry->form_id
			);

		$output         = '';
		$timestamp_prev = false;

		foreach ( $entry->user_journey as $record ) {

			$output        .= $this->get_entry_journey_plain_text_record( $record, $timestamp_prev );
			$timestamp_prev = strtotime( $record->date );
		}

		// Summary row.
		$summary = wp_strip_all_tags(
			sprintf(
				/* translators: %1$s - number of steps; %2$s - total time spent. */
				__( 'User took %1$s over %2$s', 'wpforms-user-journey' ),
				sprintf(
					/* translators: Total number of steps taken. */
					_n( '%s step', '%s steps', count( $entry->user_journey ), 'wpforms-user-journey' ),
					count( $entry->user_journey )
				),
				human_time_diff( strtotime( $entry->user_journey[0]->date ), strtotime( $entry->date ) )
			)
		);

		$time     = wpforms_time_format( $entry->date, '', true );
		$title    = sprintf(
			'%s %s',
			$form_title,
			wp_strip_all_tags( __( 'submitted', 'wpforms-user-journey' ) )
		);
		$duration = human_time_diff( $timestamp_prev, strtotime( $entry->date ) );

		$output .= "- {$time} - {$title} - {$summary} - {$duration}" . PHP_EOL;

		return $output;
	}

	/**
	 * Get Entry User Journey plain text record row.
	 *
	 * @since 1.0.3
	 *
	 * @param object   $record         Journey record data.
	 * @param int|bool $timestamp_prev Previous record timestamp. Default: false.
	 *
	 * @return string
	 */
	private function get_entry_journey_plain_text_record( $record, $timestamp_prev = false ) {

		$timestamp = isset( $record->date ) ? strtotime( $record->date ) : time();
		$url       = isset( $record->url ) ? ' - ' . $record->url : '';
		$output    = '';

		if ( empty( $timestamp_prev ) || gmdate( 'd', $timestamp ) !== gmdate( 'd', $timestamp_prev ) ) {
			$output .= esc_html( wpforms_date_format( $timestamp, '', true ) ) . PHP_EOL;
		}

		$time     = wpforms_time_format( $timestamp, '', true );
		$title    = isset( $record->title ) ? $record->title : wp_strip_all_tags( __( 'No title', 'wpforms-user-journey' ) );
		$duration = ! empty( $record->duration ) ? ' - ' . human_time_diff( $timestamp - $record->duration, $timestamp ) : '';

		$output .= "- {$time} - {$title}{$duration}{$url}" . PHP_EOL;

		return $output;
	}
}
