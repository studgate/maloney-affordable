<?php

/**
 * Metabox Pro class.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'MonsterInsights_MetaBox_ExcludePage_Pro' ) ) {
	class MonsterInsights_MetaBox_ExcludePage_Pro {

		public function __construct() {
			add_action( 'save_post', [ $this, 'save_skip_field' ] );
		}

		public function save_skip_field( $post_id ) {
			if ( ! isset( $_POST['monsterinsights_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['monsterinsights_metabox_nonce'], 'monsterinsights_metabox' ) ) { // phpcs:ignore
				return;
			}

			$skipped = intval( isset( $_POST['_monsterinsights_skip_tracking'] ) ? $_POST['_monsterinsights_skip_tracking'] : 0 );

			update_post_meta( $post_id, '_monsterinsights_skip_tracking', $skipped );
		}
	}

	new MonsterInsights_MetaBox_ExcludePage_Pro();
}
