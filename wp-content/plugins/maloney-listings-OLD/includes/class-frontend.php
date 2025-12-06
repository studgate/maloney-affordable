<?php
/**
 * Frontend Templates and Display for Maloney Listings
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('template_include', array($this, 'listing_templates'));
        add_action('wp_footer', array($this, 'add_listing_data_script'));
        add_filter('body_class', array($this, 'remove_sidebar_body_class'), 20);
        // Override Divi's sidebar class function to force no sidebar on listing pages
        add_filter('body_class', array($this, 'force_no_sidebar_on_listing_pages'), 5);
        add_action('pre_get_posts', array($this, 'set_default_sorting'));
        add_filter('posts_results', array($this, 'natural_sort_posts'), 10, 2);
        // Prevent sidebar from rendering on listing pages
        add_filter('is_active_sidebar', array($this, 'disable_sidebar_on_listing_pages'), 10, 2);
    }
    
    public function enqueue_assets() {
        global $post;
        $has_shortcode = false;
        if ($post && isset($post->post_content)) {
            $has_shortcode = has_shortcode($post->post_content, 'maloney_listings_view');
        }
        
        // Enqueue Leaflet CSS and JS (only on listing pages or pages with shortcode)
        if (is_post_type_archive('listing') || is_singular('listing') || $has_shortcode) {
            // Load Leaflet from local files (better performance, no external dependencies)
            wp_enqueue_style('leaflet-css', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4');
            
            // Fix Leaflet CSS image paths to use absolute URLs
            $leaflet_images_path = MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/images/';
            $leaflet_css_fix = "
                .leaflet-control-layers-toggle {
                    background-image: url({$leaflet_images_path}layers.png) !important;
                }
                .leaflet-retina .leaflet-control-layers-toggle {
                    background-image: url({$leaflet_images_path}layers-2x.png) !important;
                }
                .leaflet-default-icon-path {
                    background-image: url({$leaflet_images_path}marker-icon.png) !important;
                }
            ";
            wp_add_inline_style('leaflet-css', $leaflet_css_fix);
            
            wp_enqueue_script('leaflet-js', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true);
            // MarkerCluster CSS + JS for proper clustering visuals
            wp_enqueue_style('leaflet-markercluster-css', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet-markercluster/MarkerCluster.css', array('leaflet-css'), '1.5.3');
            wp_enqueue_style('leaflet-markercluster-default-css', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet-markercluster/MarkerCluster.Default.css', array('leaflet-markercluster-css'), '1.5.3');
            wp_enqueue_script('leaflet-markercluster', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet-markercluster/leaflet.markercluster.js', array('leaflet-js'), '1.5.3', true);
            
            // Ensure Dashicons is available for search/location buttons
            wp_enqueue_style('dashicons');
            
            // Enqueue custom CSS and JS
            wp_enqueue_style('maloney-listings-frontend', MALONEY_LISTINGS_PLUGIN_URL . 'assets/css/frontend.css', array(), MALONEY_LISTINGS_VERSION);
            
            // Get settings and add dynamic CSS for colors
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
            
            // Localize script for AJAX
            wp_localize_script('maloney-listings-frontend', 'maloneyListings', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('maloney_listings_nonce'),
                'adminUrl' => admin_url(),
                'archiveUrl' => get_post_type_archive_link('listings'),
                'leafletIconPath' => MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/images/', // Path to Leaflet icon images
            ));
        }
        
        // Always enqueue home options CSS (used on home page)
        wp_enqueue_style('maloney-listings-home-options', MALONEY_LISTINGS_PLUGIN_URL . 'assets/css/home-options.css', array(), MALONEY_LISTINGS_VERSION);
    }
    
    public function listing_templates($template) {
        // Use custom templates but they'll use theme's header/footer
        if (is_post_type_archive('listing')) {
            $custom_template = MALONEY_LISTINGS_PLUGIN_DIR . 'templates/archive-listing.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        } elseif (is_singular('listing')) {
            $custom_template = MALONEY_LISTINGS_PLUGIN_DIR . 'templates/single-listing.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    public function add_listing_data_script() {
        global $post;
        $has_shortcode = false;
        if ($post && isset($post->post_content)) {
            $has_shortcode = has_shortcode($post->post_content, 'maloney_listings_view');
        }
        
        // Only output data for single listing pages or shortcode pages
        // Archive pages output their own data in the template to avoid conflicts
        if (is_singular('listing') || $has_shortcode) {
            $listings = $this->get_listings_for_map();
            ?>
            <script type="text/javascript">
                // Only set if not already set (to avoid overwriting template data)
                if (typeof window.maloneyListingsData === 'undefined') {
                    window.maloneyListingsData = <?php echo json_encode($listings); ?>;
                }
            </script>
            <?php
        }
    }
    
    private function get_listings_for_map() {
        // Get ALL published listings (not just those with coordinates)
        // We'll filter by coordinates when building the array
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );
        
        $query = new WP_Query($args);
        $listings = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Get coordinates - check both meta key formats
                $lat = get_post_meta($post_id, '_listing_latitude', true);
                $lng = get_post_meta($post_id, '_listing_longitude', true);
                
                // Convert to float and validate
                $lat = $lat ? floatval($lat) : 0;
                $lng = $lng ? floatval($lng) : 0;
                
                // Only include listings with valid coordinates (not 0,0 and within valid ranges)
                if ($lat != 0 && $lng != 0 && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    // Derive bedrooms/bathrooms if missing
                    $beds = get_post_meta($post_id, '_listing_bedrooms', true);
                    if ($beds === '' || $beds === null) {
                        if (class_exists('Maloney_Listings_Data_Normalization')) {
                            $beds = Maloney_Listings_Data_Normalization::derive_number($post_id, 'bedrooms');
                        }
                    }
                    $baths = get_post_meta($post_id, '_listing_bathrooms', true);
                    if ($baths === '' || $baths === null) {
                        if (class_exists('Maloney_Listings_Data_Normalization')) {
                            $baths = Maloney_Listings_Data_Normalization::derive_number($post_id, 'bathrooms');
                        }
                    }
                    $listings[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'lat' => $lat,
                        'lng' => $lng,
                        'type' => $this->get_listing_type($post_id),
                        'status' => $this->get_listing_status($post_id),
                        'price' => $this->get_listing_price($post_id),
                        'bedrooms' => $beds,
                        'bathrooms' => $baths,
                        'image' => get_the_post_thumbnail_url($post_id, 'large') ?: get_the_post_thumbnail_url($post_id, 'full'),
                    );
                }
            }
            wp_reset_postdata();
        }
        
        return $listings;
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
    
    private function get_listing_type($post_id) {
        $terms = get_the_terms($post_id, 'listing_type');
        return $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }
    
    private function get_listing_status($post_id) {
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
     * Force no sidebar on listing pages by adding et_no_sidebar class before Divi's function runs
     * This runs at priority 5, before Divi's et_divi_sidebar_class (priority 10)
     */
    public function force_no_sidebar_on_listing_pages($classes) {
        // Check if we're on a listing page
        if (is_post_type_archive('listing') || is_singular('listing') ||
            is_post_type_archive('rental-properties') || is_singular('rental-properties') ||
            is_post_type_archive('condominiums') || is_singular('condominiums')) {
            // Remove any existing sidebar classes
            $classes = array_diff($classes, array('et_right_sidebar', 'et_left_sidebar', 'et_full_width_page'));
            // Add no sidebar class - this will prevent Divi from adding sidebar classes
            if (!in_array('et_no_sidebar', $classes)) {
                $classes[] = 'et_no_sidebar';
            }
        }
        return $classes;
    }
    
    /**
     * Remove et_right_sidebar class from body classes on listing pages (backup)
     */
    public function remove_sidebar_body_class($classes) {
        // Only remove on listing archive or single listing pages
        // Also handle old post types (rental-properties, condominiums)
        if (is_post_type_archive('listing') || is_singular('listing') ||
            is_post_type_archive('rental-properties') || is_singular('rental-properties') ||
            is_post_type_archive('condominiums') || is_singular('condominiums')) {
            $classes = array_diff($classes, array('et_right_sidebar', 'et_left_sidebar'));
            // Ensure et_no_sidebar is present
            if (!in_array('et_no_sidebar', $classes)) {
                $classes[] = 'et_no_sidebar';
            }
        }
        return $classes;
    }
    
    /**
     * Set default sorting for listing archive (Property Name by default)
     */
    public function set_default_sorting($query) {
        // Only modify the main query on the listing archive page
        if (!is_admin() && $query->is_main_query() && is_post_type_archive('listing')) {
            // Only set default if no orderby is already set
            if (!$query->get('orderby')) {
                $query->set('orderby', 'title');
                $query->set('order', 'ASC');
            }
        }
    }
    
    /**
     * Apply natural (alphanumeric) sorting to listing posts
     * This ensures "11 On The Dot" comes before "105 Washington Residences"
     */
    public function natural_sort_posts($posts, $query) {
        // Only apply to listing archive queries
        if (is_admin() || !$query->is_main_query()) {
            return $posts;
        }
        
        if (is_post_type_archive('listing') && !empty($posts)) {
            // Check if sorting by title (Property Name)
            $orderby = $query->get('orderby');
            if ($orderby === 'title' || empty($orderby)) {
                usort($posts, function($a, $b) {
                    // Use natural sort (strnatcasecmp) to treat numbers as numbers
                    return strnatcasecmp($a->post_title, $b->post_title);
                });
            }
        }
        
        return $posts;
    }
    
    /**
     * Disable sidebar on listing pages by making it inactive
     * This prevents the sidebar from being rendered at all
     */
    public function disable_sidebar_on_listing_pages($is_active_sidebar, $index) {
        // Only disable on listing archive or single listing pages
        // Also handle old post types (rental-properties, condominiums)
        if (is_post_type_archive('listing') || is_singular('listing') ||
            is_post_type_archive('rental-properties') || is_singular('rental-properties') ||
            is_post_type_archive('condominiums') || is_singular('condominiums')) {
            // Disable sidebar-1 (Divi's main sidebar)
            if ($index === 'sidebar-1') {
                return false;
            }
        }
        
        return $is_active_sidebar;
    }
}
