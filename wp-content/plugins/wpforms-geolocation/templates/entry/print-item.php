<?php
/**
 * Location template for the Entry Print page.
 *
 * @var object $entry Entry.
 * @var array  $form_data Form data and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $entry->formatted_location ) ) {
	return;
}
?>

<div class="print-item wpforms-field-location">
	<div class="print-item-title"><?php esc_html_e( 'Location', 'wpforms-geolocation' ); ?></div>
	<div class="print-item-value">
		<?php echo nl2br( esc_html( $entry->formatted_location ) ); ?>
	</div>
</div>
