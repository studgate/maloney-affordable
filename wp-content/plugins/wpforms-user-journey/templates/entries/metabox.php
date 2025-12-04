<?php
/**
 * Metabox for the entry.
 *
 * @since 1.0.0
 *
 * @var object $entry      Entry.
 * @var string $form_title Form title.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$timestamp_prev = false;
?>

<!-- User Journey metabox -->
<div id="wpforms-entry-user-journey" class="postbox">
	<div class="postbox-header">
		<h2 class="hndle"><span><?php esc_html_e( 'User Journey', 'wpforms-user-journey' ); ?></span></h2>
	</div>
	<div class="inside">
		<?php

		if ( empty( $entry->user_journey ) || ! is_array( $entry->user_journey ) ) {

			printf(
				'<p>%s</p>',
				esc_html__( 'There\'s no user journey for this entry.', 'wpforms-user-journey' )
			);

		} else {

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wpforms_user_journey()->view->get_entry_journey_table( $entry, 'entries' );

		}

		?>
	</div>
</div>
