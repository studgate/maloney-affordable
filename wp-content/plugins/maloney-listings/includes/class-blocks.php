<?php
/**
 * Gutenberg Blocks Registration
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 * 
 * @package Maloney_Listings
 * @author Responsab LLC
 * @link https://www.responsab.com
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Blocks {
    
    private static $search_form_css_output = false;
    private static $search_form_data_output = false;
    
    public function __construct() {
        add_action('init', array($this, 'register_blocks'));
    }
    
    /**
     * Register Gutenberg blocks
     */
    public function register_blocks() {
        // Check if Gutenberg is available
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Register Available Units block
        register_block_type('maloney-listings/available-units', array(
            'attributes' => array(
                'title' => array(
                    'type' => 'string',
                    'default' => 'Current Rental Availability',
                ),
            ),
            'render_callback' => array($this, 'render_available_units_block'),
            'editor_script' => 'maloney-listings-blocks',
            'editor_style' => 'maloney-listings-blocks-editor',
            'style' => 'maloney-listings-blocks',
        ));
        
        // Register Listings Search Form block
        register_block_type('maloney-listings/search-form', array(
            'attributes' => array(
                'placeholder' => array(
                    'type' => 'string',
                    'default' => 'Search location or zip code...',
                ),
                'buttonText' => array(
                    'type' => 'string',
                    'default' => 'Get started',
                ),
                'showTabs' => array(
                    'type' => 'boolean',
                    'default' => true,
                ),
            ),
            'render_callback' => array($this, 'render_search_form_block'),
            'editor_script' => 'maloney-listings-blocks',
            'editor_style' => 'maloney-listings-blocks-editor',
            'style' => 'maloney-listings-blocks',
        ));
        
        // Register Listings View block
        register_block_type('maloney-listings/listings-view', array(
            'attributes' => array(
                'type' => array(
                    'type' => 'string',
                    'default' => 'units',
                ),
            ),
            'render_callback' => array($this, 'render_listings_view_block'),
            'editor_script' => 'maloney-listings-blocks',
            'editor_style' => 'maloney-listings-blocks-editor',
            'style' => 'maloney-listings-blocks',
        ));
        
        // Enqueue block assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
    }
    
    /**
     * Render the Available Units block
     */
    public function render_available_units_block($attributes) {
        $title = isset($attributes['title']) ? $attributes['title'] : 'Current Rental Availability';
        
        // Use the shortcode to render
        $shortcode = new Maloney_Listings_Shortcodes();
        return $shortcode->available_units_shortcode(array('title' => $title));
    }
    
    /**
     * Render the Listings Search Form block
     */
    public function render_search_form_block($attributes) {
        $placeholder = isset($attributes['placeholder']) ? $attributes['placeholder'] : 'Search location or zip code...';
        $button_text = isset($attributes['buttonText']) ? $attributes['buttonText'] : 'Get started';
        $show_tabs = isset($attributes['showTabs']) ? $attributes['showTabs'] : true;
        
        // Get listings archive URL - try both singular and plural
        $archive_url = get_post_type_archive_link('listing');
        if (empty($archive_url)) {
            $archive_url = get_post_type_archive_link('listings');
        }
        if (empty($archive_url)) {
            // Fallback to /listing/ if archive link doesn't work
            $archive_url = home_url('/listing/');
        }
        
        // Generate unique ID for this form instance
        $form_id = 'maloney-search-form-' . wp_generate_password(8, false);
        
        // Get listings data for autocomplete (same as listings page)
        $listings = $this->get_listings_for_autocomplete();
        $zip_codes = $this->get_zip_codes_for_autocomplete();
        
        ob_start();
        ?>
        <div class="maloney-listings-search-form-block" id="<?php echo esc_attr($form_id); ?>">
            <?php if ($show_tabs) : ?>
            <div class="maloney-search-tabs">
                <button type="button" class="maloney-search-tab" data-type="condo">Condo</button>
                <button type="button" class="maloney-search-tab active" data-type="rental">Rental</button>
            </div>
            <?php endif; ?>
            <form class="maloney-search-form" method="get" action="<?php echo esc_url($archive_url); ?>">
                <div class="maloney-search-form-container">
                    <div class="maloney-search-input-wrapper">
                        <input 
                            type="text" 
                            name="search" 
                            class="maloney-search-input" 
                            id="<?php echo esc_attr($form_id); ?>-input"
                            placeholder="<?php echo esc_attr($placeholder); ?>" 
                            autocomplete="off"
                            required
                        />
                        <div class="maloney-location-autocomplete" id="<?php echo esc_attr($form_id); ?>-autocomplete"></div>
                    </div>
                    <button type="submit" class="maloney-search-button" aria-label="Search">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
        
        <?php
        // Output data script only once per page
        if (!self::$search_form_data_output) {
            self::$search_form_data_output = true;
            ?>
            <script type="text/javascript">
                // Pass listings and zip codes data to JavaScript for autocomplete
                if (typeof window.maloneyListingsData === 'undefined') {
                    window.maloneyListingsData = <?php echo json_encode($listings); ?>;
                }
                if (typeof window.maloneyZipCodes === 'undefined') {
                    window.maloneyZipCodes = <?php echo json_encode($zip_codes); ?>;
                }
            </script>
            <?php
        }
        
        // Output CSS only once per page
        if (!self::$search_form_css_output) {
            self::$search_form_css_output = true;
            ?>
            <style>
        .maloney-listings-search-form-block {
            margin: 20px 0;
            position: relative;
            z-index: 9999;
        }
        .maloney-search-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 0;
            border-bottom: 0;
        }
        .maloney-search-tab {
            padding: 12px 24px;
            background: #f5f5f5;
            color: #333;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            top: 2px;
        }
        .maloney-search-tab:hover {
            background: #e8e8e8;
        }
        .maloney-search-tab.active {
            background: white;
            color: #333;
            border-bottom-color: #0073aa;
            font-weight: 600;
        }
        .maloney-search-form {
            width: 100%;
            position: relative;
        }
        .maloney-search-form-container {
            display: flex;
            gap: 10px;
            align-items: stretch;
            max-width: 100%;
            background: white;
            border: 0;
            border-radius: 0;
            padding: 5px;
            position: relative;
        }
        .maloney-search-input-wrapper {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            z-index: 10000;
        }
        .maloney-search-input {
            width: 100%;
            padding: 12px 15px;
            border: 0 !important;
            border-radius: 0;
            font-size: 16px;
            background: transparent;
        }
        .maloney-search-input:focus {
            outline: none;
        }
        .maloney-location-autocomplete {
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            right: 0 !important;
            background: white !important;
            border: 1px solid #ddd !important;
            border-top: none !important;
            border-radius: 0 0 8px 8px !important;
            margin-top: 5px !important;
            margin-left: -6px !important;
            max-height: 300px !important;
            overflow-y: auto !important;
            z-index: 99999 !important;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }
        .maloney-location-autocomplete ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .maloney-location-autocomplete li {
            padding: 10px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .maloney-location-autocomplete li:hover {
            background: #f5f5f5;
        }
        .maloney-location-autocomplete li:last-child {
            border-bottom: none;
        }
        .maloney-location-autocomplete .suggestion-icon {
            flex-shrink: 0;
            color: #666;
            width: 16px;
            height: 16px;
        }
        .maloney-location-autocomplete .suggestion-primary {
            flex: 1;
        }
        .maloney-search-button {
            padding: 12px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            height: 48px;
            flex-shrink: 0;
        }
        .maloney-search-button svg {
            width: 20px;
            height: 20px;
            stroke: white;
        }
        .maloney-search-button:hover {
            background: #005a87;
        }
        .maloney-search-button:active {
            background: #004a6f;
        }
        @media (max-width: 768px) {
            .maloney-search-form-container {
                flex-direction: column;
                padding: 10px;
            }
            .maloney-search-button {
                width: 100%;
            }
            .maloney-search-tabs {
                flex-wrap: wrap;
            }
            .maloney-search-tab {
                flex: 1;
                min-width: 120px;
            }
        }
            </style>
            <?php
        }
        ?>
        
        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                var block = document.getElementById('<?php echo esc_js($form_id); ?>');
                if (!block) return;
                
                var form = block.querySelector('.maloney-search-form');
                if (!form) return;
                
                var input = document.getElementById('<?php echo esc_js($form_id); ?>-input');
                var autocomplete = document.getElementById('<?php echo esc_js($form_id); ?>-autocomplete');
                var tabs = block.querySelectorAll('.maloney-search-tab');
                var selectedType = 'rental'; // Default to rental
                var selectedSearchValue = null; // Store selected autocomplete value
                var selectedEntryType = null; // Store selected entry type (city, zip, or address)
                
                // Get listings and zip codes data
                var listingData = Array.isArray(window.maloneyListingsData) ? window.maloneyListingsData : [];
                var zipCodes = Array.isArray(window.maloneyZipCodes) ? window.maloneyZipCodes : [];
                var localEntries = [];
                var entryKeys = new Set();
                
                // Build local entries (city and zip only, NO addresses)
                var escapeHtml = function(str) {
                    if (!str) return '';
                    return str.replace(/&/g, '&amp;')
                              .replace(/</g, '&lt;')
                              .replace(/>/g, '&gt;')
                              .replace(/"/g, '&quot;')
                              .replace(/'/g, '&#039;');
                };
                
                var addEntry = function(key, entry) {
                    if (!entry || !entry.label) return;
                    var normalizedKey = key.toLowerCase();
                    if (entryKeys.has(normalizedKey)) return;
                    entryKeys.add(normalizedKey);
                    localEntries.push(entry);
                };
                
                // Process listing data - includes city, zip, and addresses
                listingData.forEach(function(listing) {
                    var lat = listing.lat ? parseFloat(listing.lat) : NaN;
                    var lng = listing.lng ? parseFloat(listing.lng) : NaN;
                    var coords = (!isNaN(lat) && !isNaN(lng)) ? { lat: lat, lng: lng } : {};
                    var city = (listing.city || '').trim();
                    var cityLabel = (listing.city_label || '').trim();
                    var stateLabel = (listing.state || 'MA').trim() || 'MA';
                    var zip = (listing.zip || '').trim();
                    var address = (listing.address || '').trim();
                    
                    // Add city entry
                    if (city) {
                        addEntry('city:' + city.toLowerCase(), {
                            type: 'city',
                            value: city,
                            searchValue: city,
                            city: city,
                            label: city + ', ' + stateLabel,
                            zip: zip,
                            lat: coords.lat,
                            lng: coords.lng
                        });
                    }
                    // Add city label entry if different
                    if (cityLabel && cityLabel.toLowerCase() !== city.toLowerCase()) {
                        addEntry('citylabel:' + cityLabel.toLowerCase(), {
                            type: 'city',
                            value: cityLabel,
                            searchValue: cityLabel,
                            city: cityLabel,
                            label: cityLabel,
                            zip: zip,
                            lat: coords.lat,
                            lng: coords.lng
                        });
                    }
                    // Add zip entry
                    if (zip) {
                        var zipLabel = zip;
                        if (city) {
                            zipLabel += ', ' + city + ', ' + stateLabel;
                        }
                        addEntry('zip:' + zip, {
                            type: 'zip',
                            value: zip,
                            searchValue: zip,
                            city: city,
                            label: zipLabel,
                            zip: zip,
                            lat: coords.lat,
                            lng: coords.lng
                        });
                    }
                    // Add address entry
                    if (address) {
                        addEntry('addr:' + address.toLowerCase(), {
                            type: 'address',
                            value: address,
                            searchValue: address,
                            city: city,
                            label: address,
                            zip: zip,
                            lat: coords.lat,
                            lng: coords.lng
                        });
                    }
                });
                
                // Add zip codes from zipCodes array
                zipCodes.forEach(function(zip) {
                    if (!zip) return;
                    addEntry('zip:' + zip, {
                        type: 'zip',
                        value: zip,
                        searchValue: zip,
                        city: '',
                        label: zip,
                        zip: zip,
                        lat: '',
                        lng: ''
                    });
                });
                
                // Get matching entries
                var getMatches = function(query) {
                    var qLower = query.toLowerCase();
                    if (!qLower) return [];
                    
                    var matchingEntries = [];
                    var seenLabels = new Set();
                    
                    localEntries.forEach(function(entry) {
                        var matches = false;
                        
                        // Match city, zip, or address entries
                        if (entry.type === 'city') {
                            var cityValue = (entry.city || entry.value || entry.searchValue || '').toLowerCase();
                            matches = cityValue.indexOf(qLower) !== -1;
                        } else if (entry.type === 'zip') {
                            var zipValue = (entry.zip || entry.value || entry.searchValue || '').toLowerCase();
                            matches = zipValue.indexOf(qLower) !== -1;
                        } else if (entry.type === 'address') {
                            // Match addresses - check if query is in the address
                            var addressValue = (entry.value || entry.searchValue || entry.label || '').toLowerCase();
                            matches = addressValue.indexOf(qLower) !== -1;
                        }
                        
                        if (matches) {
                            var labelKey = entry.label.toLowerCase().trim();
                            if (!seenLabels.has(labelKey)) {
                                seenLabels.add(labelKey);
                                matchingEntries.push(entry);
                            }
                        }
                    });
                    
                    // Sort: numbers first, then letters
                    matchingEntries.sort(function(a, b) {
                        var labelA = (a.label || '').trim();
                        var labelB = (b.label || '').trim();
                        var aStartsWithNum = /^\d/.test(labelA);
                        var bStartsWithNum = /^\d/.test(labelB);
                        if (aStartsWithNum && !bStartsWithNum) return -1;
                        if (!aStartsWithNum && bStartsWithNum) return 1;
                        return labelA.localeCompare(labelB, undefined, { numeric: true, sensitivity: 'base' });
                    });
                    
                    return matchingEntries.slice(0, 100);
                };
                
                // Render suggestions
                var renderSuggestions = function(items) {
                    if (!items || !items.length) {
                        autocomplete.style.display = 'none';
                        return;
                    }
                    var icon = '<svg class="suggestion-pin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>';
                    var html = '<ul>';
                    items.forEach(function(item) {
                        var label = escapeHtml(item.label);
                        var searchValue = escapeHtml(item.searchValue || item.value || item.label);
                        html += '<li class="location-suggestion" data-type="' + escapeHtml(item.type) + '" data-value="' + escapeHtml(item.value) + '" data-search-value="' + searchValue + '"><span class="suggestion-icon">' + icon + '</span><span class="suggestion-primary">' + label + '</span></li>';
                    });
                    html += '</ul>';
                    autocomplete.innerHTML = html;
                    autocomplete.style.display = 'block';
                };
                
                // Handle input typing
                var inputTimeout = null;
                input.addEventListener('input', function() {
                    selectedSearchValue = null; // Clear selected value on new input
                    selectedEntryType = null; // Clear selected entry type on new input
                    clearTimeout(inputTimeout);
                    var query = input.value.trim();
                    if (query.length < 1) {
                        autocomplete.style.display = 'none';
                        return;
                    }
                    inputTimeout = setTimeout(function() {
                        var matches = getMatches(query);
                        renderSuggestions(matches);
                    }, 150);
                });
                
                // Handle suggestion click
                autocomplete.addEventListener('click', function(e) {
                    var li = e.target.closest('.location-suggestion');
                    if (li) {
                        var searchValue = li.getAttribute('data-search-value');
                        var entryType = li.getAttribute('data-type');
                        input.value = li.querySelector('.suggestion-primary').textContent;
                        selectedSearchValue = searchValue;
                        selectedEntryType = entryType; // Store the entry type (city, zip, or address)
                        autocomplete.style.display = 'none';
                    }
                });
                
                // Hide autocomplete on blur (with delay to allow clicks)
                input.addEventListener('blur', function() {
                    setTimeout(function() {
                        autocomplete.style.display = 'none';
                    }, 200);
                });
                
                // Handle tab clicks
                <?php if ($show_tabs) : ?>
                if (tabs.length > 0) {
                    tabs.forEach(function(tab) {
                        tab.addEventListener('click', function() {
                            tabs.forEach(function(t) {
                                t.classList.remove('active');
                            });
                            this.classList.add('active');
                            selectedType = this.getAttribute('data-type');
                        });
                    });
                }
                <?php endif; ?>
                
                // Handle form submission
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var searchValue = selectedSearchValue || input.value.trim();
                    var entryType = selectedEntryType;
                    
                    if (!searchValue) {
                        return;
                    }
                    
                    // Build URL
                    var archiveUrl = '<?php echo esc_js($archive_url); ?>';
                    var url = new URL(archiveUrl, window.location.origin);
                    
                    // Add type parameter if tabs are shown
                    <?php if ($show_tabs) : ?>
                    if (selectedType) {
                        url.searchParams.set('type', selectedType);
                    }
                    <?php endif; ?>
                    
                    // Add location parameter based on entry type
                    if (entryType === 'zip') {
                        // For zip codes, use 'zip' parameter
                        url.searchParams.set('zip', searchValue);
                    } else if (entryType === 'address') {
                        // For addresses, pass as 'city' parameter - the listings page will geocode it
                        // The searchLocation function will handle full addresses
                        url.searchParams.set('city', searchValue);
                    } else {
                        // For city or default, use 'city' parameter
                        // Also check if it looks like a zip code (fallback)
                        var isZip = /^\d{5}(-\d{4})?$/.test(searchValue);
                        if (isZip) {
                            url.searchParams.set('zip', searchValue);
                        } else {
                            url.searchParams.set('city', searchValue);
                        }
                    }
                    
                    // Redirect to listings page
                    window.location.href = url.toString();
                });
                
                // Handle Enter key
                input.addEventListener('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        form.dispatchEvent(new Event('submit'));
                        autocomplete.style.display = 'none';
                    }
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get listings data for autocomplete (includes city, zip, and addresses)
     */
    private function get_listings_for_autocomplete() {
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
                
                // Get address
                $address = get_post_meta($post_id, 'wpcf-address', true);
                if (empty($address)) {
                    $address = get_post_meta($post_id, '_listing_address', true);
                }
                
                // Get city
                $city = get_post_meta($post_id, 'wpcf-city', true);
                if (empty($city)) {
                    $city = get_post_meta($post_id, '_listing_city', true);
                }
                $city_label = $city;
                // Extract city name if it has "|" format
                if (!empty($city) && strpos($city, '|') !== false) {
                    $city = trim(explode('|', $city)[0]);
                }
                
                // Get state
                $state = get_post_meta($post_id, 'wpcf-state-1', true);
                if (empty($state)) {
                    $state = get_post_meta($post_id, '_listing_state', true);
                }
                if (empty($state)) {
                    $state = 'MA';
                }
                
                // Get zip
                $zip = get_post_meta($post_id, 'wpcf-zip-code', true);
                if (empty($zip)) {
                    $zip = get_post_meta($post_id, '_listing_zip', true);
                }
                
                // Get coordinates
                $lat = get_post_meta($post_id, '_listing_latitude', true);
                $lng = get_post_meta($post_id, '_listing_longitude', true);
                $lat = $lat ? floatval($lat) : 0;
                $lng = $lng ? floatval($lng) : 0;
                
                $listings[] = array(
                    'address' => $address,
                    'city' => $city,
                    'city_label' => $city_label,
                    'state' => $state,
                    'zip' => $zip,
                    'lat' => ($lat != 0 && $lng != 0) ? $lat : '',
                    'lng' => ($lat != 0 && $lng != 0) ? $lng : '',
                );
            }
            wp_reset_postdata();
        }
        
        return $listings;
    }
    
    /**
     * Get unique zip codes for autocomplete
     */
    private function get_zip_codes_for_autocomplete() {
        global $wpdb;
        
        $zip_codes = array();
        
        // Get unique zip codes from wpcf-zip-code
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value != '' 
            AND meta_value IS NOT NULL
            ORDER BY meta_value ASC
        ", 'wpcf-zip-code'));
        
        if ($results) {
            $zip_codes = array_merge($zip_codes, $results);
        }
        
        // Also get from _listing_zip
        $results2 = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value != '' 
            AND meta_value IS NOT NULL
            ORDER BY meta_value ASC
        ", '_listing_zip'));
        
        if ($results2) {
            $zip_codes = array_merge($zip_codes, $results2);
        }
        
        // Remove duplicates and empty values
        $zip_codes = array_unique(array_filter($zip_codes));
        sort($zip_codes);
        
        return array_values($zip_codes);
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        // Register block script
        wp_register_script(
            'maloney-listings-blocks',
            MALONEY_LISTINGS_PLUGIN_URL . 'assets/js/blocks.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
            MALONEY_LISTINGS_VERSION,
            true
        );
        
        // Register block styles
        wp_register_style(
            'maloney-listings-blocks-editor',
            MALONEY_LISTINGS_PLUGIN_URL . 'assets/css/blocks-editor.css',
            array('wp-edit-blocks'),
            MALONEY_LISTINGS_VERSION
        );
        
        wp_register_style(
            'maloney-listings-blocks',
            MALONEY_LISTINGS_PLUGIN_URL . 'assets/css/blocks.css',
            array(),
            MALONEY_LISTINGS_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script('maloney-listings-blocks');
        wp_enqueue_style('maloney-listings-blocks-editor');
        wp_enqueue_style('maloney-listings-blocks');
        
        // Localize script
        wp_localize_script('maloney-listings-blocks', 'maloneyListingsBlocks', array(
            'pluginUrl' => MALONEY_LISTINGS_PLUGIN_URL,
        ));
    }
}

