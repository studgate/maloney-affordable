<?php

/**
 * Override the V4 ID to output if the custom tag is set
 * @param $v4_id
 *
 * @return mixed|string
 */
function monsterinsights_gtag_selector_override_v4($v4_id) {
	$custom_tag = monsterinsights_get_option('gtag_selector_tracking_tag');

	if ( !empty($custom_tag) ) {
		return $custom_tag;
	}

	return $v4_id;
}
add_filter('monsterinsights_get_v4_id_to_output', 'monsterinsights_gtag_selector_override_v4', 1);

/**
 * Override the MP call secret if the custom tag is set
 * @param $api_secret
 *
 * @return mixed|string
 */
function monsterinsights_gtag_selector_override_mp($api_secret) {

	$custom_tag = monsterinsights_get_option('gtag_selector_tracking_tag');
	$custom_secret = monsterinsights_get_option('gtag_selector_tracking_mp');

	//  No custom tag or secret set, return the default api secret for the connected tag
	if ( empty($custom_tag) || empty($custom_secret) ) {
		return $api_secret;
	}

	return $custom_secret;
}
add_filter('monsterinsights_get_mp_call_secret', 'monsterinsights_gtag_selector_override_mp', 1);