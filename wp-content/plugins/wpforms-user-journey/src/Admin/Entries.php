<?php

namespace WPFormsUserJourney\Admin;

use WPForms_Entries_Single;

/**
 * User Journey admin entries.
 *
 * @since 1.0.0
 */
class Entries {

	/**
	 * Init the class.
	 *
	 * @since 1.0.0
	 *
	 * @return Entries
	 */
	public function init(): Entries {

		$this->hooks();

		return $this;
	}

	/**
	 * Entry hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() {

		if ( wpforms_is_admin_page( 'entries', 'details' ) ) {
			add_action( 'wpforms_entry_details_init', [ $this, 'get_user_journey' ], 10, 1 );
			add_action( 'wpforms_entries_enqueue', [ $this, 'enqueues' ] );
			add_action( 'wpforms_entry_details_content', [ $this, 'metabox' ], 20, 2 );
		}

		if ( wpforms_is_admin_page( 'entries', 'list' ) ) {
			add_action( 'wpforms_post_delete_entries', [ $this, 'delete_entry_related_records' ] );
		}
	}

	/**
	 * Get user journey if available for entry.
	 *
	 * @since 1.0.0
	 *
	 * @param WPForms_Entries_Single $entries Single form entry.
	 */
	public function get_user_journey( $entries ) {

		$records = wpforms_user_journey()->db->get_rows(
			[
				'entry_id' => $entries->entry->entry_id,
			]
		);

		if ( ! empty( $records ) ) {
			$entries->entry->user_journey = $records;
		}
	}

	/**
	 * Load enqueues.
	 *
	 * @since 1.0.0
	 */
	public function enqueues() {

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-user-journey',
			wpforms_user_journey()->url . "assets/css/admin/entries-user-journey{$min}.css",
			null,
			WPFORMS_USER_JOURNEY_VERSION
		);

		wp_enqueue_script(
			'wpforms-user-journey',
			wpforms_user_journey()->url . "assets/js/admin/entries-user-journey{$min}.js",
			[ 'jquery' ],
			WPFORMS_USER_JOURNEY_VERSION,
			false
		);
	}

	/**
	 * Display user journey if found.
	 *
	 * @since 1.0.0
	 *
	 * @param object $entry     Entry data.
	 * @param array  $form_data Form data.
	 */
	public function metabox( $entry, $form_data ) {

		$form_title = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : '';

		if ( empty( $form_title ) ) {
			$form = wpforms()->obj( 'form' )->get( $entry->form_id );

			$form_title = ! empty( $form )
				? $form->post_title
				: sprintf( /* translators: %d - form id. */
					esc_html__( 'Form (#%d)', 'wpforms-user-journey' ),
					$entry->form_id
				);
		}

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'entries/metabox',
			[
				'entry'      => $entry,
				'form_title' => $form_title,
			],
			true
		);
	}

	/**
	 * Clean records that related to the deleted entry.
	 *
	 * @since 1.0.0
	 *
	 * @param int $entry_id The deleted Entry ID.
	 */
	public function delete_entry_related_records( $entry_id ) {

		// phpcs:disable WordPress.Security.NonceVerification
		if (
			empty( $_GET['entry_id'] ) ||
			empty( $_GET['action'] ) ||
			empty( $entry_id )
		) {
			return;
		}

		$entry_ids = is_array( $_GET['entry_id'] ) ? array_map( 'absint', $_GET['entry_id'] ) : [ absint( $_GET['entry_id'] ) ];
		$action    = sanitize_key( wp_unslash( $_GET['action'] ) );
		// phpcs:enable WordPress.Security.NonceVerification

		if ( 'delete' === $action && in_array( $entry_id, $entry_ids, true ) ) {
			wpforms_user_journey()->db->delete_by( 'entry_id', $entry_id );
		}
	}
}
