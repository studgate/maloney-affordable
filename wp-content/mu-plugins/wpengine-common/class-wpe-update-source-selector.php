<?php
/**
 * WPE_Update_Source_Selector
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin;

use WpeCommon;
use wpe\plugin\update_providers\Update_Providers;

/**
 * Class: WPE_Update_Source_Selector
 *
 * Enables integration with the Must-Use WP Engine Update Source Selector plugin.
 */
class WPE_Update_Source_Selector {
	/**
	 * An instance of the WpeCommon class.
	 *
	 * @var WpeCommon
	 */
	private $wpe_common;

	/**
	 * WPE_Update_Source_Selector constructor.
	 */
	public function __construct() {
		$this->wpe_common = WpeCommon::instance();

		$this->init();
	}

	/**
	 * Initialize the class.
	 *
	 * @return void
	 */
	protected function init() {
		// Set our preferred source.
		add_filter(
			'wpe_uss_get_host_preference',
			array( $this, 'filter_wpe_uss_get_host_preference' ),
			10,
			2
		);

		// Set customer account preferred source.
		add_filter(
			'wpe_uss_get_hosting_account_preference',
			array( $this, 'filter_wpe_uss_get_hosting_account_preference' ),
			10,
			2
		);

		// Override source if there are issues with other sources.
		add_filter(
			'wpe_uss_get_host_override',
			array( $this, 'filter_wpe_uss_get_host_override' ),
			10,
			2
		);
		add_filter(
			'wpe_uss_host_override_notice',
			array( $this, 'filter_wpe_uss_host_override_notice' ),
			10,
			1
		);

		// Enable or disable WP Engine Update Source Selector's admin UI depending on feature flag.
		add_filter( 'wpe_uss_enable_admin_ui', array( $this, 'filter_wpe_uss_enable_admin_ui' ) );

		// Filter WP Engine Update Source Selector's UI tabs.
		add_filter( 'wpe_uss_navigation_tabs', array( $this, 'filter_wpe_uss_navigation_tabs' ) );

		// Filter WP Engine Update Source Selector's user agent string when checking available sources.
		add_filter( 'wpe_uss_check_url_user_agent', array( $this, 'filter_wpe_uss_check_url_user_agent' ), 10, 0 );
	}

	/**
	 * Filter the host preference to set WP Engine as our preferred source.
	 *
	 * @handles wpe_uss_get_host_preference
	 *
	 * @param string   $source_key  Source key, default none (empty string).
	 * @param string[] $source_keys An array of source keys that may be selected from.
	 *
	 * @return string
	 */
	public function filter_wpe_uss_get_host_preference( string $source_key, array $source_keys ): string {
		if ( in_array( 'wpengine', $source_keys, true ) && wpe_use_wpe_updater_api() ) {
			return 'wpengine';
		}

		return $source_key;
	}

	/**
	 * Maybe filter the hosting account preference.
	 *
	 * @handles wpe_uss_get_hosting_account_preference
	 *
	 * @param string   $source_key  Source key, default none (empty string).
	 * @param string[] $source_keys An array of source keys that may be selected from.
	 *
	 * @return string
	 */
	public function filter_wpe_uss_get_hosting_account_preference( string $source_key, array $source_keys ): string {
		$account_preference = $this->wpe_common->get_account_plugin_update_source();

		if ( ! empty( $account_preference ) && in_array( $account_preference, $source_keys, true ) ) {
			return $account_preference;
		}

		return $source_key;
	}

	/**
	 * Override source to overcome widespread connectivity or security issues.
	 *
	 * @handles wpe_uss_get_host_override
	 *
	 * @param string   $source_key  Source key, default none (empty string).
	 * @param string[] $source_keys An array of source keys that may be selected from.
	 *
	 * @return string
	 */
	public function filter_wpe_uss_get_host_override( string $source_key, array $source_keys ): string {
		if ( ! wpe_allow_source_override() ) {
			// phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
			return wpe_use_wpe_updater_api() ? 'wpengine' : 'wordpress';
		}

		return $source_key;
	}

	/**
	 * Allows filtering of the data used to create the host override notice.
	 *
	 * @handles wpe_uss_host_override_notice
	 *
	 * @param array<string,string> $args             An associative array with keys dashicon, imgsrc, title and msg.
	 *                                               `dashicon` is an optional string for dashicon to show before the title, e.g. "dashicons-warning".
	 *                                               `imgsrc` is an optional URL to be used as the src for an img tag, can be a data URL. Takes priority over the dashicon.
	 *                                               `title` is an optional string to set as the warning notice's title. If not supplied, icon will not be shown either.
	 *                                               `msg` is a required string used as the warning notice's main text. If not supplied, a default is used.
	 * @return array
	 */
	public function filter_wpe_uss_host_override_notice(
		array $args
	): array {

		$update_providers   = Update_Providers::instance();
		$unavailable_source = $update_providers->get_provider( 'wordpress-org' )->display_name;
		$replacement_source = $update_providers->get_provider( 'wpengine' )->display_name;

		// If we're currently forcing users to WPE (alt source), we switch the message to be more appropriate.
		if ( ! wpe_use_wpe_updater_api() ) {
			$unavailable_source = $update_providers->get_provider( 'wpengine' )->display_name;
			$replacement_source = $update_providers->get_provider( 'wordpress-org' )->display_name;
		}

		// 18x18px WP Engine logo.
		$args['imgsrc'] = 'data:image/svg+xml;base64,PHN2ZyByb2xlPSJpbWciIGFyaWEtbGFiZWw9IldQIEVuZ2luZSBMb2dvIiB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHZpZXdCb3g9IjAgMCAxOCAxOCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KCQkJPHBhdGggZD0iTTExLjMwMDUgMTcuMjVDMTEuNDMyMiAxNy4yNSAxMS41Mzg1IDE3LjE0MzcgMTEuNTM4NSAxNy4wMTJWMTMuNjM5OEMxMS41Mzg1IDEzLjUxMzcgMTEuNDg4NSAxMy4zOTIzIDExLjM5ODggMTMuMzAzNUwxMC40MDgxIDEyLjMxMjdDMTAuMzE4NCAxMi4yMjMxIDEwLjE5NzggMTIuMTczMSAxMC4wNzE3IDEyLjE3MzFINy45Mjc1QzcuODAxMzcgMTIuMTczMSA3LjY4IDEyLjIyMzEgNy41OTExNSAxMi4zMTI3TDYuNjAwMzYgMTMuMzAzNUM2LjUxMDcyIDEzLjM5MzEgNi40NjA3NCAxMy41MTM3IDYuNDYwNzQgMTMuNjM5OFYxNy4wMTJDNi40NjA3NCAxNy4xNDM3IDYuNTY3MDQgMTcuMjUgNi42OTg3MyAxNy4yNUgxMS4zMDA1WiIgZmlsbD0iIzBFQ0FENCIvPgoJCQk8cGF0aCBkPSJNMTMuMzAyNyA2LjYwMTE1TDEyLjMxMTkgNy41OTE5NUMxMi4yMjIzIDcuNjgxNTkgMTIuMTcyMyA3LjgwMjE2IDEyLjE3MjMgNy45MjgyOVYxMC4wNzI1QzEyLjE3MjMgMTAuMTk4NiAxMi4yMjIzIDEwLjMyIDEyLjMxMTkgMTAuNDA4OEwxMy4zMDI3IDExLjM5OTZDMTMuMzkyMyAxMS40ODkzIDEzLjUxMjkgMTEuNTM5MyAxMy42MzkgMTEuNTM5M0gxNy4wMTEyQzE3LjE0MjkgMTEuNTM5MyAxNy4yNDkyIDExLjQzMyAxNy4yNDkyIDExLjMwMTNWNi43MDAzMUMxNy4yNDkyIDYuNTY4NjMgMTcuMTQyOSA2LjQ2MjMzIDE3LjAxMTIgNi40NjIzM0gxMy42MzlDMTMuNTEyOSA2LjQ2MjMzIDEzLjM5MTUgNi41MTIzMSAxMy4zMDI3IDYuNjAxOTVWNi42MDExNVoiIGZpbGw9IiMwRUNBRDQiLz4KCQkJPHBhdGggZD0iTTYuNjk5NTIgMC43NUM2LjU2Nzg0IDAuNzUgNi40NjE1NCAwLjg1NjI5OCA2LjQ2MTU0IDAuOTg3OTgxVjQuMzYwMTdDNi40NjE1NCA0LjQ4NjMgNi41MTE1MSA0LjYwNzY3IDYuNjAxMTUgNC42OTY1MUw3LjU5MTk1IDUuNjg3MzFDNy42ODE1OSA1Ljc3Njk1IDcuODAyMTYgNS44MjY5MiA3LjkyODI5IDUuODI2OTJIMTAuMDcyNUMxMC4xOTg2IDUuODI2OTIgMTAuMzIgNS43NzY5NSAxMC40MDg4IDUuNjg3MzFMMTEuMzk5NiA0LjY5NjUxQzExLjQ4OTMgNC42MDY4NyAxMS41MzkzIDQuNDg2MyAxMS41MzkzIDQuMzYwMTdWMC45ODc5ODFDMTEuNTM5MyAwLjg1NjI5OCAxMS40MzMgMC43NSAxMS4zMDEzIDAuNzVINi42OTk1MloiIGZpbGw9IiMwRUNBRDQiLz4KCQkJPHBhdGggZD0iTTE3LjAxMiAxMi4xNzMxSDEzLjYzOThDMTMuNTEzNyAxMi4xNzMxIDEzLjM5MjMgMTIuMjIzMSAxMy4zMDM1IDEyLjMxMjdMMTIuMzEyNyAxMy4zMDM1QzEyLjIyMzEgMTMuMzkzMSAxMi4xNzMxIDEzLjUxMzcgMTIuMTczMSAxMy42Mzk4VjE3LjAxMkMxMi4xNzMxIDE3LjE0MzcgMTIuMjc5NCAxNy4yNSAxMi40MTExIDE3LjI1SDE3LjAxMkMxNy4xNDM3IDE3LjI1IDE3LjI1IDE3LjE0MzcgMTcuMjUgMTcuMDEyVjEyLjQxMTFDMTcuMjUgMTIuMjc5NCAxNy4xNDM3IDEyLjE3MzEgMTcuMDEyIDEyLjE3MzFaIiBmaWxsPSIjMEVDQUQ0Ii8+CgkJCTxwYXRoIGQ9Ik01LjU4ODk0IDAuNzVIMi4yMTY3NUMyLjA4OTgzIDAuNzUgMS45NjkyNSAwLjc5OTk3NiAxLjg3OTYyIDAuODg5NjE1TDAuODg5NjE1IDEuODc5NjJDMC43OTk5NzYgMS45NjkyNSAwLjc1IDIuMDg5ODMgMC43NSAyLjIxNjc1VjUuNTg4OTRDMC43NSA1LjcyMDYyIDAuODU2Mjk4IDUuODI2OTIgMC45ODc5ODEgNS44MjY5Mkg0LjM2MDE3QzQuNDg2MyA1LjgyNjkyIDQuNjA3NjcgNS43NzY5NSA0LjY5NjUxIDUuNjg3MzFMNS42ODczMSA0LjY5NjUxQzUuNzc2OTUgNC42MDY4NyA1LjgyNjkyIDQuNDg2MyA1LjgyNjkyIDQuMzYwMTdWMC45ODc5ODFDNS44MjY5MiAwLjg1NjI5OCA1LjcyMDYyIDAuNzUgNS41ODg5NCAwLjc1WiIgZmlsbD0iIzBFQ0FENCIvPgoJCQk8cGF0aCBkPSJNMTIuMTczMSAwLjk4Nzk4MVY0LjM2MDE3QzEyLjE3MzEgNC40ODYzIDEyLjIyMzEgNC42MDc2NyAxMi4zMTI3IDQuNjk2NTFMMTMuMzAzNSA1LjY4NzMxQzEzLjM5MzEgNS43NzY5NSAxMy41MTM3IDUuODI2OTIgMTMuNjM5OCA1LjgyNjkySDE3LjAxMkMxNy4xNDM3IDUuODI2OTIgMTcuMjUgNS43MjA2MiAxNy4yNSA1LjU4ODk0VjAuOTg3OTgxQzE3LjI1IDAuODU2Mjk4IDE3LjE0MzcgMC43NSAxNy4wMTIgMC43NUgxMi40MTExQzEyLjI3OTQgMC43NSAxMi4xNzMxIDAuODU2Mjk4IDEyLjE3MzEgMC45ODc5ODFaIiBmaWxsPSIjMEVDQUQ0Ii8+CgkJCTxwYXRoIGQ9Ik05IDEwLjI2OTJDOC4yOTg3NSAxMC4yNjkyIDcuNzMwNzcgOS43MDEyNSA3LjczMDc3IDlDNy43MzA3NyA4LjI5ODc1IDguMjk5NTQgNy43MzA3NyA5IDcuNzMwNzdDOS43MDA0NiA3LjczMDc3IDEwLjI2OTIgOC4yOTg3NSAxMC4yNjkyIDlDMTAuMjY5MiA5LjcwMTI1IDkuNzAwNDYgMTAuMjY5MiA5IDEwLjI2OTJaIiBmaWxsPSIjMEVDQUQ0Ii8+CgkJCTxwYXRoIGQ9Ik0wLjc1IDEyLjQxMTFWMTcuMDEyQzAuNzUgMTcuMTQzNyAwLjg1NjI5OCAxNy4yNSAwLjk4Nzk4MSAxNy4yNUg1LjU4ODk0QzUuNzIwNjIgMTcuMjUgNS44MjY5MiAxNy4xNDM3IDUuODI2OTIgMTcuMDEyVjEzLjYzOThDNS44MjY5MiAxMy41MTM3IDUuNzc2OTUgMTMuMzkyMyA1LjY4NzMxIDEzLjMwMzVMNC42OTY1MSAxMi4zMTI3QzQuNjA2ODcgMTIuMjIzMSA0LjQ4NjMgMTIuMTczMSA0LjM2MDE3IDEyLjE3MzFIMC45ODc5ODFDMC44NTYyOTggMTIuMTczMSAwLjc1IDEyLjI3OTQgMC43NSAxMi40MTExWiIgZmlsbD0iIzBFQ0FENCIvPgoJCQk8cGF0aCBkPSJNNS42ODczMSA3LjU5MTE1TDQuNjk2NTEgNi42MDAzNkM0LjYwNjg3IDYuNTEwNzIgNC40ODYzIDYuNDYwNzQgNC4zNjAxNyA2LjQ2MDc0SDAuOTg3OTgxQzAuODU2Mjk4IDYuNDYxNTQgMC43NSA2LjU2Nzg0IDAuNzUgNi42OTk1MlYxMS4zMDA1QzAuNzUgMTEuNDMyMiAwLjg1NjI5OCAxMS41Mzg1IDAuOTg3OTgxIDExLjUzODVINC40MTQ5QzQuNTQxMDMgMTEuNTM4NSA0LjY2MjQgMTEuNDg4NSA0Ljc1MTI1IDExLjM5ODhMNS42ODczMSAxMC40NjM2QzUuNzc2OTUgMTAuMzczOSA1LjgyNjkyIDEwLjI1MzQgNS44MjY5MiAxMC4xMjcyVjcuOTI4MjlDNS44MjY5MiA3LjgwMjE2IDUuNzc2OTUgNy42ODA3OSA1LjY4NzMxIDcuNTkxOTVWNy41OTExNVoiIGZpbGw9IiMwRUNBRDQiLz4KCQk8L3N2Zz4=';
		$args['title']  = 'WP Engine is temporarily managing your source';
		$args['msg']    = sprintf(
		/* translators: 1st param Core source name, 2nd param current source name. */
			__(
				'This site cannot currently access %1$s. To ensure continued availability of WordPress core, theme, and plugin updates, %2$s has been set as your active source. Settings on this page cannot be changed at this time.',
				'wpe-update-source-selector'
			),
			$unavailable_source,
			$replacement_source
		);

		return $args;
	}

	/**
	 * Filter whether the admin UI is enabled or not.
	 *
	 * @handles wpe_uss_enable_admin_ui
	 *
	 * @param bool $enable_admin_ui Whether admin UI may be shown to users with appropriate capabilities, default false.
	 *
	 * @return bool
	 */
	public function filter_wpe_uss_enable_admin_ui( bool $enable_admin_ui ) {
		return (bool) wpe_show_update_source_selection();
	}

	/**
	 * Filter the UI tabs to remove the About tab.
	 *
	 * @param array<string,string> $tabs An associative array of tab titles, keyed by their slug.
	 *
	 * @return array
	 */
	public function filter_wpe_uss_navigation_tabs( $tabs ) {
		unset( $tabs['about'] );

		return $tabs;
	}

	/**
	 * Filter the user agent string used when checking source availability.
	 *
	 * @return string
	 */
	public function filter_wpe_uss_check_url_user_agent(): string {
		return 'WordPress/' . get_bloginfo( 'version' ) . '; http://localhost/';
	}

	/**
	 * Get the URL for the WP Engine Update Source Selector settings page.
	 *
	 * @return string URL or empty string
	 */
	public static function get_settings_page_url() {
		static $url = '';

		if ( ! empty( $url ) ) {
			return $url;
		}

		// If WP Engine Update Source Selector's UI disabled, don't supply a URL.
		if ( ! wpe_show_update_source_selection() ) {
			$url = '';

			return $url;
		}

		$url = apply_filters( 'wpe_uss_get_settings_page_url', '' );

		if ( empty( $url ) || ! is_string( $url ) ) {
			$url = '';
		}

		return $url;
	}
}
