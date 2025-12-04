<?php
/**
 * Record table row in HTML emails.
 *
 * @since 1.0.3
 *
 * @var string $time     Record time.
 * @var string $title    Page title.
 * @var string $url      Full Page URL.
 * @var string $path     Page path, without domain.
 * @var array  $params   URL parameters.
 * @var string $duration Visit duration.
 * @var string $status   Status of the record.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cell_style = WPFormsUserJourney\View::CELL_STYLE;
?>

<tr class="<?php echo sanitize_html_class( $status ); ?>">

	<td style="width: 50px; <?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
		<?php echo esc_html( $time ); ?>
	</td>

	<td style="<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
		<?php if ( $status === 'submit' ) : ?>
			<i class="fa fa-check" aria-hidden="true"></i>
		<?php elseif ( $status === 'abandon' ) : ?>
			<i class="fa fa-sign-out" aria-hidden="true"></i>
		<?php endif; ?>
		<span class="title"><?php echo esc_html( $title ); ?></span>

		<i class="fa fa-circle" aria-hidden="true"></i>

		<span class="path">
			<?php echo esc_html( $path ); ?>
			<?php if ( $path === '/' ) : ?>
				<?php if ( ! empty( $params['s'] ) ) : ?>
					<em>(<?php esc_html_e( 'Search Results', 'wpforms-user-journey' ); ?>)</em>
				<?php else : ?>
					<em>(<?php esc_html_e( 'Homepage', 'wpforms-user-journey' ); ?>)</em>
				<?php endif; ?>
			<?php endif; ?>
		</span>

		<?php if ( ! empty( $url ) && strpos( $url, home_url() ) !== false ) : ?>
			<a href="<?php echo esc_url( $url ); ?>" class="go" target="blank" rel="noopener noreferrer" title="<?php esc_html_e( 'Go to URL', 'wpforms-user-journey' ); ?>">
				<i class="fa fa-external-link" aria-hidden="true"></i>
			</a>
		<?php endif; ?>

	</td>

	<td style="width: 100px; <?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
		<?php echo ! empty( $duration ) ? esc_html( $duration ) : ''; ?>
	</td>

</tr>
