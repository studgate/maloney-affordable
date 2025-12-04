<?php
/**
 * This file contains the code to display metabox for MemberPress Admin Orders action.
 *
 * @since 8.7.0
 *
 * @package MonsterInsights
 * @subpackage MonsterInsights_User_Journey
 */

/**
 * Class to add metabox to MemberPress admin order page.
 *
 * @since 8.7.0
 */
class MonsterInsights_Pro_User_Journey_MemberPress_Metabox extends MonsterInsights_User_Journey_Pro_Metabox {

	/**
	 * Class constructor.
	 *
	 * @since 8.7.0
	 */
	public function __construct() {
		add_action( 'mepr_edit_transaction_table_after', array( $this, 'add_user_journey_metabox' ), 10 );
	}

	/**
	 * Check if we are on MemberPress edit order screen.
	 *
	 * @return boolean
	 * @since 8.7.0
	 *
	 */
	public function is_memberpress_order_screen() {
		if ( ! $this->is_valid_array( $_GET, 'page', true ) ) {
			return false;
		}

		if ( ! $this->is_valid_array( $_GET, 'action', true ) ) {
			return false;
		}

		if ( ! $this->is_valid_array( $_GET, 'id', true ) ) {
			return false;
		}

		if ( 'memberpress-trans' !== $_GET['page'] && 'edit' !== $_GET['action'] ) { // phpcs:ignore
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
			'page'   => sanitize_text_field( $_GET['page'] ), // phpcs:ignore
			'action' => sanitize_text_field( $_GET['action'] ), // phpcs:ignore
			'id'     => sanitize_text_field( $_GET['id'] ), // phpcs:ignore
		), admin_url( 'admin.php' ) );
	}

	/**
	 * Add metabox
	 *
	 * @return void
	 * @since 8.7.0
	 *
	 */
	public function add_user_journey_metabox( $txn ) {
		if ( ! $this->is_memberpress_order_screen() ) {
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

if ( defined( 'MEPR_VERSION' ) ) {
	new MonsterInsights_Pro_User_Journey_MemberPress_Metabox();
}
