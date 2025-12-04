<?php
/**
 * Header fragment.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector;

use WPE_Update_Source_Selector\Sources\Source;

/**
 * The main admin UI class.
 *
 * @var Admin_UI $this
 */

/**
 * The current source.
 *
 * @var Source $wpe_uss_current_source
 */

$wpe_uss_display_domain = $wpe_uss_current_source::get_display_domain();

$wpe_uss_checking_message = sprintf(
/* translators: Checking domain connectivity message for WP Engine Update Source Selector settings page. */
	__( 'Verifying connection to %s &hellip;', 'wpe-update-source-selector' ),
	$wpe_uss_display_domain
);

$wpe_uss_error_message = sprintf(
/* translators: Domain disconnected message for WP Engine Update Source Selector settings page. */
	__( '%s is unreachable', 'wpe-update-source-selector' ),
	$wpe_uss_display_domain
);

$wpe_uss_success_message = sprintf(
/* translators: Domain connected message for WP Engine Update Source Selector settings page. */
	__( '%s is reachable', 'wpe-update-source-selector' ),
	$wpe_uss_display_domain
);

// Get current source's cached status.
// If the status is "checking", an async check will be triggered by JS when loaded.
$wpe_uss_get_source_status = Status_Check_Manager::get_source_status( $wpe_uss_current_source::get_key() );

$wpe_uss_source_status = in_array(
	$wpe_uss_get_source_status['status'],
	array( Status_Check_Manager::SUCCESS_STATUS_KEY, Status_Check_Manager::ERROR_STATUS_KEY ),
	true
) ? $wpe_uss_get_source_status['status'] : Status_Check_Manager::CHECKING_STATUS_KEY;

if ( ! empty( $wpe_uss_get_source_status['title'] ) && is_string( $wpe_uss_get_source_status['title'] ) ) {
	$wpe_uss_status_title = $wpe_uss_get_source_status['title'];
} else {
	$wpe_uss_status_title = __( 'Checking ...', 'wpe-update-source-selector' );
}
?>

<div class="wpe-update-source-selector-header">
	<div class="wpe-update-source-selector-title-section">
		<h1><?php echo esc_html( Admin_UI::get_page_title() ); ?></h1>
	</div>
	<div class="wpe-update-source-selector-title-section">
		<p><?php esc_html_e( 'Current Source Status:', 'wpe-update-source-selector' ); ?></p>
	</div>
	<div class="wpe-update-source-selector-title-section wpe-update-source-selector-source-status-wrapper hide-if-no-js" data-source-status="<?php echo esc_attr( $wpe_uss_source_status ); ?>" title="<?php echo esc_attr( $wpe_uss_status_title ); ?>">
		<div class="dashicons-before dashicons-marker wpe-update-source-selector-source-status-label wpe-update-source-selector-source-status-checking<?php echo Status_Check_Manager::CHECKING_STATUS_KEY === $wpe_uss_source_status ? '' : ' hidden'; ?>">
			<?php echo esc_html( $wpe_uss_checking_message ); ?>
		</div>
		<div class="dashicons-before dashicons-dismiss wpe-update-source-selector-source-status-label wpe-update-source-selector-source-status-error <?php echo Status_Check_Manager::ERROR_STATUS_KEY === $wpe_uss_source_status ? '' : ' hidden'; ?>">
			<?php echo esc_html( $wpe_uss_error_message ); ?>
		</div>
		<div class="dashicons-before dashicons-yes-alt wpe-update-source-selector-source-status-label wpe-update-source-selector-source-status-success <?php echo Status_Check_Manager::SUCCESS_STATUS_KEY === $wpe_uss_source_status ? '' : ' hidden'; ?>">
			<?php echo esc_html( $wpe_uss_success_message ); ?>
		</div>
	</div>
	<?php require 'tabs.php'; ?>
</div>
<hr class="wp-header-end">

<?php
// Multisites don't automatically show notices after head end.
if ( is_multisite() ) {
	settings_errors();
}
?>

<div id="wpe-update-source-selector-source-status-error-notice" class="notice notice-error wpe-update-source-selector-notice hidden">
	<p>
		<?php
		esc_html_e(
			'There was an unexpected error while checking the current source\'s status. Please refresh the page to try again.',
			'wpe-update-source-selector'
		);
		?>
	</p>
</div>
