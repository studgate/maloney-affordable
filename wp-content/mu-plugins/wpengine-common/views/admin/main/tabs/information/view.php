<?php
/**
 * Admin UI - Information Tab
 * Adds the WP Engine Admin "Information" tab.
 *
 * @package wpengine/common-mu-plugin
 */

// Don't load directly.

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

use wpe\plugin\update_providers\Update_Providers;

// Check user capabilities.
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}
?>

<!-- Admin Information Tab -->
<div class="wpe-common-plugin-tab-info wpe-common-plugin-grid-2 uneven">
	<!-- Column 1 -->
	<div class="wpe-common-plugin-column">
		<!-- Update Provider Status -->
		<?php if ( wpe_show_update_provider_health_panel() ) : ?>
			<div class="wpe-common-plugin-container">
				<h2>WordPress Core, Theme, and Plugin Availability</h2>

				<?php if ( ! wpe_allow_source_override() ) : ?>
					<!-- Source Selection Locked Message -->
					<?php $notice_data = apply_filters( 'wpe_uss_host_override_notice', array() ); ?>
					<div class="wpe-common-settings-panel" style="border-left-color: #D18431;">
						<p>
							<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $notice_data['imgsrc'] contains a trusted Base64-encoded SVG data URL from filter_wpe_uss_host_override_notice() ?>
							<img src="<?php echo $notice_data['imgsrc']; ?>" alt="WP Engine Logo" style="margin-right: 4px; vertical-align: text-bottom;" />
							<strong><?php echo esc_html( $notice_data['title'] ); ?></strong>
						</p>
						<p><?php echo wp_kses_post( $notice_data['msg'] ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( wpe_show_update_provider_current_source() ) : ?>
					<h3>Current Source</h3>
					<p class="wpe-current-provider">
						<span class="icon-info"></span>
						<span class="wpe-current-provider-text">
							<?php echo esc_html( Update_Providers::instance()->get_current_provider()->display_name ); ?>
							(<?php echo esc_html( Update_Providers::instance()->get_current_provider()->display_domain ); ?>)
							is the current source of WordPress core, themes, and plugins for this&nbsp;site.
						</span>
					</p>
				<?php endif; ?>

				<h3>Source Statuses</h3>
				<div id="wpe-provider-status-widget">
					<ul>
						<?php foreach ( Update_Providers::instance()->providers as $provider ) : ?>
							<li class="wpe-provider-status wpe-provider-status--unknown">
								Checking connection to <?php echo esc_html( $provider->display_domain ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<p data-remove-on-error="1">WP Engine’s Mirror of WordPress.org (wpe-api.wpengine.com) provides an availability cache of the WordPress.org API.</p>
				<p><a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://wpengine.com/support/wordpress-update-source/' ); ?>">Learn more about setting a source</a></p>

				<?php if ( \wpe\plugin\WPE_Update_Source_Selector::get_settings_page_url() && wpe_allow_source_override() ) : ?>
					<div class="wpe-admin-button-controls">
						<a href="<?php echo esc_attr( \wpe\plugin\WPE_Update_Source_Selector::get_settings_page_url() ); ?>" class="wpe-admin-button-secondary">Manage Source</a>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- SFTP -->
		<div class="wpe-common-plugin-container">
			<h2>SFTP</h2>
			<p>Please read over our <a href="https://wpengine.com/support/wordpress-best-practice-configuring-dns-for-wp-engine/" target="_blank" rel="noopener noreferrer">DNS Best Practices</a> before you set your <strong>DNS</strong> to CNAME: <code><?php echo esc_html( $site_info->name . '.wpengine.com' ); ?></code> or an A record to <code class="wpe_public_ip"><?php echo esc_html( $site_info->public_ip ); ?></code></p>
			<p>Your SFTP access (<i>not FTP!</i>) is at hostname <code><?php echo esc_html( $site_info->sftp_host ); ?></code> or IP at <code class="wpe_sftp_ip"><?php echo esc_html( $site_info->sftp_ip ); ?></code> on port <code><?php echo esc_html( $site_info->sftp_port ); ?></code>.</p>
			<p>You will need to create a Username and Password in order to gain access. This can be <a href="<?php echo esc_url( get_option( 'wpe-install-userportal', 'https://my.wpengine.com' ) ); ?>" target="_blank" rel="noopener noreferrer">created in the user portal</a>.</p>
			<div class="wpe-admin-button-controls">
				<a class="wpe-admin-button-primary wpe-link-external" href="https://my.wpengine.com/support" target="_blank" rel="noopener noreferrer">Visit Support Page</a>
			</div>
		</div>
		<!-- Access and Error Logs -->
		<div class="wpe-common-plugin-container">
			<h2>Access and Error Logs</h2>
			<p><strong>Access logs</strong> are records of all the requests processed by your server. The <strong>Error logs</strong> record all errors returned by WordPress, the server, or some plugins you may have installed.</p>
			<?php if ( WpeCommon::should_render_log_links() ) : ?>
			<div class="wpe-admin-button-controls">
				<a class="wpe-admin-button-secondary wpe-link-download" href="<?php echo esc_url( $wpe_common->get_access_log_url( 'previous' ) ); ?>">Previous access logs</a>
				<?php if ( ! $wpe_common->is_legacy_staging_disabled() ) { ?>
				<a class="wpe-admin-button-secondary wpe-link-download" href="<?php echo esc_url( $this->get_error_log_url( false ) ); ?>">Legacy staging error logs</a>
				<?php } ?>
				<a class="wpe-admin-button-secondary wpe-link-download" href="<?php echo esc_url( $wpe_common->get_access_log_url( 'current' ) ); ?>">Current access logs</a>
				<a class="wpe-admin-button-secondary wpe-link-download" href="<?php echo esc_url( $this->get_error_log_url( true ) ); ?>">Current error logs</a>
			</div>
			<?php else : ?>
			<p>Please contact your WordPress site administrator to download logs.</p>
			<?php endif; ?>
			<div class="wpe-admin-button-controls">
				<a href="https://my.wpengine.com/installs/<?php echo esc_attr( $site_info->name ); ?>/access_logs" target="_blank" rel="noopener noreferrer">Access logs in the user Portal</a>
				<a href="https://my.wpengine.com/installs/<?php echo esc_attr( $site_info->name ); ?>/error_logs" target="_blank" rel="noopener noreferrer">Error logs in the user Portal</a>
			</div>
		</div>

		<?php do_action( 'wpe_common_admin_information_tab_col1' ); ?>
	</div>
	<!-- Column 2 -->
	<div class="wpe-common-plugin-column">
		<?php if ( $wpe_common->is_wpengine_news_feed_enabled() ) { ?>
		<!-- Announcements -->
		<div class="wpe-common-plugin-container wpe-announcements">
			<h2>WP Engine Announcements</h2>
			<?php
			$latest_posts = \wpengine\admin_options\get_blog_posts();

			// Loop through each post.
			foreach ( $latest_posts as $latest_post ) {
				$post_title          = isset( $latest_post['title']['rendered'] ) ? $latest_post['title']['rendered'] : '';
				$post_content        = isset( $latest_post['content']['rendered'] ) ? $latest_post['content']['rendered'] : '';
				$post_excerpt        = isset( $latest_post['excerpt']['rendered'] ) ? $latest_post['excerpt']['rendered'] : '';
				$post_permalink      = isset( $latest_post['link'] ) ? $latest_post['link'] : '';
				$post_featured_image = isset( $latest_post['_embedded']['wp:featuredmedia']['0']['media_details']['sizes']['medium']['source_url'] ) ? $latest_post['_embedded']['wp:featuredmedia']['0']['media_details']['sizes']['medium']['source_url'] : '';
				?>
				<div class="wpe-announcement-item">
					<?php if ( $post_featured_image ) { ?>
					<img class="wpe-announcement-image" src="<?php echo esc_url( $post_featured_image ); ?>" alt="" aria-hidden="true" />
					<?php } ?>
					<h3 class="wpe-announcement-title"><?php echo wp_kses_post( $post_title ); ?></h3>
					<p><?php echo wp_kses_post( wp_trim_words( $post_excerpt, 23, ' [ &#8230; ]' ) ); ?></p>
					<a class="wpe-admin-button-primary" href="<?php echo esc_url( $post_permalink ); ?>" target="_blank" rel="noopener noreferrer">Learn More<span class="screen-reader-text">About <?php echo wp_kses_post( $post_title ); ?></span></a>
				</div>
			<?php } ?>
		</div>
		<?php } ?>
		<!-- Service Status -->
		<div class="wpe-common-plugin-container">
			<h2>WP Engine Service Status</h2>
			<p>You should <a href="https://wpenginestatus.com/" target="_blank" rel="noopener noreferrer">subscribe to our WP Engine Status Page</a> to keep on top of any disruptions in service to our WP platform, support services, or product features.</p>
			<p>Of course, you can unsubscribe at any time. We use the status page only for infrequent, but important, service announcements.</p>
		</div>
		<?php do_action( 'wpe_common_admin_information_tab_col2' ); ?>
	</div>
</div>
