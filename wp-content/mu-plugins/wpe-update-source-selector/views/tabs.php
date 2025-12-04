<?php
/**
 * Tabs fragment.
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
 * The page's tabs.
 *
 * @var array<string,string> $wpe_uss_tabs
 */

/**
 * The current tab.
 *
 * @var string $wpe_uss_current_tab
 */

$wpe_uss_tabs_wrapper_classes = implode(
	' ',
	array(
		'wpe-update-source-selector-tabs-wrapper',
		'hide-if-no-js',
		'tab-count-' . count( $wpe_uss_tabs ),
	)
);

global $wpe_uss;

// Only show tabs if we have more than one.
if ( count( $wpe_uss_tabs ) > 1 ) :
	?>

	<nav
		class="<?php echo esc_attr( $wpe_uss_tabs_wrapper_classes ); ?>"
		aria-label="<?php esc_attr_e( 'Secondary menu', 'wpe-update-source-selector' ); ?>"
	>
		<?php
		$wpe_uss_tabs_slice = $wpe_uss_tabs;

		/*
		 * If there are more than 4 tabs, only output the first 3 inline,
		 * the remaining links will be added to a sub-navigation.
		 */
		if ( count( $wpe_uss_tabs ) > 4 ) {
			$wpe_uss_tabs_slice = array_slice( $wpe_uss_tabs, 0, 3 );
		}

		foreach ( $wpe_uss_tabs_slice as $wpe_uss_tab_slug => $wpe_uss_tab_title ) {
			if ( empty( $wpe_uss_tab_title ) ) {
				continue;
			}

			printf(
				'<a href="%s" class="wpe-update-source-selector-tab %s">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'tab' => $wpe_uss_tab_slug,
							),
							Admin_UI::get_page_url()
						),
						'wpe-update-source-selector-nonce',
						'_wpe_uss_nonce'
					)
				),
				( $wpe_uss_current_tab === $wpe_uss_tab_slug ? 'active' : '' ),
				esc_html( $wpe_uss_tab_title )
			);
		}
		?>

		<?php if ( count( $wpe_uss_tabs ) > 4 ) : ?>
			<button type="button" class="wpe-update-source-selector-tab wpe-update-source-selector-offscreen-nav-wrapper" aria-haspopup="true">
				<span class="dashicons dashicons-ellipsis"></span>
				<span class="screen-reader-text">
					<?php
					/* translators: Hidden accessibility text. */
					esc_html_e( 'Toggle extra menu items', 'wpe-update-source-selector' );
					?>
				</span>

				<div class="wpe-update-source-selector-offscreen-nav">
					<?php
					// Remove the first few entries from the array as being already output.
					$wpe_uss_tabs_slice = array_slice( $wpe_uss_tabs, 3 );

					foreach ( $wpe_uss_tabs_slice as $wpe_uss_tab_slug => $wpe_uss_tab_title ) {
						if ( empty( $wpe_uss_tab_title ) ) {
							continue;
						}

						printf(
							'<a href="%s" class="wpe-update-source-selector-tab %s">%s</a>',
							esc_url(
								wp_nonce_url(
									add_query_arg(
										array(
											'tab' => $wpe_uss_tab_slug,
										),
										Admin_UI::get_page_url()
									),
									'wpe-update-source-selector-nonce',
									'_wpe_uss_nonce'
								)
							),
							( $wpe_uss_current_tab === $wpe_uss_tab_slug ? 'active' : '' ),
							esc_html( $wpe_uss_tab_title )
						);
					}
					?>
				</div>
			</button>
		<?php endif; ?>
	</nav>
<?php endif; ?>
