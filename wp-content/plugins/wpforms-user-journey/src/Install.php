<?php

namespace WPFormsUserJourney;

use WP_Site;

/**
 * User Journey addon install.
 *
 * @since 1.0.0
 */
class Install {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 1.0.6
	 */
	public function hooks() {

		register_activation_hook( WPFORMS_USER_JOURNEY_FILE, [ $this, 'install' ] );

		add_action( 'wp_initialize_site', [ $this, 'new_multisite_blog' ], 10, 2 );
		add_action( 'wpforms_loaded', [ $this, 'check_table' ] );
	}

	/**
	 * Perform certain actions on plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $network_wide Whether to enable the plugin for all sites in the network
	 *                           or just the current site. Multisite only. Default is false.
	 *
	 * @noinspection DisconnectedForeachInstructionInspection
	 */
	public function install( $network_wide = false ) {

		// Check if we are on multisite and network activating.
		if ( $network_wide && is_multisite() ) {

			// Multisite - go through each sub site and run the installer.
			$sites = get_sites(
				[
					'fields' => 'ids',
					'number' => 0,
				]
			);

			foreach ( $sites as $blog_id ) {
				switch_to_blog( $blog_id );
				$this->run();
				restore_current_blog();
			}
		} else {

			// Normal single site.
			$this->run();
		}
	}

	/**
	 * Run the actual installer.
	 * We cannot use DB class here, as it needs WPForms Core plugin.
	 *
	 * @since 1.0.0
	 */
	protected function run() {

		global $wpdb;

		$table = $wpdb->prefix . 'wpforms_user_journey';

		// Create the table if it doesn't exist.
		if ( ! $this->table_exists( $table ) ) {
			$this->create_table( $table );
		}

		update_option( 'wpforms_user_journey_version', WPFORMS_USER_JOURNEY_VERSION );
	}

	/**
	 * Check if the given table exists.
	 *
	 * @since 1.1.0
	 *
	 * @param string $table The table name.
	 *
	 * @return bool If the table name exists.
	 */
	private function table_exists( $table ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Create custom user journey table. Used on plugin activation.
	 *
	 * @since 1.1.0
	 *
	 * @param string $table The table name.
	 */
	private function create_table( $table ) {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) NOT NULL,
			form_id bigint(20) NOT NULL,
			post_id bigint(20) NOT NULL,
			url varchar(2083) NOT NULL,
			parameters varchar(2000) NOT NULL,
			external tinyint(1) DEFAULT 0,
			title varchar(256) NOT NULL,
			duration int NOT NULL,
			step tinyint NOT NULL,
			date datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * When a new site is created in multisite, see if we are network activated,
	 * and if so run the installer.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added $new_site and $args parameters and removed $blog_id, $user_id, $domain, $path,
	 *        $site_id, $meta parameters.
	 *
	 * @param WP_Site $new_site New site object.
	 * @param array   $args     Arguments for the initialization.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function new_multisite_blog( $new_site, $args ) {

		if ( is_plugin_active_for_network( plugin_basename( WPFORMS_USER_JOURNEY_FILE ) ) ) {
			switch_to_blog( $new_site->blog_id );
			$this->run();
			restore_current_blog();
		}
	}

	/**
	 * Check that addon table exists.
	 *
	 * @since 1.0.6
	 */
	public function check_table() {

		if ( WPFORMS_USER_JOURNEY_VERSION !== get_option( 'wpforms_user_journey_version' ) ) {
			$this->run();
		}
	}
}
