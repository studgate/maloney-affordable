<?php

namespace WPFormsUserJourney\Admin;

/**
 * Class for the form-related things in the admin area.
 *
 * @since 1.0.0
 */
class Form {

	/**
	 * Init the class.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		$this->hooks();

		return $this;
	}

	/**
	 * Hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() {

		if ( wpforms_is_admin_page( 'overview' ) ) {
			add_action( 'wpforms_delete_form', [ $this, 'delete_form_related_records' ] );
		}
	}

	/**
	 * Clean records that related to the deleted form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form_ids The collection with form IDs, which were deleted.
	 */
	public function delete_form_related_records( $form_ids ) {

		foreach ( $form_ids as $form_id ) {

			// If the user doesn't have permissions - the form wasn't deleted. So, nothing to do.
			if ( wpforms_current_user_can( 'delete_form_single', $form_id ) ) {
				wpforms_user_journey()->db->delete_by( 'form_id', $form_id );
			}
		}
	}
}
