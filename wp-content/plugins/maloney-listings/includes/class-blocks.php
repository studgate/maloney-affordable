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
        
        // Detect if we're on the homepage
        $is_homepage = is_front_page() || is_home();
        
        // Update placeholder for homepage (no zip code mention)
        if ($is_homepage) {
            $placeholder = 'Search by town, city, or neighborhood...';
        }
        
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
        <div class="maloney-listings-search-form-block maloney-search-form-wrapper <?php echo $is_homepage ? 'maloney-search-homepage' : ''; ?>" id="<?php echo esc_attr($form_id); ?>">
            <?php if ($show_tabs) : ?>
            <div class="maloney-search-tabs">
                <button type="button" class="maloney-search-tab <?php echo $is_homepage ? 'active' : ''; ?>" data-type="condo">Condo</button>
                <button type="button" class="maloney-search-tab <?php echo $is_homepage ? '' : 'active'; ?>" data-type="rental">Rental</button>
            </div>
            <?php endif; ?>
            <form class="maloney-search-form" method="get" action="<?php echo esc_url($archive_url); ?>">
                <div class="maloney-search-form-container <?php echo $is_homepage ? 'maloney-search-container-homepage' : ''; ?>">
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
                    window.maloneyListingsData = <?php echo wp_json_encode($listings); ?>;
                }
                if (typeof window.maloneyZipCodes === 'undefined') {
                    window.maloneyZipCodes = <?php echo wp_json_encode($zip_codes); ?>;
                }
                // Pass homepage flag
                if (typeof window.maloneySearchIsHomepage === 'undefined') {
                    window.maloneySearchIsHomepage = <?php echo $is_homepage ? 'true' : 'false'; ?>;
                }
            </script>
            <?php
        }
        
        // Output CSS only once per page
        if (!self::$search_form_css_output) {
            self::$search_form_css_output = true;
            ?>
            <style>
        .maloney-listings-search-form-block,
        .maloney-search-form-wrapper {
            margin: 20px 0;
            position: relative;
            z-index: 9999;
        }
        /* Ensure proper z-index stacking for Divi sections */
        .et_pb_section_0 {
            position: relative;
            z-index: 2;
        }
        .et_pb_section_1 {
            position: relative;
            z-index: 1;
        }
        .home-hero {
            position: relative;
            z-index: 3;
        }
        .home-hero h1 {
            color: #FFFFFF !important;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8), 0 0 20px rgba(0, 0, 0, 0.5) !important;
            border-radius: 5px !important;
            padding: 15px 0 !important;
            display: inline-block !important;
            margin-bottom: 10px !important;
            backdrop-filter: blur(5px) !important;
            -webkit-backdrop-filter: blur(5px) !important;
        }
        .home-hero .et_pb_text_1.et_pb_text {
            color: #FFFFFF !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.8), 0 0 15px rgba(0, 0, 0, 0.5) !important;
            background: rgba(0, 0, 0, 0.5) !important;
            border-radius: 5px !important;
            padding: 15px 20px !important;
            display: inline-block !important;
            backdrop-filter: blur(5px) !important;
            -webkit-backdrop-filter: blur(5px) !important;
        }
        .home-hero .et_pb_text_1.et_pb_text p,
        .home-hero .et_pb_text_1.et_pb_text h2,
        .home-hero .et_pb_text_1.et_pb_text h3 {
            color: #FFFFFF !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.8), 0 0 15px rgba(0, 0, 0, 0.5) !important;
        }
        .listings-search {
            position: relative;
            z-index: 3;
        }
        /* Homepage specific styling - transparent blurred background */
        .maloney-listings-search-form-block.maloney-search-homepage {
            background: rgba(255, 255, 255, 0.85) !important;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 800px;
            margin: 20px 0;
            position: relative;
            z-index: 999999 !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
        }
        .maloney-search-container-homepage {
            background: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1000000;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
        }
        .maloney-search-container-homepage:focus-within {
            border-color: #0073aa;
            box-shadow: 0 2px 12px rgba(0,115,170,0.15);
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
            border-bottom: 3px solid #BA4D4A;
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
            font-weight: 600;
            background: #0073aa;
            color: #ffffff;
            border-bottom-color: #0073aa;
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
        /* Homepage input wrapper needs higher z-index */
        .maloney-search-homepage .maloney-search-input-wrapper {
            z-index: 1000001 !important;
            position: relative;
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
        /* Homepage autocomplete - must appear above everything */
        .maloney-search-homepage .maloney-location-autocomplete {
            z-index: 2147483647 !important; /* Maximum z-index value */
            position: absolute !important;
            background: white !important;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25) !important; /* Stronger shadow for visibility */
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
                var selectedType = isHomepage ? 'condo' : 'rental'; // Default to condo on homepage, rental elsewhere
                var selectedSearchValue = null; // Store selected autocomplete value
                var selectedEntryType = null; // Store selected entry type (city, zip, or address)
                var selectedCoordinates = null; // Store selected coordinates (lat/lng)
                
                // Check if we're on homepage
                var isHomepage = window.maloneySearchIsHomepage === true;
                var citiesWithListings = Array.isArray(window.maloneyCitiesWithListings) ? window.maloneyCitiesWithListings : [];
                
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
                
                // Helper function to extract city from address
                var extractCityFromAddress = function(address) {
                    if (!address) return null;
                    // Common patterns: "Street, City, State ZIP" or "Street, City, State"
                    // Try to match city before state (MA, Massachusetts, etc.)
                    // Handle various formats:
                    // - "123 Main St, Boston, MA 02120"
                    // - "123 Main St, Boston, Massachusetts"
                    // - "123 Main St, Boston, MA"
                    var patterns = [
                        // Pattern: ", City, MA ZIP" or ", City, MA"
                        /,\s*([^,]+?),\s*(?:MA|Massachusetts|Mass\.?)(?:\s+\d{5})?$/i,
                        // Pattern: ", City, MA ZIP" (with space before ZIP)
                        /,\s*([^,]+?),\s*(?:MA|Massachusetts|Mass\.?)\s+\d{5}/i,
                        // Pattern: ", City, State" (more general)
                        /,\s*([^,]+?),\s*[A-Z]{2}(?:\s+\d{5})?$/i,
                    ];
                    
                    for (var i = 0; i < patterns.length; i++) {
                        var match = address.match(patterns[i]);
                        if (match && match[1]) {
                            var extractedCity = match[1].trim();
                            // Clean up - remove any trailing state abbreviations or zip codes
                            extractedCity = extractedCity.replace(/\s+(?:MA|Massachusetts|Mass\.?|\d{5}).*$/i, '').trim();
                            if (extractedCity) {
                                return extractedCity;
                            }
                        }
                    }
                    return null;
                };
                
                // Process listing data
                listingData.forEach(function(listing) {
                    var lat = listing.lat ? parseFloat(listing.lat) : NaN;
                    var lng = listing.lng ? parseFloat(listing.lng) : NaN;
                    var coords = (!isNaN(lat) && !isNaN(lng)) ? { lat: lat, lng: lng } : {};
                    var city = (listing.city || '').trim();
                    var cityLabel = (listing.city_label || '').trim();
                    var stateLabel = (listing.state || 'MA').trim() || 'MA';
                    var zip = (listing.zip || '').trim();
                    var address = (listing.address || '').trim();
                    
                    // Extract city from address field
                    var cityFromAddress = null;
                    if (address) {
                        cityFromAddress = extractCityFromAddress(address);
                    }
                    
                    // Collect all unique cities (from city field and address field)
                    var allCities = [];
                    var seenCities = {};
                    
                    // Add city from city field
                    if (city && !seenCities[city.toLowerCase()]) {
                        allCities.push(city);
                        seenCities[city.toLowerCase()] = true;
                    }
                    
                    // Add city from address field
                    if (cityFromAddress && !seenCities[cityFromAddress.toLowerCase()]) {
                        allCities.push(cityFromAddress);
                        seenCities[cityFromAddress.toLowerCase()] = true;
                    }
                    
                    // Process each unique city
                    allCities.forEach(function(cityName) {
                        // Extract base city name if it contains "|" (e.g., "Boston | Allston" -> "Boston")
                        var baseCity = cityName;
                        var hasNeighborhood = false;
                        if (cityName.indexOf('|') !== -1) {
                            var parts = cityName.split('|');
                            baseCity = parts[0].trim();
                            hasNeighborhood = true;
                        }
                        
                        // Add standalone city entry (e.g., "Boston, MA")
                        if (baseCity) {
                            // Use a unique key that includes the source to avoid conflicts
                            var entryKey = 'city:' + baseCity.toLowerCase();
                            addEntry(entryKey, {
                                type: 'city',
                                value: baseCity,
                                searchValue: baseCity,
                                city: baseCity,
                                label: baseCity + ', ' + stateLabel,
                                zip: zip,
                                lat: coords.lat,
                                lng: coords.lng
                            });
                        }
                        
                        // Also add the full city entry if it has a neighborhood (e.g., "Boston | Allston, MA")
                        if (hasNeighborhood && cityName !== baseCity) {
                            addEntry('cityfull:' + cityName.toLowerCase(), {
                                type: 'city',
                                value: cityName,
                                searchValue: cityName,
                                city: cityName,
                                label: cityName + ', ' + stateLabel,
                                zip: zip,
                                lat: coords.lat,
                                lng: coords.lng
                            });
                        }
                    });
                    // Add city label entry if different (also process it like other cities)
                    if (cityLabel && cityLabel.toLowerCase() !== (city || '').toLowerCase()) {
                        // Process cityLabel the same way we process other cities
                        var baseCityLabel = cityLabel;
                        var hasNeighborhoodLabel = false;
                        if (cityLabel.indexOf('|') !== -1) {
                            var labelParts = cityLabel.split('|');
                            baseCityLabel = labelParts[0].trim();
                            hasNeighborhoodLabel = true;
                        }
                        
                        // Add standalone city entry from label
                        if (baseCityLabel) {
                            addEntry('citylabel:' + baseCityLabel.toLowerCase(), {
                                type: 'city',
                                value: baseCityLabel,
                                searchValue: baseCityLabel,
                                city: baseCityLabel,
                                label: baseCityLabel + ', ' + stateLabel,
                                zip: zip,
                                lat: coords.lat,
                                lng: coords.lng
                            });
                        }
                        
                        // Add full city label entry if it has neighborhood
                        if (hasNeighborhoodLabel && cityLabel !== baseCityLabel) {
                            addEntry('citylabelfull:' + cityLabel.toLowerCase(), {
                                type: 'city',
                                value: cityLabel,
                                searchValue: cityLabel,
                                city: cityLabel,
                                label: cityLabel + ', ' + stateLabel,
                                zip: zip,
                                lat: coords.lat,
                                lng: coords.lng
                            });
                        }
                    }
                    
                    // Only add zip codes and addresses if NOT on homepage
                    if (!isHomepage) {
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
                    }
                });
                
                // Add zip codes from zipCodes array (only if NOT on homepage)
                if (!isHomepage) {
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
                }
                
                // Get matching entries
                var getMatches = function(query) {
                    var qLower = query.toLowerCase();
                    if (!qLower) return [];
                    
                    var matchingEntries = [];
                    var seenLabels = new Set();
                    var searchCoords = null; // Store coordinates if we're searching for a location
                    
                    // On homepage, only show cities
                    if (isHomepage) {
                        // First, try to find exact matches from localEntries (cities only)
                        // Prioritize standalone city entries (without "|") over neighborhood entries
                        var standaloneCityEntries = [];
                        var neighborhoodCityEntries = [];
                        
                        localEntries.forEach(function(entry) {
                            if (entry.type !== 'city') return; // Only cities on homepage
                            
                            var cityValue = (entry.city || entry.value || entry.searchValue || '').toLowerCase();
                            // Match only if city name STARTS with the query (not contains)
                            var matches = cityValue.startsWith(qLower);
                            
                            if (matches) {
                                var labelKey = entry.label.toLowerCase().trim();
                                if (!seenLabels.has(labelKey)) {
                                    seenLabels.add(labelKey);
                                    // Check if this is a standalone city (no "|" in city name)
                                    var isStandalone = cityValue.indexOf('|') === -1;
                                    if (isStandalone) {
                                        standaloneCityEntries.push(entry);
                                    } else {
                                        neighborhoodCityEntries.push(entry);
                                    }
                                    // Store coordinates for distance calculation
                                    if (entry.lat && entry.lng) {
                                        searchCoords = { lat: entry.lat, lng: entry.lng };
                                    }
                                }
                            }
                        });
                        
                        // Add standalone city entries first, then neighborhood entries
                        matchingEntries = matchingEntries.concat(standaloneCityEntries);
                        matchingEntries = matchingEntries.concat(neighborhoodCityEntries);
                        
                        // If no exact matches found, show fallback cities (always show at least 3)
                        if (matchingEntries.length === 0) {
                            // Use citiesWithListings as fallback - show first 3 that match query or just first 3
                            var fallbackCities = [];
                            if (citiesWithListings.length > 0) {
                                // Try to find cities that partially match
                                citiesWithListings.forEach(function(city) {
                                    if (!city || (!city.city && !city.name)) return;
                                    var cityName = (city.city || city.name).toLowerCase();
                                    // Match only if city name STARTS with the query
                                    if (cityName.startsWith(qLower)) {
                                        fallbackCities.push(city);
                                    }
                                });
                                // If no partial matches, just take first 3
                                if (fallbackCities.length === 0) {
                                    fallbackCities = citiesWithListings.slice(0, 3);
                                } else {
                                    // Limit to 3
                                    fallbackCities = fallbackCities.slice(0, 3);
                                }
                            }
                            
                            fallbackCities.forEach(function(city) {
                                if (!city || (!city.city && !city.name)) return;
                                var cityEntry = {
                                    type: 'city',
                                    value: city.city || city.name,
                                    searchValue: city.city || city.name,
                                    city: city.city || city.name,
                                    label: (city.city || city.name) + ', MA',
                                    zip: city.zip || '',
                                    lat: city.lat || '',
                                    lng: city.lng || ''
                                };
                                var labelKey = cityEntry.label.toLowerCase().trim();
                                if (!seenLabels.has(labelKey)) {
                                    seenLabels.add(labelKey);
                                    matchingEntries.push(cityEntry);
                                }
                            });
                        }
                        
                        // If still no results and we have localEntries with cities, show first 10 cities
                        if (matchingEntries.length === 0 && localEntries.length > 0) {
                            var cityCount = 0;
                            localEntries.forEach(function(entry) {
                                if (entry.type === 'city' && cityCount < 10) {
                                    var labelKey = entry.label.toLowerCase().trim();
                                    if (!seenLabels.has(labelKey)) {
                                        seenLabels.add(labelKey);
                                        matchingEntries.push(entry);
                                        cityCount++;
                                    }
                                }
                            });
                        }
                    } else {
                        // Not homepage - include all types
                        localEntries.forEach(function(entry) {
                            var matches = false;
                            
                            // Match city, zip, or address entries - must START with query
                            if (entry.type === 'city') {
                                var cityValue = (entry.city || entry.value || entry.searchValue || '').toLowerCase();
                                matches = cityValue.startsWith(qLower);
                            } else if (entry.type === 'zip') {
                                var zipValue = (entry.zip || entry.value || entry.searchValue || '').toLowerCase();
                                matches = zipValue.startsWith(qLower);
                            } else if (entry.type === 'address') {
                                // Match addresses - check if address STARTS with the query
                                var addressValue = (entry.value || entry.searchValue || entry.label || '').toLowerCase();
                                matches = addressValue.startsWith(qLower);
                            }
                            
                            if (matches) {
                                var labelKey = entry.label.toLowerCase().trim();
                                if (!seenLabels.has(labelKey)) {
                                    seenLabels.add(labelKey);
                                    matchingEntries.push(entry);
                                }
                            }
                        });
                    }
                    
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
                        if (!item) return;
                        var itemType = escapeHtml(item.type || 'city');
                        // For cities, use the city name (item.city or item.value), not the full label
                        var itemValue = '';
                        if (item.type === 'city' && item.city) {
                            itemValue = escapeHtml(item.city);
                        } else {
                            itemValue = escapeHtml(item.value || '');
                        }
                        var itemLabel = escapeHtml(item.label || '');
                        // Use city name for search value on homepage, otherwise use searchValue
                        var itemSearchValue = '';
                        if (isHomepage && item.type === 'city' && item.city) {
                            itemSearchValue = escapeHtml(item.city);
                        } else {
                            itemSearchValue = escapeHtml(item.searchValue || item.value || item.city || '');
                        }
                        var itemLat = item.lat ? escapeHtml(String(item.lat)) : '';
                        var itemLng = item.lng ? escapeHtml(String(item.lng)) : '';
                        var dataAttrs = 'data-type="' + itemType + '" data-value="' + itemValue + '" data-search-value="' + itemSearchValue + '"';
                        if (itemLat) dataAttrs += ' data-lat="' + itemLat + '"';
                        if (itemLng) dataAttrs += ' data-lng="' + itemLng + '"';
                        html += '<li class="location-suggestion" ' + dataAttrs + '><span class="suggestion-icon">' + icon + '</span><span class="suggestion-primary">' + itemLabel + '</span></li>';
                    });
                    html += '</ul>';
                    autocomplete.innerHTML = html;
                    autocomplete.style.display = 'block';
                    
                    // On homepage, ensure autocomplete is positioned correctly and has highest z-index
                    if (isHomepage) {
                        autocomplete.style.zIndex = '2147483647'; // Maximum z-index value
                        autocomplete.style.position = 'absolute';
                        autocomplete.style.background = 'white';
                        // Force a reflow to ensure styles are applied
                        autocomplete.offsetHeight;
                    }
                };
                
                // Handle input typing
                var inputTimeout = null;
                input.addEventListener('input', function() {
                    selectedSearchValue = null; // Clear selected value on new input
                    selectedEntryType = null; // Clear selected entry type on new input
                    selectedCoordinates = null; // Clear selected coordinates on new input
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
                
                // Handle suggestion click - just select, don't submit
                autocomplete.addEventListener('mousedown', function(e) {
                    e.preventDefault(); // Prevent input blur
                    var li = e.target.closest('.location-suggestion');
                    if (li) {
                        var searchValue = li.getAttribute('data-search-value');
                        var entryType = li.getAttribute('data-type');
                        var dataValue = li.getAttribute('data-value');
                        var dataLat = li.getAttribute('data-lat');
                        var dataLng = li.getAttribute('data-lng');
                        input.value = li.querySelector('.suggestion-primary').textContent;
                        // Use data-value (city name) if available, otherwise use searchValue
                        selectedSearchValue = dataValue || searchValue;
                        selectedEntryType = entryType; // Store the entry type (city, zip, or address)
                        // Store coordinates if available
                        if (dataLat && dataLng) {
                            selectedCoordinates = { lat: parseFloat(dataLat), lng: parseFloat(dataLng) };
                        } else {
                            selectedCoordinates = null;
                        }
                        autocomplete.style.display = 'none';
                        // Don't submit - let user click search button or change tabs
                    }
                });
                
                // Also handle click for better compatibility
                autocomplete.addEventListener('click', function(e) {
                    var li = e.target.closest('.location-suggestion');
                    if (li) {
                        var searchValue = li.getAttribute('data-search-value');
                        var entryType = li.getAttribute('data-type');
                        var dataValue = li.getAttribute('data-value');
                        var dataLat = li.getAttribute('data-lat');
                        var dataLng = li.getAttribute('data-lng');
                        input.value = li.querySelector('.suggestion-primary').textContent;
                        // Use data-value (city name) if available, otherwise use searchValue
                        selectedSearchValue = dataValue || searchValue;
                        selectedEntryType = entryType;
                        // Store coordinates if available
                        if (dataLat && dataLng) {
                            selectedCoordinates = { lat: parseFloat(dataLat), lng: parseFloat(dataLng) };
                        } else {
                            selectedCoordinates = null;
                        }
                        autocomplete.style.display = 'none';
                        // Don't submit - let user click search button or change tabs
                    }
                });
                
                // Hide autocomplete on blur (with delay to allow clicks)
                input.addEventListener('blur', function() {
                    setTimeout(function() {
                        autocomplete.style.display = 'none';
                    }, 300); // Increased delay for better click handling
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
                    
                    // Get coordinates from selectedCoordinates
                    var selectedLat = selectedCoordinates ? selectedCoordinates.lat : null;
                    var selectedLng = selectedCoordinates ? selectedCoordinates.lng : null;
                    
                    // Build URL
                    var archiveUrl = '<?php echo esc_js($archive_url); ?>';
                    var url = new URL(archiveUrl, window.location.origin);
                    
                    // Add type parameter if tabs are shown - ALWAYS get from active tab to ensure accuracy
                    <?php if ($show_tabs) : ?>
                    // ALWAYS get the type from the currently active tab (don't rely on selectedType variable)
                    var activeTab = block.querySelector('.maloney-search-tab.active');
                    if (activeTab) {
                        var tabType = activeTab.getAttribute('data-type');
                        if (tabType) {
                            url.searchParams.set('type', tabType);
                        }
                    } else if (selectedType) {
                        // Fallback to selectedType if no active tab found
                        url.searchParams.set('type', selectedType);
                    }
                    <?php endif; ?>
                    
                    // Add location parameter based on entry type
                    // On homepage, always use 'city' parameter (no zip codes)
                    if (isHomepage) {
                        // Clean city name - remove special chars and use space-separated format
                        url.searchParams.set('city', searchValue);
                        // Add coordinates if available for proximity search and map zoom
                        if (selectedLat && selectedLng) {
                            url.searchParams.set('lat', selectedLat);
                            url.searchParams.set('lng', selectedLng);
                            // Add zoom parameter for better map centering - use 16 for city searches (much closer zoom)
                            url.searchParams.set('zoom', '16');
                        }
                    } else {
                        // Not homepage - handle zip codes and addresses
                        if (entryType === 'zip') {
                            // For zip codes, use 'zip' parameter
                            url.searchParams.set('zip', searchValue);
                            if (selectedLat && selectedLng) {
                                url.searchParams.set('lat', selectedLat);
                                url.searchParams.set('lng', selectedLng);
                            }
                        } else if (entryType === 'address') {
                            // For addresses, pass as 'city' parameter - the listings page will geocode it
                            // The searchLocation function will handle full addresses
                            url.searchParams.set('city', searchValue);
                            if (selectedLat && selectedLng) {
                                url.searchParams.set('lat', selectedLat);
                                url.searchParams.set('lng', selectedLng);
                            }
                        } else {
                            // For city or default, use 'city' parameter
                            // Also check if it looks like a zip code (fallback)
                            var isZip = /^\d{5}(-\d{4})?$/.test(searchValue);
                            if (isZip) {
                                url.searchParams.set('zip', searchValue);
                                if (selectedLat && selectedLng) {
                                    url.searchParams.set('lat', selectedLat);
                                    url.searchParams.set('lng', selectedLng);
                                }
                            } else {
                                url.searchParams.set('city', searchValue);
                                if (selectedLat && selectedLng) {
                                    url.searchParams.set('lat', selectedLat);
                                    url.searchParams.set('lng', selectedLng);
                                }
                            }
                        }
                    }
                    
                    // Redirect to listings page
                    window.location.href = url.toString();
                });
                
                // Handle Enter key - submit form
                input.addEventListener('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        autocomplete.style.display = 'none';
                        // If no selectedSearchValue, use input value
                        if (!selectedSearchValue) {
                            selectedSearchValue = input.value.trim();
                        }
                        form.dispatchEvent(new Event('submit'));
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

