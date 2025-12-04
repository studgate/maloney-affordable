<?php
/**
 * WP Engine Available Updates
 *
 * @package wpengine/common-mu-plugin
 * @owner wpengine/golden
 */

namespace wpe\plugin;

/**
 * A class for saving information about available updates of plugins, themes, and WP core in the database
 * to ensure a copy of the updates is pushed to the database as well as object cache.
 */
class Wpe_Available_Updates {

	/**
	 * The instance of the class.
	 *
	 * @var Wpe_Available_Updates
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// High (9999) priority number means we want to execute filters as late as possible, after any plugin
		// or theme has modified the transient and possibly added/modified info about its update.
		add_filter( 'set_site_transient_update_plugins', array( $this, 'store_update_plugins_in_db' ), 9999, 1 );
		add_filter( 'set_site_transient_update_themes', array( $this, 'store_update_themes_in_db' ), 9999, 1 );
		add_filter( 'set_site_transient_update_core', array( $this, 'store_update_core_in_db' ), 9999, 1 );

		// Attach to filters that delete the transients.
		add_action( 'delete_site_transient_update_plugins', array( $this, 'delete_update_plugins_from_db' ) );
		add_action( 'delete_site_transient_update_themes', array( $this, 'delete_update_themes_from_db' ) );
		add_action( 'delete_site_transient_update_core', array( $this, 'delete_update_core_from_db' ) );
	}

	/**
	 * Get the instance of the class.
	 *
	 * @return Wpe_Available_Updates
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Store the available plugins updates in the database.
	 *
	 * @param object $transient The transient object.
	 * @return object The transient object.
	 */
	public function store_update_plugins_in_db( $transient ) {
		if ( is_object( $transient ) ) {
			update_option( 'wpe_site_transient_update_plugins', $transient, false );
		}

		return $transient;
	}

	/**
	 * Store the available themes updates in the database.
	 *
	 * @param object $transient The transient object.
	 * @return object The transient object.
	 */
	public function store_update_themes_in_db( $transient ) {
		if ( is_object( $transient ) ) {
			update_option( 'wpe_site_transient_update_themes', $transient, false );
		}

		return $transient;
	}

	/**
	 * Store the available WP core update in the database.
	 *
	 * @param object $transient The transient object.
	 * @return object The transient object.
	 */
	public function store_update_core_in_db( $transient ) {
		if ( is_object( $transient ) ) {
			update_option( 'wpe_site_transient_update_core', $transient, false );
		}

		return $transient;
	}

	/**
	 * Delete the available plugins updates from the database.
	 */
	public function delete_update_plugins_from_db() {
		delete_option( 'wpe_site_transient_update_plugins' );
	}

	/**
	 * Delete the available themes updates from the database.
	 */
	public function delete_update_themes_from_db() {
		delete_option( 'wpe_site_transient_update_themes' );
	}

	/**
	 * Delete the available WP core update from the database.
	 */
	public function delete_update_core_from_db() {
		delete_option( 'wpe_site_transient_update_core' );
	}
}
