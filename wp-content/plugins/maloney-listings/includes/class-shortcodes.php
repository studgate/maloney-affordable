<?php
/**
 * Shortcodes for Listings
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Shortcodes {
    
    public function __construct() {
        add_shortcode('maloney_listings_view', array($this, 'listings_view_shortcode'));
        add_shortcode('maloney_listings_home_option', array($this, 'home_option_shortcode'));
        add_shortcode('maloney_available_units', array($this, 'available_units_shortcode'));
        add_shortcode('maloney_listing_availability', array($this, 'listing_availability_shortcode'));
        add_shortcode('maloney_listing_condo_listings', array($this, 'condo_listings_shortcode'));
        add_shortcode('maloney_listings_link', array($this, 'listings_link_shortcode'));
        add_shortcode('maloney_listings_search_form', array($this, 'listings_search_form_shortcode'));
        add_shortcode('maloney_listing_map', array($this, 'listing_map_shortcode'));
    }
    
    /**
     * Main listings view shortcode - similar to Toolset Views
     * Usage: [maloney_listings_view type="units|condo|rental"]
     */
    public function listings_view_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'units', // units, condo, rental
        ), $atts);
        
        // Enqueue assets (same as frontend class does)
        // Load Leaflet from local files (better performance, no external dependencies)
        wp_enqueue_style('leaflet-css', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4');
        wp_enqueue_script('leaflet-js', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true);
        wp_enqueue_style('leaflet-markercluster-css', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet-markercluster/MarkerCluster.css', array('leaflet-css'), '1.5.3');
        wp_enqueue_style('leaflet-markercluster-default-css', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet-markercluster/MarkerCluster.Default.css', array('leaflet-markercluster-css'), '1.5.3');
        wp_enqueue_script('leaflet-markercluster', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet-markercluster/leaflet.markercluster.js', array('leaflet-js'), '1.5.3', true);
        wp_enqueue_style('maloney-listings-frontend', MALONEY_LISTINGS_PLUGIN_URL . 'assets/css/frontend.css', array(), MALONEY_LISTINGS_VERSION);
        wp_enqueue_style('dashicons'); // Enqueue Dashicons for icons
        
        // Get settings for dynamic CSS
        $settings = Maloney_Listings_Settings::get_setting(null, array());
        $settings = is_array($settings) ? $settings : array();
        $rental_color = isset($settings['rental_color']) ? $settings['rental_color'] : '#E86962';
        $condo_color = isset($settings['condo_color']) ? $settings['condo_color'] : '#E4C780';
        
        // Add dynamic CSS for badge colors
        $dynamic_css = "
            .badge-rental { background-color: {$rental_color} !important; }
            .badge-condo { background-color: {$condo_color} !important; }
            .legend-pin-rental { background-color: {$rental_color} !important; }
            .legend-pin-condo { background-color: {$condo_color} !important; }
        ";
        wp_add_inline_style('maloney-listings-frontend', $dynamic_css);
        
        wp_enqueue_script('maloney-listings-frontend', MALONEY_LISTINGS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery', 'leaflet-js'), MALONEY_LISTINGS_VERSION, true);
        
        // Pass settings to JavaScript
        wp_localize_script('maloney-listings-frontend', 'maloneyListingsSettings', array(
            'enableSearchArea' => isset($settings['enable_search_area']) ? $settings['enable_search_area'] === '1' : false,
            'enableDirections' => isset($settings['enable_directions']) ? $settings['enable_directions'] === '1' : true,
            'enableStreetView' => isset($settings['enable_street_view']) ? $settings['enable_street_view'] === '1' : true,
            'rentalColor' => $rental_color,
            'condoColor' => $condo_color,
        ));
        
        wp_localize_script('maloney-listings-frontend', 'maloneyListings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('maloney_listings_nonce'),
            'adminUrl' => admin_url(),
            'archiveUrl' => get_post_type_archive_link('listing'),
        ));
        
        // Get appropriate query
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => 12,
            'post_status' => 'publish',
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        );
        
        if ($atts['type'] === 'condo') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'listing_type',
                    'field' => 'slug',
                    'terms' => 'condo',
                ),
            );
        } elseif ($atts['type'] === 'rental') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'listing_type',
                    'field' => 'slug',
                    'terms' => 'rental',
                ),
            );
        }
        // 'units' or default shows all
        
        // Set up query
        $query = new WP_Query($args);
        
                // Get listings data for map (only those with coordinates)
        $listings_data = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Get coordinates (only include if already geocoded)
                $lat = get_post_meta($post_id, '_listing_latitude', true);
                $lng = get_post_meta($post_id, '_listing_longitude', true);
                
                if ($lat && $lng) {
                    $listings_data[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'lat' => floatval($lat),
                        'lng' => floatval($lng),
                        'type' => $this->get_listing_type_name($post_id),
                        'status' => $this->get_listing_status_name($post_id),
                        'price' => $this->get_listing_price($post_id),
                        'bedrooms' => get_post_meta($post_id, '_listing_bedrooms', true),
                        'bathrooms' => get_post_meta($post_id, '_listing_bathrooms', true),
                        'image' => get_the_post_thumbnail_url($post_id, 'large') ?: get_the_post_thumbnail_url($post_id, 'full'),
                    );
                }
            }
            wp_reset_postdata();
        }
        
        // Get zip codes for autocomplete
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
        
        // Output listings data and zip codes as script
        $listings_script = '<script type="text/javascript">
            window.maloneyListingsData = ' . json_encode($listings_data) . ';
            window.maloneyZipCodes = ' . json_encode($zip_codes ? $zip_codes : array()) . ';
        </script>';
        
        // Set up global query for template
        global $wp_query;
        $original_query = $wp_query;
        $wp_query = $query;
        
        // Define context constant so template knows it's being called from shortcode
        define('MALONEY_LISTINGS_SHORTCODE_CONTEXT', true);
        
        // Use the full archive template (it will skip header/footer when called from shortcode)
        ob_start();
        include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/archive-listing.php';
        $content = ob_get_clean();
        
        // Restore original query
        $wp_query = $original_query;
        wp_reset_postdata();
        
        return $listings_script . $content;
    }
    
    /**
     * Get listings page URL with optional type filter
     * 
     * @param string $type Optional. Type filter: 'condo', 'rental', or empty for all
     * @return string URL to listings page with filter
     */
    public static function get_listings_url($type = '') {
        $base_url = home_url('/listing/');
        
        if (!empty($type) && in_array($type, array('condo', 'rental'), true)) {
            return add_query_arg('type', $type, $base_url);
        }
        
        return $base_url;
    }
    
    /**
     * Home page option card shortcode
     * Usage: [maloney_listings_home_option type="condo|rental|units" title="Title" image="url" description="Text" button_text="Button"]
     */
    public function home_option_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'units',
            'title' => '',
            'image' => '',
            'description' => '',
            'button_text' => 'Search',
            'link' => '',
        ), $atts);
        
        // Default titles
        if (empty($atts['title'])) {
            switch ($atts['type']) {
                case 'condo':
                    $atts['title'] = 'CONDOMINIUMS FOR SALE';
                    if (empty($atts['link'])) {
                        $atts['link'] = self::get_listings_url('condo');
                    }
                    break;
                case 'rental':
                    $atts['title'] = 'APARTMENT RENTALS';
                    if (empty($atts['link'])) {
                        $atts['link'] = self::get_listings_url('rental');
                    }
                    break;
                case 'units':
                    $atts['title'] = 'ALL UNITS';
                    if (empty($atts['link'])) {
                        $atts['link'] = self::get_listings_url();
                    }
                    break;
            }
        }
        
        // Default descriptions
        if (empty($atts['description'])) {
            switch ($atts['type']) {
                case 'condo':
                    $atts['description'] = 'Search current affordable condo resale and lottery listings';
                    break;
                case 'rental':
                    $atts['description'] = 'Search our current listings of affordable rental opportunities';
                    break;
                case 'units':
                    $atts['description'] = 'Search all available affordable housing units';
                    break;
            }
        }
        
        // Color classes
        $color_class = '';
        switch ($atts['type']) {
            case 'condo':
                $color_class = 'condo-color'; // Teal
                break;
            case 'rental':
                $color_class = 'rental-color'; // Reddish-brown
                break;
            case 'units':
                $color_class = 'units-color'; // Blue or another color
                break;
        }
        
        ob_start();
        ?>
        <div class="home-listing-option <?php echo esc_attr($color_class); ?>">
            <div class="option-header">
                <h3><?php echo esc_html($atts['title']); ?></h3>
            </div>
            <?php if ($atts['image']) : ?>
                <div class="option-image">
                    <img src="<?php echo esc_url($atts['image']); ?>" alt="<?php echo esc_attr($atts['title']); ?>" />
                </div>
            <?php endif; ?>
            <div class="option-description">
                <p><?php echo esc_html($atts['description']); ?></p>
            </div>
            <div class="option-button">
                <a href="<?php echo esc_url($atts['link']); ?>" class="button">
                    <?php echo esc_html($atts['button_text']); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_listing_type_name($post_id) {
        $terms = get_the_terms($post_id, 'listing_type');
        return $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }
    
    private function get_listing_status_name($post_id) {
        $terms = get_the_terms($post_id, 'listing_status');
        return $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }
    
    private function get_listing_price($post_id) {
        $rent = get_post_meta($post_id, '_listing_rent_price', true);
        $purchase = get_post_meta($post_id, '_listing_purchase_price', true);
        
        if ($rent) {
            return '$' . number_format($rent) . '/mo';
        } elseif ($purchase) {
            return '$' . number_format($purchase);
        }
        return '';
    }
    
    /**
     * Get address from fields
     * Uses ONLY the address field - no longer combines with city/town
     */
    private function build_address_from_fields($post_id) {
        // Use ONLY the address field - users should enter the full address
        $address = get_post_meta($post_id, 'wpcf-address', true);
        if (empty($address)) {
            $address = get_post_meta($post_id, '_listing_address', true);
        }
        
        // Return the address field as-is (no combining with city/town)
        return !empty($address) ? trim($address) : '';
    }
    
    /**
     * All Available Units shortcode
     * Displays a table of rental properties with available units
     * Usage: [maloney_available_units title="Current Rental Availability"]
     */
    public function available_units_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Current Availability',
        ), $atts);
        
        // Get all availability entries from all rental properties
        $all_entries = array();
        if (class_exists('Maloney_Listings_Available_Units_Fields')) {
            $all_entries = Maloney_Listings_Available_Units_Fields::get_all_availability_entries();
        }
        
        // Filter out entries with 0 or empty units available
        $all_entries = array_filter($all_entries, function($entry) {
            return !empty($entry['units_available']) && intval($entry['units_available']) > 0;
        });
        
        // Determine if we're showing a single property (hide Property and Town columns)
        $is_single_property = !empty($atts['property_id']);
        
        // Sort entries
        if ($is_single_property) {
            // For single property, sort by unit size only
            usort($all_entries, function($a, $b) {
                $bed_a = isset($a['bedrooms']) ? strtolower($a['bedrooms']) : '';
                $bed_b = isset($b['bedrooms']) ? strtolower($b['bedrooms']) : '';
                return strcmp($bed_a, $bed_b);
            });
        } else {
            // For all properties, sort by property name using natural sort, then by unit size
            usort($all_entries, function($a, $b) {
                $prop_a = isset($a['property']) ? strtolower($a['property']) : '';
                $prop_b = isset($b['property']) ? strtolower($b['property']) : '';
                if ($prop_a !== $prop_b) {
                    // Use natural sort for property names
                    return strnatcasecmp($prop_a, $prop_b);
                }
                $bed_a = isset($a['bedrooms']) ? strtolower($a['bedrooms']) : '';
                $bed_b = isset($b['bedrooms']) ? strtolower($b['bedrooms']) : '';
                return strcmp($bed_a, $bed_b);
            });
        }
        
        ob_start();
        ?>
        <div class="maloney-available-units-table">
            <?php if (!empty($atts['title'])) : ?>
                <h2><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <?php if (!empty($all_entries)) : ?>
                <table class="available-units-table" id="available-units-table">
                    <thead>
                        <tr>
                            <th class="sortable sorted-asc" data-sort="property">Property <span class="sort-indicator">↑</span></th>
                            <th class="sortable" data-sort="town">Town <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="unit-size">Unit Size <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="rent">Total Monthly Leasing Price <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="min-income">Minimum Income <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="income-limit">Income Limit (AMI %) <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="type">Type <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="units-available">Units Available <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="accessible-units">Accessible Units <span class="sort-indicator">↕</span></th>
                            <th>Learn More</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_entries as $entry) : 
                            $property_id = isset($entry['source_post_id']) ? $entry['source_post_id'] : (isset($entry['property_id']) ? $entry['property_id'] : 0);
                            $property_link = '';
                            if ($property_id) {
                                $property_link = get_permalink($property_id);
                            } elseif (!empty($entry['view_apply'])) {
                                $property_link = $entry['view_apply'];
                            }
                        ?>
                            <tr data-property="<?php echo esc_attr($entry['property']); ?>" 
                                data-town="<?php echo esc_attr($entry['town']); ?>" 
                                data-unit-size="<?php echo esc_attr($entry['bedrooms']); ?>" 
                                data-rent="<?php echo !empty($entry['rent']) ? floatval($entry['rent']) : 0; ?>" 
                                data-min-income="<?php echo !empty($entry['minimum_income']) ? floatval($entry['minimum_income']) : 0; ?>" 
                                data-income-limit="<?php echo esc_attr($entry['income_limit']); ?>" 
                                data-type="<?php echo esc_attr($entry['type']); ?>" 
                                data-units-available="<?php echo intval($entry['units_available']); ?>" 
                                data-accessible-units="<?php echo !empty($entry['accessible_units']) && $entry['accessible_units'] !== '0' ? intval($entry['accessible_units']) : 0; ?>">
                                <td data-label="Property">
                                    <?php if (!empty($property_link)) : ?>
                                        <strong><a href="<?php echo esc_url($property_link); ?>" style="color: #2271b1; text-decoration: none;"><?php echo esc_html($entry['property']); ?></a></strong>
                                    <?php else : ?>
                                        <strong><?php echo esc_html($entry['property']); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Town"><?php echo esc_html($entry['town']); ?></td>
                                <td data-label="Unit Size"><?php echo esc_html($entry['bedrooms']); ?></td>
                                <td data-label="Total Monthly Leasing Price"><?php echo !empty($entry['rent']) ? '$' . number_format(floatval($entry['rent']), 0) : '—'; ?></td>
                                <td data-label="Minimum Income"><?php echo !empty($entry['minimum_income']) ? '$' . number_format(floatval($entry['minimum_income']), 0) : '—'; ?></td>
                                <td data-label="Income Limit (AMI %)"><?php echo !empty($entry['income_limit']) ? esc_html($entry['income_limit']) : '—'; ?></td>
                                <td data-label="Type"><?php echo !empty($entry['type']) ? esc_html($entry['type']) : '—'; ?></td>
                                <td data-label="Units Available"><strong><?php echo esc_html($entry['units_available']); ?></strong></td>
                                <td data-label="Accessible Units"><?php echo !empty($entry['accessible_units']) && $entry['accessible_units'] !== '0' ? esc_html($entry['accessible_units']) : '0'; ?></td>
                                <td data-label="Learn More">
                                    <?php if (!empty($entry['view_apply'])) : ?>
                                        <a href="<?php echo esc_url($entry['view_apply']); ?>" class="button">View & Apply</a>
                                    <?php else : ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No properties with available units found.</p>
            <?php endif; ?>
        </div>
        
        <style>
        .maloney-available-units-table {
            margin: 20px 0;
        }
        .maloney-available-units-table h2 {
            margin-bottom: 20px;
        }
        .available-units-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }
        .available-units-table th,
        .available-units-table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .available-units-table th {
            background-color: #f5f5f5;
            font-weight: 600;
        }
        .available-units-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .available-units-table tr:hover {
            background-color: #f0f0f0;
        }
        .available-units-table .button {
            padding: 6px 12px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            display: inline-block;
            font-size: 13px;
        }
        .available-units-table .button:hover {
            background: #005a87;
        }
        .available-units-table th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }
        .available-units-table th.sortable:hover {
            background-color: #e8e8e8;
        }
        .available-units-table .sort-indicator {
            font-size: 10px;
            margin-left: 5px;
            opacity: 0.5;
        }
        .available-units-table th.sortable.sorted-asc .sort-indicator::before {
            content: '↑';
            opacity: 1;
        }
        .available-units-table th.sortable.sorted-desc .sort-indicator::before {
            content: '↓';
            opacity: 1;
        }
        @media (max-width: 768px) {
            .available-units-table,
            .available-units-table thead,
            .available-units-table tbody,
            .available-units-table th,
            .available-units-table td,
            .available-units-table tr {
                display: block;
            }
            .available-units-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .available-units-table tr {
                border: 1px solid #ddd;
                margin-bottom: 20px;
                background: white;
                border-radius: 4px;
                padding: 0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .available-units-table td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding: 12px;
                text-align: left;
                display: grid;
                grid-template-columns: 160px 1fr;
                gap: 10px;
                align-items: start;
            }
            .available-units-table td:last-child {
                border-bottom: none;
                padding: 12px;
                display: block;
                text-align: center;
                grid-template-columns: 1fr;
            }
            .available-units-table td:not(:last-child):before {
                content: attr(data-label) ":";
                font-weight: 600;
                color: #333;
                background-color: #f0f0f0;
                padding: 6px 10px;
                border-radius: 3px;
                font-size: 13px;
                grid-column: 1;
            }
            .available-units-table td:not(:last-child) > * {
                grid-column: 2;
            }
            .available-units-table td:last-child:before {
                display: none;
            }
        }
        </style>
        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                var table = document.getElementById('available-units-table');
                if (!table) return;
                
                var headers = table.querySelectorAll('th.sortable');
                var tbody = table.querySelector('tbody');
                var currentSort = { column: 'property', direction: 'asc' };
                
                headers.forEach(function(header, index) {
                    header.addEventListener('click', function() {
                        var sortColumn = this.getAttribute('data-sort');
                        var direction = 'asc';
                        
                        // If clicking the same column, toggle direction
                        if (currentSort.column === sortColumn) {
                            direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                        }
                        
                        // Remove sorted classes and indicators from all headers
                        headers.forEach(function(h) {
                            h.classList.remove('sorted-asc', 'sorted-desc');
                            var indicator = h.querySelector('.sort-indicator');
                            if (indicator) {
                                indicator.textContent = '↕';
                            }
                        });
                        
                        // Add sorted class to current header
                        this.classList.add('sorted-' + direction);
                        var indicator = this.querySelector('.sort-indicator');
                        if (indicator) {
                            indicator.textContent = direction === 'asc' ? '↑' : '↓';
                        }
                        
                        // Sort the table
                        var rows = Array.from(tbody.querySelectorAll('tr'));
                        rows.sort(function(a, b) {
                            var aVal, bVal;
                            
                            switch(sortColumn) {
                                case 'property':
                                    aVal = a.getAttribute('data-property').toLowerCase();
                                    bVal = b.getAttribute('data-property').toLowerCase();
                                    // Use natural sort for property names
                                    return direction === 'asc' ? (aVal.localeCompare(bVal, undefined, {numeric: true, sensitivity: 'base'})) : (bVal.localeCompare(aVal, undefined, {numeric: true, sensitivity: 'base'}));
                                case 'town':
                                    aVal = a.getAttribute('data-town').toLowerCase();
                                    bVal = b.getAttribute('data-town').toLowerCase();
                                    return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                                case 'unit-size':
                                    aVal = a.getAttribute('data-unit-size').toLowerCase();
                                    bVal = b.getAttribute('data-unit-size').toLowerCase();
                                    return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                                case 'rent':
                                    aVal = parseFloat(a.getAttribute('data-rent')) || 0;
                                    bVal = parseFloat(b.getAttribute('data-rent')) || 0;
                                    return direction === 'asc' ? aVal - bVal : bVal - aVal;
                                case 'min-income':
                                    aVal = parseFloat(a.getAttribute('data-min-income')) || 0;
                                    bVal = parseFloat(b.getAttribute('data-min-income')) || 0;
                                    return direction === 'asc' ? aVal - bVal : bVal - aVal;
                                case 'income-limit':
                                    aVal = a.getAttribute('data-income-limit').toLowerCase();
                                    bVal = b.getAttribute('data-income-limit').toLowerCase();
                                    return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                                case 'type':
                                    aVal = a.getAttribute('data-type').toLowerCase();
                                    bVal = b.getAttribute('data-type').toLowerCase();
                                    return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                                case 'units-available':
                                    aVal = parseInt(a.getAttribute('data-units-available')) || 0;
                                    bVal = parseInt(b.getAttribute('data-units-available')) || 0;
                                    return direction === 'asc' ? aVal - bVal : bVal - aVal;
                                case 'accessible-units':
                                    aVal = parseInt(a.getAttribute('data-accessible-units')) || 0;
                                    bVal = parseInt(b.getAttribute('data-accessible-units')) || 0;
                                    return direction === 'asc' ? aVal - bVal : bVal - aVal;
                                default:
                                    return 0;
                            }
                        });
                        
                        // Re-append sorted rows
                        rows.forEach(function(row) {
                            tbody.appendChild(row);
                        });
                        
                        // Update current sort
                        currentSort = { column: sortColumn, direction: direction };
                    });
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function condo_listings_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Current Condo Listings',
            'property_id' => '',
        ), $atts);
        
        if (empty($atts['property_id'])) {
            global $post;
            if ($post && $post->post_type === 'listing') {
                $atts['property_id'] = $post->ID;
            }
        }
        
        // Get condo listing entries
        $all_entries = array();
        if (class_exists('Maloney_Listings_Condo_Listings_Fields')) {
            if (!empty($atts['property_id'])) {
                // Get entries for specific property only
                $property_id = intval($atts['property_id']);
                $all_entries = Maloney_Listings_Condo_Listings_Fields::get_condo_listings_data($property_id);
            } else {
                // Get all entries from all properties
                $all_entries = Maloney_Listings_Condo_Listings_Fields::get_all_condo_listings_entries();
            }
        }
        
        // Filter out entries with 0 or empty units available
        $all_entries = array_filter($all_entries, function($entry) {
            return !empty($entry['units_available']) && intval($entry['units_available']) > 0;
        });
        
        // Determine if we're showing a single property (hide Property and Town columns)
        $is_single_property = !empty($atts['property_id']);
        
        // Sort entries
        if ($is_single_property) {
            // For single property, sort by unit size only
            usort($all_entries, function($a, $b) {
                $bed_a = isset($a['bedrooms']) ? strtolower($a['bedrooms']) : '';
                $bed_b = isset($b['bedrooms']) ? strtolower($b['bedrooms']) : '';
                return strcmp($bed_a, $bed_b);
            });
        } else {
            // For all properties, sort by property name using natural sort, then by unit size
            usort($all_entries, function($a, $b) {
                $prop_a = isset($a['property']) ? strtolower($a['property']) : '';
                $prop_b = isset($b['property']) ? strtolower($b['property']) : '';
                if ($prop_a !== $prop_b) {
                    // Use natural sort for property names
                    return strnatcasecmp($prop_a, $prop_b);
                }
                $bed_a = isset($a['bedrooms']) ? strtolower($a['bedrooms']) : '';
                $bed_b = isset($b['bedrooms']) ? strtolower($b['bedrooms']) : '';
                return strcmp($bed_a, $bed_b);
            });
        }
        
        ob_start();
        ?>
        <div class="maloney-condo-listings-table">
            <?php if (!empty($atts['title'])) : ?>
                <h2><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <?php if (!empty($all_entries)) : ?>
                <table class="condo-listings-table" id="condo-listings-table">
                    <thead>
                        <tr>
                            <?php if (!$is_single_property) : ?>
                                <th class="sortable sorted-asc" data-sort="property">Property <span class="sort-indicator">↑</span></th>
                                <th class="sortable" data-sort="town">Town <span class="sort-indicator">↕</span></th>
                            <?php endif; ?>
                            <th class="sortable <?php echo $is_single_property ? 'sorted-asc' : ''; ?>" data-sort="unit-size"># Beds <span class="sort-indicator"><?php echo $is_single_property ? '↑' : '↕'; ?></span></th>
                            <th class="sortable" data-sort="price">Price <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="income-limit">Income Limit (AMI %) <span class="sort-indicator">↕</span></th>
                            <?php if (!$is_single_property) : ?>
                                <th class="sortable" data-sort="type">Type <span class="sort-indicator">↕</span></th>
                            <?php endif; ?>
                            <th class="sortable" data-sort="units-available">Units Available <span class="sort-indicator">↕</span></th>
                            <th class="sortable" data-sort="accessible-units">Accessible Units <span class="sort-indicator">↕</span></th>
                            <?php if (!$is_single_property) : ?>
                                <th>Learn More</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_entries as $entry) : 
                            $property_id = isset($entry['source_post_id']) ? $entry['source_post_id'] : (isset($entry['property_id']) ? $entry['property_id'] : 0);
                            $property_link = '';
                            if ($property_id) {
                                $property_link = get_permalink($property_id);
                            } elseif (!empty($entry['view_apply'])) {
                                $property_link = $entry['view_apply'];
                            }
                        ?>
                            <tr data-property="<?php echo esc_attr($entry['property']); ?>" 
                                data-town="<?php echo esc_attr($entry['town']); ?>" 
                                data-unit-size="<?php echo esc_attr($entry['bedrooms']); ?>" 
                                data-price="<?php echo !empty($entry['price']) ? floatval($entry['price']) : 0; ?>" 
                                data-income-limit="<?php echo esc_attr($entry['income_limit']); ?>" 
                                data-type="<?php echo esc_attr($entry['type']); ?>" 
                                data-units-available="<?php echo intval($entry['units_available']); ?>" 
                                data-accessible-units="<?php echo !empty($entry['accessible_units']) && $entry['accessible_units'] !== '0' ? intval($entry['accessible_units']) : 0; ?>">
                                <?php if (!$is_single_property) : ?>
                                    <td data-label="Property">
                                        <?php if (!empty($property_link)) : ?>
                                            <strong><a href="<?php echo esc_url($property_link); ?>" style="color: #2271b1; text-decoration: none;"><?php echo esc_html($entry['property']); ?></a></strong>
                                        <?php else : ?>
                                            <strong><?php echo esc_html($entry['property']); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Town"><?php echo esc_html($entry['town']); ?></td>
                                <?php endif; ?>
                                <td data-label="# Beds"><?php echo esc_html($entry['bedrooms']); ?></td>
                                <td data-label="Price"><?php echo !empty($entry['price']) ? '$' . number_format(floatval($entry['price']), 0) : '—'; ?></td>
                                <td data-label="Income Limit (AMI %)"><?php echo !empty($entry['income_limit']) ? esc_html($entry['income_limit']) : '—'; ?></td>
                                <?php if (!$is_single_property) : ?>
                                    <td data-label="Type"><?php echo !empty($entry['type']) ? esc_html($entry['type']) : '—'; ?></td>
                                <?php endif; ?>
                                <td data-label="Units Available"><strong><?php echo esc_html($entry['units_available']); ?></strong></td>
                                <td data-label="Accessible Units"><?php echo !empty($entry['accessible_units']) && $entry['accessible_units'] !== '0' ? esc_html($entry['accessible_units']) : '0'; ?></td>
                                <?php if (!$is_single_property) : ?>
                                    <td data-label="Learn More">
                                        <?php if (!empty($entry['view_apply'])) : ?>
                                            <a href="<?php echo esc_url($entry['view_apply']); ?>" class="button">View & Apply</a>
                                        <?php else : ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('There is no current condo availability at this property. Applications submitted at this time will be added to the waitlist in the order they are received.', 'maloney-listings'); ?></p>
            <?php endif; ?>
        </div>
        
        <style>
        .maloney-condo-listings-table {
            margin: 20px 0;
        }
        .maloney-condo-listings-table h2 {
            margin-bottom: 20px;
        }
        .condo-listings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }
        .condo-listings-table th,
        .condo-listings-table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .condo-listings-table th {
            background-color: #f5f5f5;
            font-weight: 600;
        }
        .condo-listings-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .condo-listings-table tr:hover {
            background-color: #f0f0f0;
        }
        .condo-listings-table .button {
            padding: 6px 12px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            display: inline-block;
            font-size: 13px;
        }
        .condo-listings-table .button:hover {
            background: #005a87;
        }
        .condo-listings-table th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }
        .condo-listings-table th.sortable:hover {
            background-color: #e8e8e8;
        }
        .condo-listings-table .sort-indicator {
            font-size: 10px;
            margin-left: 5px;
            opacity: 0.5;
        }
        .condo-listings-table th.sortable.sorted-asc .sort-indicator::before {
            content: '↑';
            opacity: 1;
        }
        .condo-listings-table th.sortable.sorted-desc .sort-indicator::before {
            content: '↓';
            opacity: 1;
        }
        @media (max-width: 768px) {
            .condo-listings-table,
            .condo-listings-table thead,
            .condo-listings-table tbody,
            .condo-listings-table th,
            .condo-listings-table td,
            .condo-listings-table tr {
                display: block;
            }
            .condo-listings-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .condo-listings-table tr {
                border: 1px solid #ddd;
                margin-bottom: 20px;
                background: white;
                border-radius: 4px;
                padding: 0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .condo-listings-table td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding: 12px;
                text-align: left;
                display: grid;
                grid-template-columns: 160px 1fr;
                gap: 10px;
                align-items: start;
            }
            .condo-listings-table td:before {
                content: attr(data-label);
                font-weight: 600;
                color: #333;
            }
        }
        </style>
        
        <script>
        (function() {
            var table = document.getElementById('condo-listings-table');
            if (!table) return;
            
            var tbody = table.querySelector('tbody');
            var headers = table.querySelectorAll('th.sortable');
            // Determine default sort column - use 'unit-size' if property column doesn't exist
            var hasPropertyColumn = table.querySelector('th[data-sort="property"]');
            var currentSort = { column: hasPropertyColumn ? 'property' : 'unit-size', direction: 'asc' };
            
            headers.forEach(function(header) {
                header.addEventListener('click', function() {
                    var sortColumn = this.getAttribute('data-sort');
                    var direction = 'asc';
                    
                    // If clicking the same column, toggle direction
                    if (currentSort.column === sortColumn) {
                        direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                    }
                    
                    // Update sort indicators
                    headers.forEach(function(h) {
                        h.classList.remove('sorted-asc', 'sorted-desc');
                        var indicator = h.querySelector('.sort-indicator');
                        if (indicator) {
                            indicator.textContent = '↕';
                        }
                    });
                    this.classList.add('sorted-' + direction);
                    var indicator = this.querySelector('.sort-indicator');
                    if (indicator) {
                        indicator.textContent = direction === 'asc' ? '↑' : '↓';
                    }
                    
                    // Sort rows
                    var rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.sort(function(a, b) {
                        var aVal, bVal;
                        switch(sortColumn) {
                            case 'property':
                                aVal = (a.getAttribute('data-property') || '').toLowerCase();
                                bVal = (b.getAttribute('data-property') || '').toLowerCase();
                                return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                            case 'town':
                                aVal = (a.getAttribute('data-town') || '').toLowerCase();
                                bVal = (b.getAttribute('data-town') || '').toLowerCase();
                                return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                            case 'unit-size':
                                aVal = a.getAttribute('data-unit-size').toLowerCase();
                                bVal = b.getAttribute('data-unit-size').toLowerCase();
                                return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                            case 'price':
                                aVal = parseFloat(a.getAttribute('data-price')) || 0;
                                bVal = parseFloat(b.getAttribute('data-price')) || 0;
                                return direction === 'asc' ? aVal - bVal : bVal - aVal;
                            case 'income-limit':
                                aVal = a.getAttribute('data-income-limit').toLowerCase();
                                bVal = b.getAttribute('data-income-limit').toLowerCase();
                                return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                            case 'type':
                                aVal = a.getAttribute('data-type').toLowerCase();
                                bVal = b.getAttribute('data-type').toLowerCase();
                                return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                            case 'units-available':
                                aVal = parseInt(a.getAttribute('data-units-available')) || 0;
                                bVal = parseInt(b.getAttribute('data-units-available')) || 0;
                                return direction === 'asc' ? aVal - bVal : bVal - aVal;
                            case 'accessible-units':
                                aVal = parseInt(a.getAttribute('data-accessible-units')) || 0;
                                bVal = parseInt(b.getAttribute('data-accessible-units')) || 0;
                                return direction === 'asc' ? aVal - bVal : bVal - aVal;
                            default:
                                return 0;
                        }
                    });
                    
                    // Re-append sorted rows
                    rows.forEach(function(row) {
                        tbody.appendChild(row);
                    });
                    
                    // Update current sort
                    currentSort = { column: sortColumn, direction: direction };
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Display current rental availability for a specific listing
     * Usage: [maloney_listing_availability id="123" title="Current Rental Availability"]
     */
    public function listing_availability_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'slug' => '',
            'title' => 'Current Rental Availability',
        ), $atts);
        
        // Get listing by ID or slug
        $post_id = 0;
        if (!empty($atts['id'])) {
            $post_id = intval($atts['id']);
        } elseif (!empty($atts['slug'])) {
            $post = get_page_by_path($atts['slug'], OBJECT, 'listing');
            if ($post) {
                $post_id = $post->ID;
            }
        } else {
            // Try to get current post ID if in a listing context
            global $post;
            if ($post && $post->post_type === 'listing') {
                $post_id = $post->ID;
            }
        }
        
        if (!$post_id) {
            return '<p>' . __('Listing not found.', 'maloney-listings') . '</p>';
        }
        
        // Check if this is a rental
        $listing_type = get_the_terms($post_id, 'listing_type');
        $is_rental = false;
        if ($listing_type && !is_wp_error($listing_type)) {
            $type_slug = strtolower($listing_type[0]->slug);
            if (strpos($type_slug, 'rental') !== false) {
                $is_rental = true;
            }
        }
        
        if (!$is_rental) {
            return '<p>' . __('This shortcode is only available for rental properties.', 'maloney-listings') . '</p>';
        }
        
        ob_start();
        
        if (class_exists('Maloney_Listings_Available_Units_Fields')) {
            $availability_data = Maloney_Listings_Available_Units_Fields::get_availability_data($post_id);
            $total_available = Maloney_Listings_Available_Units_Fields::get_total_available($post_id);
            
            if ($total_available > 0 && !empty($availability_data)) {
                ?>
                <div class="listing-available-units maloney-listing-availability-block" style="margin: 20px 0; padding: 20px;">
                    <?php if (!empty($atts['title'])) : ?>
                        <h3 class="tb-heading has-text-color has-background available-heading"><?php echo esc_html($atts['title']); ?></h3>
                    <?php endif; ?>
                    <table class="maloney-availability-table" style="width: 100%; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden;">
                        <thead>
                            <tr style="background: #f9f9f9; border-bottom: 2px solid #ddd;">
                                <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #333; border-bottom: 2px solid #ddd;"><?php _e('# Beds', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px; color: #333; border-bottom: 2px solid #ddd;"><?php _e('Total Monthly Leasing Price', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px; color: #333; border-bottom: 2px solid #ddd;"><?php _e('Minimum Income', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px; color: #333; border-bottom: 2px solid #ddd;"><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px; color: #333; border-bottom: 2px solid #ddd;"><?php _e('Units Available', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #333; border-bottom: 2px solid #ddd;"><?php _e('Accessible Units', 'maloney-listings'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($availability_data as $entry) {
                                if (!empty($entry['bedrooms']) && !empty($entry['units_available'])) {
                                    $units_text = $entry['units_available'];
                                    // Extract number to check if > 0
                                    $count = 0;
                                    if (preg_match('/(\d+)/', $units_text, $matches)) {
                                        $count = intval($matches[1]);
                                    } else {
                                        $count = intval($units_text);
                                    }
                                    
                                    if ($count > 0) {
                                        $rent = !empty($entry['rent']) ? '$' . number_format(floatval($entry['rent'])) : '—';
                                        $min_income = !empty($entry['minimum_income']) ? '$' . number_format(floatval($entry['minimum_income'])) : '—';
                                        $income_limit = !empty($entry['income_limit']) ? esc_html($entry['income_limit']) : '—';
                                        $accessible_units = !empty($entry['accessible_units']) && $entry['accessible_units'] !== '0' ? esc_html($entry['accessible_units']) : '0';
                                        ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td class="availability-beds" data-label="" style="color: #333; text-align: left;"><?php echo esc_html($entry['bedrooms']); ?></td>
                                            <td data-label="<?php _e('Total Monthly Leasing Price', 'maloney-listings'); ?>" style="padding: 12px; color: #333; text-align: center;"><span class="availability-value"><?php echo $rent; ?></span></td>
                                            <td data-label="<?php _e('Minimum Income', 'maloney-listings'); ?>" style="padding: 12px; color: #333; text-align: center;"><span class="availability-value"><?php echo $min_income; ?></span></td>
                                            <td data-label="<?php _e('Income Limit (AMI %)', 'maloney-listings'); ?>" style="padding: 12px; color: #333; text-align: center;"><span class="availability-value"><?php echo $income_limit; ?></span></td>
                                            <td data-label="<?php _e('Units Available', 'maloney-listings'); ?>" style="padding: 12px; color: #333; text-align: center;"><span class="availability-value"><?php echo esc_html($units_text); ?></span></td>
                                            <td class="availability-accessible" data-label="<?php _e('Accessible Units', 'maloney-listings'); ?>" style="padding: 12px; color: #333; text-align: left; font-size: 14px;"><span class="availability-value"><?php echo $accessible_units; ?></span></td>
                                        </tr>
                                        <?php
                                    }
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                } else {
                    // No available units - show message
                    ?>
                    <div class="listing-available-units maloney-listing-availability-block" style="margin: 20px 0; padding: 20px;">
                        <?php if (!empty($atts['title'])) : ?>
                            <h3 class="tb-heading has-text-color has-background available-heading"><?php echo esc_html($atts['title']); ?></h3>
                        <?php endif; ?>
                    <div style="font-size: 15px; color: #666; line-height: 1.6;">
                        <?php _e('There is no current availability at this property. Applications submitted at this time will be added to the waitlist in the order they are received.', 'maloney-listings'); ?>
                    </div>
                </div>
                <?php
            }
        } else {
            return '<p>' . __('Availability data is not available.', 'maloney-listings') . '</p>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Listings link shortcode - generates a link to the listings page with optional type filter
     * Usage: [maloney_listings_link type="condo|rental" text="Link Text"]
     */
    public function listings_link_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => '', // 'condo', 'rental', or empty for all listings
            'text' => '', // Link text, defaults to "View Condos", "View Rentals", or "View Listings"
            'class' => '', // Additional CSS classes
        ), $atts);
        
        $type = !empty($atts['type']) ? strtolower($atts['type']) : '';
        $url = self::get_listings_url($type);
        
        // Default link text based on type
        if (empty($atts['text'])) {
            switch ($type) {
                case 'condo':
                    $atts['text'] = __('View Condos', 'maloney-listings');
                    break;
                case 'rental':
                    $atts['text'] = __('View Rentals', 'maloney-listings');
                    break;
                default:
                    $atts['text'] = __('View Listings', 'maloney-listings');
                    break;
            }
        }
        
        $class_attr = !empty($atts['class']) ? ' class="' . esc_attr($atts['class']) . '"' : '';
        
        return '<a href="' . esc_url($url) . '"' . $class_attr . '>' . esc_html($atts['text']) . '</a>';
    }
    
    /**
     * Listings Search Form shortcode
     * Usage: [maloney_listings_search_form placeholder="Search location..." button_text="Get started" show_tabs="1"]
     */
    public function listings_search_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Search location or zip code...',
            'button_text' => 'Get started',
            'show_tabs' => '1', // '1' or '0' (as string for shortcode compatibility)
        ), $atts);
        
        // Convert show_tabs to boolean
        $show_tabs = ($atts['show_tabs'] === '1' || $atts['show_tabs'] === 'true' || $atts['show_tabs'] === true);
        
        // Use the block's render method if available (it's public)
        if (class_exists('Maloney_Listings_Blocks')) {
            $blocks = new Maloney_Listings_Blocks();
            $attributes = array(
                'placeholder' => $atts['placeholder'],
                'buttonText' => $atts['button_text'],
                'showTabs' => $show_tabs,
            );
            return $blocks->render_search_form_block($attributes);
        }
        
        // Fallback: return error message if blocks class not available
        return '<!-- Listings Search Form: Blocks class not available -->';
    }
    
    /**
     * Listing Map shortcode
     * Usage: [maloney_listing_map height="400"]
     * Displays a map for the current listing (single listing page only)
     */
    public function listing_map_shortcode($atts) {
        global $post;
        
        // Only work on single listing pages
        if (!$post || $post->post_type !== 'listing') {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'height' => '400', // Default height in pixels
        ), $atts);
        
        // Get coordinates
        $latitude = get_post_meta($post->ID, '_listing_latitude', true);
        $longitude = get_post_meta($post->ID, '_listing_longitude', true);
        
        // Get address
        $address = get_post_meta($post->ID, 'wpcf-address', true);
        if (empty($address)) {
            $address = get_post_meta($post->ID, '_listing_address', true);
        }
        
        // Get listing type
        $listing_type = get_the_terms($post->ID, 'listing_type');
        $type_slug = ($listing_type && !is_wp_error($listing_type)) ? strtolower($listing_type[0]->slug) : '';
        
        // If no coordinates, return empty
        if (empty($latitude) || empty($longitude)) {
            return '';
        }
        
        // Enqueue map assets
        wp_enqueue_style('leaflet-css', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4');
        wp_enqueue_script('leaflet-js', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true);
        wp_enqueue_style('maloney-listings-frontend', MALONEY_LISTINGS_PLUGIN_URL . 'assets/css/frontend.css', array(), MALONEY_LISTINGS_VERSION);
        wp_enqueue_script('maloney-listings-frontend', MALONEY_LISTINGS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery', 'leaflet-js'), MALONEY_LISTINGS_VERSION, true);
        
        // Get settings for colors
        $settings = Maloney_Listings_Settings::get_setting(null, array());
        $settings = is_array($settings) ? $settings : array();
        $rental_color = isset($settings['rental_color']) ? $settings['rental_color'] : '#E86962';
        $condo_color = isset($settings['condo_color']) ? $settings['condo_color'] : '#E4C780';
        
        // Pass settings to JavaScript
        wp_localize_script('maloney-listings-frontend', 'maloneyListingsSettings', array(
            'rentalColor' => $rental_color,
            'condoColor' => $condo_color,
        ));
        
        // Generate unique ID for this map instance
        $map_id = 'listing-map-' . $post->ID . '-' . uniqid();
        
        // Output map container
        ob_start();
        ?>
        <div class="listing-map" id="<?php echo esc_attr($map_id); ?>" 
             data-lat="<?php echo esc_attr($latitude); ?>" 
             data-lng="<?php echo esc_attr($longitude); ?>" 
             data-type="<?php echo esc_attr($type_slug); ?>" 
             data-address="<?php echo esc_attr($address); ?>" 
             style="height: <?php echo esc_attr($atts['height']); ?>px; width: 100%; margin: 30px 0;"></div>
        <script type="text/javascript">
        (function() {
            // Initialize map - wait for Leaflet to load
            function initMap() {
                if (typeof L === 'undefined' || typeof jQuery === 'undefined') {
                    setTimeout(initMap, 100);
                    return;
                }
                
                var $ = jQuery;
                var mapElement = $('#<?php echo esc_js($map_id); ?>');
                if (!mapElement.length) {
                    setTimeout(initMap, 100);
                    return;
                }
                
                // Check if map already initialized
                if (mapElement.data('map-initialized')) {
                    return;
                }
                
                var lat = parseFloat(mapElement.data('lat'));
                var lng = parseFloat(mapElement.data('lng'));
                var typeSlug = String(mapElement.data('type') || '').toLowerCase();
                var address = String(mapElement.data('address') || '').trim();
                
                if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                    try {
                        // Disable scroll zoom, dragging, touch zoom, and double-click zoom
                        var map = L.map('<?php echo esc_js($map_id); ?>', {
                            scrollWheelZoom: false,
                            dragging: false,
                            touchZoom: false,
                            doubleClickZoom: false,
                            boxZoom: false,
                            keyboard: false,
                            zoomControl: false
                        }).setView([lat, lng], 15);
                        
                        // Mark as initialized
                        mapElement.data('map-initialized', true);
                        
                        // Colorful map style
                        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                            subdomains: 'abcd',
                            maxZoom: 19
                        }).addTo(map);
                        
                        // Add zoom control
                        var zoomControl = L.control.zoom({
                            position: 'topright'
                        }).addTo(map);
                        
                        // Get colors from settings
                        var rentalColor = (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.rentalColor) 
                            ? maloneyListingsSettings.rentalColor 
                            : '#E86962';
                        var condoColor = (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.condoColor) 
                            ? maloneyListingsSettings.condoColor 
                            : '#E4C780';
                        
                        // Create colored pin based on type
                        var pinColor = (typeSlug === 'condo') ? condoColor : rentalColor;
                        var pinIcon = L.divIcon({
                            className: 'custom-pin',
                            html: '<div style="background-color: ' + pinColor + '; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                            iconSize: [30, 30],
                            iconAnchor: [15, 30],
                            popupAnchor: [0, -30]
                        });
                        
                        // Add marker
                        var marker = L.marker([lat, lng], { icon: pinIcon }).addTo(map);
                        
                        // Add popup with address if available
                        if (address) {
                            var safeAddress = address.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            marker.bindPopup('<div style="padding: 5px; font-size: 14px;">' + safeAddress + '</div>');
                        }
                        
                        // Invalidate size after a short delay to ensure proper rendering
                        setTimeout(function() {
                            if (map && typeof map.invalidateSize === 'function') {
                                map.invalidateSize();
                            }
                        }, 250);
                    } catch (e) {
                        console.error('Error initializing map:', e);
                    }
                }
            }
            
            // Start initialization when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initMap);
            } else {
                // DOM already loaded
                setTimeout(initMap, 100);
            }
            
            // Also try on window load as fallback
            if (typeof jQuery !== 'undefined') {
                jQuery(window).on('load', function() {
                    setTimeout(initMap, 200);
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

