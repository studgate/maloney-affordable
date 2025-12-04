<?php
/**
 * Output record table row.
 *
 * @since 1.0.0
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

		<?php if ( ! empty( $params ) ) : ?>
			<button class="parameter-toggle" title="<?php esc_html_e( 'Toggle URL parameter display', 'wpforms-user-journey' ); ?>">
				<i class="fa fa-info-circle" aria-hidden="true"></i>
			</button>
		<?php endif; ?>

		<?php if ( ! empty( $params ) ) : ?>
			<ul class="parameters">
				<?php foreach ( $params as $key => $param ) : ?>
					<li>
						<?php echo esc_html( $key ); ?>
						<i class="fa fa-long-arrow-right" aria-hidden="true"></i>
						<?php echo esc_html( is_array( $param ) ? print_r( $param, true ) : $param ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</td>

	<td class="duration">
		<?php echo ! empty( $duration ) ? esc_html( $duration ) : ''; ?>
	</td>

</tr>
