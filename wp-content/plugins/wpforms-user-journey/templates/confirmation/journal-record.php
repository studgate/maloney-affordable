<?php
/**
 * Record table row in confirmations.
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

?>

<tr class="<?php echo sanitize_html_class( $status ); ?>">

	<td class="time"><?php echo esc_html( $time ); ?></td>

	<td class="title-area">

		<span class="title"><?php echo esc_html( $title ); ?></span><br>

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
			<a href="<?php echo esc_url( $url ); ?>" class="go" target="blank" rel="noopener noreferrer" title="<?php esc_html_e( 'Go to URL', 'wpforms-user-journey' ); ?>"></a>
		<?php endif; ?>

	</td>

	<td class="duration">
		<?php echo ! empty( $duration ) ? esc_html( $duration ) : ''; ?>
	</td>

</tr>
