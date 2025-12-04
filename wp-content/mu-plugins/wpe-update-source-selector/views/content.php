<?php
/**
 * Content fragment.
 *
 * Renders the content for the tabbed interface.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector;

/**
 * The main admin UI class.
 *
 * @var Admin_UI $this
 */

/**
 * The current tab.
 *
 * @var string $wpe_uss_current_tab
 */

?>

<div class="wpe-update-source-selector-body wpe-update-source-selector-tab-<?php echo sanitize_key( $wpe_uss_current_tab ); ?> hide-if-no-js">
	<?php require 'tabs/' . $wpe_uss_current_tab . '.php'; ?>
</div>

<div class="notice notice-error wpe-update-source-selector-notice hide-if-js">
	<p>
		<?php
		esc_html_e( 'WP Engine Update Source Selector requires JavaScript.', 'wpe-update-source-selector' );
		?>
	</p>
</div>
