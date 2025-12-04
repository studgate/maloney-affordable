<?php
/**
 * Wp_Cli_Multisite_Cache
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin;

use WP_CLI;

/**
 * Manage cache on WordPress Multisite installations.
 */
class Wp_Cli_Multisite_Cache_Flush {
	/**
	 * Flushes the cache on all multisite sub-sites.
	 *
	 * @subcommand flush
	 */
	public function flush() {
		if ( ! \is_multisite() ) {
			WP_CLI::error( 'This command is only available on multisite installations.' );
			return;
		}

		WP_CLI::log( 'Flushing cache on all multisite sub-sites.' );
		foreach ( \get_sites() as $site ) {
			$result = \switch_to_blog( $site->blog_id ) && \wp_cache_flush() && \restore_current_blog();
			if ( ! $result ) {
				WP_CLI::error( 'Failed to flush cache on sub-site ' . $site->blog_id );
				return;
			}
		}
		WP_CLI::success( 'Cache successfully flushed on all multisite sub-sites.' );
	}
}
