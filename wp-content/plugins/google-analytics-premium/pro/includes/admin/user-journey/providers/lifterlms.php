<?php
/**
 * This file contains the code to display metabox for LifterLMS Admin Orders Page.
 *
 * @since 8.5.0
 *
 * @package MonsterInsights
 * @subpackage MonsterInsights_User_Journey
 */

/**
 * Class to add metabox to LifterLMS admin order page.
 *
 * @since 8.5.0
 */
class MonsterInsights_Pro_User_Journey_LifterLMS_Metabox extends MonsterInsights_User_Journey_Pro_Metabox {

	/**
	 * Class constructor.
	 *
	 * @since 8.5.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_user_journey_metabox' ) );
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
			'post'   => sanitize_text_field( $_GET['post'] ), // phpcs:ignore
			'action' => sanitize_text_field( $_GET['action'] ), // phpcs:ignore
		), admin_url( 'post.php' ) );
	}

	/**
	 * Add metabox
	 *
	 * @return void
	 * @since 8.5.0
	 *
	 * @uses add_meta_boxes WP Hook
	 *
	 */
	public function add_user_journey_metabox() {
		add_meta_box(
			'lifterlms-monsterinsights-pro-user-journey-metabox',
			esc_html__( 'User Journey by MonsterInsights', 'monsterinsights' ),
			array( $this, 'display_meta_box' ),
			'llms_order',
			'normal',
			'core'
		);
	}

	/**
	 * Display metabox HTML.
	 *
	 * @param object $post LifterLMS Order custom post
	 *
	 * @return void
	 * @since 8.5.0
	 *
	 */
	public function display_meta_box( $post ) {
		$this->metabox_html( $post );
	}
}

if ( function_exists( 'llms' ) ) {
	new MonsterInsights_Pro_User_Journey_LifterLMS_Metabox();
}
