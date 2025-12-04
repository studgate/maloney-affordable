<?php
/**
 * Archive Template for Listings
 * Full-width map with listing cards overlaid on left
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

// Only get header if not called from shortcode (check if we're in a shortcode context)
if (!defined('MALONEY_LISTINGS_SHORTCODE_CONTEXT')) {
    get_header();
} 

// Get zip codes early for use in autocomplete - check both new field (zip-code) and legacy fields
global $wpdb;
$zip_codes = $wpdb->get_col("
    SELECT DISTINCT meta_value 
    FROM {$wpdb->postmeta} 
    WHERE meta_key IN ('wpcf-zip-code', 'wpcf-zip', '_listing_zip') 
    AND meta_value != '' 
    AND meta_value IS NOT NULL
    AND post_id IN (
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'listing' 
        AND post_status = 'publish'
    )
    ORDER BY meta_value ASC
");
?>

<div class="listings-map-layout">
    
    <!-- Page Title -->
    <div class="listings-page-title">
        <h1><?php 
            if (is_page()) {
                echo esc_html(get_the_title());
            } else {
                echo __('Listings', 'maloney-listings');
            }
        ?></h1>
    </div>
    
    <!-- Search/Filter Bar -->
    <div class="listings-search-bar">
        <div class="listings-search-container">
            <div class="search-location">
                <input type="text" id="search_location_input" placeholder="Search location or zip code..." autocomplete="off" />
                <button type="button" id="search_location_btn" class="search-btn">
                    <span class="dashicons dashicons-search"></span>
                </button>
                <div id="location-autocomplete" class="location-autocomplete"></div>
                <script type="text/javascript">
                    // Pass zip codes to JavaScript for autocomplete
                    window.maloneyZipCodes = <?php echo json_encode($zip_codes ? $zip_codes : array()); ?>;
                </script>
            </div>
            
            <div class="filter-dropdowns">
                <!-- Popover filter buttons -->
                <button type="button" class="filter-btn" id="btn_bedrooms">Beds &amp; Baths <span class="filter-arrow">▾</span></button>
                <div class="filter-popover" id="popover_bedrooms" style="display:none;">
                    <div class="filter-popover-content">
                        <div class="filter-popover-header">
                            <div class="filter-popover-header-title">Beds &amp; Baths</div>
                        </div>
                        <div class="beds-baths-section">
                            <div class="filter-section-title">Number of Bedrooms</div>
                            <div class="filter-popover-options filter-button-group">
                                <label class="filter-option-button"><input type="checkbox" name="bedroom_options[]" value="any" class="auto-filter-checkbox default-filter" checked /><span>Any</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bedroom_options[]" value="0" class="auto-filter-checkbox" /><span>Studio</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bedroom_options[]" value="1" class="auto-filter-checkbox" /><span>1</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bedroom_options[]" value="2" class="auto-filter-checkbox" /><span>2</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bedroom_options[]" value="3" class="auto-filter-checkbox" /><span>3</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bedroom_options[]" value="4+" class="auto-filter-checkbox" /><span>4+</span></label>
                            </div>
                        </div>
                        
                        <?php 
                        $settings = Maloney_Listings_Settings::get_setting(null, array());
                        $enable_bathrooms = isset($settings['enable_bathrooms_filter']) ? $settings['enable_bathrooms_filter'] === '1' : false;
                        if ($enable_bathrooms) : ?>
                        <div class="beds-baths-section">
                            <div class="filter-section-title">Number of Bathrooms</div>
                            <div class="filter-popover-options filter-button-group">
                                <label class="filter-option-button"><input type="checkbox" name="bathroom_options[]" value="any" class="auto-filter-checkbox default-filter" checked /><span>Any</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bathroom_options[]" value="1+" class="auto-filter-checkbox" /><span>1+</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bathroom_options[]" value="1.5+" class="auto-filter-checkbox" /><span>1.5+</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bathroom_options[]" value="2+" class="auto-filter-checkbox" /><span>2+</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bathroom_options[]" value="3+" class="auto-filter-checkbox" /><span>3+</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="bathroom_options[]" value="4+" class="auto-filter-checkbox" /><span>4+</span></label>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="filter-popover-actions"><button type="button" class="filter-done-btn" id="done_bedrooms"><span class="dashicons dashicons-yes"></span> Done</button></div>
                    </div>
                </div>

                <?php 
                $settings = Maloney_Listings_Settings::get_setting(null, array());
                $enable_income_limits = isset($settings['enable_income_limits_filter']) ? $settings['enable_income_limits_filter'] === '1' : false;
                if ($enable_income_limits) : 
                    $income_limit_terms = get_terms(array(
                        'taxonomy' => 'income_limit',
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'order' => 'ASC',
                    ));
                    if (!is_wp_error($income_limit_terms) && !empty($income_limit_terms)) :
                ?>
                <button type="button" class="filter-btn" id="btn_income_limits">Income Limits <span class="filter-arrow">▾</span></button>
                <div class="filter-popover" id="popover_income_limits" style="display:none;">
                    <div class="filter-popover-content">
                        <div class="filter-popover-header">
                            <div class="filter-popover-header-title">Income Limits</div>
                        </div>
                        <div class="filter-popover-options filter-button-group">
                            <?php foreach ($income_limit_terms as $term) : ?>
                                <label class="filter-option-button"><input type="checkbox" name="income_limits[]" value="<?php echo esc_attr($term->name); ?>" class="auto-filter-checkbox" /><span><?php echo esc_html($term->name); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="filter-popover-actions"><button type="button" class="filter-done-btn" id="done_income_limits"><span class="dashicons dashicons-yes"></span> Done</button></div>
                    </div>
                </div>
                <?php 
                    endif;
                endif; 
                ?>

                <!-- Available Units Filter (Rentals Only) - Main Filter -->
                <button type="button" class="filter-btn" id="btn_available_units">Available Units <span class="filter-arrow">▾</span></button>
                <div class="filter-popover" id="popover_available_units" style="display:none;">
                    <div class="filter-popover-content">
                        <div class="filter-popover-header">
                            <div class="filter-popover-header-title">Available Units</div>
                        </div>
                        <div class="filter-popover-options filter-button-group">
                            <label class="filter-option-button">
                                <input type="checkbox" name="has_available_units" value="1" class="auto-filter-checkbox" />
                                <span>Has Available Units</span>
                            </label>
                        </div>
                        <?php
                        // Check if Unit Size field should be hidden
                        $settings = Maloney_Listings_Settings::get_setting(null, array());
                        $hide_unit_size = isset($settings['hide_unit_size_field']) ? $settings['hide_unit_size_field'] === '1' : false;
                        if (!$hide_unit_size) :
                        ?>
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">
                            <div style="font-weight: 500; margin-bottom: 8px;">Unit Size:</div>
                            <div class="filter-popover-options filter-button-group">
                                <label class="filter-option-button"><input type="checkbox" name="available_unit_type[]" value="studio" class="auto-filter-checkbox" /><span>Studio</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="available_unit_type[]" value="1br" class="auto-filter-checkbox" /><span>1BR</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="available_unit_type[]" value="2br" class="auto-filter-checkbox" /><span>2BR</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="available_unit_type[]" value="3br" class="auto-filter-checkbox" /><span>3BR</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="available_unit_type[]" value="4br" class="auto-filter-checkbox" /><span>4+BR</span></label>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">
                            <div style="font-weight: 500; margin-bottom: 8px;">Other:</div>
                            <div class="filter-popover-options filter-button-group">
                                <label class="filter-option-button"><input type="checkbox" name="available_unit_type[]" value="sro" class="auto-filter-checkbox" /><span>SRO</span></label>
                            </div>
                        </div>
                        <div class="filter-popover-actions"><button type="button" class="filter-done-btn" id="done_available_units"><span class="dashicons dashicons-yes"></span> Done</button></div>
                    </div>
                </div>
                
                <button type="button" class="filter-btn" id="btn_listing_type">Listing Type <span class="filter-arrow">▾</span></button>
                <div class="filter-popover" id="popover_listing_type" style="display:none;">
                    <div class="filter-popover-content">
                        <div class="filter-popover-header">
                            <div class="filter-popover-header-title">Listing Type</div>
                        </div>
                        <div class="filter-popover-options filter-button-group">
                            <label class="filter-option-button"><input type="radio" name="listing_type_filter" value="show_all" class="auto-filter-radio default-filter" checked /><span>Any</span></label>
                            <?php
                            $types = get_terms(array('taxonomy' => 'listing_type', 'hide_empty' => false));
                            foreach ($types as $type) :
                                $display_name = $type->name;
                                // Map to display names
                                if (strtolower($type->slug) === 'rental' || strtolower($type->slug) === 'rental-properties') {
                                    $display_name = 'Rental';
                                } elseif (strtolower($type->slug) === 'condo' || strtolower($type->slug) === 'condominium' || strtolower($type->slug) === 'condominiums') {
                                    $display_name = 'Condo';
                                }
                                ?>
                                <label class="filter-option-button"><input type="radio" name="listing_type_filter" value="<?php echo esc_attr($type->slug); ?>" class="auto-filter-radio" /><span><?php echo esc_html($display_name); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="filter-popover-actions"><button type="button" class="filter-done-btn" id="done_listing_type"><span class="dashicons dashicons-yes"></span> Done</button></div>
                    </div>
                </div>
                
                <!-- Keep hidden select for backwards compatibility -->
                <select id="filter_listing_type" name="listing_type" class="auto-filter" style="display:none;">
                    <option value="">Show All</option>
                    <?php
                    $types = get_terms(array('taxonomy' => 'listing_type', 'hide_empty' => false));
                    foreach ($types as $type) :
                        $display_name = $type->name;
                        if (strtolower($type->slug) === 'rental' || strtolower($type->slug) === 'rental-properties') {
                            $display_name = 'Rental';
                        } elseif (strtolower($type->slug) === 'condo' || strtolower($type->slug) === 'condominium' || strtolower($type->slug) === 'condominiums') {
                            $display_name = 'Condo';
                        }
                        ?>
                        <option value="<?php echo esc_attr($type->slug); ?>"><?php echo esc_html($display_name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Combined Status Filter (Rental and Condo) - Button Style -->
                <button type="button" class="filter-btn" id="btn_status">Status <span class="filter-arrow">▾</span></button>
                <div class="filter-popover" id="popover_status" style="display:none;">
                    <div class="filter-popover-content">
                        <div class="filter-popover-header">
                            <div class="filter-popover-header-title">Status</div>
                        </div>
                        <div class="filter-popover-options filter-button-group">
                            <label class="filter-option-button"><input type="checkbox" name="status_filter[]" value="show_all" class="auto-filter-checkbox default-filter" checked /><span>Any</span></label>
                        </div>
                        <div class="status-filter-buttons" style="margin-top: 12px;">
                            <button type="button" class="status-type-btn" id="btn_status_rental">Rental <span class="status-arrow">▾</span></button>
                            <button type="button" class="status-type-btn" id="btn_status_condo">Condo <span class="status-arrow">▾</span></button>
                        </div>
                        
                        <!-- Rental Status Options -->
                        <div class="status-options-panel" id="status_panel_rental" style="display:none;">
                            <div class="filter-popover-options filter-button-group">
                                <label class="filter-option-button"><input type="checkbox" name="rental_status[]" value="1" class="auto-filter-checkbox" /><span>Active Rental</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="rental_status[]" value="2" class="auto-filter-checkbox" /><span>Open Lottery</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="rental_status[]" value="6" class="auto-filter-checkbox" /><span>Upcoming Lottery</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="rental_status[]" value="3" class="auto-filter-checkbox" /><span>Closed Lottery</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="rental_status[]" value="4" class="auto-filter-checkbox" /><span>Inactive Rental</span></label>
                            </div>
                        </div>
                        
                        <!-- Condo Status Options -->
                        <div class="status-options-panel" id="status_panel_condo" style="display:none;">
                            <div class="filter-popover-options filter-button-group">
                                <label class="filter-option-button"><input type="checkbox" name="condo_status[]" value="1" class="auto-filter-checkbox" /><span>FCFS Condo Sales</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="condo_status[]" value="2" class="auto-filter-checkbox" /><span>Active Condo Lottery</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="condo_status[]" value="3" class="auto-filter-checkbox" /><span>Closed Condo Lottery</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="condo_status[]" value="4" class="auto-filter-checkbox" /><span>Inactive Condo Property</span></label>
                                <label class="filter-option-button"><input type="checkbox" name="condo_status[]" value="5" class="auto-filter-checkbox" /><span>Upcoming Condo</span></label>
                            </div>
                        </div>
                        
                        <div class="filter-popover-actions"><button type="button" class="filter-done-btn" id="done_status"><span class="dashicons dashicons-yes"></span> Done</button></div>
                    </div>
                </div>
                
                <!-- Keep status select hidden for backwards compatibility -->
                <select id="filter_status" name="status" class="auto-filter" style="display:none">
                    <option value="">Status</option>
                </select>

                <select id="filter_income_level" name="income_level" class="auto-filter" style="display:none;">
                    <option value="">Income Limits</option>
                    <option value="70">70%</option>
                    <option value="80">80%</option>
                </select>
                
                <select id="filter_location" name="location" class="auto-filter" style="display:none;">
                    <option value="">Town</option>
            <?php
            // Get all unique cities from listings (both condos and rentals)
            // Note: zip_codes already fetched at top of template
            global $wpdb;
            $cities = $wpdb->get_col("
                SELECT DISTINCT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key IN ('wpcf-city', '_listing_city') 
                AND meta_value != '' 
                AND meta_value IS NOT NULL
                AND post_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'listing' 
                    AND post_status = 'publish'
                )
                ORDER BY meta_value ASC
            ");
                    
                    if (!empty($cities)) {
                        foreach ($cities as $city) {
                            if (!empty($city)) {
                                ?>
                                <option value="<?php echo esc_attr($city); ?>"><?php echo esc_html($city); ?></option>
                            <?php
                            }
                        }
                    }
                    ?>
                </select>
                
                <button type="button" id="toggle_advanced_filters" class="filter-btn">
                    <span class="dashicons dashicons-ellipsis"></span> More <span class="filter-arrow">▾</span>
                </button>
                <button type="button" id="reset_filters" class="filter-btn reset-btn">
                    <span class="dashicons dashicons-info"></span> Reset Filters
                </button>
            </div>
            
            <div class="advanced-filters" id="advanced_filters" style="display: none;">
                <div class="advanced-filters-grid">
                    <div class="filter-group">
                        <label>Amenities</label>
                        <div class="amenities-checkboxes">
                            <?php
                            $amenities = get_terms(array('taxonomy' => 'amenities', 'hide_empty' => false));
                            foreach ($amenities as $amenity) :
                                ?>
                                <label>
                                    <input type="checkbox" name="amenities[]" value="<?php echo esc_attr($amenity->term_id); ?>" class="auto-filter-checkbox" />
                                    <?php echo esc_html($amenity->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <label style="margin-top: 20px;">Property Accessibility</label>
                        <div class="accessibility-checkboxes">
                            <?php
                            $accessibility = get_terms(array('taxonomy' => 'property_accessibility', 'hide_empty' => false));
                            foreach ($accessibility as $term) :
                                ?>
                                <label>
                                    <input type="checkbox" name="property_accessibility[]" value="<?php echo esc_attr($term->term_id); ?>" class="auto-filter-checkbox" />
                                    <?php echo esc_html($term->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>Other</label>
                        <div class="other-filters">
                            <?php
                            // Concessions filter (only show if enabled in settings)
                            $enable_concessions = isset($settings['enable_concessions_filter']) ? $settings['enable_concessions_filter'] === '1' : false;
                            if ($enable_concessions) :
                                $concessions = get_terms(array('taxonomy' => 'concessions', 'hide_empty' => false));
                                foreach ($concessions as $concession) :
                                    ?>
                                    <label>
                                        <input type="checkbox" name="concessions[]" value="<?php echo esc_attr($concession->term_id); ?>" class="auto-filter-checkbox" />
                                        <?php echo esc_html($concession->name); ?>
                                    </label>
                                <?php 
                                endforeach;
                            endif;
                            ?>
                            <label>
                                <input type="checkbox" name="just_listed" value="1" class="auto-filter-checkbox" />
                                Just Listed
                            </label>
                            
                        </div>
                    </div>
                </div>
                <button type="button" id="close_advanced_filters" class="filter-done-btn" style="margin-top: 15px;">
                    <span class="dashicons dashicons-yes"></span> Done
                </button>
            </div>
        </div>
    </div>
    
    <!-- View Toggle (Mobile Only) -->
    <div class="mobile-view-toggle" style="display: none;">
        <button type="button" class="view-toggle-btn active" id="toggle-list-view" data-view="list">
            <span class="dashicons dashicons-list-view"></span> List
        </button>
        <button type="button" class="view-toggle-btn" id="toggle-map-view" data-view="map">
            <span class="dashicons dashicons-location"></span> Map
        </button>
    </div>
    
    <!-- Full-width Map Container -->
    <div class="listings-map-container" id="listings-map-container">
        <!-- Map (full width) - always visible on desktop, toggleable on mobile -->
        <div id="listings-map" class="full-width-map">
            <div style="text-align: center; padding: 50px; color: #999;">
                Loading map...
            </div>
        </div>
        
        <?php
        // Load ALL listings with coordinates for the map (not just current page)
        $all_listings_args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND',
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
        $all_listings_query = new WP_Query($all_listings_args);
        $all_listings_data = array();
        
        if ($all_listings_query->have_posts()) {
            while ($all_listings_query->have_posts()) {
                $all_listings_query->the_post();
                $post_id = get_the_ID();
                
                $lat = get_post_meta($post_id, '_listing_latitude', true);
                $lng = get_post_meta($post_id, '_listing_longitude', true);
                
                // Convert to float and validate
                $lat = $lat ? floatval($lat) : 0;
                $lng = $lng ? floatval($lng) : 0;
                
                // Only include listings with valid coordinates
                if ($lat != 0 && $lng != 0 && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    // Get listing type
                    $listing_type_terms = get_the_terms($post_id, 'listing_type');
                    $listing_type = $listing_type_terms && !is_wp_error($listing_type_terms) ? $listing_type_terms[0]->name : '';
                    
                    // Get status using the same method as AJAX handler
                    $is_condo = false;
                    $is_rental = false;
                    if ($listing_type_terms && !is_wp_error($listing_type_terms)) {
                        $type_slug = strtolower($listing_type_terms[0]->slug);
                        if (strpos($type_slug, 'condo') !== false || strpos($type_slug, 'condominium') !== false) {
                            $is_condo = true;
                        } elseif (strpos($type_slug, 'rental') !== false) {
                            $is_rental = true;
                        }
                    }
                    
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
                    
                    $listing_status = '';
                    if (!empty($status_value) || $status_value === '0' || $status_value === 0) {
                        if (class_exists('Maloney_Listings_Custom_Fields')) {
                            $listing_status = Maloney_Listings_Custom_Fields::map_status_display_frontend($status_value, $is_condo);
                        }
                    }
                    
                    // Get available units
                    $unit_sizes = get_post_meta($post_id, 'wpcf-unit-sizes', true);
                    if (empty($unit_sizes)) {
                        $unit_sizes = get_post_meta($post_id, '_listing_unit_sizes', true);
                    }
                    if (is_string($unit_sizes)) {
                        $unit_sizes = maybe_unserialize($unit_sizes);
                    }
                    
                    // Get Toolset field definition for unit sizes mapping
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
                    
                    $available_units = array();
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
                            
                            if (is_string($key) && strpos($key, 'wpcf-fields-checkboxes-option-') === 0) {
                                if (isset($option_key_to_title[$key])) {
                                    $size_label = $option_key_to_title[$key];
                                }
                            } elseif (is_array($value)) {
                                if (isset($value['title'])) {
                                    $size_label = trim($value['title']);
                                } elseif (isset($value['value'])) {
                                    $size_label = trim($value['value']);
                                }
                            } else {
                                $size_label = trim($value);
                            }
                            
                            if ($size_label) {
                                if (isset($unit_size_map[$size_label])) {
                                    $available_units[] = $unit_size_map[$size_label];
                                } elseif (stripos($size_label, 'studio') !== false) {
                                    $available_units[] = 'Studio';
                                } elseif (stripos($size_label, 'one') !== false && stripos($size_label, 'bedroom') !== false) {
                                    $available_units[] = '1BR';
                                } elseif (stripos($size_label, 'two') !== false && stripos($size_label, 'bedroom') !== false) {
                                    $available_units[] = '2BR';
                                } elseif (stripos($size_label, 'three') !== false && stripos($size_label, 'bedroom') !== false) {
                                    $available_units[] = '3BR';
                                } elseif (stripos($size_label, 'four') !== false && stripos($size_label, 'bedroom') !== false) {
                                    $available_units[] = '4+BR';
                                }
                            }
                        }
                        
                        $available_units = array_unique($available_units);
                        $sort_order = array('Studio' => 0, '1BR' => 1, '2BR' => 2, '3BR' => 3, '4+BR' => 4);
                        usort($available_units, function($a, $b) use ($sort_order) {
                            $a_order = isset($sort_order[$a]) ? $sort_order[$a] : 999;
                            $b_order = isset($sort_order[$b]) ? $sort_order[$b] : 999;
                            return $a_order - $b_order;
                        });
                    }
                    
                    // City / state / zip details for autocomplete + filtering
                    $city_display = trim((string) get_post_meta($post_id, 'wpcf-city', true));
                    $city_clean = $city_display;
                    if (!empty($city_clean) && strpos($city_clean, '|') !== false) {
                        $city_parts = array_map('trim', preg_split('/\|+/', $city_clean));
                        $city_clean = !empty($city_parts) ? $city_parts[0] : $city_clean;
                    }
                    if (empty($city_clean)) {
                        $city_clean = trim((string) get_post_meta($post_id, '_listing_city', true));
                    }
                    if (empty($city_display)) {
                        $city_display = $city_clean;
                    }
                    $state_value = trim((string) get_post_meta($post_id, 'wpcf-state-1', true));
                    if (empty($state_value)) {
                        $state_value = trim((string) get_post_meta($post_id, '_listing_state', true));
                    }
                    if (empty($state_value)) {
                        $state_value = 'MA';
                    }
                    $zip_code_value = trim((string) get_post_meta($post_id, 'wpcf-zip-code', true));
                    if (empty($zip_code_value)) {
                        $zip_code_value = trim((string) get_post_meta($post_id, 'wpcf-zip', true));
                    }
                    if (empty($zip_code_value)) {
                        $zip_code_value = trim((string) get_post_meta($post_id, '_listing_zip', true));
                    }
                    
                    // Get address
                    $full_address = get_post_meta($post_id, 'wpcf-address', true);
                    if (empty($full_address)) {
                        $full_address = get_post_meta($post_id, '_listing_address', true);
                    }
                    if (empty($full_address)) {
                        // Use ONLY the address field - no longer combines with city/town
                        $full_address = get_post_meta($post_id, 'wpcf-address', true);
                        if (empty($full_address)) {
                            $full_address = get_post_meta($post_id, '_listing_address', true);
                        }
                        $full_address = !empty($full_address) ? trim($full_address) : '';
                    }
                    
                    $all_listings_data[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'lat' => $lat,
                        'lng' => $lng,
                        'type' => $listing_type,
                        'status' => $listing_status,
                        'available_units' => !empty($available_units) ? implode(', ', $available_units) : '',
                        'address' => $full_address,
                        'city' => $city_clean,
                        'city_label' => $city_display,
                        'state' => $state_value,
                        'zip' => $zip_code_value,
                        'image' => get_the_post_thumbnail_url($post_id, 'large') ?: get_the_post_thumbnail_url($post_id, 'full'),
                    );
                }
            }
            wp_reset_postdata();
        }
        ?>
        <script type="text/javascript">
            // Ensure all listings with coordinates are loaded for the map
            window.maloneyListingsData = <?php echo json_encode($all_listings_data); ?>;
        </script>
        
        <!-- Listing Cards Overlay (left side) - hidden on mobile when map view is active -->
        <div class="listings-cards-overlay" id="listings-cards-overlay">
            <div class="listings-cards-header">
                <div class="results-header-top">
                    <h2>
                        <span id="listings-count"><?php 
                            $count = $wp_query->found_posts;
                            echo $count . ' ' . ($count == 1 ? 'Result' : 'Results');
                        ?></span>
                    </h2>
                    <div class="results-sort">
                        <div class="results-sort-wrapper">
                            <label for="sort_listings">Sort by:</label>
                            <select id="sort_listings" name="sort" class="auto-filter">
                                <option value="property_name" selected>Property Name</option>
                                <option value="city_town">City</option>
                            </select>
                        </div>
                        <div class="page-info" id="page-info">
                            <?php if ($wp_query->found_posts > 0) : ?>
                                Page <?php echo max(1, get_query_var('paged')); ?> of <?php echo $wp_query->max_num_pages; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div id="active-filters" class="active-filters"></div>
            </div>
            
            <div class="listings-cards-scroll" id="listings-grid">
                <?php
                if (have_posts()) :
                    while (have_posts()) : the_post();
                        include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/listing-card.php';
                    endwhile;
                else :
                    ?>
            <div class="no-listings-found">
                <p><?php _e('No listings found.', 'maloney-listings'); ?></p>
                <a href="#" class="reset-filters-link" id="reset-filters-link"><?php _e('Reset Filters', 'maloney-listings'); ?></a>
            </div>
                <?php endif; ?>
                
                <div class="listings-pagination" id="listings-pagination">
                    <?php
                    if ($wp_query->found_posts > 0 && $wp_query->max_num_pages > 1) {
                        echo paginate_links(array(
                            'total' => $wp_query->max_num_pages,
                            'prev_text' => __('&laquo; Previous', 'maloney-listings'),
                            'next_text' => __('Next &raquo;', 'maloney-listings'),
                        ));
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
</div>

<?php
// Only get footer if not called from shortcode
if (!defined('MALONEY_LISTINGS_SHORTCODE_CONTEXT')) {
    get_footer();
}
?>
