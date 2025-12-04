<?php
namespace OTGS\Toolset\Maps\Controller\Troubleshooting;

use Toolset_Addon_Maps_Common;
use Toolset_Admin_Notice_Dismissible;
use Toolset_Menu;
use OTGS\Toolset\Maps\Controller\Ajax;
use OTGS\Toolset\Maps\Controller\Cache\CreateDatabaseTable;

/**
 * Class CacheUpdate handles address cache updating to new format.
 *
 * @since 2.0
 */
class CacheUpdate {
	const TROUBLESHOOTING_SECTION_SLUG = 'maps_cache_update';

	/**
	 * For a new installation, create database table. For an update, register the troubleshooting section to convert
	 * old cache and a dismissable admin notice that will point towards it.
	 */
	public function init() {
		if ( empty( get_option( Toolset_Addon_Maps_Common::ADDRESS_COORDINATES_OPTION, [] ) ) ) {
			if (
				Toolset_Addon_Maps_Common::are_coordinates_migrated()
				// We check this so upgrades that potentially went wrong can be fixed. To be removed in a future version
				&& CreateDatabaseTable::cache_table_exists()
			) {
				return;
			}

			CreateDatabaseTable::run();

			// If database table for cache creation went fine, use it. Otherwise, use the old cache.
			if ( CreateDatabaseTable::cache_table_exists() ) {
				update_option( Toolset_Addon_Maps_Common::ARE_COORDINATES_MIGRATED_OPTION, true );
			} else {
				update_option( Toolset_Addon_Maps_Common::ARE_COORDINATES_MIGRATED_OPTION, false );
			}
		} else {
			add_filter( 'toolset_get_troubleshooting_sections', array( $this, 'add_troubleshooting_section' ) );
			add_action( 'toolset_admin_notices_manager_show_notices', array( $this, 'add_notice' ) );
		}
	}

	/**
	 * @param array $notices
	 *
	 * @return array
	 */
	public function add_notice( array $notices ) {
		$notices[] = new Toolset_Admin_Notice_Dismissible(
			self::TROUBLESHOOTING_SECTION_SLUG,
			sprintf(
				// translators: this is an admin notice with a link to solution page
				__( 'Toolset Maps: You\'ve got some address data cached. Convert it to a new, more efficient format to gain significant performance benefits on %s!', 'toolset-maps' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_attr( add_query_arg( array( 'page' => Toolset_Menu::TROUBLESHOOTING_PAGE_SLUG ), admin_url( 'admin.php' ) ) ),
					__( 'Troubleshooting Page', 'toolset-maps' )
				)
			)
		);
		return $notices;
	}

	/**
	 * @param array $sections
	 *
	 * @return array
	 */
	public function add_troubleshooting_section( array $sections ) {
		$sections[ self::TROUBLESHOOTING_SECTION_SLUG ] = array(
			'title' => __( 'Convert Maps address cache to new format.', 'toolset-maps' ),
			'description' => __( 'You\'ve got some address data cached. Convert it to a new, more efficient format to gain significant performance benefits.', 'toolset-maps' ),
			'button_label' => __( 'Convert cache', 'toolset-maps' ),
			'is_dangerous' => false,
			'action_name' => Ajax::get_instance()->get_action_js_name( Ajax::CALLBACK_UPDATE_ADDRESS_CACHE ),
			'nonce' => wp_create_nonce( Ajax::CALLBACK_UPDATE_ADDRESS_CACHE ),
		);
		return $sections;
	}
}
