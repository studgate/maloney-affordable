<?php
/**
 * This file contains the code to display metabox for Restrict Content Pro Admin Orders Page.
 *
 * @since 8.7.0
 *
 * @package MonsterInsights
 * @subpackage MonsterInsights_User_Journey
 */

/**
 * Class to add metabox to Restrict Content Pro admin order page.
 *
 * @since 8.7.0
 */
class MonsterInsights_Pro_User_Journey_Restrict_Content_Pro_Metabox extends MonsterInsights_User_Journey_Pro_Metabox {

	/**
	 * Class constructor.
	 *
	 * @since 8.7.0
	 */
	public function __construct() {
		add_action( 'rcp_edit_payment_after', array( $this, 'add_user_journey_metabox' ), 10, 3 );
	}

	/**
	 * Check if we are on RCP Edit Order page.
	 *
	 * @return boolean
	 * @since 8.7.0
	 *
	 */
	public function is_rcp_order_screen() {
		if ( ! $this->is_valid_array( $_GET, 'page', true ) ) {
			return false;
		}

		if ( ! $this->is_valid_array( $_GET, 'payment_id', true ) ) {
			return false;
		}

		if ( ! $this->is_valid_array( $_GET, 'view', true ) ) {
			return false;
		}

		if ( 'rcp-payments' !== $_GET['page'] && 'edit-payment' !== $_GET['view'] ) { // phpcs:ignore
			return false;
		}

		return true;
	}

	/**
	 * Get Provider Admin URL.
	 *
	 * @return string
	 * @since 8.7.0
	 *
	 */
	protected function get_provider_admin_url() {
		return add_query_arg( array(
			'page'       => sanitize_text_field( $_GET['page'] ), // phpcs:ignore
			'payment_id' => sanitize_text_field( $_GET['payment_id'] ), // phpcs:ignore
			'view'       => sanitize_text_field( $_GET['view'] ), // phpcs:ignore
		), admin_url( 'admin.php' ) );
	}

	/**
	 * Add metabox
	 *
	 * @param object $payment RCP Payment Object
	 * @param object $membership_level RCP Membership level Object
	 * @param object $uer WordPress User Info from RCP
	 *
	 * @return void
	 * @since 8.7.0
	 *
	 */
	public function add_user_journey_metabox( $payment, $membership_level, $user ) {
		if ( ! $this->is_rcp_order_screen() ) {
			return;
		}

		?>
		<tr>
			<td colspan="2">
				<?php $this->metabox_html(); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Display metabox HTML.
	 *
	 * @return void
	 * @since 8.7.0
	 *
	 */
	public function metabox_title() {
		?>
		<div class="monsterinsights-metabox-title">
			<h2><?php esc_html_e( 'User Journey by MonsterInsights', 'monsterinsights' ); ?></h2>
		</div>
		<?php
	}
}

if ( class_exists( 'Restrict_Content_Pro' ) ) {
	new MonsterInsights_Pro_User_Journey_Restrict_Content_Pro_Metabox();
}
