<?php

use OTGS\Toolset\Maps\Controller\Cache\CreateDatabaseTable;

/**
 * Class Toolset_Maps_Views_Distance
 *
 * Common class for things related to distance in Views
 *
 * @package ToolsetMaps
 * @since 1.5
 */
abstract class Toolset_Maps_Views_Distance {
	const ORDERBY_AS = 'DISTANCE';

	const DB_ENGINE_MYSQL = 'mysql';
	const DB_ENGINE_MARIADB = 'mariadb';

	protected static $inited = false;
	protected static $children = array();

	protected $wp_db_engine = null;
	protected $wp_db_version = null;

	private $orderby_field_address_key = null;
	private $filter_by_field_address_key = null;

	/**
	 * Toolset_Maps_Views_Distance constructor.
	 */
	public function __construct() {
		if ( ! self::$inited ) {
			add_action( 'init', array( $this, 'init_abstract' ) );
			self::$inited = true;
		}
	}

	public function init_abstract() {
		if ( ! Toolset_Addon_Maps_Common::are_coordinates_migrated() ) {
			add_filter( 'wpv_filter_query', array( $this, 'make_paging_post_process_aware' ), 20, 3 );
		}
	}

	/**
     * Remove paging from query, as we need the whole result set. After postprocess, add paging back manually.
     *
	 * @param array $query
	 * @param array $view_settings
	 * @param int $id
	 *
	 * @return array
	 */
	public function make_paging_post_process_aware( array $query, array $view_settings, $id ) {
		// Nothing to do if query is skipped, pagination disabled, or we are not in map distance filter or order
		if (
			$this->is_query_skipped( $query )
			|| $this->is_no_limit_or_pagination( $view_settings )
			|| ! $this->is_views_distance_child_in_settings( $view_settings )
		) {
			return $query;
		}

		$query['posts_per_page'] = - 1;

		return $query;
	}

	/**
	 * Checks that there is no limit or pagination set in view settings
	 *
	 * @since 1.5.3
	 *
	 * @param array $view_settings
	 *
	 * @return bool
	 */
	protected function is_no_limit_or_pagination( $view_settings ) {
		return ( $view_settings['limit'] === -1 && $view_settings['pagination']['type'] === 'disabled' );
	}

	/**
	 * @param Toolset_Maps_Location $center_coords
	 * @param Toolset_Maps_Location $address_coords
	 * @param string $unit
	 *
	 * @return float|int
	 */
	public function calculate_distance(
		Toolset_Maps_Location $center_coords, Toolset_Maps_Location $address_coords, $unit = 'km'
	) {
		$earth_radius = ( $unit == 'mi' ? 3963.0 : 6371 );

		$lat_diff = deg2rad( $address_coords->get_lat() - $center_coords->get_lat() );
		$lon_diff = deg2rad( $address_coords->get_lng() - $center_coords->get_lng() );

		$lat_lon_delta = sin( $lat_diff / 2 ) * sin( $lat_diff / 2 ) + cos( deg2rad( $center_coords->get_lat() ) ) * cos( deg2rad( $address_coords->get_lat() ) ) * sin( $lon_diff / 2 ) * sin( $lon_diff / 2 );
		$lat_lon_angle = 2 * asin( sqrt( $lat_lon_delta ) );
		$distance      = $earth_radius * $lat_lon_angle;

		return $distance;
	}

	/**
     * Checks if query has a WP hack implemented to be skipped.
     *
	 * @param array $query
	 *
	 * @return bool
	 */
	protected function is_query_skipped( array $query ) {
		return (
			isset( $query['post__in'] )
			&& isset( $query['post__in'][0] )
			&& $query['post__in'][0] === 0
		);
	}

	/**
	 * Checks if View has a distance filter, order or maybe some other child.
	 *
	 * @param array $view_settings
	 *
	 * @return bool
	 */
	protected function is_views_distance_child_in_settings( array $view_settings ) {
		foreach ( self::$children as $setting_string => $set ) {
			if ( isset( $view_settings[$setting_string] ) ) {
				if (
					'distance_order' === $setting_string
					&& isset( $view_settings['distance_order'] )
				) {
					// Special case: check if distance order was set, then removed. In that case, it's key exists in
					// $view_settings, but it's value is 'undefined'.
					if ( $view_settings['distance_order']['source'] === 'undefined' ) return false;
				}

				// Otherwise, if we have distance order or filter in view settings, return true
				return true;
			}
		}
		return false;
	}

	/**
	 * Allows for child to register it's setting string, so is_views_distance_child_in_settings can do it's check.
	 *
	 * @param string $setting_string
	 */
	protected function register_child_setting_string( $setting_string ) {
		// Using keys to prevent duplicates. Values could potentially be used for something later on.
		self::$children[$setting_string] = true;
	}

	/**
	 * Checks if there was query limit removed in order to allow post processing to do its thing, and brings it back.
	 *
	 * @param WP_Query $post_query
	 *
	 * @return WP_Query
	 */
	protected function bring_paging_back( WP_Query $post_query ) {
		// Bring back removed limit
		if ( $post_query->query['wpv_original_limit'] !== -1 ) {
			$post_query->posts = array_slice(
				$post_query->posts,
				$post_query->query['wpv_original_offset'],
				$post_query->query['wpv_original_limit']
			);
			$post_query->query['posts_per_page'] = $post_query->query['wpv_original_limit'];
		}

		// Bring back removed paging
		if ( $post_query->query['wpv_original_posts_per_page'] !== - 1 ) {
			$post_query->query['posts_per_page'] = $post_query->query['wpv_original_posts_per_page'];
			$post_query->max_num_pages           = ceil( count( $post_query->posts ) / $post_query->query['posts_per_page'] );
			$post_query->posts                   = array_slice(
				$post_query->posts,
				( $post_query->query['paged'] - 1 ) * $post_query->query['posts_per_page'],
				$post_query->query['posts_per_page']
			);
		}

		return $post_query;
	}

	/**
	 * Given view name, returns its rendered HTML
	 * @param string $view
	 * @param array $vars Optional vars to pass through
	 * @return string HTML
	 */
	protected function render_view( $view, $vars=array() ) {
		return self::render_view_static( $view, $vars );
	}

	/**
	 * Static version of render_view, so it can be called from static methods...
	 *
	 * @since 1.6
	 *
	 * @param string $view
	 * @param array $vars
	 *
	 * @return false|string
	 */
	protected static function render_view_static( $view, $vars = array() ) {
		ob_start();
		include( TOOLSET_ADDON_MAPS_TEMPLATE_PATH . "$view.phtml" );
		$html = ob_get_contents();

		ob_end_clean();

		return $html;
	}

	/**
	 * Check if Toolset Types is active
	 *
	 * This class and child classes won't even load if Views is not active, but they also can't work if Types is not
	 * active, therefore a little helper method to check for Types.
	 *
	 * @return bool
	 */
	protected function is_types_active() {
		return class_exists( 'Types_Main' );
	}

	/**
	 * @param array $view_settings
	 *
	 * @return bool
	 */
	protected function is_orderby_as_distance( array $view_settings ) {
		return self::ORDERBY_AS === $this->get_orderby_as( $view_settings );
	}

	/**
	 * Given $view_settings, returns orderby_as depending on query type
	 *
	 * @param array $view_settings
	 *
	 * @return string
	 */
	protected function get_orderby_as( array $view_settings ) {
		switch ( $view_settings['query_type'][0] ) {
			case 'users':
				return $view_settings['users_orderby_as'];
			case 'posts':
				return $view_settings['orderby_as'];
			case 'taxonomy':
				return $view_settings['taxonomy_orderby_as'];
			default:
				return '';
		}
	}

	/**
	 * Answers if ordering is by an address field.
	 *
	 * @param array $view_settings
	 *
	 * @return bool
	 */
	protected function is_orderby_field_address( array $view_settings ) {
		$orderby    = $this->get_orderby( $view_settings );
		$query_type = $this->get_query_type( $view_settings );
		$field_name = $this->remove_prefix_from_field_name( $query_type, $orderby );

		$is_orderby_field_address = ( $this->get_field_type( $query_type, $field_name ) === 'google_address' );
		if ( $is_orderby_field_address ) {
			$this->orderby_field_address_key = $field_name;
		}
		return $is_orderby_field_address;
	}

	/**
	 * Checks $view_settings to see if distance order is requested.
	 *
	 * @param array $view_settings
	 *
	 * @return bool
	 */
	protected function is_distance_order_requested( array $view_settings ) {
		return (
			$this->is_orderby_field_address( $view_settings )
			&& $this->is_orderby_as_distance( $view_settings )
		);
	}

	/**
	 * @param array $view_settings
	 * @param int   $view_id
	 *
	 * @return bool
	 */
	protected function is_distance_filter_requested( array $view_settings, $view_id ) {
		$has_distance_filter = array_key_exists( 'map_distance_filter', $view_settings );
		if ( ! $has_distance_filter ) {
			return false;
		}
		$distance_filter_field = array_key_exists( 'map_distance_compare_field', $view_settings['map_distance_filter'] )
			? $view_settings['map_distance_filter']['map_distance_compare_field']
			: '';

		$this->filter_by_field_address_key = apply_filters( 'toolset_filter_toolset_maps_get_types_field_meta_key', $distance_filter_field );

		// Backward compatibility!!!
		// For some reason, Views filters might store a 'compare_field' string instead of the field meta_key
		// when there filter is added and saved without modifying any of its options.
		// In this case, the field that appears as selected in the editor is the first one.
		// Let's save this first one, because that first one might change in the future!
		if (
			'compare_field' === $distance_filter_field
			&& 'compare_field' !== $this->filter_by_field_address_key
		) {
			$view_settings['map_distance_filter']['map_distance_compare_field'] = $this->filter_by_field_address_key;
			update_post_meta( $view_id, '_wpv_settings', $view_settings );
			do_action( 'wpv_action_wpv_save_item', $view_id );
		}
		return true;
	}

	/**
	 * @param array $view_settings
	 *
	 * @return string
	 */
	protected function get_query_type( array $view_settings ) {
		return $view_settings['query_type'][0];
	}

	/**
	 * @param string $query_type
	 * @param string $field_name
	 *
	 * @return string
	 */
	protected function get_field_type( $query_type, $field_name ) {
		switch ( $query_type ) {
			case 'posts':
				return wpv_types_get_field_type( $field_name, 'cf' );
			case 'users':
				return wpv_types_get_field_type( $field_name, 'uf' );
			case 'taxonomy':
				return wpv_types_get_field_type( $field_name, 'tf' );
			default:
				return '';
		}
	}

	/**
	 * Returns unprefixed field name based ony query type
	 *
	 * @param string $query_type
	 * @param string $field
	 *
	 * @return string
	 */
	protected function remove_prefix_from_field_name( $query_type, $field ) {
		switch ( $query_type ) {
			case 'posts':
				return $this->remove_prefix( $field, 'field-' );
			case 'users':
				return $this->remove_prefix( $field, 'user-field-' );
			case 'taxonomy':
				return $this->remove_prefix( $field, 'taxonomy-field-' );
			default:
				return '';
		}
	}

	/**
	 * Given $view_settings, returns orderby field name depending on query type
	 *
	 * @param array $view_settings
	 *
	 * @return string
	 */
	protected function get_orderby( array $view_settings ) {
		switch ( $this->get_query_type( $view_settings ) ) {
			case 'users':
				return $view_settings['users_orderby'];
			case 'posts':
				return $view_settings['orderby'];
			case 'taxonomy':
				return $view_settings['taxonomy_orderby'];
			default:
				return '';
		}
	}

	/**
	 * Removes prefix from string, if it exists
	 *
	 * @param string $text
	 * @param string $prefix
	 *
	 * @return string
	 */
	protected function remove_prefix( $text, $prefix ) {
		if ( 0 === strpos( $text, $prefix ) ) {
			$text = substr( $text, strlen( $prefix ) );
		}
		return $text;
	}

	/**
	 * Returns given database table name with $wpdb prefix
	 *
	 * @since 1.8
	 *
	 * @param string $unprefixed_table_name
	 *
	 * @return string
	 */
	protected function get_prefixed_table_name( $unprefixed_table_name ) {
		global $wpdb;

		return $wpdb->prefix . $unprefixed_table_name;
	}

	/**
	 * @since 1.8
	 *
	 * @return string
	 */
	protected function get_address_cache_table_name() {
		return $this->get_prefixed_table_name( CreateDatabaseTable::TABLE_NAME );
	}

	/**
	 * @since 1.8
	 *
	 * @return string
	 */
	protected function get_postmeta_table_name() {
		return $this->get_prefixed_table_name( 'postmeta' );
	}

	/**
	 * @since 1.8
	 *
	 * @return string
	 */
	protected function get_posts_table_name() {
		return $this->get_prefixed_table_name( 'posts' );
	}

	/**
	 * Joins the address cache table to the query.
	 *
	 * @since 1.8
	 *
	 * @param string $join
	 * @param string $meta_table_name
	 *
	 * @return string
	 */
	protected function join_address_cache_to_query( $join, $meta_table_name ) {
		$table_name = $this->get_address_cache_table_name();
		$posts_table_name = $this->get_posts_table_name();

		// Always join the postmeta table with an alias,
		// so we do not clash with sorting or filtering by other fields.
		$sql = "\nINNER JOIN $meta_table_name AS tmapsmeta ON ( $posts_table_name.ID = tmapsmeta.post_id )";
		if ( $this->orderby_field_address_key || $this->filter_by_field_address_key ) {
			// TODO We can not filter by one address field AND sort by another address field
			// To do so, we would need two different JOIN aliases
			$address_key = $this->orderby_field_address_key ?: $this->filter_by_field_address_key;
			$and = " AND tmapsmeta.meta_key = '{$address_key}' ";
		}

		// Join the cache table using the alias. Left join because, until there is an actual WHERE clause using
		// location, we don't want this join to affect results. (Or, in other words, show even results that don't have
		// a location until distance filtering is actually used. Maybe there are multiple filters and someone wants
		// results from other filter to include even results with no location.)
		$sql .= "\nLEFT JOIN $table_name ON $table_name.address_passed = tmapsmeta.meta_value\n";

		if ( strpos( $join, $sql ) === false ) {
			$join .= $sql;
		}
		if ( isset($and) && strpos( $join, $and ) === false ) {
			$join .= $and;
		}

		return $join;
	}

	/**
	 * Joins the address cache table to the post query. Idempotent.
	 *
	 * @since 1.8
	 *
	 * @param string $join
	 *
	 * @return string
	 */
	public function join_address_cache_to_post_query( $join ) {
		remove_filter( 'posts_join', [ $this, 'join_address_cache_to_post_query' ] );

		return $this->join_address_cache_to_query( $join, $this->get_postmeta_table_name() );
	}

	/**
	 * Get the engine powering the currently active database.
	 * Can be MySQL or MariaDB.
	 *
	 * @return string
	 * @since 1.8.2
	 */
	protected function get_db_engine() {
		if ( null !== $this->wp_db_engine ) {
			return $this->wp_db_engine;
		}

		global $wpdb;
		$ver = $wpdb->get_var('SELECT VERSION()');

		if ( stripos( $ver, 'maria' ) !== false ) {
			$this->wp_db_engine = self::DB_ENGINE_MARIADB;
		} else {
			$this->wp_db_engine = self::DB_ENGINE_MYSQL;
		}

		return $this->wp_db_engine;
	}

	/**
	 * Get the current database version number.
	 *
	 * @return string
	 * @since 1.8.2
	 */
	protected function get_db_version() {
		if ( null !== $this->wp_db_version ) {
			return $this->wp_db_version;
		}

		global $wpdb;
		$this->wp_db_version = preg_replace( '/[^0-9\.]/i', '', $wpdb->db_version() );

		return $this->wp_db_version;
	}

	/**
	 * Check whether the plugin can use sphere distance functions in SQL statements,
	 * as they are supported only over 5.7.6 and above, and only on MySQL.
	 *
	 * @return bool
	 * @since 1.8.2
	 */
	protected function can_use_sphere_distance_sql() {
		if ( self::DB_ENGINE_MYSQL !== $this->get_db_engine() ) {
			return false;
		}

		$mysql_version = $this->get_db_version();
		if ( version_compare( $mysql_version, '5.7.6', '<' ) ) {
			return false;
		}

		return true;
	}
}
