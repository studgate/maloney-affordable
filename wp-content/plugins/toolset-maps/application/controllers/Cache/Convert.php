<?php
namespace OTGS\Toolset\Maps\Controller\Cache;

use Toolset_Addon_Maps_Common;

/**
 * Convert all the data from old address cache into new one.
 *
 * Even though this runs the whole process in one go, we are pretty sure it will not time out or run out of memory.
 * This is because previously, to add just one new address to cache, the process was:
 * load from database -> unserialize -> serialize -> write to database
 * and this whole conversion has one step less:
 * load from database -> unserialize -> write to database
 * so, whatever the users' server setup is, if it could handle the old cache, it will handle this conversion in one go.
 */
class Convert {
	/**
	 * @return string
	 */
	public static function run() {
		if ( empty( get_option( Toolset_Addon_Maps_Common::ADDRESS_COORDINATES_OPTION, [] ) ) ) {
			return __( 'Coordinates were already converted, so there is nothing to do here.', 'toolset-maps' );
		}

		global $wpdb;

		$table_name = $wpdb->prefix . CreateDatabaseTable::TABLE_NAME;
		$old_address_cache = Toolset_Addon_Maps_Common::get_stored_coordinates();
		$values = [];

		foreach ( $old_address_cache as $item ) {
			// Some addresses may contain characters that need escaping before being inserted.
			$address_passed = $wpdb->_real_escape( $item['address_passed'] );
			$address = $wpdb->_real_escape( $item['address'] );

			// It's better to skip invalid lat/lng than insert it to table. We shouldn't have any, but just in case.
			if ( ! Toolset_Addon_Maps_Common::is_valid_longitude( $item['lon'] ) ) {
				continue;
			}
			if ( ! Toolset_Addon_Maps_Common::is_valid_latitude( $item['lat'] ) ) {
				continue;
			}

			$values[] = "( '$address_passed', '$address', ST_PointFromText('POINT({$item['lon']} {$item['lat']})') )";
		}

		$values_string = implode( ',', $values );
		$sql = "INSERT IGNORE INTO $table_name ( `address_passed`, `address`, `point` ) VALUES $values_string";

		$rows_inserted = $wpdb->query( $sql );

		if ( count( $old_address_cache ) === $rows_inserted ) {
			$result = __( 'Cache converted successfully.', 'toolset-maps' );
		} else {
			$result = __(
				'Cache could not be converted completely. Do not worry, it will be regenerated from the API.',
				'toolset-maps'
			);
		}

		update_option( Toolset_Addon_Maps_Common::ARE_COORDINATES_MIGRATED_OPTION, true );
		delete_option( Toolset_Addon_Maps_Common::ADDRESS_COORDINATES_OPTION );

		return sprintf(
			// translators: first 2 placeholders are numbers, the 3rd one is the final result of conversion operation, and 4th one is end of line
			__( 'Found %1$d addresses in old cache.%4$sInserted %2$d rows.%4$s%3$s', 'toolset-maps' ),
			count( $old_address_cache ),
			$rows_inserted,
			$result,
			PHP_EOL
		);
	}
}
