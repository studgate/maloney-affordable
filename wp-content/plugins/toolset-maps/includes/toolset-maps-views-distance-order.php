<?php

/**
 * Class Toolset_Maps_Views_Distance_Order
 *
 * Provides Views ordering by distance.
 *
 * @package ToolsetMaps
 * @since 1.5
 */
class Toolset_Maps_Views_Distance_Order extends Toolset_Maps_Views_Distance {

	public $supported_query_types = array( 'posts', 'users', 'taxonomy' );

	/** @var null|Toolset_Maps_Location Passed here from order_posts_in_sql() to orderby_address_cache() */
	private $center = null;

	public function __construct() {
		if ( ! $this->is_types_active() ) return;

		parent::__construct();

		$this->register_child_setting_string( 'distance_order' );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public function init() {
		if ( Toolset_Addon_Maps_Common::are_coordinates_migrated() ) {
			add_filter( 'wpv_filter_query', array( $this, 'order_posts_in_sql' ), 10, 3 );
			add_filter( 'wpv_filter_user_query', array( $this, 'order_users_in_sql' ), 10, 3 );
			add_filter( 'wpv_filter_taxonomy_query', array( $this, 'order_taxonomy_in_sql' ), 10, 3 );
		} else {
			// Add post processing filters to order items by distance before they're rendered
			// (Priority 110 for posts because distance filter is at 100.)
			add_filter( 'wpv_filter_query_post_process', array( $this, 'order_posts' ), 110, 3 );
			add_filter( 'wpv_filter_user_post_query', array( $this, 'order_items' ), 20, 4 );
			add_filter( 'wpv_filter_taxonomy_post_query', array( $this, 'order_items' ), 20, 4 );
		}
	}

	public function admin_init() {
		$distance_order_admin_js_dependecies = array( 'jquery', 'underscore', 'views-editor-js' );
		if ( Toolset_Addon_Maps_Common::API_GOOGLE === apply_filters( 'toolset_maps_get_api_used', '' ) ) {
			$distance_order_admin_js_dependecies[] = 'jquery-geocomplete';
		} else {
			$distance_order_admin_js_dependecies[] = 'toolset-maps-address-autocomplete';
		}

		wp_register_script(
			'toolset-maps-views-distance-order-admin',
			TOOLSET_ADDON_MAPS_URL_JS . 'toolset_maps_views_distance_order_admin.js',
			$distance_order_admin_js_dependecies,
			TOOLSET_ADDON_MAPS_VERSION
		);
		add_action('admin_enqueue_scripts',	array( $this, 'admin_enqueue_scripts' ) );

		// Add filters for distance order specific options
		add_filter( 'wpv_filter_wpv_get_orderby_as_options', array( $this, 'orderby_as_option' ), 10, 2 );
		add_filter( 'wpv_filter_wpv_get_additional_order_options', array( $this, 'add_order_options' ), 10, 2 );
		add_filter( 'wpv_filter_wpv_get_additional_sorting_options', array( $this, 'add_sorting_options' ) );
	}

	public function admin_enqueue_scripts() {
		if (
			isset( $_GET['page'] )
			&& $_GET['page'] === 'views-editor'
		) {
			wp_enqueue_script( 'toolset-maps-views-distance-order-admin' );

			// Send all address field names to JS for updating order availability
			$js = 'WPViews.distanceOrderAdmin.addressFieldNames = '
			      . json_encode( $this->get_all_address_field_names() )
			      . ';'
			      . 'WPViews.distanceOrderAdmin.apiUsed = "'
			      . apply_filters( 'toolset_maps_get_api_used', '' )
			      . '";' ;
			wp_add_inline_script( 'toolset-maps-views-distance-order-admin', $js );
		}
	}

	/**
	 * Adds distance order specific orderby_as option to supported query types.
	 *
	 * @param array $options
	 * @param array $view_settings
	 * @return array
	 */
	public function orderby_as_option( array $options, array $view_settings ) {
		if ( in_array( $this->get_query_type( $view_settings ), $this->supported_query_types ) ) {
			$options[self::ORDERBY_AS] = __( 'As a distance from', 'toolset-maps' );
		}

		return $options;
	}

	/**
	 * Is this a post view?
	 *
	 * @since 1.6
	 *
	 * @param array $view_settings
	 *
	 * @return bool
	 */
	private function is_post_view( array $view_settings ) {
		return ( $this->get_query_type( $view_settings ) === 'posts' );
	}

	/**
	 * Adds HTML & JS for distance order specific options: 'from' and, if 'from' is 'fixed', location address field.
	 *
	 * @param string $html
	 * @param array $view_settings
	 *
	 * @return string
	 */
	public function add_order_options( $html, array $view_settings ) {
		$post_view = $this->is_post_view( $view_settings );
		$selected_source = isset( $view_settings['distance_order']['source'] )
			? $view_settings['distance_order']['source']
			: 'fixed';
		$distance_center_style = ( $selected_source === 'fixed' && $this->is_orderby_as_distance( $view_settings ) )
			? ''
			: 'display: none';
		$distance_center_url_style = (
				$selected_source === 'url_parameter'
				&& $this->is_orderby_as_distance( $view_settings )
				&& $post_view
			)
			? ''
			: 'display: none';
		$distance_source_style = $this->is_orderby_as_distance( $view_settings ) ? '' : 'display: none';
		$distance_order_center = isset( $view_settings['distance_order']['center'] )
			? $view_settings['distance_order']['center']
			: '';

		$html .= $this->render_view(
			'distance_order_options',
			array(
				$selected_source, $distance_center_style, $distance_source_style, $distance_order_center,
				$distance_center_url_style, $post_view
			)
		);

		return $html;
	}


	/**
	 * Add distance_order as option to be processed in wpv_update_sorting_callback()
	 *
	 * @param array $sorting_options
	 *
	 * @return array
	 */
	public function add_sorting_options( array $sorting_options ) {
		$sorting_options[] = 'distance_order';

		return $sorting_options;
	}


	/**
	 * Combines filters to return all fields of type google_address, be it postmeta, termmeta or usermeta.
	 * @return array
	 */
	protected function get_all_address_fields() {
		return array_merge(
			apply_filters('toolset_filter_toolset_maps_get_types_postmeta_fields', array() ),
			apply_filters('toolset_filter_toolset_maps_get_types_termmeta_fields', array() ),
			apply_filters('toolset_filter_toolset_maps_get_types_usermeta_fields', array() )
		);
	}

	/**
	 * @return array of google_address fields meta keys
	 */
	protected function get_all_address_fields_meta_keys() {
		$meta_keys = array();

		foreach ( $this->get_all_address_fields() as $address_field ) {
			$meta_keys[] = $address_field['meta_key'];
		}

		return $meta_keys;
	}

	/**
	 * @return array of google_address fields names with 'field-' prefix
	 */
	protected function get_all_address_field_names() {
		$field_names = array();

		foreach ( $this->get_all_address_fields_meta_keys() as $meta_key ) {
			$field_names[] = "field-$meta_key";
		}

		return $field_names;
	}

	/**
	 * Orders the query by distance. Used for posts.
	 *
	 * This ordering method's specific part in $view_settings:
	 *
	 * $view_settings['distance_order'] = array(
	 * 		'map_center_source' => 'fixed', // or 'visitor_location'
	 * 		'map_distance_center' => 'Madrid, Spain' // only needed for 'map_center_source' => 'fixed'
	 * );
	 *
	 * @param WP_Query $query
	 * @param array $view_settings
	 * @param string $view_id
	 *
	 * @return WP_Query
	 */
	public function order_posts( WP_Query $query, array $view_settings, $view_id ) {
		// No ordering by distance is asked for, get out
		if ( ! $this->is_distance_order_requested( $view_settings ) ) {
			return $query;
		}

		// Check if we have needed settings
		if (
			array_key_exists( 'distance_order', $view_settings )
			&& is_array( $view_settings['distance_order'] )
		) {
			$distance_order = $view_settings['distance_order'];
		} else {
			return $this->bring_paging_back( $query );
		}

		// Get center depending on source
		$center = $this->get_location_center_object( $distance_order );

		// Can't resolve ordering center to a location??? Get out.
		if ( ! $center ) return $this->bring_paging_back( $query );

		// Going through all posts to calculate distances
		$meta_key = $this->remove_prefix( $view_settings['orderby'], 'field-' );
		$posts = $this->add_distance_to_items( $query->posts, $meta_key, $center );

		// Sort by distance
		if ( $view_settings['order'] === 'ASC' ) {
			usort( $posts, array( $this, 'compare_distances_asc' ) );
		} elseif ( $view_settings['order'] === 'DESC' ) {
			usort( $posts, array( $this, 'compare_distances_desc' ) );
		}

		$query->posts = $posts;

		return $this->bring_paging_back( $query );
	}

	/**
	 * Adds filters which will add distance ordering SQL, if a distance order is requested.
	 *
	 * @since 2.0
	 *
	 * @param array $query
	 * @param array $view_settings
	 * @param string $view_id
	 *
	 * @return array
	 */
	public function order_posts_in_sql( array $query, array $view_settings, $view_id ) {
		if ( ! $this->is_distance_order_requested( $view_settings ) ) {
			return $query;
		}

		// Check if we have needed settings
		if (
			array_key_exists( 'distance_order', $view_settings )
			&& is_array( $view_settings['distance_order'] )
		) {
			$distance_order = $view_settings['distance_order'];
		} else {
			return $query;
		}

		// Get center depending on source
		$center = $this->get_location_center_object( $distance_order );

		// Can't resolve ordering center to a location??? Get out.
		if ( ! $center ) {
			return $query;
		}

		// Save the center, so it can be picked up by orderby_address_cache()
		$this->center = $center;

		// Add the filters
		add_filter( 'posts_join', [ $this, 'join_address_cache_to_post_query' ] );
		add_filter( 'posts_orderby', [ $this, 'orderby_address_cache' ] );

		return $query;
	}

	/**
	 * Replaces meta_table_name.meta_value with actual distance calculation SQL code to order by
	 *
	 * @since 2.0
	 *
	 * @param string $orderby
	 * @param string $meta_table_name
	 * @param Toolset_Maps_Location $center
	 *
	 * @return string
	 */
	private function replace_orderby( $orderby, $meta_table_name, Toolset_Maps_Location $center ) {
		$address_cache_table_name = $this->get_address_cache_table_name();

		if ( $this->can_use_sphere_distance_sql() ) {
			return str_replace(
				"$meta_table_name.meta_value",
				"ST_Distance_Sphere(ST_PointFromText('POINT({$center->get_lng()} {$center->get_lat()})'), "
					. "{$address_cache_table_name}.point)",
				$orderby
			);
		}

		return str_replace(
			"$meta_table_name.meta_value",
			"ASIN("
				. "SQRT("
					. "POW( SIN( ( radians( ST_Y({$address_cache_table_name}.point) - {$center->get_lat()} ) ) / 2 ), 2 ) "
					. "+ COS( radians( {$center->get_lat()} ) ) * COS( radians( ST_Y({$address_cache_table_name}.point) ) ) "
					. "* POW( SIN( ( radians( ST_X({$address_cache_table_name}.point) - {$center->get_lng()} ) ) / 2 ), 2 ) "
				. ")"
			. ")",
			$orderby
		);
	}

	/**
	 * Replaces ORDER BY distance SQL for post queries.
	 *
	 * @since 2.0
	 *
	 * @param string $orderby
	 *
	 * @return string
	 */
	public function orderby_address_cache( $orderby ) {
		$center = $this->center;

		if ( ! $center ) {
			return $orderby;
		}

		$orderby = $this->replace_orderby( $orderby, $this->get_postmeta_table_name(), $center );

		remove_filter( 'posts_orderby', [ $this, 'orderby_address_cache' ] );

		return $orderby;
	}

	/**
	 * Adds action to modify user query to be distance ordered.
	 *
	 * @since 2.0
	 *
	 * @param array $args
	 * @param array $view_settings
	 * @param int $view_id
	 *
	 * @return array
	 */
	public function order_users_in_sql( array $args, array $view_settings, $view_id ) {
		// No ordering by distance is asked for, get out
		if ( ! $this->is_distance_order_requested( $view_settings ) ) {
			return $args;
		}

		// Check if we have needed settings
		if (
			array_key_exists( 'distance_order', $view_settings )
			&& is_array( $view_settings['distance_order'] )
		) {
			$distance_order = $view_settings['distance_order'];
		} else {
			return $args;
		}

		$center = $this->get_location_center_object( $distance_order );

		// Can't resolve ordering center to a location??? Get out.
		if ( ! $center ) {
			return $args;
		}

		// Save the center, so it can be picked up by action
		$this->center = $center;

		add_action( 'pre_user_query', [ $this, 'change_pre_user_query' ] );

		return $args;
	}

	/**
	 * Adds ORDER BY distance SQL to user query. ($query object is passed by reference, directly modifying it.)
	 *
	 * @since 2.0
	 *
	 * @param WP_User_Query $query
	 */
	public function change_pre_user_query( WP_User_Query $query ) {
		$query->query_from = $this->join_address_cache_to_query( $query->query_from, $this->get_usermeta_table_name() );

		$query->query_orderby = $this->replace_orderby(
			$query->query_orderby,
			$this->get_usermeta_table_name(),
			$this->center
		);

		remove_action( 'pre_user_query', [ $this, 'change_pre_user_query' ] );
	}

	/**
	 * @since 2.0
	 *
	 * @return string
	 */
	private function get_usermeta_table_name() {
		return $this->get_prefixed_table_name( 'usermeta' );
	}

	/**
	 * @since 2.0
	 *
	 * @return string
	 */
	private function get_termmeta_table_name() {
		return $this->get_prefixed_table_name( 'termmeta' );
	}

	/**
	 * Adds action to modify taxonomy query to be distance ordered.
	 *
	 * @since 2.0
	 *
	 * @param array $tax_query_settings
	 * @param array $view_settings
	 * @param int $view_id
	 *
	 * @return array
	 */
	public function order_taxonomy_in_sql( array $tax_query_settings, array $view_settings, $view_id ) {
		// No ordering by distance is asked for, get out
		if ( ! $this->is_distance_order_requested( $view_settings ) ) {
			return $tax_query_settings;
		}

		// Check if we have needed settings
		if (
			array_key_exists( 'distance_order', $view_settings )
			&& is_array( $view_settings['distance_order'] )
		) {
			$distance_order = $view_settings['distance_order'];
		} else {
			return $tax_query_settings;
		}

		$center = $this->get_location_center_object( $distance_order );

		// Can't resolve ordering center to a location??? Get out.
		if ( ! $center ) {
			return $tax_query_settings;
		}

		// Save the center, so it can be picked up by filter
		$this->center = $center;

		add_filter( 'terms_clauses', [ $this, 'change_terms_query' ], 10, 3 );

		return $tax_query_settings;
	}

	/**
	 * Adds ORDER BY distance SQL to term query.
	 *
	 * @since 2.0
	 *
	 * @param array $clauses
	 * @param array $taxonomies
	 * @param array $args
	 *
	 * @return array
	 */
	public function change_terms_query( array $clauses, array $taxonomies, array $args ) {
		$termmeta_name = $this->get_termmeta_table_name();

		$clauses['join'] = $this->join_address_cache_to_query( $clauses['join'], $termmeta_name );

		$clauses['orderby'] = $this->replace_orderby( $clauses['orderby'], $termmeta_name, $this->center );

		remove_filter( 'terms_clauses', [ $this, 'change_terms_query' ], 10 );

		return $clauses;
	}

	/**
	 * Orders an array of user or term items by distance.
	 *
	 * @param array $items
	 * @param array $args
	 * @param array $view_settings
	 * @param string $view_id
	 *
	 * @return array
	 */
	public function order_items( array $items, array $args, array $view_settings, $view_id ) {
		// No ordering by distance is asked for, get out
		if ( ! $this->is_distance_order_requested( $view_settings ) ) {
			return $items;
		}

		// Check if we have needed settings
		if (
			array_key_exists( 'distance_order', $view_settings )
			&& is_array( $view_settings['distance_order'] )
		) {
			$distance_order = $view_settings['distance_order'];
		} else {
			return $items;
		}

		$center = $this->get_location_center_object( $distance_order );

		// Can't resolve ordering center to a location??? Get out.
		if ( ! $center ) return $items;

		// Going through all items to calculate distances
		$sorted_items = $this->add_distance_to_items( $items, $args['meta_key'], $center );

		// Sort by distance
		if ( $args['order'] === 'ASC' ) {
			usort( $sorted_items, array( $this, 'compare_distances_asc' ) );
		} elseif ( $args['order'] === 'DESC' ) {
			usort( $sorted_items, array( $this, 'compare_distances_desc' ) );
		}

		return $sorted_items;
	}

	/**
	 * Given an array of items (posts, users or terms), adds distances to them.
	 *
	 * @param array $items
	 * @param string $meta_key
	 * @param Toolset_Maps_Location $center
	 *
	 * @return array
	 */
	protected function add_distance_to_items( array $items, $meta_key, Toolset_Maps_Location $center ) {
		$items_with_distance = array();

		// Going through all items to calculate distances
		foreach ( $items as $item ) {

			$addresses = $this->get_item_meta( $item, $meta_key );
			$distances = array();

			// We can have repeatable address fields
			foreach ( $addresses as $address ) {

				// Address field can be empty, so there's nothing to sort by. Behaviour in similar cases in WP seems
				// to be to drop the item, so we do the same. Though it is unintuitive that an order would filter
				// out stuff, but there it is.
				if ( empty( $address ) ) continue;

				$location = Toolset_Maps_Location_Factory::create_from_address( $address );

				// Address can also be some random string, or in any other way unresolvable to location
				if ( ! $location ) continue;

				// Calculate distances (we don't care for unit here - just relative distances to order by)
				$distance = $this->calculate_distance( $center, $location );
				$item->distance = $distance;
				$distances[$distance] = $item;
			}

			// If we have any distances calculated, use the item with the smallest one
			if ( ! empty( $distances ) ) {
				$items_with_distance[] = $distances[ min( array_keys( $distances ) ) ];
			}
		}
		return $items_with_distance;
	}

	/**
	 * Get metadata for post, user or term item.
	 *
	 * @param object $item
	 * @param string $meta_key
	 *
	 * @return array
	 */
	protected function get_item_meta( $item, $meta_key ) {
		if ( $item instanceof WP_Post ) {
			return get_post_meta( $item->ID, $meta_key );
		} elseif ( $item instanceof WP_User ) {
			return get_user_meta( $item->ID, $meta_key );
		} elseif ( $item instanceof WP_Term ) {
			return get_term_meta( $item->term_id, $meta_key );
		}
		return array();
	}

	/**
	 * Get center depending on source.
	 *
	 * @param array $distance_order
	 *
	 * @return null|Toolset_Maps_Location
	 */
	protected function get_location_center_object( array $distance_order ) {
		if ( $distance_order['source'] === 'fixed' ) {
			return Toolset_Maps_Location_Factory::create_from_address( $distance_order['center'] );
		} elseif ( $distance_order['source'] === 'visitor_location' ) {
			return Toolset_Maps_Location_Factory::create_from_cookie();
		} elseif ( $distance_order['source'] === 'url_parameter' ) {
			return Toolset_Maps_Location_Factory::create_from_address(
				toolset_getget( $distance_order['url_parameter'] )
			);
		}
		return null;
	}

	/**
	 * Used by usort to order by distance
	 *
	 * @param object $a
	 * @param object $b
	 *
	 * @return bool
	 */
	protected function compare_distances_desc( $a, $b ) {
		return $a->distance < $b->distance;
	}

	/**
	 * @param object $a
	 * @param object $b
	 *
	 * @return bool
	 */
	protected function compare_distances_asc( $a, $b ) {
		return $a->distance > $b->distance;
	}
}

new Toolset_Maps_Views_Distance_Order();
