<?php
/**
 * Override notice fragment.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector;

/**
 * The img src for an icon to be displayed.
 *
 * @var string $wpe_uss_imgsrc
 */

/**
 * The dash icon to be displayed.
 *
 * @var string $wpe_uss_dashicon
 */

/**
 * The title to be displayed.
 *
 * @var string $wpe_uss_title
 */

/**
 * The message to be displayed.
 *
 * @var string $wpe_uss_msg
 */
?>

<div class="notice notice-warning wpe-update-source-selector-notice wpe-update-source-selector-host-override-warning">
	<?php
	if ( ! empty( $wpe_uss_title ) ) {
		?>
		<h2>
			<?php
			if ( ! empty( $wpe_uss_imgsrc ) ) {
				?>
				<img class="icon" src="<?php echo esc_attr( $wpe_uss_imgsrc ); ?>" aria-hidden="true">
				<?php
			} elseif ( ! empty( $wpe_uss_dashicon ) ) {
				?>
				<span class="dashicons <?php echo esc_attr( $wpe_uss_dashicon ); ?>" aria-hidden="true"></span>
				<?php
			}
			echo esc_html( $wpe_uss_title );
			?>
		</h2>
		<?php
	}
	?>
	<p>
		<?php
		echo esc_html( $wpe_uss_msg );
		?>
	</p>
</div>
