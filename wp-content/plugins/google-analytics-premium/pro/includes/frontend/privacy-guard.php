<?php

/**
 * Load JS file to manage privacy guard functionality.
 */
function monsterinsights_add_privacy_guard_script_tag() {
	if ( ! monsterinsights_get_option( 'privacy_guard', false ) ) {
		return;
	}

	$suffix      = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	$src         = plugins_url( 'pro/assets/js/privacy-guard' . $suffix . '.js', MONSTERINSIGHTS_PLUGIN_FILE );
	$attr_string = monsterinsights_get_frontend_analytics_script_atts();

	printf( '<script src="%s" %s></script>' . PHP_EOL, $src, $attr_string ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- False positive.
}

add_action( 'monsterinsights_tracking_gtag_frontend_before_script_tag', 'monsterinsights_add_privacy_guard_script_tag' );
