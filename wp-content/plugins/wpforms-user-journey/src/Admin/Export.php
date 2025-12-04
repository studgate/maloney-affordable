<?php

namespace WPFormsUserJourney\Admin;

/**
 * User Journey admin export.
 *
 * @since 1.4.0
 */
class Export {

	/**
	 * Init the class.
	 *
	 * @since 1.4.0
	 *
	 * @return Export
	 */
	public function init(): Export {

		$this->hooks();

		return $this;
	}

	/**
	 * Entry hooks.
	 *
	 * @since 1.4.0
	 */
	public function hooks() {

		add_action( 'wpforms_pro_admin_entries_export_additional_info_fields', [ $this, 'add_additional_info_field' ] );
		add_filter( 'wpforms_pro_admin_entries_export_ajax_get_additional_info_value', [ $this, 'get_additional_info_value' ], 10, 3 );
	}

	/**
	 * Add User Journey info to the additional export fields.
	 *
	 * @since 1.4.0
	 *
	 * @param array|mixed $additional_fields Additional export fields.
	 *
	 * @return array
	 */
	public function add_additional_info_field( $additional_fields ): array {

		$additional_fields                 = (array) $additional_fields;
		$additional_fields['user_journey'] = esc_html__( 'User Journey information', 'wpforms-user-journey' );

		return $additional_fields;
	}

	/**
	 * Get the value of additional information column.
	 *
	 * @since 1.4.0
	 *
	 * @param string|mixed $val    The value.
	 * @param string       $col_id Column id.
	 * @param object       $entry  Entry object.
	 *
	 * @return string
	 */
	public function get_additional_info_value( $val, string $col_id, $entry ): string {

		$val = (string) $val;

		if ( $col_id !== 'user_journey' || empty( $entry['entry_id'] ) ) {
			return $val;
		}

		$journey   = wpforms_user_journey()->db->get_rows( [ 'entry_id' => $entry['entry_id'] ] );
		$entry_obj = (object) $entry;

		if ( ! empty( $journey ) ) {
			$entry_obj->user_journey = $journey;
		}

		return wpforms_user_journey()->view->get_entry_journey_plain_text( $entry_obj );
	}
}
