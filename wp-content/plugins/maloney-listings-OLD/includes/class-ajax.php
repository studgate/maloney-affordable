<?php
/**
 * AJAX Handlers
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_AJAX {
    private static $unit_size_option_cache = null;
    private static $amenity_names_for_features_search = array(); // Store amenity names for Features field search
    
    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @param string $unit 'mi' for miles, 'km' for kilometers
     * @return float Distance in specified unit
     */
    private function calculate_distance($lat1, $lng1, $lat2, $lng2, $unit = 'mi') {
        $earth_radius = ($unit == 'mi' ? 3963.0 : 6371);
        
        $lat_diff = deg2rad($lat2 - $lat1);
        $lng_diff = deg2rad($lng2 - $lng1);
        
        $a = sin($lat_diff / 2) * sin($lat_diff / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lng_diff / 2) * sin($lng_diff / 2);
        
        $c = 2 * asin(sqrt($a));
        $distance = $earth_radius * $c;
        
        return $distance;
    }
    
    public function __construct() {
        add_action('wp_ajax_filter_listings', array($this, 'filter_listings'));
        add_action('wp_ajax_nopriv_filter_listings', array($this, 'filter_listings'));
        add_action('wp_ajax_get_similar_listings', array($this, 'get_similar_listings'));
        add_action('wp_ajax_nopriv_get_similar_listings', array($this, 'get_similar_listings'));
    }
    
    public function filter_listings() {
        check_ajax_referer('maloney_listings_nonce', 'nonce');
        
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 12,
            'paged' => isset($_POST['page']) ? intval($_POST['page']) : 1,
            'post_status' => 'publish',
            'meta_query' => array(),
            'tax_query' => array(),
            'orderby' => 'title', // Default: Property Name
            'order' => 'ASC',
        );
        
        // Handle sorting
        if (!empty($_POST['sort'])) {
            $sort = sanitize_text_field($_POST['sort']);
            switch ($sort) {
                case 'property_name':
                    $args['orderby'] = 'title';
                    $args['order'] = 'ASC';
                    break;
                case 'city_town':
                    $args['orderby'] = 'meta_value';
                    $args['order'] = 'ASC';
                    $args['meta_key'] = 'wpcf-city';
                    // Ensure meta_query doesn't conflict
                    if (empty($args['meta_query'])) {
                        $args['meta_query'] = array();
                    }
                    // Add city meta query if not already filtering by city
                    $has_city_filter = false;
                    foreach ($args['meta_query'] as $mq) {
                        if (is_array($mq) && isset($mq['key']) && 
                            ($mq['key'] === 'wpcf-city' || $mq['key'] === '_listing_city')) {
                            $has_city_filter = true;
                            break;
                        }
                    }
                    if (!$has_city_filter) {
                        // Add meta query to ensure city exists for sorting
                        $args['meta_query'][] = array(
                            'relation' => 'OR',
                            array('key' => 'wpcf-city', 'compare' => 'EXISTS'),
                            array('key' => '_listing_city', 'compare' => 'EXISTS'),
                        );
                    }
                    break;
            }
        }
        
        // Filter by listing type
        // BUT: If available units filter is active, we need to include rentals in the query
        // (we'll filter them post-query based on availability)
        $has_available_units_filter_check = !empty($_POST['has_available_units']) || 
                                           (!empty($_POST['available_unit_type']) && is_array($_POST['available_unit_type']) && count($_POST['available_unit_type']) > 0);
        
        if (!empty($_POST['listing_type'])) {
            $selected_type = sanitize_text_field($_POST['listing_type']);
            // If available units filter is active, we need rentals in the query
            // So if they selected a type that's not rental, we still need to include rentals
            if ($has_available_units_filter_check && strpos(strtolower($selected_type), 'rental') === false) {
                // Include both the selected type AND rentals
                $args['tax_query'][] = array(
                    'taxonomy' => 'listing_type',
                    'field' => 'slug',
                    'terms' => array($selected_type, 'rental', 'rental-properties'),
                    'operator' => 'IN',
                );
            } else {
                $args['tax_query'][] = array(
                    'taxonomy' => 'listing_type',
                    'field' => 'slug',
                    'terms' => $selected_type,
                );
            }
        } elseif ($has_available_units_filter_check) {
            // If available units filter is active but no listing type filter, 
            // we need to include rentals in the query to check their availability
            // Don't filter by type - include all types, we'll filter post-query
        }
        
        // Filter by status
        if (!empty($_POST['status'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'listing_status',
                'field' => 'slug',
                'terms' => sanitize_text_field($_POST['status']),
            );
        }
        
        // Filter by location (city or zip code)
        if (!empty($_POST['location'])) {
            $location = sanitize_text_field($_POST['location']);
            // Check if it's a zip code (numeric, 5 digits or 5+4 format)
            $is_zip = preg_match('/^\d{5}(-\d{4})?$/', $location);
            
            if ($is_zip) {
                // Filter by zip code - check both new field (zip-code) and legacy fields for compatibility
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wpcf-zip-code',
                        'value' => $location,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'wpcf-zip',
                        'value' => $location,
                        'compare' => '=',
                    ),
                    array(
                        'key' => '_listing_zip',
                        'value' => $location,
                        'compare' => '=',
                    ),
                );
            } else {
                // Filter by city
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wpcf-city',
                        'value' => $location,
                        'compare' => '=',
                    ),
                    array(
                        'key' => '_listing_city',
                        'value' => $location,
                        'compare' => '=',
                    ),
                );
            }
        }
        
        // Also handle search_location_input (from autocomplete)
        // Store zip code search info for fallback to nearby listings
        $zip_search_location = null;
        $zip_search_lat = null;
        $zip_search_lng = null;
        
        if (!empty($_POST['search_location'])) {
            global $wpdb;
            $search_location = sanitize_text_field(wp_unslash($_POST['search_location']));
            $search_location = trim($search_location);
            if ($search_location !== '') {
                $is_zip = preg_match('/^\d{3,5}(-\d{4})?$/', $search_location);
                
                // WordPress automatically adds % and escapes for LIKE queries, so just pass the raw value
                $search_meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wpcf-address',
                        'value' => $search_location,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => '_listing_address',
                        'value' => $search_location,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'wpcf-city',
                        'value' => $search_location,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => '_listing_city',
                        'value' => $search_location,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'wpcf-availability-town',
                        'value' => $search_location,
                        'compare' => 'LIKE',
                    ),
                );
                
                if ($is_zip) {
                    $zip_search_location = $search_location;
                    
                    if (!empty($_POST['search_location_lat']) && !empty($_POST['search_location_lng'])) {
                        $zip_search_lat = floatval($_POST['search_location_lat']);
                        $zip_search_lng = floatval($_POST['search_location_lng']);
                    }
                    
                    $search_meta_query[] = array(
                        'key' => 'wpcf-zip-code',
                        'value' => $search_location,
                        'compare' => '=',
                    );
                    $search_meta_query[] = array(
                        'key' => 'wpcf-zip',
                        'value' => $search_location,
                        'compare' => '=',
                    );
                    $search_meta_query[] = array(
                        'key' => '_listing_zip',
                        'value' => $search_location,
                        'compare' => '=',
                    );
                }
                
                $args['meta_query'][] = $search_meta_query;
            }
        }
        
        // Filter by bedrooms - search wpcf-unit-sizes field for matching unit sizes
        // Listings can have multiple unit sizes (Studio, One Bedroom, Two Bedroom, etc.)
        // Filter should return listings that have at least one of the selected unit sizes
        // Toolset checkbox fields store option keys in a serialized array, so we need to:
        // 1. Get all listings first
        // 2. Check each listing's wpcf-unit-sizes array for matching unit sizes
        // 3. Filter by post__in with matching post IDs
        $has_bed_filter = false;
        $bedrooms_options = array();
        
        if (!empty($_POST['bedrooms_multi']) && is_array($_POST['bedrooms_multi'])) {
            $bedrooms_options = array_map('sanitize_text_field', $_POST['bedrooms_multi']);
            // Remove "any" default option
            $bedrooms_options = array_filter($bedrooms_options, function($val) { return $val !== 'any'; });
        }
        // Also check bedroom_options (from checkbox filters)
        if (empty($bedrooms_options) && !empty($_POST['bedroom_options']) && is_array($_POST['bedroom_options'])) {
            $bedrooms_options = array_map('sanitize_text_field', $_POST['bedroom_options']);
            // Remove "any" and "show_all" default options
            $bedrooms_options = array_filter($bedrooms_options, function($val) { return $val !== 'any' && $val !== 'show_all'; });
        }
        
        // Check if "Has Available Units" is checked - if so, we'll filter by available unit types instead of general bedrooms
        $has_available_units_check = !empty($_POST['has_available_units']);
        
        if (!empty($bedrooms_options)) {
            // Check if "Has Available Units" is checked - if so, skip general bedrooms filter
            // and only filter by available unit types (handled below)
            $has_available_units_check = !empty($_POST['has_available_units']);
            
            // Only apply general bedrooms filter if "Has Available Units" is NOT checked
            // When "Has Available Units" is checked, we'll filter by available unit types instead
            if (!$has_available_units_check) {
                $has_bed_filter = true;
                
                // Map filter values to unit size labels
                $unit_size_labels = array(
                    '0' => 'Studio',
                    '1' => 'One Bedroom',
                    '2' => 'Two Bedroom',
                    '3' => 'Three Bedroom',
                    '4+' => 'Four Bedroom',
                );
                
                // Get Toolset field definition to map titles to option keys
                $title_to_option_keys = array();
                if (function_exists('wpcf_admin_fields_get_fields')) {
                    $all_fields = wpcf_admin_fields_get_fields();
                    foreach ($all_fields as $fid => $f) {
                        if (!empty($f['meta_key']) && $f['meta_key'] === 'wpcf-unit-sizes' && !empty($f['data']['options'])) {
                            foreach ($f['data']['options'] as $okey => $odata) {
                                $title = isset($odata['title']) ? trim($odata['title']) : '';
                                if ($title) {
                                    // Normalize title for matching
                                    $title_lower = strtolower($title);
                                    foreach ($unit_size_labels as $filter_val => $label) {
                                        $label_lower = strtolower($label);
                                        // Check if this option title matches our filter label
                                        if ($title_lower === $label_lower || 
                                            (stripos($title_lower, 'studio') !== false && $label_lower === 'studio') ||
                                            (stripos($title_lower, 'one') !== false && stripos($title_lower, 'bedroom') !== false && $label_lower === 'one bedroom') ||
                                            (stripos($title_lower, 'two') !== false && stripos($title_lower, 'bedroom') !== false && $label_lower === 'two bedroom') ||
                                            (stripos($title_lower, 'three') !== false && stripos($title_lower, 'bedroom') !== false && $label_lower === 'three bedroom') ||
                                            (stripos($title_lower, 'four') !== false && stripos($title_lower, 'bedroom') !== false && $label_lower === 'four bedroom')) {
                                            if (!isset($title_to_option_keys[$filter_val])) {
                                                $title_to_option_keys[$filter_val] = array();
                                            }
                                            $title_to_option_keys[$filter_val][] = $okey;
                                        }
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
                
                // Get all listings with all current filters applied (except bedrooms)
                $base_args = $args;
                // Remove any existing bedrooms meta_query
                if (isset($base_args['meta_query'])) {
                    foreach ($base_args['meta_query'] as $key => $meta_q) {
                        if (is_array($meta_q) && isset($meta_q['maloney_tag']) && $meta_q['maloney_tag'] === 'bedrooms') {
                            unset($base_args['meta_query'][$key]);
                        }
                    }
                    $base_args['meta_query'] = array_values($base_args['meta_query']); // Re-index
                }
                $base_args['posts_per_page'] = -1;
                $base_args['fields'] = 'ids';
                
                $all_listings_query = new WP_Query($base_args);
                $all_listing_ids = $all_listings_query->posts;
                wp_reset_postdata();
                
                // Filter listings by checking their wpcf-unit-sizes field
                $matching_listing_ids = array();
                foreach ($all_listing_ids as $listing_id) {
                    $unit_sizes = get_post_meta($listing_id, 'wpcf-unit-sizes', true);
                    if (empty($unit_sizes)) {
                        $unit_sizes = get_post_meta($listing_id, '_listing_unit_sizes', true);
                    }
                    
                    // Unserialize if needed
                    if (is_string($unit_sizes)) {
                        $unit_sizes = maybe_unserialize($unit_sizes);
                    }
                    
                    if (!is_array($unit_sizes)) {
                        continue;
                    }
                    
                    // Check if any of the selected bedroom options match this listing's unit sizes
                    $matches = false;
                    foreach ($bedrooms_options as $opt) {
                        if (!isset($unit_size_labels[$opt])) {
                            continue;
                        }
                        
                        $label = $unit_size_labels[$opt];
                        
                        // Check if this listing has the unit size by:
                        // 1. Checking option keys (if we have the mapping)
                        if (!empty($title_to_option_keys[$opt])) {
                            foreach ($title_to_option_keys[$opt] as $option_key) {
                                if (isset($unit_sizes[$option_key])) {
                                    $matches = true;
                                    break 2; // Break out of both loops
                                }
                            }
                        }
                        
                        // 2. Checking if the label appears in the array values
                        foreach ($unit_sizes as $key => $value) {
                            if (is_array($value)) {
                                // Check in array structure
                                if (isset($value['title']) && stripos($value['title'], $label) !== false) {
                                    $matches = true;
                                    break 2;
                                }
                                if (isset($value['value']) && stripos($value['value'], $label) !== false) {
                                    $matches = true;
                                    break 2;
                                }
                            } else {
                                // Check string value
                                $value_str = is_string($value) ? $value : (string)$value;
                                if (stripos($value_str, $label) !== false) {
                                    $matches = true;
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    if ($matches) {
                        $matching_listing_ids[] = $listing_id;
                    }
                }
                
                // Use post__in to filter by matching IDs
                if (!empty($matching_listing_ids)) {
                    if (isset($args['post__in']) && is_array($args['post__in'])) {
                        // Intersect with existing post__in if any
                        $args['post__in'] = array_intersect($args['post__in'], $matching_listing_ids);
                    } else {
                        $args['post__in'] = $matching_listing_ids;
                    }
                } else {
                    // No matches, return empty result
                    $args['post__in'] = array(0);
                }
            }
            // If "Has Available Units" is checked, the bedrooms filter will be handled via available_unit_type_filter below
        } elseif (!empty($_POST['bedrooms'])) {
            // Legacy single bedroom filter - use same approach
            $bedrooms = intval($_POST['bedrooms']);
            $has_bed_filter = true;
            
            $unit_size_labels = array(
                0 => 'Studio',
                1 => 'One Bedroom',
                2 => 'Two Bedroom',
                3 => 'Three Bedroom',
            );
            
            if ($bedrooms >= 4) {
                $label = 'Four Bedroom';
            } elseif (isset($unit_size_labels[$bedrooms])) {
                $label = $unit_size_labels[$bedrooms];
            } else {
                $label = '';
            }
            
            if ($label) {
                // Use same post-processing approach for legacy filter
                $base_args = $args;
                if (isset($base_args['meta_query'])) {
                    foreach ($base_args['meta_query'] as $key => $meta_q) {
                        if (is_array($meta_q) && isset($meta_q['maloney_tag']) && $meta_q['maloney_tag'] === 'bedrooms') {
                            unset($base_args['meta_query'][$key]);
                        }
                    }
                    $base_args['meta_query'] = array_values($base_args['meta_query']);
                }
                $base_args['posts_per_page'] = -1;
                $base_args['fields'] = 'ids';
                
                $all_listings_query = new WP_Query($base_args);
                $all_listing_ids = $all_listings_query->posts;
                wp_reset_postdata();
                
                $matching_listing_ids = array();
                foreach ($all_listing_ids as $listing_id) {
                    $unit_sizes = get_post_meta($listing_id, 'wpcf-unit-sizes', true);
                    if (empty($unit_sizes)) {
                        $unit_sizes = get_post_meta($listing_id, '_listing_unit_sizes', true);
                    }
                    if (is_string($unit_sizes)) {
                        $unit_sizes = maybe_unserialize($unit_sizes);
                    }
                    if (!is_array($unit_sizes)) {
                        continue;
                    }
                    
                    $matches = false;
                    foreach ($unit_sizes as $key => $value) {
                        if (is_array($value)) {
                            if (isset($value['title']) && stripos($value['title'], $label) !== false) {
                                $matches = true;
                                break;
                            }
                            if (isset($value['value']) && stripos($value['value'], $label) !== false) {
                                $matches = true;
                                break;
                            }
                        } else {
                            $value_str = is_string($value) ? $value : (string)$value;
                            if (stripos($value_str, $label) !== false) {
                                $matches = true;
                                break;
                            }
                        }
                    }
                    
                    if ($matches) {
                        $matching_listing_ids[] = $listing_id;
                    }
                }
                
                if (!empty($matching_listing_ids)) {
                    if (isset($args['post__in']) && is_array($args['post__in'])) {
                        $args['post__in'] = array_intersect($args['post__in'], $matching_listing_ids);
                    } else {
                        $args['post__in'] = $matching_listing_ids;
                    }
                } else {
                    $args['post__in'] = array(0);
                }
            }
        }
        
        // Filter by bathrooms - now from Current Rental Availability entries (rentals only)
        // Bathrooms are stored per availability entry, not at listing level
        $has_bath_filter = false;
        $bathrooms_options = array();
        
        if (!empty($_POST['bathrooms_multi']) && is_array($_POST['bathrooms_multi'])) {
            $bathrooms_options = array_map('sanitize_text_field', $_POST['bathrooms_multi']);
            // Remove default options
            $bathrooms_options = array_filter($bathrooms_options, function($val) { return $val !== 'show_all' && $val !== 'any'; });
            if (!empty($bathrooms_options)) {
                $has_bath_filter = true;
            }
        }
        // Also check bathroom_options (from checkbox filters)
        if (!$has_bath_filter && !empty($_POST['bathroom_options']) && is_array($_POST['bathroom_options'])) {
            $bathrooms_options = array_map('sanitize_text_field', $_POST['bathroom_options']);
            // Remove default options
            $bathrooms_options = array_filter($bathrooms_options, function($val) { return $val !== 'show_all' && $val !== 'any'; });
            if (!empty($bathrooms_options)) {
                $has_bath_filter = true;
            }
        }
        // Legacy single bathroom filter
        if (!$has_bath_filter && !empty($_POST['bathrooms'])) {
            $bathrooms_val = sanitize_text_field($_POST['bathrooms']);
            if ($bathrooms_val !== 'show_all' && $bathrooms_val !== 'any') {
                $bathrooms_options = array($bathrooms_val);
                $has_bath_filter = true;
            }
        }
        
        // Store bathrooms filter for post-processing (only applies to rentals with availability data)
        if ($has_bath_filter) {
            $args['_filter_bathrooms'] = $bathrooms_options;
        }
        
        // Filter by income limits - now from Current Rental Availability entries (rentals only)
        // Income limits are stored per availability entry, not at listing level
        $has_income_limits_filter = false;
        $income_limits_options = array();
        
        if (!empty($_POST['income_limits']) && is_array($_POST['income_limits'])) {
            $income_limits_options = array_map('sanitize_text_field', $_POST['income_limits']);
            if (!empty($income_limits_options)) {
                $has_income_limits_filter = true;
            }
        }
        
        // Store income limits filter for post-processing (only applies to rentals with availability data)
        if ($has_income_limits_filter) {
            $args['_filter_income_limits'] = $income_limits_options;
        }

        // Affordability filter
        if (!empty($_POST['affordability'])) {
            $aff = sanitize_text_field($_POST['affordability']);
            if ($aff === 'rent_based_on_income') {
                // Look for the phrase in marketing text fields
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_listing_main_marketing_text',
                        'value' => 'rent based on income',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'wpcf-main-marketing-text',
                        'value' => 'rent based on income',
                        'compare' => 'LIKE',
                    ),
                );
            } elseif ($aff === 'fixed_rent') {
                // Presence of a numeric rent price
                $args['meta_query'][] = array(
                    'key' => '_listing_rent_price',
                    'compare' => 'EXISTS',
                );
            }
        }

        // Eligibility filter
        if (!empty($_POST['eligibility'])) {
            $elig = sanitize_text_field($_POST['eligibility']);
            if ($elig === 'age_restricted') {
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_listing_eligibility',
                        'value' => 'age',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'wpcf-eligibility',
                        'value' => 'age',
                        'compare' => 'LIKE',
                    ),
                );
            } elseif ($elig === 'disability') {
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_listing_eligibility',
                        'value' => 'disab',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'wpcf-eligibility',
                        'value' => 'disab',
                        'compare' => 'LIKE',
                    ),
                );
            }
        }
        
        // Filter by price range
        if (!empty($_POST['price_min']) || !empty($_POST['price_max'])) {
            $price_query = array('relation' => 'OR');
            
            // Check rent price
            if (!empty($_POST['price_min']) || !empty($_POST['price_max'])) {
                $rent_query = array(
                    'key' => '_listing_rent_price',
                    'type' => 'NUMERIC',
                );
                
                if (!empty($_POST['price_min'])) {
                    $rent_query['value'] = intval($_POST['price_min']);
                    $rent_query['compare'] = '>=';
                }
                
                if (!empty($_POST['price_max'])) {
                    if (isset($rent_query['value'])) {
                        $rent_query['value'] = array($rent_query['value'], intval($_POST['price_max']));
                        $rent_query['compare'] = 'BETWEEN';
                    } else {
                        $rent_query['value'] = intval($_POST['price_max']);
                        $rent_query['compare'] = '<=';
                    }
                }
                
                $price_query[] = $rent_query;
            }
            
            // Check purchase price
            if (!empty($_POST['price_min']) || !empty($_POST['price_max'])) {
                $purchase_query = array(
                    'key' => '_listing_purchase_price',
                    'type' => 'NUMERIC',
                );
                
                if (!empty($_POST['price_min'])) {
                    $purchase_query['value'] = intval($_POST['price_min']);
                    $purchase_query['compare'] = '>=';
                }
                
                if (!empty($_POST['price_max'])) {
                    if (isset($purchase_query['value'])) {
                        $purchase_query['value'] = array($purchase_query['value'], intval($_POST['price_max']));
                        $purchase_query['compare'] = 'BETWEEN';
                    } else {
                        $purchase_query['value'] = intval($_POST['price_max']);
                        $purchase_query['compare'] = '<=';
                    }
                }
                
                $price_query[] = $purchase_query;
            }
            
            $args['meta_query'][] = $price_query;
        }
        
        // Filter by income level (percentage like 70%, 80%) - supports multiple
        if (!empty($_POST['income_levels']) && is_array($_POST['income_levels'])) {
            $income_group = array('relation' => 'OR');
            foreach ($_POST['income_levels'] as $lvl) {
                $income_percent = intval($lvl);
                $income_group[] = array(
                    'relation' => 'OR',
                    array('key' => 'wpcf-income-level', 'value' => $income_percent, 'compare' => 'LIKE'),
                    array('key' => '_listing_income_level', 'value' => $income_percent, 'compare' => 'LIKE'),
                    array('key' => 'wpcf-ami-percentage', 'value' => $income_percent, 'compare' => 'LIKE'),
                    array('key' => '_listing_ami_percentage', 'value' => $income_percent, 'compare' => 'LIKE'),
                    array('key' => 'wpcf-income-limits', 'value' => $income_percent, 'compare' => 'LIKE'),
                    array('key' => '_listing_income_limits', 'value' => $income_percent, 'compare' => 'LIKE'),
                );
            }
            $args['meta_query'][] = $income_group;
        } elseif (!empty($_POST['income_level'])) {
            $income_percent = intval($_POST['income_level']);
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array('key' => 'wpcf-income-level', 'value' => $income_percent, 'compare' => 'LIKE'),
                array('key' => '_listing_income_level', 'value' => $income_percent, 'compare' => 'LIKE'),
                array('key' => 'wpcf-ami-percentage', 'value' => $income_percent, 'compare' => 'LIKE'),
                array('key' => '_listing_ami_percentage', 'value' => $income_percent, 'compare' => 'LIKE'),
                array('key' => 'wpcf-income-limits', 'value' => $income_percent, 'compare' => 'LIKE'),
                array('key' => '_listing_income_limits', 'value' => $income_percent, 'compare' => 'LIKE'),
            );
        }
        
        // Filter by unit type (lottery, first come, resale, etc.)
        if (!empty($_POST['unit_type']) && is_array($_POST['unit_type'])) {
            // Tighten by selected listing_type: ignore irrelevant unit_type options server-side
            $unit_types = array_map('sanitize_text_field', $_POST['unit_type']);
            if (!empty($_POST['listing_type'])) {
                $lt = sanitize_text_field($_POST['listing_type']);
                if (strpos($lt, 'condo') !== false) {
                    $unit_types = array_filter($unit_types, function($u){ return strpos($u, 'condo_') === 0; });
                } elseif (strpos($lt, 'rental') !== false) {
                    $unit_types = array_filter($unit_types, function($u){ return strpos($u, 'rental_') === 0; });
                }
            }
            $_POST['unit_type'] = array_values($unit_types);
            $unit_type_query = array('relation' => 'OR', 'maloney_tag' => 'unit_type');
            foreach ($_POST['unit_type'] as $unit_type) {
                $unit_type = sanitize_text_field($unit_type);
                if ($unit_type === 'rental_lottery') {
                    $unit_type_query[] = array(
                        'key' => 'wpcf-lottery-process',
                        'value' => 'lottery',
                        'compare' => 'LIKE',
                    );
                    $unit_type_query[] = array(
                        'key' => '_listing_lottery_process',
                        'value' => 'lottery',
                        'compare' => 'LIKE',
                    );
                } elseif ($unit_type === 'rental_first_come') {
                    $unit_type_query[] = array(
                        'key' => 'wpcf-lottery-process',
                        'value' => array('first come', 'first-come', 'first come first served'),
                        'compare' => 'IN',
                    );
                    $unit_type_query[] = array(
                        'key' => '_listing_lottery_process',
                        'value' => array('first come', 'first-come', 'first come first served'),
                        'compare' => 'IN',
                    );
                } elseif ($unit_type === 'condo_lottery') {
                    $unit_type_query[] = array(
                        'key' => 'wpcf-lottery-process',
                        'value' => 'lottery',
                        'compare' => 'LIKE',
                    );
                    $unit_type_query[] = array(
                        'key' => '_listing_lottery_process',
                        'value' => 'lottery',
                        'compare' => 'LIKE',
                    );
                } elseif ($unit_type === 'condo_resale') {
                    $unit_type_query[] = array(
                        'key' => 'wpcf-for-sale',
                        'value' => array('1', 'yes'),
                        'compare' => 'IN',
                    );
                    $unit_type_query[] = array(
                        'key' => '_listing_for_sale',
                        'value' => array('1', 'yes'),
                        'compare' => 'IN',
                    );
                    $unit_type_query[] = array(
                        'key' => 'wpcf-lottery-process',
                        'value' => array('resale', 'for sale'),
                        'compare' => 'IN',
                    );
                    $unit_type_query[] = array(
                        'key' => '_listing_lottery_process',
                        'value' => array('resale', 'for sale'),
                        'compare' => 'IN',
                    );
                }
            }
            if (count($unit_type_query) > 1) {
                $args['meta_query'][] = $unit_type_query;
            }
        }
        
        // Filter by amenities (handle both term IDs and string values like 'parking', 'pool', 'gym')
        // Also searches the Features field (wpcf-features/_listing_features) for matching values
        // TODO: Future task - Migrate Features field values into Amenities taxonomy during migration
        if (!empty($_POST['amenities']) && is_array($_POST['amenities'])) {
            $amenity_ids = array();
            $amenity_strings = array();
            self::$amenity_names_for_features_search = array(); // Reset for this query
            
            foreach ($_POST['amenities'] as $amenity) {
                if (is_numeric($amenity)) {
                    $amenity_ids[] = intval($amenity);
                    // Get term name for Features field search
                    $term = get_term(intval($amenity), 'amenities');
                    if ($term && !is_wp_error($term)) {
                        self::$amenity_names_for_features_search[] = $term->name;
                    }
                } else {
                    $amenity_strings[] = sanitize_text_field($amenity);
                    self::$amenity_names_for_features_search[] = sanitize_text_field($amenity);
                }
            }
            
            // If we have taxonomy terms, we need to search both taxonomy AND Features field
            // Since WordPress doesn't allow OR between tax_query and meta_query, we'll:
            // 1. Get post IDs from taxonomy query (with all other filters applied)
            // 2. Get post IDs from Features field search (with all other filters applied)
            // 3. Combine them and use post__in
            
            if (!empty($amenity_ids) && !empty(self::$amenity_names_for_features_search)) {
                // Build base query args with all current filters (except amenities)
                $base_args = $args;
                // Remove any existing amenities tax_query
                if (isset($base_args['tax_query'])) {
                    foreach ($base_args['tax_query'] as $key => $tax_q) {
                        if (isset($tax_q['taxonomy']) && $tax_q['taxonomy'] === 'amenities') {
                            unset($base_args['tax_query'][$key]);
                        }
                    }
                    $base_args['tax_query'] = array_values($base_args['tax_query']); // Re-index
                }
                // Remove any existing amenities meta_query
                if (isset($base_args['meta_query'])) {
                    foreach ($base_args['meta_query'] as $key => $meta_q) {
                        if (isset($meta_q['key']) && ($meta_q['key'] === 'wpcf-features' || $meta_q['key'] === '_listing_features' || $meta_q['key'] === 'wpcf-amenities')) {
                            unset($base_args['meta_query'][$key]);
                        }
                    }
                    $base_args['meta_query'] = array_values($base_args['meta_query']); // Re-index
                }
                
                // Get post IDs from taxonomy query (with all other filters)
                $tax_query_args = $base_args;
                $tax_query_args['posts_per_page'] = -1;
                $tax_query_args['fields'] = 'ids';
                if (!isset($tax_query_args['tax_query'])) {
                    $tax_query_args['tax_query'] = array();
                }
                $tax_query_args['tax_query'][] = array(
                    'taxonomy' => 'amenities',
                    'field' => 'term_id',
                    'terms' => $amenity_ids,
                    'operator' => 'IN',
                );
                $tax_query = new WP_Query($tax_query_args);
                $tax_post_ids = $tax_query->posts;
                wp_reset_postdata();
                
                // Get post IDs from Features field (with all other filters)
                $features_meta_query = array('relation' => 'OR');
                foreach (self::$amenity_names_for_features_search as $amenity_name) {
                    $features_meta_query[] = array(
                        'key' => 'wpcf-features',
                        'value' => $amenity_name,
                        'compare' => 'LIKE',
                    );
                    $features_meta_query[] = array(
                        'key' => '_listing_features',
                        'value' => $amenity_name,
                        'compare' => 'LIKE',
                    );
                }
                $features_query_args = $base_args;
                $features_query_args['posts_per_page'] = -1;
                $features_query_args['fields'] = 'ids';
                if (!isset($features_query_args['meta_query'])) {
                    $features_query_args['meta_query'] = array();
                }
                $features_query_args['meta_query'][] = $features_meta_query;
                $features_query = new WP_Query($features_query_args);
                $features_post_ids = $features_query->posts;
                wp_reset_postdata();
                
                // Combine post IDs (OR logic)
                $combined_post_ids = array_unique(array_merge($tax_post_ids, $features_post_ids));
                
                if (!empty($combined_post_ids)) {
                    // Use post__in to filter by combined IDs
                    if (isset($args['post__in']) && is_array($args['post__in'])) {
                        // Intersect with existing post__in if any
                        $args['post__in'] = array_intersect($args['post__in'], $combined_post_ids);
                    } else {
                        $args['post__in'] = $combined_post_ids;
                    }
                } else {
                    // No matches, return empty result
                    $args['post__in'] = array(0);
                }
            } elseif (!empty($amenity_ids)) {
                // Only taxonomy search
                $args['tax_query'][] = array(
                    'taxonomy' => 'amenities',
                    'field' => 'term_id',
                    'terms' => $amenity_ids,
                    'operator' => 'IN',
                );
            } elseif (!empty(self::$amenity_names_for_features_search)) {
                // Only Features field search
                $features_meta_query = array('relation' => 'OR');
                foreach (self::$amenity_names_for_features_search as $amenity_name) {
                    $features_meta_query[] = array(
                        'key' => 'wpcf-features',
                        'value' => $amenity_name,
                        'compare' => 'LIKE',
                    );
                    $features_meta_query[] = array(
                        'key' => '_listing_features',
                        'value' => $amenity_name,
                        'compare' => 'LIKE',
                    );
                }
                $args['meta_query'][] = $features_meta_query;
            }
            
            // For string values, also search in wpcf-amenities meta field
            if (!empty($amenity_strings)) {
                $amenity_meta_query = array('relation' => 'OR');
                foreach ($amenity_strings as $amenity_str) {
                    $amenity_meta_query[] = array(
                        'key' => 'wpcf-amenities',
                        'value' => $amenity_str,
                        'compare' => 'LIKE',
                    );
                }
                $args['meta_query'][] = $amenity_meta_query;
            }
        }
        
        // Filter by concessions (taxonomy)
        if (!empty($_POST['concessions']) && is_array($_POST['concessions'])) {
            $concession_ids = array_map('intval', $_POST['concessions']);
            if (!empty($concession_ids)) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'concessions',
                    'field' => 'term_id',
                    'terms' => $concession_ids,
                    'operator' => 'IN',
                );
            }
        }
        
        // Filter by property accessibility (taxonomy)
        if (!empty($_POST['property_accessibility']) && is_array($_POST['property_accessibility'])) {
            $accessibility_ids = array_map('intval', $_POST['property_accessibility']);
            if (!empty($accessibility_ids)) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'property_accessibility',
                    'field' => 'term_id',
                    'terms' => $accessibility_ids,
                    'operator' => 'IN',
                );
            }
        }

        // Filter by Status (combined Rental and Condo)
        // Check if "Show Everything" is selected - if so, don't filter by status
        $status_filter = !empty($_POST['status_filter']) && is_array($_POST['status_filter']) 
            ? array_map('sanitize_text_field', $_POST['status_filter']) 
            : array();
        $show_all_status = in_array('show_all', $status_filter, true);
        
        // Filter by Condo Status (only if not "Show Everything")
        if (!$show_all_status && !empty($_POST['condo_status']) && is_array($_POST['condo_status'])) {
            $condo_statuses = array_map('sanitize_text_field', $_POST['condo_status']);
            // Remove "show_all" default option
            $condo_statuses = array_filter($condo_statuses, function($val) { return $val !== 'show_all'; });
            if (!empty($condo_statuses)) {
                $condo_status_group = array('relation' => 'OR', 'maloney_tag' => 'condo_status');
                foreach ($condo_statuses as $status_val) {
                    $condo_status_group[] = array(
                        'relation' => 'OR',
                        array('key' => 'wpcf-condo-status', 'value' => $status_val, 'compare' => '='),
                        array('key' => '_listing_condo_status', 'value' => $status_val, 'compare' => '='),
                    );
                }
                if (count($condo_status_group) > 1) {
                    $args['meta_query'][] = $condo_status_group;
                }
            }
        }
        
        // Filter by Rental Status (only if not "Show Everything")
        if (!$show_all_status && !empty($_POST['rental_status']) && is_array($_POST['rental_status'])) {
            $rental_statuses = array_map('sanitize_text_field', $_POST['rental_status']);
            // Remove "show_all" default option
            $rental_statuses = array_filter($rental_statuses, function($val) { return $val !== 'show_all'; });
            if (!empty($rental_statuses)) {
                $rental_status_group = array('relation' => 'OR', 'maloney_tag' => 'rental_status');
                foreach ($rental_statuses as $status_val) {
                    $rental_status_group[] = array(
                        'relation' => 'OR',
                        array('key' => 'wpcf-status', 'value' => $status_val, 'compare' => '='),
                        array('key' => '_listing_rental_status', 'value' => $status_val, 'compare' => '='),
                    );
                }
                if (count($rental_status_group) > 1) {
                    $args['meta_query'][] = $rental_status_group;
                }
            }
        }
        
        // Filter by just listed (period from settings)
        if (!empty($_POST['just_listed'])) {
            $settings = Maloney_Listings_Settings::get_setting(null, array());
            $period = isset($settings['just_listed_period']) ? intval($settings['just_listed_period']) : 7;
            $args['date_query'] = array(
                array(
                    'after' => $period . ' days ago',
                    'inclusive' => true,
                ),
            );
        }
        
        // Filter by available units (rentals only)
        // "Show only properties with available units" - filter rentals where total-available-units > 0
        if (!empty($_POST['has_available_units'])) {
            // This will be processed post-query since we need to check rental type
            // Store flag for post-processing
            $args['_filter_has_available_units'] = true;
        }
        
        // Filter by unit type availability (e.g., "Has 1BR available")
        $available_unit_types = array();
        if (!empty($_POST['available_unit_type']) && is_array($_POST['available_unit_type'])) {
            $available_unit_types = array_map('sanitize_text_field', $_POST['available_unit_type']);
        }
        
        // If SRO is selected, automatically enable "Has Available Units" filter
        // SRO only exists in rental availability data, so condos should never show
        if (in_array('sro', $available_unit_types)) {
            $has_available_units_filter = true;
        }
        
        // If "Has Available Units" is checked AND bedrooms are selected,
        // automatically filter by those bedroom sizes in available units
        if (!empty($_POST['has_available_units']) && !empty($bedrooms_options)) {
            // Map bedroom filter values to available unit type values
            $bedroom_to_unit_type = array(
                '0' => 'studio',
                '1' => '1br',
                '2' => '2br',
                '3' => '3br',
                '4+' => '4br'
            );
            
            // Convert bedroom selections to available unit type filters
            foreach ($bedrooms_options as $bedroom_val) {
                if (isset($bedroom_to_unit_type[$bedroom_val])) {
                    $unit_type_val = $bedroom_to_unit_type[$bedroom_val];
                    // Only add if not already in the array
                    if (!in_array($unit_type_val, $available_unit_types)) {
                        $available_unit_types[] = $unit_type_val;
                    }
                }
            }
        }
        
        if (!empty($available_unit_types)) {
            $args['_filter_available_unit_type'] = $available_unit_types;
        }
        
        
        // Filter by map bounds (visible area search)
        if (!empty($_POST['map_bounds']) && is_array($_POST['map_bounds'])) {
            $bounds = $_POST['map_bounds'];
            $north = isset($bounds['north']) ? floatval($bounds['north']) : null;
            $south = isset($bounds['south']) ? floatval($bounds['south']) : null;
            $east = isset($bounds['east']) ? floatval($bounds['east']) : null;
            $west = isset($bounds['west']) ? floatval($bounds['west']) : null;
            
            if ($north !== null && $south !== null && $east !== null && $west !== null) {
                // Ensure proper order (south < north, west < east for negative longitudes)
                $min_lat = min($south, $north);
                $max_lat = max($south, $north);
                $min_lng = min($west, $east);
                $max_lng = max($west, $east);
                
                // Add meta query to filter by coordinates within bounds
                // Use >= and <= for more reliable comparison with negative values
                $args['meta_query'][] = array(
                    'relation' => 'AND',
                    array(
                        'key' => '_listing_latitude',
                        'value' => $min_lat,
                        'type' => 'DECIMAL',
                        'compare' => '>=',
                    ),
                    array(
                        'key' => '_listing_latitude',
                        'value' => $max_lat,
                        'type' => 'DECIMAL',
                        'compare' => '<=',
                    ),
                    array(
                        'key' => '_listing_longitude',
                        'value' => $min_lng,
                        'type' => 'DECIMAL',
                        'compare' => '>=',
                    ),
                    array(
                        'key' => '_listing_longitude',
                        'value' => $max_lng,
                        'type' => 'DECIMAL',
                        'compare' => '<=',
                    ),
                );
            }
        }
        
        // Set relation for tax_query
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }
        
        // Set relation for meta_query
        if (count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }
        
        // Remove custom filter flags before query (they're not valid WP_Query args)
        $has_available_units_filter = isset($args['_filter_has_available_units']) ? $args['_filter_has_available_units'] : false;
        $available_unit_type_filter = isset($args['_filter_available_unit_type']) ? $args['_filter_available_unit_type'] : array();
        unset($args['_filter_has_available_units'], $args['_filter_available_unit_type']);
        
        // If available units filter is active, we MUST include rentals in the query
        // So modify the query to ensure rentals are included
        if ($has_available_units_filter || !empty($available_unit_type_filter)) {
            // Check if we already have a listing_type filter
            $has_type_filter = false;
            $type_filter_index = -1;
            foreach ($args['tax_query'] as $index => $tax_query) {
                if (isset($tax_query['taxonomy']) && $tax_query['taxonomy'] === 'listing_type') {
                    $has_type_filter = true;
                    $type_filter_index = $index;
                    // If it's filtering to something other than rental, we need to add rental
                    if (isset($tax_query['terms'])) {
                        $terms = is_array($tax_query['terms']) ? $tax_query['terms'] : array($tax_query['terms']);
                        $has_rental = false;
                        foreach ($terms as $term) {
                            if (stripos($term, 'rental') !== false) {
                                $has_rental = true;
                                break;
                            }
                        }
                        if (!$has_rental) {
                            // Add rental terms to the existing filter
                            $terms[] = 'rental';
                            $terms[] = 'rental-properties';
                            $args['tax_query'][$index]['terms'] = array_unique($terms);
                            $args['tax_query'][$index]['operator'] = 'IN';
                        }
                    }
                    break;
                }
            }
            
            // If no type filter exists, we need to ensure rentals are included
            // Since available units filter only applies to rentals, we need rentals in the query
            // But we also want to show other types if they pass other filters
            // So we don't restrict to ONLY rentals - we include all types, then filter post-query
        }
        
        // IMPORTANT: When available units filter or bathrooms filter is active, we need to query ALL listings first
        // (not just rentals) because we'll filter post-query. But we need to ensure rentals are included.
        // The query will get all listings, then we filter to only show rentals with availability.
        // We need to query ALL posts (no pagination) when filtering by availability or bathrooms, then paginate after filtering
        $original_posts_per_page = isset($args['posts_per_page']) ? $args['posts_per_page'] : 12;
        $original_paged = isset($args['paged']) ? $args['paged'] : 1;
        
        // Get bathrooms filter options if set
        $bathrooms_filter_options = isset($args['_filter_bathrooms']) ? $args['_filter_bathrooms'] : array();
        $has_bathrooms_filter = !empty($bathrooms_filter_options);
        
        // Get income limits filter options if set
        $income_limits_filter_options = isset($args['_filter_income_limits']) ? $args['_filter_income_limits'] : array();
        $has_income_limits_filter = !empty($income_limits_filter_options);
        
        // Remove filter flags before query
        unset($args['_filter_bathrooms'], $args['_filter_income_limits']);
        
        if ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter) {
            // Query all posts when filtering by availability, bathrooms, or income limits (we'll paginate after filtering)
            $args['posts_per_page'] = -1;
            $args['paged'] = 1;
        }
        
        $query = new WP_Query($args);
        
        // Post-process available units filters, bathrooms filters, and income limits filters (rentals only)
        if ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter) {
            $filtered_posts = array();
            foreach ($query->posts as $post) {
                $post_id = $post->ID;
                
                // Check if this is a rental
                $listing_type_terms = get_the_terms($post_id, 'listing_type');
                $is_rental = false;
                if ($listing_type_terms && !is_wp_error($listing_type_terms)) {
                    $type_slug = strtolower($listing_type_terms[0]->slug);
                    if (strpos($type_slug, 'rental') !== false) {
                        $is_rental = true;
                    }
                }
                
                // For available units filters, bathrooms filters, and income limits filters: ONLY show rentals that have availability data
                if (!$is_rental && ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter)) {
                    // Exclude non-rentals when filter is active
                    continue;
                }
                
                // Get available units using repetitive field structure
                $total_available = 0;
                $availability_data = array();
                $has_availability_data = false;
                
                if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                    // Get raw availability data
                    $availability_data_raw = Maloney_Listings_Available_Units_Fields::get_availability_data($post_id);
                    
                    // Check if we have any availability entries at all
                    $has_availability_data = !empty($availability_data_raw);
                    
                    // Calculate total available from raw data
                    foreach ($availability_data_raw as $entry) {
                        if (!empty($entry['units_available'])) {
                            $units_text = $entry['units_available'];
                            // Extract number from text like "1 (ADA-M Unit)" or "2"
                            if (preg_match('/(\d+)/', $units_text, $matches)) {
                                $total_available += intval($matches[1]);
                            } else {
                                $total_available += intval($units_text);
                            }
                        }
                    }
                    
                    // Parse for filtering (grouped by unit type)
                    $availability_data = Maloney_Listings_Available_Units_Fields::parse_availability_data($post_id);
                }
                
                // Apply filters
                $include_post = true;
                
                // Filter: has available units - ONLY show rentals that have availability data AND total > 0
                // If bedrooms are selected, also check that those specific bedroom sizes are available
                if ($has_available_units_filter) {
                    // Must have availability data AND total > 0
                    if (!$has_availability_data || $total_available <= 0) {
                        $include_post = false;
                    } else if (!empty($available_unit_type_filter)) {
                        // If bedrooms are selected (which auto-populate available_unit_type_filter),
                        // we need to check that those specific unit types are available
                        // This check is done below in the unit type availability filter
                    }
                }
                
                // Filter: unit type availability
                // This filters by specific unit types that are available for rent
                // When "Has Available Units" is checked with bedrooms selected, this ensures
                // only rentals with those specific bedroom sizes available are shown
                if ($include_post && !empty($available_unit_type_filter)) {
                    $has_requested_type = false;
                    
                    // Map filter values to unit type patterns
                    $type_patterns = array();
                    foreach ($available_unit_type_filter as $filter_type) {
                        $filter_type = strtolower(trim($filter_type));
                        if ($filter_type === 'studio') {
                            $type_patterns[] = array('studio');
                        } elseif ($filter_type === '1br' || $filter_type === '1-bedroom' || $filter_type === 'one bedroom') {
                            $type_patterns[] = array('1-bedroom', '1 bedroom', '1br', 'one bedroom');
                        } elseif ($filter_type === '2br' || $filter_type === '2-bedroom' || $filter_type === 'two bedroom') {
                            $type_patterns[] = array('2-bedroom', '2 bedroom', '2br', 'two bedroom');
                        } elseif ($filter_type === '3br' || $filter_type === '3-bedroom' || $filter_type === 'three bedroom') {
                            $type_patterns[] = array('3-bedroom', '3 bedroom', '3br', 'three bedroom');
                        } elseif ($filter_type === '4br' || $filter_type === '4+br' || $filter_type === '4-bedroom' || $filter_type === 'four bedroom') {
                            $type_patterns[] = array('4+ bedroom', '4+bedroom', '4-bedroom', '4 bedroom', '4+br', '4br', 'four bedroom');
                        } elseif ($filter_type === 'sro') {
                            $type_patterns[] = array('single room occupancy', 'sro', 'single room occupancy (sro)');
                        }
                    }
                    
                    // Check if any availability data matches the requested types
                    foreach ($availability_data as $item) {
                        if (isset($item['count']) && intval($item['count']) > 0) {
                            $item_unit_type = strtolower(trim($item['unit_type']));
                            
                            // Normalize unit type for matching
                            $normalized_type = $item_unit_type;
                            if (stripos($item_unit_type, 'single room occupancy') !== false || stripos($item_unit_type, 'sro') !== false) {
                                $normalized_type = 'single room occupancy';
                            } elseif (stripos($item_unit_type, 'studio') !== false) {
                                $normalized_type = 'studio';
                            } elseif (stripos($item_unit_type, '1-bedroom') !== false || stripos($item_unit_type, '1 bedroom') !== false) {
                                $normalized_type = '1-bedroom';
                            } elseif (stripos($item_unit_type, '2-bedroom') !== false || stripos($item_unit_type, '2 bedroom') !== false) {
                                $normalized_type = '2-bedroom';
                            } elseif (stripos($item_unit_type, '3-bedroom') !== false || stripos($item_unit_type, '3 bedroom') !== false) {
                                $normalized_type = '3-bedroom';
                            } elseif (stripos($item_unit_type, '4') !== false && (stripos($item_unit_type, 'bedroom') !== false || stripos($item_unit_type, 'br') !== false)) {
                                $normalized_type = '4+ bedroom';
                            }
                            
                            foreach ($type_patterns as $patterns) {
                                foreach ($patterns as $pattern) {
                                    if (stripos($normalized_type, $pattern) !== false || stripos($item_unit_type, $pattern) !== false) {
                                        $has_requested_type = true;
                                        break 3; // Break out of all loops
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$has_requested_type) {
                        $include_post = false;
                    }
                }
                
                // Apply bathrooms filter (only for rentals with availability data)
                if ($include_post && $has_bathrooms_filter && $is_rental) {
                    $has_matching_bathrooms = false;
                    
                    // Get availability data
                    if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                        $availability_data_raw = Maloney_Listings_Available_Units_Fields::get_availability_data($post_id);
                        
                        // Check if any availability entry has matching bathrooms
                        foreach ($availability_data_raw as $entry) {
                            if (!empty($entry['bathrooms'])) {
                                $entry_bathrooms = trim($entry['bathrooms']);
                                
                                // Check against each filter option
                                foreach ($bathrooms_filter_options as $filter_bath) {
                                    $filter_bath = trim($filter_bath);
                                    
                                    $entry_val = null;
                                    if (preg_match('/\d+(\.\d+)?/', $entry_bathrooms, $matches)) {
                                        $entry_val = floatval($matches[0]);
                                    } else {
                                        $entry_val = floatval($entry_bathrooms);
                                    }
                                    
                                    if (substr($filter_bath, -1) === '+') {
                                        $threshold = floatval(rtrim($filter_bath, '+'));
                                        if ($entry_val >= $threshold) {
                                            $has_matching_bathrooms = true;
                                            break 2;
                                        }
                                    } elseif ($entry_bathrooms === $filter_bath) {
                                        // Exact match
                                        $has_matching_bathrooms = true;
                                        break 2;
                                    } else {
                                        // Try numeric comparison for decimal values
                                        $filter_val = floatval($filter_bath);
                                        if (abs($entry_val - $filter_val) < 0.01) { // Allow for floating point precision
                                            $has_matching_bathrooms = true;
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$has_matching_bathrooms) {
                        $include_post = false;
                    }
                }
                
                // Apply income limits filter (only for rentals with availability data)
                if ($include_post && $has_income_limits_filter && $is_rental) {
                    $has_matching_income_limit = false;
                    
                    // Get availability data
                    if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                        $availability_data_raw = Maloney_Listings_Available_Units_Fields::get_availability_data($post_id);
                        
                        // Check if any availability entry has matching income limit
                        foreach ($availability_data_raw as $entry) {
                            if (!empty($entry['income_limit'])) {
                                $entry_income_limit = trim($entry['income_limit']);
                                
                                // Check against each filter option
                                foreach ($income_limits_filter_options as $filter_income_limit) {
                                    $filter_income_limit = trim($filter_income_limit);
                                    
                                    // Exact match (case-insensitive)
                                    if (strcasecmp($entry_income_limit, $filter_income_limit) === 0) {
                                        $has_matching_income_limit = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$has_matching_income_limit) {
                        $include_post = false;
                    }
                }
                
                
                if ($include_post) {
                    $filtered_posts[] = $post;
                }
            }
            
            // Apply pagination to filtered posts
            $total_filtered = count($filtered_posts);
            $offset = ($original_paged - 1) * $original_posts_per_page;
            
            // Get paginated slice of filtered posts
            $paginated_posts = array_slice($filtered_posts, $offset, $original_posts_per_page);
            
            // Update query with paginated filtered posts
            $query->posts = $paginated_posts;
            $query->post_count = count($paginated_posts);
            $query->found_posts = $total_filtered;
            // Ensure max_num_pages is at least 1 if we have results
            $query->max_num_pages = $original_posts_per_page > 0 ? max(1, ceil($total_filtered / $original_posts_per_page)) : 1;
        }

        // Build facet counts (Unit Type) without disabling
        $facet_counts = array(
            'unit_type' => array(
                'rental_first_come' => 0,
                'rental_lottery' => 0,
                'condo_lottery' => 0,
                'condo_resale' => 0,
            ),
        );

        $base_args = $args;
        // Remove unit_type groups from meta_query for base
        if (!empty($base_args['meta_query'])) {
            $base_args['meta_query'] = array_values(array_filter($base_args['meta_query'], function($cl) {
                return !(is_array($cl) && isset($cl['maloney_tag']) && in_array($cl['maloney_tag'], array('unit_type'), true));
            }));
            if (count($base_args['meta_query']) <= 1) unset($base_args['meta_query']['relation']);
        }

        // Helper to run a count with additional meta group
        $count_with = function($extra_group) use ($base_args) {
            $a = $base_args;
            if (empty($a['meta_query'])) $a['meta_query'] = array();
            if (!empty($a['meta_query']) && !isset($a['meta_query']['relation']) && count($a['meta_query']) > 1) {
                $a['meta_query']['relation'] = 'AND';
            }
            $a['meta_query'][] = $extra_group;
            $a['posts_per_page'] = 1; // we only need found_posts
            $q = new WP_Query($a);
            $c = intval($q->found_posts);
            return $c;
        };

        // Unit type counts
        $facet_counts['unit_type']['rental_first_come'] = $count_with(array('relation'=>'OR',
            array('key'=>'wpcf-lottery-process','value'=>array('first come','first-come','first come first served'),'compare'=>'IN'),
            array('key'=>'_listing_lottery_process','value'=>array('first come','first-come','first come first served'),'compare'=>'IN'),
        ));
        $facet_counts['unit_type']['rental_lottery'] = $count_with(array('relation'=>'OR',
            array('key'=>'wpcf-lottery-process','value'=>'lottery','compare'=>'LIKE'),
            array('key'=>'_listing_lottery_process','value'=>'lottery','compare'=>'LIKE'),
        ));
        $facet_counts['unit_type']['condo_lottery'] = $facet_counts['unit_type']['rental_lottery'];
        $facet_counts['unit_type']['condo_resale'] = $count_with(array('relation'=>'OR',
            array('key'=>'wpcf-for-sale','value'=>array('1','yes'),'compare'=>'IN'),
            array('key'=>'_listing_for_sale','value'=>array('1','yes'),'compare'=>'IN'),
        ));
        // If bedrooms filter is active and query returns nothing, drop only the bedrooms filter to ensure we never return empty solely due to bed filter
        if ($has_bed_filter && intval($query->found_posts) === 0) {
            $args_no_bed = $args;
            if (!empty($args_no_bed['meta_query'])) {
                $args_no_bed['meta_query'] = array_filter($args_no_bed['meta_query'], function($cond) {
                    return !(is_array($cond) && isset($cond['maloney_tag']) && $cond['maloney_tag'] === 'bedrooms');
                });
                if (count($args_no_bed['meta_query']) <= 1) {
                    // Remove relation if only one element
                    unset($args_no_bed['meta_query']['relation']);
                }
            }
            $query = new WP_Query($args_no_bed);
        }
        
        // Handle Property Name sorting with natural (alphanumeric) sort
        // This ensures "11 On The Dot" comes before "105 Washington Residences"
        $sort_type = !empty($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'property_name';
        if ($sort_type === 'property_name' && !empty($query->posts)) {
            $posts = $query->posts;
            usort($posts, function($a, $b) {
                // Use natural sort (strnatcasecmp) to treat numbers as numbers
                return strnatcasecmp($a->post_title, $b->post_title);
            });
            $query->posts = $posts;
        }
        
        // Handle Sales & Lotteries sorting with post-processing
        if (!empty($_POST['sort']) && $_POST['sort'] === 'sales_lotteries' && !empty($query->posts)) {
$posts = $query->posts;
            usort($posts, function($a, $b) {
                $a_id = $a->ID;
                $b_id = $b->ID;
                
                // Get status values
                $a_condo_status = get_post_meta($a_id, 'wpcf-condo-status', true);
                $a_rental_status = get_post_meta($a_id, 'wpcf-status', true);
                $b_condo_status = get_post_meta($b_id, 'wpcf-condo-status', true);
                $b_rental_status = get_post_meta($b_id, 'wpcf-status', true);
                
                // Priority: Sales (1) > Lotteries (2, 5) > Others
                $get_priority = function($condo_status, $rental_status) {
                    if ($condo_status == '1') return 1; // FCFS Condo Sales
                    if ($condo_status == '2' || $condo_status == '5' || $rental_status == '2' || $rental_status == '5' || $rental_status == '6') return 2; // Lotteries
                    return 3; // Others
                };
                
                $a_priority = $get_priority($a_condo_status, $a_rental_status);
                $b_priority = $get_priority($b_condo_status, $b_rental_status);
                
                if ($a_priority != $b_priority) {
                    return $a_priority - $b_priority;
                }
                
                // Same priority, sort by title using natural sort
                return strnatcasecmp($a->post_title, $b->post_title);
            });
            $query->posts = $posts;
        }
        
        // Check if we need to find nearby listings for zip code search (before building map query)
        $nearby_posts_for_map = null;
        if (!$query->have_posts() && $zip_search_location && $zip_search_lat !== null && $zip_search_lng !== null) {
            // Try to find nearby listings within 10 miles
            $nearby_radius = 10; // miles
            
            // Get all listings with coordinates
            $all_listings_args = array(
                'post_type' => 'listing',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => '_listing_latitude',
                        'compare' => 'EXISTS',
                    ),
                    array(
                        'key' => '_listing_longitude',
                        'compare' => 'EXISTS',
                    ),
                ),
            );
            
            // Apply other filters (except zip code) to nearby search
            if (!empty($args['tax_query'])) {
                $all_listings_args['tax_query'] = $args['tax_query'];
            }
            
            // Copy other meta queries except zip code
            if (!empty($args['meta_query'])) {
                $all_listings_args['meta_query'] = array();
                foreach ($args['meta_query'] as $meta_query) {
                    // Skip zip code meta queries
                    if (is_array($meta_query)) {
                        $is_zip_query = false;
                        if (isset($meta_query['key'])) {
                            $key = $meta_query['key'];
                            if (in_array($key, array('wpcf-zip-code', 'wpcf-zip', '_listing_zip'))) {
                                $is_zip_query = true;
                            }
                        } elseif (isset($meta_query[0]) && is_array($meta_query[0])) {
                            // Check if any sub-query is a zip code query
                            foreach ($meta_query as $sub_query) {
                                if (isset($sub_query['key']) && in_array($sub_query['key'], array('wpcf-zip-code', 'wpcf-zip', '_listing_zip'))) {
                                    $is_zip_query = true;
                                    break;
                                }
                            }
                        }
                        if (!$is_zip_query) {
                            $all_listings_args['meta_query'][] = $meta_query;
                        }
                    }
                }
            }
            
            $all_listings_query = new WP_Query($all_listings_args);
            $nearby_posts = array();
            
            if ($all_listings_query->have_posts()) {
                while ($all_listings_query->have_posts()) {
                    $all_listings_query->the_post();
                    $post_id = get_the_ID();
                    
                    // Get listing coordinates
                    $lat = get_post_meta($post_id, '_listing_latitude', true);
                    $lng = get_post_meta($post_id, '_listing_longitude', true);
                    
                    // Convert to float and validate
                    $lat = $lat ? floatval($lat) : 0;
                    $lng = $lng ? floatval($lng) : 0;
                    
                    // Skip if no valid coordinates
                    if ($lat == 0 || $lng == 0 || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                        continue;
                    }
                    
                    // Calculate distance
                    $distance = $this->calculate_distance($zip_search_lat, $zip_search_lng, $lat, $lng, 'mi');
                    
                    // Include if within radius
                    if ($distance <= $nearby_radius) {
                        $nearby_posts[] = get_post($post_id);
                    }
                }
                wp_reset_postdata();
            }
            
            // Store nearby posts for both query and map_query
            if (!empty($nearby_posts)) {
                $nearby_posts_for_map = $nearby_posts;
            }
        }
        
        // Build listings_data for map from ALL matching listings (not just current page)
        // Create a separate query to get ALL listings that match the current filters
        $map_query_args = $args;
        $map_query_args['posts_per_page'] = -1; // Get all matching listings
        $map_query_args['paged'] = 1;
        // Remove any post-processing flags
        unset($map_query_args['_filter_has_available_units'], $map_query_args['_filter_available_unit_type'], $map_query_args['_filter_bathrooms']);
        
        // If we had post-processed filtering (available units), we need to apply the same logic
        $map_query = new WP_Query($map_query_args);
        
        // If we found nearby posts for zip code search, update map_query to use them too
        if ($nearby_posts_for_map !== null && !empty($nearby_posts_for_map)) {
            $map_query->posts = $nearby_posts_for_map;
            $map_query->post_count = count($nearby_posts_for_map);
            $map_query->found_posts = count($nearby_posts_for_map);
        }
        
        // Get bathrooms filter options if set
        $bathrooms_filter_options = isset($args['_filter_bathrooms']) ? $args['_filter_bathrooms'] : array();
        $has_bathrooms_filter = !empty($bathrooms_filter_options);
        
        // If available units filter or bathrooms filter was active, apply the same post-processing
        if ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter) {
            $map_filtered_posts = array();
            foreach ($map_query->posts as $post) {
                $post_id = $post->ID;
                
                // Check if this is a rental
                $listing_type_terms = get_the_terms($post_id, 'listing_type');
                $is_rental = false;
                if ($listing_type_terms && !is_wp_error($listing_type_terms)) {
                    $type_slug = strtolower($listing_type_terms[0]->slug);
                    if (strpos($type_slug, 'rental') !== false) {
                        $is_rental = true;
                    }
                }
                
                // For available units filters and bathrooms filters: ONLY show rentals that have availability data
                if (!$is_rental && ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter)) {
                    continue;
                }
                
                // Get available units using repetitive field structure
                $total_available = 0;
                $availability_data = array();
                $has_availability_data = false;
                
                if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                    $availability_data_raw = Maloney_Listings_Available_Units_Fields::get_availability_data($post_id);
                    $has_availability_data = !empty($availability_data_raw);
                    
                    foreach ($availability_data_raw as $entry) {
                        if (!empty($entry['units_available'])) {
                            $units_text = $entry['units_available'];
                            if (preg_match('/(\d+)/', $units_text, $matches)) {
                                $total_available += intval($matches[1]);
                            } else {
                                $total_available += intval($units_text);
                            }
                        }
                    }
                    $availability_data = Maloney_Listings_Available_Units_Fields::parse_availability_data($post_id);
                }
                
                // Apply filters
                $include_post = true;
                
                if ($has_available_units_filter) {
                    if (!$has_availability_data || $total_available <= 0) {
                        $include_post = false;
                    }
                }
                
                if ($include_post && !empty($available_unit_type_filter)) {
                    $has_requested_type = false;
                    $type_patterns = array();
                    foreach ($available_unit_type_filter as $filter_type) {
                        $filter_type = strtolower(trim($filter_type));
                        if ($filter_type === 'studio') {
                            $type_patterns[] = array('studio');
                        } elseif ($filter_type === '1br' || $filter_type === '1-bedroom' || $filter_type === 'one bedroom') {
                            $type_patterns[] = array('1-bedroom', '1 bedroom', '1br', 'one bedroom');
                        } elseif ($filter_type === '2br' || $filter_type === '2-bedroom' || $filter_type === 'two bedroom') {
                            $type_patterns[] = array('2-bedroom', '2 bedroom', '2br', 'two bedroom');
                        } elseif ($filter_type === '3br' || $filter_type === '3-bedroom' || $filter_type === 'three bedroom') {
                            $type_patterns[] = array('3-bedroom', '3 bedroom', '3br', 'three bedroom');
                        } elseif ($filter_type === '4br' || $filter_type === '4+br' || $filter_type === '4-bedroom' || $filter_type === 'four bedroom') {
                            $type_patterns[] = array('4+ bedroom', '4+bedroom', '4-bedroom', '4 bedroom', '4+br', '4br', 'four bedroom');
                        } elseif ($filter_type === 'sro') {
                            $type_patterns[] = array('single room occupancy', 'sro', 'single room occupancy (sro)');
                        }
                    }
                    
                    foreach ($availability_data as $item) {
                        if (isset($item['count']) && intval($item['count']) > 0) {
                            $item_unit_type = strtolower(trim($item['unit_type']));
                            $normalized_type = $item_unit_type;
                            if (stripos($item_unit_type, 'single room occupancy') !== false || stripos($item_unit_type, 'sro') !== false) {
                                $normalized_type = 'single room occupancy';
                            } elseif (stripos($item_unit_type, 'studio') !== false) {
                                $normalized_type = 'studio';
                            } elseif (stripos($item_unit_type, '1-bedroom') !== false || stripos($item_unit_type, '1 bedroom') !== false) {
                                $normalized_type = '1-bedroom';
                            } elseif (stripos($item_unit_type, '2-bedroom') !== false || stripos($item_unit_type, '2 bedroom') !== false) {
                                $normalized_type = '2-bedroom';
                            } elseif (stripos($item_unit_type, '3-bedroom') !== false || stripos($item_unit_type, '3 bedroom') !== false) {
                                $normalized_type = '3-bedroom';
                            } elseif (stripos($item_unit_type, '4') !== false && (stripos($item_unit_type, 'bedroom') !== false || stripos($item_unit_type, 'br') !== false)) {
                                $normalized_type = '4+ bedroom';
                            }
                            
                            foreach ($type_patterns as $patterns) {
                                foreach ($patterns as $pattern) {
                                    if (stripos($normalized_type, $pattern) !== false || stripos($item_unit_type, $pattern) !== false) {
                                        $has_requested_type = true;
                                        break 3;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$has_requested_type) {
                        $include_post = false;
                    }
                }
                
                // Apply bathrooms filter (only for rentals with availability data)
                if ($include_post && $has_bathrooms_filter && $is_rental) {
                    $has_matching_bathrooms = false;
                    
                    // Get availability data
                    if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                        $availability_data_raw = Maloney_Listings_Available_Units_Fields::get_availability_data($post_id);
                        
                        // Check if any availability entry has matching bathrooms
                        foreach ($availability_data_raw as $entry) {
                            if (!empty($entry['bathrooms'])) {
                                $entry_bathrooms = trim($entry['bathrooms']);
                                
                                // Check against each filter option
                                foreach ($bathrooms_filter_options as $filter_bath) {
                                    $filter_bath = trim($filter_bath);
                                    
                                    $entry_val = null;
                                    if (preg_match('/\d+(\.\d+)?/', $entry_bathrooms, $matches)) {
                                        $entry_val = floatval($matches[0]);
                                    } else {
                                        $entry_val = floatval($entry_bathrooms);
                                    }
                                    
                                    if (substr($filter_bath, -1) === '+') {
                                        $threshold = floatval(rtrim($filter_bath, '+'));
                                        if ($entry_val >= $threshold) {
                                            $has_matching_bathrooms = true;
                                            break 2;
                                        }
                                    } elseif ($entry_bathrooms === $filter_bath) {
                                        // Exact match
                                        $has_matching_bathrooms = true;
                                        break 2;
                                    } else {
                                        // Try numeric comparison for decimal values
                                        $filter_val = floatval($filter_bath);
                                        if (abs($entry_val - $filter_val) < 0.01) { // Allow for floating point precision
                                            $has_matching_bathrooms = true;
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$has_matching_bathrooms) {
                        $include_post = false;
                    }
                }
                
                if ($include_post) {
                    $map_filtered_posts[] = $post;
                }
            }
            $map_query->posts = $map_filtered_posts;
        }
        
        ob_start();
        $listings_data = array();
        
        // Build map data from ALL matching listings (not just current page)
        if ($map_query->have_posts()) {
            while ($map_query->have_posts()) {
                $map_query->the_post();
                $post_id = get_the_ID();
                
                // Collect listing data for map - check both meta key formats
                $lat = get_post_meta($post_id, '_listing_latitude', true);
                $lng = get_post_meta($post_id, '_listing_longitude', true);
                
                // Convert to float and validate
                $lat = $lat ? floatval($lat) : 0;
                $lng = $lng ? floatval($lng) : 0;
                
                // Only include listings with valid coordinates (not 0,0 and within valid ranges)
                if ($lat != 0 && $lng != 0 && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    // Determine if this is a rental or condo
                    $listing_type_terms = get_the_terms($post_id, 'listing_type');
                    $is_rental = false;
                    $is_condo = false;
                    if ($listing_type_terms && !is_wp_error($listing_type_terms)) {
                        $type_slug = strtolower($listing_type_terms[0]->slug);
                        if (strpos($type_slug, 'rental') !== false) {
                            $is_rental = true;
                        } elseif (strpos($type_slug, 'condo') !== false || strpos($type_slug, 'condominium') !== false) {
                            $is_condo = true;
                        }
                    }
                    
                    // For rentals, get available units from new repetitive field structure; for condos, use unit-sizes
                    $available_units = '';
                    if ($is_rental) {
                        // Get available units using the repetitive field structure (same logic as listing cards)
                        if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                            $availability_data = Maloney_Listings_Available_Units_Fields::get_availability_data($post_id);
                            $total_available = Maloney_Listings_Available_Units_Fields::get_total_available($post_id);
                            
                            if ($total_available > 0) {
                                // Group by unit type and sum counts, preserving full text
                                $units_by_type = array();
                                foreach ($availability_data as $entry) {
                                    if (!empty($entry['bedrooms']) && !empty($entry['units_available'])) {
                                        $unit_type = $entry['bedrooms'];
                                        $units_text = $entry['units_available'];
                                        
                                        // Extract count for summing
                                        $count = 0;
                                        if (preg_match('/(\d+)/', $units_text, $matches)) {
                                            $count = intval($matches[1]);
                                        } else {
                                            $count = intval($units_text);
                                        }
                                        
                                        if ($count > 0) {
                                            if (!isset($units_by_type[$unit_type])) {
                                                $units_by_type[$unit_type] = array(
                                                    'count' => 0,
                                                    'display_text' => $units_text, // Keep original text for display
                                                );
                                            }
                                            $units_by_type[$unit_type]['count'] += $count;
                                            // Keep the most descriptive text (one with parentheses)
                                            if (strpos($units_text, '(') !== false) {
                                                $units_by_type[$unit_type]['display_text'] = $units_text;
                                            }
                                        }
                                    }
                                }
                                
                                if (!empty($units_by_type)) {
                                    $display_parts = array();
                                    foreach ($units_by_type as $type => $data) {
                                        // Format as "1-Bedroom(21)" instead of "21 1-Bedroom"
                                        if (strpos($data['display_text'], '(') !== false) {
                                            // If it already has parentheses, try to extract and reformat
                                            // Extract number and text from patterns like "1 (ADA-M Unit)"
                                            if (preg_match('/^(\d+)\s*\((.+)\)$/', $data['display_text'], $matches)) {
                                                $count = $matches[1];
                                                $extra = $matches[2];
                                                $display_parts[] = $type . '(' . $count . ' ' . $extra . ')';
                                            } else {
                                                // Keep original if we can't parse it
                                                $display_parts[] = $data['display_text'];
                                            }
                                        } else {
                                            // Format: "1-Bedroom(21)"
                                            $display_parts[] = $type . '(' . $data['count'] . ')';
                                        }
                                    }
                                    $available_units = implode(', ', $display_parts);
                                }
                            } else {
                                $available_units = '0';
                            }
                        }
                    } else {
                        // For condos, use existing unit-sizes logic
                        $unit_sizes = get_post_meta($post_id, 'wpcf-unit-sizes', true);
                        if (empty($unit_sizes)) {
                            $unit_sizes = get_post_meta($post_id, '_listing_unit_sizes', true);
                        }
                        if (is_string($unit_sizes)) {
                            $unit_sizes = maybe_unserialize($unit_sizes);
                        }
                        
                        // Get Toolset field definition to map option keys to titles
                        $option_key_to_title = array();
                        if (function_exists('wpcf_admin_fields_get_fields')) {
                            $all_fields = wpcf_admin_fields_get_fields();
                            foreach ($all_fields as $fid => $f) {
                                if (!empty($f['meta_key']) && $f['meta_key'] === 'wpcf-unit-sizes' && !empty($f['data']['options'])) {
                                    foreach ($f['data']['options'] as $okey => $odata) {
                                        $title = isset($odata['title']) ? trim($odata['title']) : '';
                                        if ($title) {
                                            $option_key_to_title[$okey] = $title;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        
                        $available_units_array = array();
                        if (is_array($unit_sizes) && !empty($unit_sizes)) {
                            $unit_size_map = array(
                                'Studio' => 'Studio',
                                'One Bedroom' => '1BR',
                                'Two Bedroom' => '2BR',
                                'Three Bedroom' => '3BR',
                                'Four Bedroom' => '4+BR',
                            );
                            
                            foreach ($unit_sizes as $key => $value) {
                                $size_label = '';
                                
                                // Check if this is a Toolset option key (starts with wpcf-fields-checkboxes-option-)
                                if (is_string($key) && strpos($key, 'wpcf-fields-checkboxes-option-') === 0) {
                                    // Look up the title from the field definition
                                    if (isset($option_key_to_title[$key])) {
                                        $size_label = $option_key_to_title[$key];
                                    }
                                } elseif (is_array($value)) {
                                    if (isset($value['title'])) {
                                        $size_label = trim($value['title']);
                                    } elseif (isset($value['value'])) {
                                        $size_label = trim($value['value']);
                                    } elseif (isset($value['bedrooms'])) {
                                        $bed = intval($value['bedrooms']);
                                        if ($bed === 0) {
                                            $size_label = 'Studio';
                                        } elseif ($bed >= 1 && $bed <= 3) {
                                            $size_label = ($bed === 1 ? 'One' : ($bed === 2 ? 'Two' : 'Three')) . ' Bedroom';
                                        } elseif ($bed >= 4) {
                                            $size_label = 'Four Bedroom';
                                        }
                                    }
                                } else {
                                    $size_label = trim($value);
                                }
                                
                                if ($size_label) {
                                    if (isset($unit_size_map[$size_label])) {
                                        $available_units_array[] = $unit_size_map[$size_label];
                                    } elseif (stripos($size_label, 'studio') !== false) {
                                        $available_units_array[] = 'Studio';
                                    } elseif (stripos($size_label, 'one') !== false && stripos($size_label, 'bedroom') !== false) {
                                        $available_units_array[] = '1BR';
                                    } elseif (stripos($size_label, 'two') !== false && stripos($size_label, 'bedroom') !== false) {
                                        $available_units_array[] = '2BR';
                                    } elseif (stripos($size_label, 'three') !== false && stripos($size_label, 'bedroom') !== false) {
                                        $available_units_array[] = '3BR';
                                    } elseif (stripos($size_label, 'four') !== false && stripos($size_label, 'bedroom') !== false) {
                                        $available_units_array[] = '4+BR';
                                    }
                                }
                            }
                            
                            $available_units_array = array_unique($available_units_array);
                            $sort_order = array('Studio' => 0, '1BR' => 1, '2BR' => 2, '3BR' => 3, '4+BR' => 4);
                            usort($available_units_array, function($a, $b) use ($sort_order) {
                                $a_order = isset($sort_order[$a]) ? $sort_order[$a] : 999;
                                $b_order = isset($sort_order[$b]) ? $sort_order[$b] : 999;
                                return $a_order - $b_order;
                            });
                        }
                        $available_units = !empty($available_units_array) ? implode(', ', $available_units_array) : '';
                    }
                    
                    // Get address - try full address first, then build from parts
                    $full_address = get_post_meta($post_id, 'wpcf-address', true);
                    
                    // If wpcf-address is empty, try _listing_address
                    if (empty($full_address)) {
                        $full_address = get_post_meta($post_id, '_listing_address', true);
                    }
                    
                    // If still empty, build from separate fields
                    if (empty($full_address)) {
                        $address = get_post_meta($post_id, 'wpcf-address', true);
                        $city = get_post_meta($post_id, 'wpcf-city', true);
                        $state = get_post_meta($post_id, 'wpcf-state-1', true);
                        
                        // Fallback to mapped fields
                        if (empty($address)) {
                            $address = get_post_meta($post_id, '_listing_address', true);
                        }
                        if (empty($city)) {
                            $city = get_post_meta($post_id, '_listing_city', true);
                        }
                        if (empty($state)) {
                            $state = get_post_meta($post_id, '_listing_state', true);
                        }
                        
                        // Use ONLY the address field - no longer combines with city/town
                        $full_address = !empty($address) ? trim($address) : '';
                    }
                    
                    $listings_data[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'lat' => $lat,
                        'lng' => $lng,
                        'type' => $this->get_listing_type_name($post_id),
                        'status' => $this->get_listing_status_name($post_id),
                        'price' => $this->get_listing_price($post_id),
                        'available_units' => $available_units,
                        'address' => $full_address,
                        'image' => get_the_post_thumbnail_url($post_id, 'large') ?: get_the_post_thumbnail_url($post_id, 'full'),
                    );
                }
            }
            wp_reset_postdata();
        }
        
        // Now generate HTML for listing cards from the paginated query (current page only)
        // If we found nearby posts for zip code search, use them for the query
        if ($nearby_posts_for_map !== null && !empty($nearby_posts_for_map)) {
            // Update query with nearby posts
            $query->posts = $nearby_posts_for_map;
            $query->post_count = count($nearby_posts_for_map);
            $query->found_posts = count($nearby_posts_for_map);
            
            // Apply pagination
            $original_posts_per_page = isset($args['posts_per_page']) ? intval($args['posts_per_page']) : 12;
            $original_paged = isset($args['paged']) ? intval($args['paged']) : 1;
            
            if ($original_posts_per_page > 0) {
                $offset = ($original_paged - 1) * $original_posts_per_page;
                $query->posts = array_slice($nearby_posts_for_map, $offset, $original_posts_per_page);
                $query->post_count = count($query->posts);
                $query->max_num_pages = ceil(count($nearby_posts_for_map) / $original_posts_per_page);
            } else {
                $query->max_num_pages = 1;
            }
        }
        
        // Generate HTML for listing cards
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/listing-card.php';
            }
            wp_reset_postdata();
        } else {
            echo '<div class="no-listings-found">';
            echo '<p>No listings found matching your criteria.</p>';
            echo '<a href="#" class="reset-filters-link" id="reset-filters-link">Reset Filters</a>';
            echo '</div>';
        }
        
        // Generate pagination HTML - always show if there are results
        // Ensure we have valid pagination values
        $found_posts = isset($query->found_posts) ? intval($query->found_posts) : 0;
        $max_pages = isset($query->max_num_pages) ? intval($query->max_num_pages) : 1;
        $current_page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        
        // Add pagination HTML directly to the main HTML output (inside listings-grid)
        echo '<div class="listings-pagination" id="listings-pagination">';
        if ($found_posts > 0 && $max_pages > 0) {
            if ($max_pages > 1) {
                echo paginate_links(array(
                    'total' => $max_pages,
                    'current' => $current_page,
                    'prev_text' => __('&laquo; Previous', 'maloney-listings'),
                    'next_text' => __('Next &raquo;', 'maloney-listings'),
                ));
            } else {
                // Show pagination container even for single page to maintain layout
                echo '<span class="page-numbers current">1</span>';
            }
        }
        echo '</div>';
        
        $html = ob_get_clean();
        
        // Also generate pagination HTML separately for backwards compatibility
        ob_start();
        if ($found_posts > 0 && $max_pages > 0) {
            if ($max_pages > 1) {
                echo paginate_links(array(
                    'total' => $max_pages,
                    'current' => $current_page,
                    'prev_text' => __('&laquo; Previous', 'maloney-listings'),
                    'next_text' => __('Next &raquo;', 'maloney-listings'),
                ));
            } else {
                echo '<span class="page-numbers current">1</span>';
            }
        }
        $pagination_html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'listings' => $listings_data,
            'found_posts' => $query->found_posts,
            'max_pages' => $query->max_num_pages,
            'pagination' => $pagination_html,
            'facet_counts' => $facet_counts,
        ));
    }
    
    public function get_similar_listings() {
        check_ajax_referer('maloney_listings_nonce', 'nonce');
        
        if (empty($_POST['listing_id'])) {
            wp_send_json_error('Listing ID required');
        }
        
        $listing_id = intval($_POST['listing_id']);
        $similar = $this->find_similar_listings($listing_id);

        ob_start();
        if (!empty($similar)) {
            global $post;
            foreach ($similar as $post) {
                setup_postdata($post);
                include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/listing-card.php';
            }
            wp_reset_postdata();
        } else {
            // Fallback: recent listings of same type
            $curr_type = wp_get_post_terms($listing_id, 'listing_type', array('fields'=>'ids'));
            $args = array('post_type'=>'listing','posts_per_page'=>6,'post__not_in'=>array($listing_id),'post_status'=>'publish');
            if (!empty($curr_type)) {
                $args['tax_query'] = array(array('taxonomy'=>'listing_type','field'=>'term_id','terms'=>$curr_type));
            }
            $q = new WP_Query($args);
            if ($q->have_posts()) {
                while ($q->have_posts()) { $q->the_post(); include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/listing-card.php'; }
                wp_reset_postdata();
            }
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    private function find_similar_listings($post_id, $limit = 6) {
        $listing = get_post($post_id);
        if (!$listing || $listing->post_type !== 'listing') {
            return array();
        }
        
        // Get listing attributes
        $listing_type = wp_get_post_terms($post_id, 'listing_type');
        $location = wp_get_post_terms($post_id, 'location');
        $bedrooms = get_post_meta($post_id, '_listing_bedrooms', true);
        $rent_price = get_post_meta($post_id, '_listing_rent_price', true);
        $purchase_price = get_post_meta($post_id, '_listing_purchase_price', true);
        $price = $rent_price ?: $purchase_price;
        
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => $limit,
            'post__not_in' => array($post_id),
            'post_status' => 'publish',
            'tax_query' => array(),
            'meta_query' => array(),
        );
        
        // Same listing type
        if ($listing_type && !is_wp_error($listing_type)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'listing_type',
                'field' => 'term_id',
                'terms' => $listing_type[0]->term_id,
            );
        }
        
        // Same location
        if ($location && !is_wp_error($location)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'location',
                'field' => 'term_id',
                'terms' => $location[0]->term_id,
            );
        }
        
        // Similar bedrooms (±1)
        if ($bedrooms) {
            $args['meta_query'][] = array(
                'key' => '_listing_bedrooms',
                'value' => array(max(0, $bedrooms - 1), $bedrooms + 1),
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN',
            );
        }
        
        // Similar price (±20%)
        if ($price) {
            $min_price = $price * 0.8;
            $max_price = $price * 1.2;
            
            $price_query = array('relation' => 'OR');
            if ($rent_price) {
                $price_query[] = array(
                    'key' => '_listing_rent_price',
                    'value' => array($min_price, $max_price),
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN',
                );
            }
            if ($purchase_price) {
                $price_query[] = array(
                    'key' => '_listing_purchase_price',
                    'value' => array($min_price, $max_price),
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN',
                );
            }
            $args['meta_query'][] = $price_query;
        }
        
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }
        
        $query = new WP_Query($args);
        if (!empty($query->posts)) return $query->posts;
        // Fallback: relax constraints
        unset($args['meta_query']);
        $query = new WP_Query($args);
        return $query->posts;
    }
    
    private function get_listing_type_name($post_id) {
        $terms = get_the_terms($post_id, 'listing_type');
        return $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }
    
    private function get_listing_status_name($post_id) {
        // Determine if this is a condo or rental
        $listing_type = get_the_terms($post_id, 'listing_type');
        $is_condo = false;
        $is_rental = false;
        
        if ($listing_type && !is_wp_error($listing_type)) {
            $type_slug = strtolower($listing_type[0]->slug);
            if (strpos($type_slug, 'condo') !== false || strpos($type_slug, 'condominium') !== false) {
                $is_condo = true;
            } elseif (strpos($type_slug, 'rental') !== false) {
                $is_rental = true;
            }
        }
        
        // Get status value from Toolset fields (same logic as listing cards)
        $status_value = '';
        if ($is_condo) {
            $status_value = get_post_meta($post_id, 'wpcf-condo-status', true);
            if (empty($status_value) && $status_value !== '0' && $status_value !== 0) {
                $status_value = get_post_meta($post_id, '_listing_condo_status', true);
            }
        } elseif ($is_rental) {
            $status_value = get_post_meta($post_id, 'wpcf-status', true);
            if (empty($status_value) && $status_value !== '0' && $status_value !== 0) {
                $status_value = get_post_meta($post_id, '_listing_rental_status', true);
            }
        }
        
        // Map status to display label using frontend mapping
        if (!empty($status_value) || $status_value === '0' || $status_value === 0) {
            if (class_exists('Maloney_Listings_Custom_Fields')) {
                return Maloney_Listings_Custom_Fields::map_status_display_frontend($status_value, $is_condo);
            }
        }
        
        // Fallback to taxonomy status if no Toolset status found
        $terms = get_the_terms($post_id, 'listing_status');
        return $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }
    
    private function get_listing_price($post_id) {
        // Check both Toolset and standard meta fields
        $rent = get_post_meta($post_id, '_listing_rent_price', true);
        if (empty($rent)) {
            $rent = get_post_meta($post_id, 'wpcf-rent-price', true);
        }
        
        $purchase = get_post_meta($post_id, '_listing_purchase_price', true);
        if (empty($purchase)) {
            $purchase = get_post_meta($post_id, 'wpcf-purchase-price', true);
        }
        
        if ($rent) {
            return '$' . number_format(floatval($rent)) . '/mo';
        } elseif ($purchase) {
            return '$' . number_format(floatval($purchase));
        }
        return '';
    }

    private function get_unit_size_option_keys() {
        if (self::$unit_size_option_cache !== null) return self::$unit_size_option_cache;
        $map = array('studio'=>array(),'one'=>array(),'two'=>array(),'three'=>array(),'four'=>array());
        if (function_exists('wpcf_admin_fields_get_fields')) {
            $all_fields = wpcf_admin_fields_get_fields();
            foreach ($all_fields as $fid => $f) {
                if (!empty($f['meta_key']) && $f['meta_key'] === 'wpcf-unit-sizes' && !empty($f['data']['options'])) {
                    foreach ($f['data']['options'] as $okey => $odata) {
                        $title = isset($odata['title']) ? strtolower($odata['title']) : '';
                        if ($title) {
                            if (strpos($title,'studio') !== false) $map['studio'][] = $okey;
                            elseif (strpos($title,'one') !== false) $map['one'][] = $okey;
                            elseif (strpos($title,'two') !== false) $map['two'][] = $okey;
                            elseif (strpos($title,'three') !== false) $map['three'][] = $okey;
                            elseif (strpos($title,'four') !== false) $map['four'][] = $okey;
                        }
                    }
                    break;
                }
            }
        }
        self::$unit_size_option_cache = $map;
        return $map;
    }

}

