/**
 * Frontend JavaScript for Listings
 */

(function($) {
    'use strict';
    
    const ListingFilters = {
        lastSearchCoords: null,
        activeAreaSearch: null, // Store active area search bounds
        isInitializingFromUrl: false, // Flag to prevent duplicate applyFilters calls during URL init
        init: function() {
            this.bindEvents();
            this.initAdvancedFilters();
            this.initFromUrl();
            // Initialize mobile view toggle after a short delay to ensure DOM is ready
            setTimeout(() => {
                this.initMobileViewToggle();
            }, 100);
            // Configure Leaflet icon path to use local images
            if (typeof L !== 'undefined' && typeof maloneyListings !== 'undefined' && maloneyListings.leafletIconPath) {
                // Set default icon path for Leaflet markers
                delete L.Icon.Default.prototype._getIconUrl;
                L.Icon.Default.mergeOptions({
                    iconUrl: maloneyListings.leafletIconPath + 'marker-icon.png',
                    iconRetinaUrl: maloneyListings.leafletIconPath + 'marker-icon-2x.png',
                    shadowUrl: maloneyListings.leafletIconPath + 'marker-shadow.png',
                });
            }
            
            // Map is always visible in new layout, initialize it
            // Wait for Leaflet to be fully loaded
            if (typeof L !== 'undefined') {
                setTimeout(function() {
                    ListingFilters.initMap();
                }, 300);
            } else {
                // Wait for Leaflet to load
                $(window).on('load', function() {
                    setTimeout(function() {
                        ListingFilters.initMap();
                    }, 500);
                });
            }
        },
        
        bindEvents: function() {
            // Apply filters
            $('#apply_filters').on('click', this.applyFilters.bind(this));
            
            // Clear filters
            $('#clear_filters, #reset_filters').on('click', this.clearFilters.bind(this));
            
            // Handle reset filters link in no-listings-found message (using event delegation)
            $(document).on('click', '#reset-filters-link', function(e) {
                e.preventDefault();
                ListingFilters.clearFilters(e);
            });
            
            // Search visible area button
            $(document).on('click', '#search-visible-area-btn', this.searchVisibleArea.bind(this));
            
            // Remove area search filter
            $(document).on('click', '.remove-area-search', this.removeAreaSearch.bind(this));
            
            // Vacancy notification form
            $('#vacancy-notify-form').on('submit', this.handleVacancyNotification.bind(this));
            
            // Click on listing card to highlight on map
            $(document).on('click', '.listing-card', function() {
                const card = $(this);
                const lat = card.data('lat');
                const lng = card.data('lng');
                const listingId = card.data('listing-id');
                
                // Remove active state from all cards
                $('.listing-card').removeClass('active');
                // Add active state to clicked card
                card.addClass('active');
                
                if (lat && lng && ListingFilters.map && ListingFilters.markerClusterGroup) {
                    // Remove active state from all markers
                    ListingFilters.markerClusterGroup.eachLayer(function(marker) {
                        if (marker._icon) {
                            marker._icon.classList.remove('marker-active');
                            const innerDiv = marker._icon.querySelector('div');
                            if (innerDiv) {
                                innerDiv.classList.remove('marker-active-inner');
                            }
                            marker.setZIndexOffset(0);
                        }
                    });
                    
                    // Find the marker for this listing
                    let markerFound = false;
                    let foundMarker = null;
                    
                    // First try to find by listing ID (most reliable) - handle both string and number
                    if (listingId) {
                        const listingIdNum = parseInt(listingId, 10);
                        ListingFilters.markerClusterGroup.eachLayer(function(marker) {
                            if (marker.options && marker.options.listingId) {
                                const markerId = parseInt(marker.options.listingId, 10);
                                // Match by ID (handle both string and number)
                                if ((!isNaN(markerId) && !isNaN(listingIdNum) && markerId === listingIdNum) || 
                                    marker.options.listingId == listingId) {
                                    markerFound = true;
                                    foundMarker = marker;
                                    return false; // Break out of loop
                                }
                            }
                        });
                    }
                    
                    // Fallback: find by coordinates (within small tolerance)
                    if (!markerFound && lat && lng) {
                        const cardLat = parseFloat(lat);
                        const cardLng = parseFloat(lng);
                        ListingFilters.markerClusterGroup.eachLayer(function(marker) {
                            const markerLat = parseFloat(marker.getLatLng().lat);
                            const markerLng = parseFloat(marker.getLatLng().lng);
                            
                            // Check if this marker matches the clicked listing (within small tolerance)
                            if (!isNaN(markerLat) && !isNaN(markerLng) && 
                                Math.abs(markerLat - cardLat) < 0.0001 && 
                                Math.abs(markerLng - cardLng) < 0.0001) {
                                markerFound = true;
                                foundMarker = marker;
                                return false; // Break out of loop
                            }
                        });
                    }
                    
                    // If marker found, zoom to show it (unclustering if needed) and highlight it
                    if (markerFound && foundMarker) {
                        // Use zoomToShowLayer to uncluster and zoom to the marker
                        ListingFilters.markerClusterGroup.zoomToShowLayer(foundMarker, function() {
                            // Callback executed after marker is visible (unclustered if needed)
                            // Add active class to marker icon
                            if (foundMarker._icon) {
                                foundMarker._icon.classList.add('marker-active');
                                // Also add to inner div for better styling
                                const innerDiv = foundMarker._icon.querySelector('div');
                                if (innerDiv) {
                                    innerDiv.classList.add('marker-active-inner');
                                }
                            }
                            // Bring marker to front
                            foundMarker.setZIndexOffset(1000);
                            // Open popup
                            foundMarker.openPopup();
                        });
                    } else {
                        // If marker not found, just zoom to coordinates (fallback)
                        ListingFilters.map.setView([lat, lng], 15);
                    }
                }
            });
            
            // Remove active state when clicking elsewhere
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.listing-card').length) {
                    $('.listing-card').removeClass('active');
                    if (ListingFilters.markerClusterGroup) {
                        ListingFilters.markerClusterGroup.eachLayer(function(marker) {
                            if (marker._icon) {
                                marker._icon.classList.remove('marker-active');
                                const innerDiv = marker._icon.querySelector('div');
                                if (innerDiv) {
                                    innerDiv.classList.remove('marker-active-inner');
                                }
                                marker.setZIndexOffset(0);
                            }
                        });
                    }
                }
            });
            
            // Search location button
            $('#search_location_btn').on('click', function() {
                const location = $('#search_location_input').val().trim();
                if (location) {
                    // Check if it's a zip code
                    const isZip = /^\d{5}(-\d{4})?$/.test(location);
                    if (isZip) {
                        // For zip codes, geocode first to get coordinates, then filter
                        ListingFilters.searchLocation(location, true); // true = is zip code
                    } else {
                        // For cities, geocode and apply filters
                        ListingFilters.searchLocation(location);
                        ListingFilters.applyFilters();
                    }
                }
            });
            
            // Enter key on search input
            $('#search_location_input').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#search_location_btn').click();
                    $('#location-autocomplete').hide();
                }
            });
            
            // Initialize location autocomplete
            ListingFilters.initLocationAutocomplete();
            
            // Handle pagination clicks - only update cards, not map
            $(document).on('click', '.listings-pagination .page-numbers', function(e) {
                e.preventDefault();
                const $link = $(this);
                
                // Skip if span (current page or dots)
                if ($link.is('span') || $link.hasClass('current') || $link.hasClass('dots')) {
                    return;
                }
                
                // Must be an anchor tag
                if (!$link.is('a')) {
                    return;
                }
                
                let page = null;
                
                // Try to get page from href first (handles both ?paged=2 and /page/2/)
                const href = $link.attr('href');
                if (href) {
                    // Try ?paged=2 format
                    let match = href.match(/[?&]paged[=_](\d+)/);
                    if (match) {
                        page = parseInt(match[1]);
                    } else {
                        // Try /page/2/ format
                        match = href.match(/\/page\/(\d+)\//);
                        if (match) {
                            page = parseInt(match[1]);
                        }
                    }
                }
                
                // If still no page, try to get from text content
                if (!page || isNaN(page)) {
                    const pageText = $link.text().trim();
                    const cleanText = pageText.replace(/[^\d]/g, ''); // Remove non-digits
                    
                    if ($link.hasClass('prev') || pageText.includes('Previous') || pageText.includes('&laquo;')) {
                        // Get current page and go back
                        const $current = $('.page-numbers.current');
                        const currentPage = $current.length ? parseInt($current.text().trim()) || 1 : 1;
                        page = Math.max(1, currentPage - 1);
                    } else if ($link.hasClass('next') || pageText.includes('Next') || pageText.includes('&raquo;')) {
                        // Get current page and go forward
                        const $current = $('.page-numbers.current');
                        const currentPage = $current.length ? parseInt($current.text().trim()) || 1 : 1;
                        // Find max page from pagination
                        let maxPages = 1;
                        $('.page-numbers').each(function() {
                            const text = $(this).text().trim();
                            const num = parseInt(text);
                            if (!isNaN(num) && num > maxPages) {
                                maxPages = num;
                            }
                        });
                        page = Math.min(maxPages, currentPage + 1);
                    } else if (cleanText) {
                        // Try to parse page number from text
                        page = parseInt(cleanText);
                    }
                }
                
                // Validate page number
                if (page && !isNaN(page) && page > 0) {
                    ListingFilters.applyFilters(e, page, true); // true = pagination only, don't update map
                }
            });
        },
        
        initMobileViewToggle: function() {
            // Mobile view toggle - show/hide toggle buttons and handle view switching
            const $toggleContainer = $('.mobile-view-toggle');
            const $listBtn = $('#toggle-list-view');
            const $mapBtn = $('#toggle-map-view');
            const $cardsOverlay = $('#listings-cards-overlay');
            const $mapContainer = $('#listings-map-container');
            
            // If elements don't exist, return early
            if (!$toggleContainer.length || !$listBtn.length || !$mapBtn.length) {
                return;
            }
            
            // Check if we're on mobile
            function isMobile() {
                return window.innerWidth <= 768;
            }
            
            // Show/hide toggle buttons based on screen size
            function updateToggleVisibility() {
                if (isMobile()) {
                    $toggleContainer.show();
                } else {
                    $toggleContainer.hide();
                    // On desktop, show both
                    $cardsOverlay.show().removeClass('mobile-list-hidden');
                    $mapContainer.show().removeClass('mobile-map-active');
                }
            }
            
            // Get saved view preference or default to 'list'
            function getCurrentView() {
                if (!isMobile()) return 'both'; // Desktop shows both
                return localStorage.getItem('ml_mobile_view') || 'list';
            }
            
            // Set view
            function setView(view) {
                if (!isMobile()) {
                    // Desktop: show both
                    $cardsOverlay.show().removeClass('mobile-list-hidden mobile-list-active');
                    $mapContainer.show().removeClass('mobile-map-active');
                    return;
                }
                
                localStorage.setItem('ml_mobile_view', view);
                
                if (view === 'list') {
                    $listBtn.addClass('active');
                    $mapBtn.removeClass('active');
                    $cardsOverlay.show().removeClass('mobile-list-hidden').addClass('mobile-list-active');
                    $mapContainer.hide().removeClass('mobile-map-active').addClass('mobile-list-active');
                } else if (view === 'map') {
                    $listBtn.removeClass('active');
                    $mapBtn.addClass('active');
                    $cardsOverlay.hide().removeClass('mobile-list-active').addClass('mobile-list-hidden');
                    $mapContainer.show().removeClass('mobile-list-active').addClass('mobile-map-active');
                    // Ensure map is initialized when switching to map view
                    if (this.map && !this.mapInitialized) {
                        setTimeout(() => {
                            this.initMap();
                        }, 100);
                    } else if (this.map) {
                        // Invalidate size in case it was hidden
                        setTimeout(() => {
                            this.map.invalidateSize();
                        }, 100);
                    }
                }
            }
            
            // Initialize view on load - ensure listings are visible by default on mobile
            const currentView = getCurrentView();
            setView.call(this, currentView);
            
            // Update visibility on load and resize
            updateToggleVisibility();
            $(window).on('resize', function() {
                updateToggleVisibility();
                const view = getCurrentView();
                setView.call(this, view);
            }.bind(this));
            
            // Handle button clicks
            $listBtn.on('click', function() {
                setView.call(this, 'list');
            }.bind(this));
            
            $mapBtn.on('click', function() {
                setView.call(this, 'map');
            }.bind(this));
        },
        
        switchView: function(view) {
            // Legacy function - redirect to mobile toggle if on mobile
            if (window.innerWidth <= 768) {
                this.initMobileViewToggle();
            }
        },
        
        refreshSummaries: function() {
            const getSelectedBedroomLabels = function() {
                const vals = $('input[name="bedroom_options[]"]:checked').map(function(){return $(this).val();}).get();
                const filteredVals = vals.filter(v => v !== 'any' && v !== 'show_all');
                if (!filteredVals.length) return [];
                const map = {'0':'Studio','1':'1BR','2':'2BR','3':'3BR','4+':'4+BR'};
                return filteredVals.map(v=>map[v]||v);
            };
            const getSelectedBathroomLabels = function() {
                if ($('input[name="bathroom_options[]"]').length === 0) return [];
                const vals = $('input[name="bathroom_options[]"]:checked').map(function(){return $(this).val();}).get();
                const filteredVals = vals.filter(v => v !== 'any' && v !== 'show_all');
                return filteredVals;
            };
            function summarizeBedsBaths() {
                const bedLabels = getSelectedBedroomLabels();
                const bathLabels = getSelectedBathroomLabels();
                const hasBathFilter = $('input[name="bathroom_options[]"]').length > 0;
                if (!bedLabels.length && !bathLabels.length) {
                    return hasBathFilter ? 'Beds & Baths' : 'Bedrooms';
                }
                const parts = [];
                if (bedLabels.length) {
                    parts.push('Beds: ' + bedLabels.join(', '));
                }
                if (bathLabels.length) {
                    parts.push('Baths: ' + bathLabels.join(', '));
                }
                return parts.join(' • ');
            }
            function summarizeStatus() {
                const rentalVals = $('input[name="rental_status[]"]:checked').map(function(){return $(this).val();}).get();
                const condoVals = $('input[name="condo_status[]"]:checked').map(function(){return $(this).val();}).get();
                const statusFilter = $('input[name="status_filter[]"]:checked').map(function(){return $(this).val();}).get();
                if (statusFilter.includes('show_all') && rentalVals.length === 0 && condoVals.length === 0) {
                    return 'Status';
                }
                const parts = [];
                if (rentalVals.length > 0) {
                    const rentalLabels = {
                        '1': 'Active Rental',
                        '2': 'Open Lottery',
                        '3': 'Closed Lottery',
                        '4': 'Inactive Rental',
                        '6': 'Upcoming Lottery'
                    };
                    parts.push('Rental: ' + rentalVals.map(v => rentalLabels[v] || v).join(', '));
                }
                if (condoVals.length > 0) {
                    const condoLabels = {
                        '1': 'FCFS Condo Sales',
                        '2': 'Active Condo Lottery',
                        '3': 'Closed Condo Lottery',
                        '4': 'Inactive Condo Property',
                        '5': 'Upcoming Condo'
                    };
                    parts.push('Condo: ' + condoVals.map(v => condoLabels[v] || v).join(', '));
                }
                return parts.length > 0 ? parts.join('; ') : 'Status';
            }
            
            function summarizeType() {
                const val = $('input[name="listing_type_filter"]:checked').val();
                if (!val || val === 'show_all') return 'Listing Type';
                let label = val;
                if (val.toLowerCase().indexOf('rental') >= 0) label = 'Rental';
                if (val.toLowerCase().indexOf('condo') >= 0) label = 'Condo';
                return label;
            }
            
            // Helper to get arrow based on popover state
            const getArrowForButton = function(btnSelector, popoverSelector) {
                const isOpen = $(popoverSelector).is(':visible');
                return isOpen ? '▴' : '▾';
            };
            
            // Helper to update button text with arrow
            const updateButtonWithArrow = function(btnSelector, text, popoverSelector) {
                if (!$(btnSelector).length) return;
                const arrow = getArrowForButton(btnSelector, popoverSelector);
                // Remove any existing arrow from text and add the arrow span
                const cleanText = text.replace(/[▾▴]/g, '').trim();
                $(btnSelector).html(cleanText + ' <span class="filter-arrow">' + arrow + '</span>');
            };
            
            const bedsBathsText = summarizeBedsBaths();
            updateButtonWithArrow('#btn_bedrooms', bedsBathsText, '#popover_bedrooms');
            
            // Summarize Income Limits filter
            function summarizeIncomeLimits() {
                if ($('#btn_income_limits').length === 0) return '';
                const vals = $('input[name="income_limits[]"]:checked').map(function(){return $(this).val();}).get();
                if (!vals.length) return 'Income Limits';
                return vals.join(', ');
            }
            const incomeLimitsText = summarizeIncomeLimits();
            if (incomeLimitsText !== '' && $('#btn_income_limits').length) {
                updateButtonWithArrow('#btn_income_limits', incomeLimitsText, '#popover_income_limits');
            }
            
            // Summarize Available Units filter
            function summarizeAvailableUnits() {
                const hasUnits = $('input[name="has_available_units"]:checked').length > 0;
                const unitTypes = $('input[name="available_unit_type[]"]:checked').map(function(){return $(this).next('span').text();}).get();
                
                const parts = [];
                if (hasUnits) {
                    parts.push('Has Units');
                }
                if (unitTypes.length > 0) {
                    parts.push(unitTypes.join(', '));
                }
                
                return parts.length > 0 ? parts.join(' • ') : 'Available Units';
            }
            const availableUnitsText = summarizeAvailableUnits();
            if (availableUnitsText && $('#btn_available_units').length) {
                updateButtonWithArrow('#btn_available_units', availableUnitsText, '#popover_available_units');
            }
            
            const typeText = summarizeType();
            updateButtonWithArrow('#btn_listing_type', typeText, '#popover_listing_type');
            
            const statusText = summarizeStatus();
            updateButtonWithArrow('#btn_status', statusText, '#popover_status');
        },
        
        initAdvancedFilters: function() {
            // Helper function to update arrow direction
            const updateArrow = function(btnSelector, isOpen) {
                const $arrow = $(btnSelector).find('.filter-arrow');
                if ($arrow.length) {
                    $arrow.text(isOpen ? '▴' : '▾');
                }
            };
            
            // Popover toggles - position relative to button
            const togglePopover = function(btnSelector, popSelector){
                $(document).on('click', btnSelector, function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    const $btn = $(this);
                    const $popover = $(popSelector);
                    
                    // Position popover below the button
                    if ($popover.is(':visible')) {
                        $popover.hide();
                        updateArrow(btnSelector, false);
                    } else {
                        // Close ALL other popovers and More container first, reset their arrows
                        $('.filter-popover').not($popover).each(function() {
                            const $otherPop = $(this);
                            const $otherBtn = $('.filter-btn').filter(function() {
                                return $(this).attr('id') && $otherPop.attr('id') && 
                                       $otherPop.attr('id').replace('popover_', 'btn_') === $(this).attr('id');
                            });
                            if ($otherBtn.length) {
                                updateArrow('#' + $otherBtn.attr('id'), false);
                            }
                            $otherPop.hide();
                        });
                        $('#advanced_filters').hide();
                        updateArrow('#toggle_advanced_filters', false);
                        
                        // Position relative to button (button is in filter-dropdowns container)
                        const $container = $btn.closest('.filter-dropdowns');
                        const btnOffset = $btn.position();
                        const btnHeight = $btn.outerHeight();
                        
                        $popover.css({
                            'position': 'absolute',
                            'top': (btnOffset.top + btnHeight + 5) + 'px',
                            'left': btnOffset.left + 'px',
                            'z-index': 2100
                        });
                        
                        $popover.show();
                        updateArrow(btnSelector, true);
                    }
                });
            };
            const closePopover = function(popSelector, btnSelector){
                $(popSelector).hide();
                if (btnSelector) {
                    updateArrow(btnSelector, false);
                }
            };
            togglePopover('#btn_bedrooms', '#popover_bedrooms');
            if ($('#btn_income_limits').length) {
                togglePopover('#btn_income_limits', '#popover_income_limits');
            }
            togglePopover('#btn_listing_type', '#popover_listing_type');
            togglePopover('#btn_available_units', '#popover_available_units');
            togglePopover('#btn_status', '#popover_status');
            $(document).on('click', '#done_bedrooms', function(){ closePopover('#popover_bedrooms', '#btn_bedrooms'); ListingFilters.applyFilters(); });
            $(document).on('click', '#done_income_limits', function(){ closePopover('#popover_income_limits', '#btn_income_limits'); ListingFilters.applyFilters(); });
            $(document).on('click', '#done_available_units', function(){ closePopover('#popover_available_units', '#btn_available_units'); ListingFilters.applyFilters(); });
            $(document).on('click', '#done_listing_type', function(){ closePopover('#popover_listing_type', '#btn_listing_type'); ListingFilters.applyFilters(); });
            $(document).on('click', '#done_status', function(){ closePopover('#popover_status', '#btn_status'); ListingFilters.applyFilters(); });
            
            
            // When "Any" is checked in status, uncheck all status options and hide panels
            $(document).on('change', 'input[name="status_filter[]"][value="show_all"]', function() {
                if ($(this).is(':checked')) {
                    $('input[name="rental_status[]"], input[name="condo_status[]"]').prop('checked', false);
                    $('input[name="rental_status[]"], input[name="condo_status[]"]').closest('.filter-option-button').removeClass('checked');
                    $('.status-options-panel').hide();
                    $('.status-type-btn').removeClass('active');
                }
            });
            
            // When a status option is checked, uncheck "Any"
            $(document).on('change', 'input[name="rental_status[]"], input[name="condo_status[]"]', function() {
                if ($(this).is(':checked')) {
                    $('input[name="status_filter[]"][value="show_all"]').prop('checked', false);
                    $('input[name="status_filter[]"][value="show_all"]').closest('.filter-option-button').removeClass('checked');
                }
            });
            // Close popovers when clicking outside - ensure only one is open at a time
            $(document).on('click', function(e){
                const $t = $(e.target);
                // Don't close if clicking inside any popover, button, or More container
                if ($t.closest('.filter-popover, .filter-btn, #advanced_filters, #toggle_advanced_filters, .filter-popover-actions, .status-type-btn').length) {
                    return;
                }
                // Close all popovers and More container, reset all arrows to down
                $('.filter-popover').hide();
                $('#advanced_filters').hide();
                $('.filter-btn .filter-arrow').text('▾');
                $('#toggle_advanced_filters .filter-arrow').text('▾');
                $('.status-type-btn .status-arrow').text('▾');
                $('.status-options-panel').hide();
                $('.status-type-btn').removeClass('active');
            });
            
            // Status filter button toggle with arrows
            $(document).on('click', '.status-type-btn', function(e) {
                e.stopPropagation();
                const $btn = $(this);
                const type = $btn.attr('id').replace('btn_status_', '');
                const $panel = $('#status_panel_' + type);
                const isOpen = $panel.is(':visible');
                
                // Toggle the panel
                if (isOpen) {
                    $panel.slideUp(200);
                    $btn.removeClass('active');
                    $btn.find('.status-arrow').text('▾');
                } else {
                    // Close other panels first
                    $('.status-options-panel').slideUp(200);
                    $('.status-type-btn').removeClass('active');
                    $('.status-type-btn .status-arrow').text('▾');
                    
                    // Open this panel
                    $panel.slideDown(200);
                    $btn.addClass('active');
                    $btn.find('.status-arrow').text('▴');
                    // Uncheck "Any" when selecting a type
                    $('input[name="status_filter[]"][value="show_all"]').prop('checked', false);
                    $('input[name="status_filter[]"][value="show_all"]').closest('.filter-option-button').removeClass('checked');
                }
            });
            
            // More button toggle
            $(document).on('click', '#toggle_advanced_filters', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $('#toggle_advanced_filters');
                const $panel = $('#advanced_filters');
                
                // Close all popovers when opening More container
                if ($panel.is(':visible')) {
                    $panel.slideUp(200);
                    updateArrow('#toggle_advanced_filters', false);
                } else {
                    // Close ALL filter popovers first, reset their arrows
                    $('.filter-popover').each(function() {
                        const $otherPop = $(this);
                        const $otherBtn = $('.filter-btn').filter(function() {
                            return $(this).attr('id') && $otherPop.attr('id') && 
                                   $otherPop.attr('id').replace('popover_', 'btn_') === $(this).attr('id');
                        });
                        if ($otherBtn.length) {
                            updateArrow('#' + $otherBtn.attr('id'), false);
                        }
                        $otherPop.hide();
                    });
                    // Also close status panels and reset arrows
                    $('.status-options-panel').hide();
                    $('.status-type-btn').removeClass('active');
                    $('.status-type-btn .status-arrow').text('▾');
                    
                    // Update arrow to show open state
                    updateArrow('#toggle_advanced_filters', true);
                    
                    // Position panel - different for mobile vs desktop
                    if (window.innerWidth <= 768) {
                        // Mobile: full width, positioned under button
                        $panel.css({
                            left: '0',
                            right: 'auto',
                            width: '100%',
                            maxWidth: '100%',
                            minWidth: 'auto',
                            position: 'absolute',
                            bottom: 'auto',
                            top: '100%',
                            marginTop: '5px'
                        });
                    } else {
                        // Desktop: positioned below button
                        const btnOffset = $btn.offset();
                        const containerOffset = $('.listings-search-container').offset() || {left:0, top:0};
                        const left = Math.max(10, btnOffset.left - containerOffset.left);
                        $panel.css({
                            left: left + 'px',
                            right: 'auto',
                            width: 'auto',
                            minWidth: '560px',
                            maxWidth: '760px',
                            position: 'absolute',
                            bottom: 'auto',
                            top: '100%'
                        });
                    }
                    $panel.slideDown(200);
                }
            });
            
            // Close advanced filters
            $(document).on('click', '#close_advanced_filters', function(e) {
                e.preventDefault();
                $('#advanced_filters').slideUp(300);
                updateArrow('#toggle_advanced_filters', false);
            });
            
            // Handle button-style checkbox/radio checked state
            $(document).on('change', '.filter-option-button input[type="checkbox"], .filter-option-button input[type="radio"]', function() {
                const $button = $(this).closest('.filter-option-button');
                if ($(this).is(':checked')) {
                    $button.addClass('checked');
                } else {
                    $button.removeClass('checked');
                }
            });
            
            // Initialize checked state on page load
            $('.filter-option-button input:checked').each(function() {
                $(this).closest('.filter-option-button').addClass('checked');
            });
            
            // Auto-apply filters on change (use event delegation for dynamically loaded content)
            $(document).on('change', '.auto-filter', function() {
                // Don't auto-apply if we're initializing from URL (to prevent duplicate calls)
                if (!ListingFilters.isInitializingFromUrl) {
                    ListingFilters.applyFilters();
                }
            });
            
            // Auto-apply filters on checkbox change
            $(document).on('change', '.auto-filter-checkbox', function() {
                // Don't auto-apply if we're initializing from URL (to prevent duplicate calls)
                if (!ListingFilters.isInitializingFromUrl) {
                    ListingFilters.applyFilters();
                }
            });
            
            // Show/hide filter options by selected listing type
            function updateFilterVisibilityByType() {
                const type = ($('#filter_listing_type').val() || '').toLowerCase();
                const showCondo = (type === 'condo' || type === 'condominiums');
                const showRental = (type === 'rental' || type === 'rental-properties');
                // Unit type checkboxes
                $('.unit-type-filters label').each(function(){
                    const val = $(this).find('input').val();
                    const isCondo = val && val.indexOf('condo_') === 0;
                    const isRental = val && val.indexOf('rental_') === 0;
                    if (showCondo && isRental) $(this).hide(); else if (showRental && isCondo) $(this).hide(); else $(this).show();
                });
            }
            
            // Note: togglePopover for #btn_status is already registered above in initAdvancedFilters
            updateFilterVisibilityByType();
            
            // Handle sorting
            $(document).on('change', '#sort_listings', function() {
                ListingFilters.applyFilters();
            });

            // Use the ListingFilters method for refreshSummaries
            const refreshSummaries = () => ListingFilters.refreshSummaries();
            $(document).on('change', 'input[name="bedroom_options[]"], input[name="bathroom_options[]"], input[name="income_limits[]"], input[name="listing_type_filter"], input[name="rental_status[]"], input[name="condo_status[]"], input[name="status_filter[]"], input[name="has_available_units"], input[name="available_unit_type[]"]', function() {
                // Update button state for checkboxes
                if ($(this).is(':checkbox')) {
                    if ($(this).is(':checked')) {
                        $(this).closest('.filter-option-button').addClass('checked');
                    } else {
                        $(this).closest('.filter-option-button').removeClass('checked');
                    }
                }
                
                // If SRO is selected, automatically check "Has Available Units"
                if ($(this).attr('name') === 'available_unit_type[]' && $(this).val() === 'sro') {
                    if ($(this).is(':checked')) {
                        $('input[name="has_available_units"]').prop('checked', true);
                        $('input[name="has_available_units"]').closest('.filter-option-button').addClass('checked');
                    }
                }
                
                refreshSummaries();
            });
            
            // Bedrooms filter: when "Any" is checked, uncheck all others; when a specific option is checked, uncheck "Any"
            $(document).on('change', 'input[name="bedroom_options[]"]', function() {
                const $checkbox = $(this);
                const value = $checkbox.val();
                
                if (value === 'any') {
                    // If "Any" is checked, uncheck all other bedroom options
                    if ($checkbox.is(':checked')) {
                        $('input[name="bedroom_options[]"]').not('[value="any"]').prop('checked', false);
                        $('input[name="bedroom_options[]"]').not('[value="any"]').closest('.filter-option-button').removeClass('checked');
                    }
                } else {
                    // If a specific option is checked, uncheck "Any"
                    if ($checkbox.is(':checked')) {
                        $('input[name="bedroom_options[]"][value="any"]').prop('checked', false);
                        $('input[name="bedroom_options[]"][value="any"]').closest('.filter-option-button').removeClass('checked');
                    } else {
                        // If a specific option is unchecked and no other options are selected, check "Any"
                        const otherChecked = $('input[name="bedroom_options[]"]:checked').not('[value="any"]').length;
                        if (otherChecked === 0) {
                            $('input[name="bedroom_options[]"][value="any"]').prop('checked', true);
                            $('input[name="bedroom_options[]"][value="any"]').closest('.filter-option-button').addClass('checked');
                        }
                    }
                }
                // Update button states
                $('input[name="bedroom_options[]"]:checked').closest('.filter-option-button').addClass('checked');
                $('input[name="bedroom_options[]"]:not(:checked)').closest('.filter-option-button').removeClass('checked');
                ListingFilters.refreshSummaries();
            });
            
            // Bathrooms filter: mirror "Any" behavior
            $(document).on('change', 'input[name="bathroom_options[]"]', function() {
                const $checkbox = $(this);
                const value = $checkbox.val();
                
                if (value === 'any') {
                    if ($checkbox.is(':checked')) {
                        $('input[name="bathroom_options[]"]').not('[value="any"]').prop('checked', false);
                        $('input[name="bathroom_options[]"]').not('[value="any"]').closest('.filter-option-button').removeClass('checked');
                    }
                } else {
                    if ($checkbox.is(':checked')) {
                        $('input[name="bathroom_options[]"][value="any"]').prop('checked', false);
                        $('input[name="bathroom_options[]"][value="any"]').closest('.filter-option-button').removeClass('checked');
                    } else {
                        const otherChecked = $('input[name="bathroom_options[]"]:checked').not('[value="any"]').length;
                        if (otherChecked === 0) {
                            $('input[name="bathroom_options[]"][value="any"]').prop('checked', true);
                            $('input[name="bathroom_options[]"][value="any"]').closest('.filter-option-button').addClass('checked');
                        }
                    }
                }
                $('input[name="bathroom_options[]"]:checked').closest('.filter-option-button').addClass('checked');
                $('input[name="bathroom_options[]"]:not(:checked)').closest('.filter-option-button').removeClass('checked');
                ListingFilters.refreshSummaries();
            });
            
            // Type filter: only one selection at a time (radio behavior)
            $(document).on('change', 'input[name="listing_type_filter"]', function() {
                // Radio buttons already handle single selection
                // Ensure only one is selected
                const selectedValue = $(this).val();
                if (selectedValue !== 'show_all') {
                    // Uncheck "show_all" if another option is selected
                    $('input[name="listing_type_filter"][value="show_all"]').prop('checked', false);
                    $('input[name="listing_type_filter"][value="show_all"]').closest('.filter-option-button').removeClass('checked');
                } else {
                    // If "show_all" is selected, uncheck all others
                    $('input[name="listing_type_filter"]').not('[value="show_all"]').prop('checked', false);
                    $('input[name="listing_type_filter"]').not('[value="show_all"]').closest('.filter-option-button').removeClass('checked');
                }
                // Update button states
                $('input[name="listing_type_filter"]:checked').closest('.filter-option-button').addClass('checked');
                $('input[name="listing_type_filter"]:not(:checked)').closest('.filter-option-button').removeClass('checked');
                ListingFilters.refreshSummaries();
                // Auto-apply filters when type changes (unless initializing from URL)
                if (!ListingFilters.isInitializingFromUrl) {
                    ListingFilters.applyFilters();
                }
            });
            ListingFilters.refreshSummaries();
            
            // Toggle status section visibility when clicking section title
            $(document).on('click', '.status-section-title', function() {
                $(this).next('.status-options-container').slideToggle(200);
            });
            
            // Show status options when a status is selected
            $(document).on('change', 'input[name="rental_status[]"], input[name="condo_status[]"]', function() {
                const $section = $(this).closest('.status-section');
                const $container = $section.find('.status-options-container');
                if ($(this).is(':checked') && $container.is(':hidden')) {
                    $container.slideDown(200);
                }
            });
            
            // Prevent unchecking default filters unless another option is selected
            $(document).on('change', '.default-filter', function() {
                const $this = $(this);
                const $container = $this.closest('.filter-popover-options');
                const $otherChecked = $container.find('input[type="checkbox"]:not(.default-filter):checked');
                
                // If trying to uncheck default and no other options are selected, prevent it
                if (!$this.is(':checked') && $otherChecked.length === 0) {
                    $this.prop('checked', true);
                    return false;
                }
                
                // If default is checked, uncheck others
                if ($this.is(':checked')) {
                    $container.find('input[type="checkbox"]:not(.default-filter)').prop('checked', false);
                }
            });
            
            // When a non-default option is checked, uncheck the default option
            $(document).on('change', '.filter-popover-options input[type="checkbox"]:not(.default-filter)', function() {
                const $container = $(this).closest('.filter-popover-options');
                if ($(this).is(':checked')) {
                    $container.find('.default-filter').prop('checked', false);
                } else {
                    // If unchecking and no other options are selected, check default
                    const $otherChecked = $container.find('input[type="checkbox"]:not(.default-filter):checked');
                    if ($otherChecked.length === 0) {
                        $container.find('.default-filter').prop('checked', true);
                    }
                }
            });
        },
        
        // Prefill filters from URL query params and apply
        initFromUrl: function() {
            try {
                // Set flag to prevent auto-filter handlers from triggering during initialization
                this.isInitializingFromUrl = true;
                
                const params = new URLSearchParams(window.location.search);
                let changed = false;
                
                // First, clear all filter states
                $('#filter_listing_type, #filter_status, #filter_location, #search_location_input').val('');
                $('input[name="bedroom_options[]"], input[name="bathroom_options[]"], input[name="unit_type[]"], input[name="listing_type_filter"], input[name="rental_status[]"], input[name="condo_status[]"], input[name="status_filter[]"], input[name="has_available_units"], input[name="available_unit_type[]"]').prop('checked', false);
                $('.filter-option-button').removeClass('checked');
                
                // Set defaults
                $('input[name="bedroom_options[]"][value="any"]').prop('checked', true);
                $('input[name="bedroom_options[]"][value="any"]').closest('.filter-option-button').addClass('checked');
                $('input[name="listing_type_filter"][value="show_all"]').prop('checked', true);
                $('input[name="listing_type_filter"][value="show_all"]').closest('.filter-option-button').addClass('checked');
                $('input[name="status_filter[]"][value="show_all"]').prop('checked', true);
                $('input[name="status_filter[]"][value="show_all"]').closest('.filter-option-button').addClass('checked');
                
                // Now apply URL parameters if they exist
                // Type / Status / City
                if (params.get('type')) { 
                    const typeParam = params.get('type').toLowerCase();
                    $('#filter_listing_type').val(typeParam); 
                    
                    // Find matching radio button - handle both simple values (condo/rental) and taxonomy slugs
                    let typeVal = null;
                    $('input[name="listing_type_filter"]').each(function() {
                        const val = $(this).val().toLowerCase();
                        const label = $(this).closest('label').text().toLowerCase();
                        
                        // Check if this matches the type parameter
                        if (typeParam === 'condo' && (val.includes('condo') || val.includes('condominium') || label.includes('condo'))) {
                            typeVal = $(this).val();
                            return false; // break
                        } else if (typeParam === 'rental' && (val.includes('rental') || label.includes('rental'))) {
                            typeVal = $(this).val();
                            return false; // break
                        } else if (val === typeParam) {
                            typeVal = $(this).val();
                            return false; // break
                        }
                    });
                    
                    if (typeVal) {
                        $('input[name="listing_type_filter"]').prop('checked', false);
                        $('input[name="listing_type_filter"][value="'+typeVal+'"]').prop('checked', true);
                        $('input[name="listing_type_filter"]').closest('.filter-option-button').removeClass('checked');
                        $('input[name="listing_type_filter"][value="'+typeVal+'"]').closest('.filter-option-button').addClass('checked');
                        changed = true;
                    }
                }
                if (params.get('status')) { $('#filter_status').val(params.get('status')); changed = true; }
                if (params.get('city')) { 
                    const city = params.get('city');
                    $('#filter_location').val(city); 
                    $('#search_location_input').val(city);
                    changed = true; 
                    // Always preserve lat/lng from URL if present (even if map isn't ready yet)
                    const lat = parseFloat(params.get('lat'));
                    const lng = parseFloat(params.get('lng'));
                    if (!isNaN(lat) && !isNaN(lng)) {
                        // Store coordinates immediately so they're available when applyFilters() runs
                        this.lastSearchCoords = { lat: lat, lng: lng };
                        // Center the map if it's ready, otherwise it will be centered when map initializes
                        if (this.map) {
                            this.map.setView([lat, lng], 12);
                        }
                    } else {
                        // Only geocode if coordinates weren't in URL
                        this.searchLocation(city);
                    }
                }
                // Zip code from URL
                if (params.get('zip')) {
                    const zip = params.get('zip');
                    $('#search_location_input').val(zip);
                    changed = true;
                    // Center the map from URL lat/lng if present; else geocode
                    const lat = parseFloat(params.get('lat'));
                    const lng = parseFloat(params.get('lng'));
                    if (!isNaN(lat) && !isNaN(lng) && this.map) {
                        this.lastSearchCoords = { lat: lat, lng: lng };
                        this.map.setView([lat, lng], 12);
                    } else {
                        // Geocode the zip code
                        this.searchLocation(zip, true);
                    }
                }
                // Beds/Baths/Avail/Unit Types (multi)
                const beds = params.getAll('beds') || [];
                if (beds.length > 0) {
                    $('input[name="bedroom_options[]"][value="any"]').prop('checked', false);
                    $('input[name="bedroom_options[]"][value="any"]').closest('.filter-option-button').removeClass('checked');
                    beds.forEach(v => { 
                        $('input[name="bedroom_options[]"][value="'+v+'"]').prop('checked', true);
                        $('input[name="bedroom_options[]"][value="'+v+'"]').closest('.filter-option-button').addClass('checked');
                        changed = true; 
                    });
                }
                const baths = params.getAll('baths') || [];
                if (baths.length > 0) {
                    $('input[name="bathroom_options[]"][value="any"]').prop('checked', false);
                    $('input[name="bathroom_options[]"][value="any"]').closest('.filter-option-button').removeClass('checked');
                    baths.forEach(v => { 
                        $('input[name="bathroom_options[]"][value="'+v+'"]').prop('checked', true);
                        $('input[name="bathroom_options[]"][value="'+v+'"]').closest('.filter-option-button').addClass('checked');
                        changed = true; 
                    });
                }
                const incomeLimits = params.getAll('income_limits') || [];
                if (incomeLimits.length > 0) {
                    incomeLimits.forEach(v => { 
                        $('input[name="income_limits[]"][value="'+v+'"]').prop('checked', true);
                        $('input[name="income_limits[]"][value="'+v+'"]').closest('.filter-option-button').addClass('checked');
                        changed = true; 
                    });
                }
                (params.getAll('unit_type') || []).forEach(v => { 
                    $('input[name="unit_type[]"][value="'+v+'"]').prop('checked', true);
                    changed = true; 
                });
                
                // Has Available Units filter
                if (params.get('has_units') === '1') {
                    $('input[name="has_available_units"]').prop('checked', true);
                    $('input[name="has_available_units"]').closest('.filter-option-button').addClass('checked');
                    changed = true;
                }
                
                // Available Unit Type filter
                (params.getAll('available_unit_type') || []).forEach(v => {
                    $('input[name="available_unit_type[]"][value="'+v+'"]').prop('checked', true);
                    $('input[name="available_unit_type[]"][value="'+v+'"]').closest('.filter-option-button').addClass('checked');
                    changed = true;
                });
                
                // Update summaries
                this.refreshSummaries();
                
                // Clear the flag before calling applyFilters
                this.isInitializingFromUrl = false;
                
                if (changed) {
                    // Remember this URL for Back to Results
                    sessionStorage.setItem('ml_filters_url', window.location.pathname + window.location.search);
                    // Wait for map to initialize before applying filters
                    // This ensures the map is ready when updateMapMarkers() is called
                    const self = this;
                    const tryApplyFilters = (attempts) => {
                        attempts = attempts || 0;
                        if (self.map && self.markerClusterGroup) {
                            // Map is ready, apply filters immediately
                            self.applyFilters();
                        } else if (attempts < 10) {
                            // Map not ready yet, retry after 100ms (max 1 second total)
                            setTimeout(() => {
                                tryApplyFilters(attempts + 1);
                            }, 100);
                        } else {
                            // Map still not ready after 1 second, apply filters anyway (updateMapMarkers will retry)
                            self.applyFilters();
                        }
                    };
                    // Start checking after a short delay to let map init start
                    setTimeout(() => {
                        tryApplyFilters(0);
                    }, 200);
                } else {
                    // No URL params, clear session storage and ensure filters are reset
                    sessionStorage.removeItem('ml_filters_url');
                    // Update summaries to show default labels
                    this.refreshSummaries();
                    // Still call applyFilters to ensure proper sorting is applied (natural sort for property name)
                    // This ensures the initial page load uses the same sorting logic as AJAX requests
                    // Use a small delay to ensure the map is initialized first
                    setTimeout(() => {
                        this.applyFilters();
                    }, 100);
                }
            } catch (e) {
                // Make sure to clear flag even if there's an error
                this.isInitializingFromUrl = false;
            }
        },
        
        applyFilters: function(e, page, paginationOnly) {
            if (e) e.preventDefault();
            
            const bedroomMulti = $('input[name="bedroom_options[]"]:checked').map(function(){return $(this).val();}).get();
            const bathroomMulti = $('input[name="bathroom_options[]"]:checked').map(function(){return $(this).val();}).get();
            const incomeLevels = $('input[name="income_levels[]"]:checked').map(function(){return $(this).val();}).get();
            const incomeLimits = $('input[name="income_limits[]"]:checked').map(function(){return $(this).val();}).get();
            const searchInputEl = $('#search_location_input');
            const resolvedSearchLocation = searchInputEl.data('searchOverride') || searchInputEl.val() || '';

            const filters = {
                listing_type: (function() {
                    const typeFilter = $('input[name="listing_type_filter"]:checked').val();
                    if (typeFilter && typeFilter !== 'show_all') {
                        return typeFilter;
                    }
                    return $('#filter_listing_type').val() || '';
                })(),
                status: $('#filter_status').val() || '',
                // Only set location if search_location is empty (to avoid duplicates)
                location: resolvedSearchLocation ? '' : ($('#filter_location').val() || ''),
                search_location: resolvedSearchLocation,
                bedrooms: $('#filter_bedrooms').val() || '',
                bathrooms: $('#filter_bathrooms').val() || '',
                bedrooms_multi: bedroomMulti,
                bathrooms_multi: bathroomMulti,
                income_levels: incomeLevels.length ? incomeLevels : ( $('#filter_income_level').val() ? [$('#filter_income_level').val()] : [] ),
                income_limits: incomeLimits,
                condo_status: $('input[name="condo_status[]"]:checked').map(function(){return $(this).val();}).get(),
                rental_status: $('input[name="rental_status[]"]:checked').map(function(){return $(this).val();}).get(),
                status_filter: $('input[name="status_filter[]"]:checked').map(function(){return $(this).val();}).get(),
                unit_type: $('input[name="unit_type[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                amenities: $('input[name="amenities[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                property_accessibility: $('input[name="property_accessibility[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                concessions: $('input[name="concessions[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                just_listed: $('input[name="just_listed"]:checked').val() || '',
                has_available_units: $('input[name="has_available_units"]:checked').val() || '',
                available_unit_type: $('input[name="available_unit_type[]"]:checked').map(function(){return $(this).val();}).get(),
                sort: $('#sort_listings').val() || 'property_name',
                page: page || 1,
                nonce: maloneyListings.nonce
            };
            
            // Include active area search if it exists
            if (this.activeAreaSearch && !paginationOnly) {
                filters.map_bounds = this.activeAreaSearch;
            }
            // DEBUG: Log filters being sent to backend
            console.log('🔍 [DEBUG] applyFilters - Filters being sent:', filters);
            console.log('🔍 [DEBUG] search_location:', filters.search_location);
            console.log('🔍 [DEBUG] location:', filters.location);
            console.log('🔍 [DEBUG] resolvedSearchLocation:', resolvedSearchLocation);
            
            // Persist filters in URL + session for back-to-results
            try {
                const params = new URLSearchParams();
                if (filters.listing_type) params.set('type', filters.listing_type);
                // Don't set city from filters.location if search_location exists (to avoid duplicates)
                if (filters.location && !filters.search_location) {
                    params.set('city', filters.location);
                }
                if (filters.status) params.set('status', filters.status);
                // Only add non-default values to URL (exclude "any" and "show_all")
                (filters.bedrooms_multi||[]).filter(v => v !== 'any' && v !== 'show_all').forEach(v=>params.append('beds', v));
                (filters.bathrooms_multi||[]).filter(v => v !== 'show_all' && v !== 'any').forEach(v=>params.append('baths', v));
                (filters.income_limits||[]).forEach(v=>params.append('income_limits', v));
                (filters.unit_type||[]).forEach(v=>params.append('unit_type', v));
                // Has Available Units
                if (filters.has_available_units === '1') {
                    params.set('has_units', '1');
                }
                // Available Unit Type
                (filters.available_unit_type||[]).forEach(v=>params.append('available_unit_type', v));
            // Add search_location to URL if present (takes priority over location)
            if (filters.search_location) {
                const searchLoc = filters.search_location.trim();
                const isZip = /^\d{5}(-\d{4})?$/.test(searchLoc);
                if (isZip) {
                    params.set('zip', searchLoc);
                    // Add coordinates to AJAX request for zip code searches (for nearby listings fallback)
                    if (ListingFilters.lastSearchCoords && !isNaN(ListingFilters.lastSearchCoords.lat) && !isNaN(ListingFilters.lastSearchCoords.lng)) {
                        filters.search_location_lat = ListingFilters.lastSearchCoords.lat;
                        filters.search_location_lng = ListingFilters.lastSearchCoords.lng;
                    }
                } else {
                    params.set('city', searchLoc);
                    // Add coordinates to AJAX request for city searches (for nearby listings fallback)
                    if (ListingFilters.lastSearchCoords && !isNaN(ListingFilters.lastSearchCoords.lat) && !isNaN(ListingFilters.lastSearchCoords.lng)) {
                        // Pass coordinates both as search_location_lat/lng AND as lat/lng for city parameter
                        filters.search_location_lat = ListingFilters.lastSearchCoords.lat;
                        filters.search_location_lng = ListingFilters.lastSearchCoords.lng;
                        // Also pass as lat/lng for city parameter compatibility (homepage search)
                        filters.lat = ListingFilters.lastSearchCoords.lat;
                        filters.lng = ListingFilters.lastSearchCoords.lng;
                    }
                }
            }
            // Add coordinates to URL if available
            if (ListingFilters.lastSearchCoords && !isNaN(ListingFilters.lastSearchCoords.lat) && !isNaN(ListingFilters.lastSearchCoords.lng)) {
                params.set('lat', ListingFilters.lastSearchCoords.lat);
                params.set('lng', ListingFilters.lastSearchCoords.lng);
            }
                const qs = params.toString();
                const url = qs ? (location.pathname + '?' + qs) : location.pathname;
                history.replaceState(null, '', url);
                sessionStorage.setItem('ml_filters_url', url);
            } catch(e) {}

            const currentPage = filters.page || 1;
            
            $.ajax({
                url: maloneyListings.ajaxUrl,
                type: 'POST',
                data: Object.assign({ action: 'filter_listings' }, filters),
                beforeSend: function() {
                    $('#listings-grid').html('<div class="loading">Loading...</div>');
                },
                    success: function(response) {
                        if (response.success) {
                            $('#listings-grid').html(response.data.html);
                            
                            // Only update map if NOT pagination-only
                            // Map should always show ALL listings with coordinates, not just current page
                            if (!paginationOnly) {
                                // Update map markers with filtered results
                                // This will automatically zoom to the most clustered listings
                                ListingFilters.updateMapMarkers(response.data.listings || []);
                                
                                // Don't override the cluster-based zoom with search location
                                // The updateMapMarkers function will handle zooming to the densest cluster
                            }
                            // Keep all filter options enabled (no disabling)
                            // Update inline facet counts for Availability + Unit Type
                            try {
                                if (response.data.facet_counts) {
                                    const fc = response.data.facet_counts;
                                    // Unit type
                                    const mapUnit = {
                                        'rental_first_come': 'Rental – First Come',
                                        'rental_lottery': 'Rental – Lottery',
                                        'condo_lottery': 'Homeownership - Lottery',
                                        'condo_resale': 'Homeownership - Resale'
                                    };
                                    $('.unit-type-filters label').each(function(){
                                        const val = $(this).find('input').val();
                                        const count = fc.unit_type && fc.unit_type[val] !== undefined ? fc.unit_type[val] : null;
                                        if (count !== null) {
                                            const base = mapUnit[val] || $(this).text().trim();
                                            $(this).contents().filter(function(){ return this.nodeType===3; }).remove();
                                            $(this).append(' ' + base + ' (' + count + ')');
                                        }
                                    });
                                }
                            } catch (e) { /* ignore */ }
                            // Update listings count with proper singular/plural
                            if (response.data.found_posts !== undefined) {
                                const count = response.data.found_posts;
                                $('#listings-count').text(count + ' ' + (count == 1 ? 'Result' : 'Results'));
                            }
                            // Update page info
                            if (response.data.max_pages !== undefined) {
                                const maxPages = response.data.max_pages;
                                if (response.data.found_posts > 0 && maxPages > 0) {
                                    $('#page-info').text('Page ' + currentPage + ' of ' + maxPages);
                                } else {
                                    $('#page-info').text('');
                                }
                            }
                            // Pagination is now included in the HTML response, so it should already be in the DOM
                            // But we can still update it separately if needed for backwards compatibility
                            // The pagination container should already exist in the updated HTML
                            if ($('#listings-pagination').length === 0) {
                                // If pagination container doesn't exist, create it
                                if (response.data.found_posts > 0) {
                                    var paginationHtml = response.data.pagination || '<span class="page-numbers current">1</span>';
                                    $('#listings-grid').append('<div class="listings-pagination" id="listings-pagination">' + paginationHtml + '</div>');
                                }
                            } else if (response.data.pagination !== undefined && response.data.pagination !== '') {
                                // Update existing pagination
                                $('#listings-pagination').html(response.data.pagination).show();
                            }
                            // If "Has Units" filter is active and results are rentals, automatically change Type filter to "Rental"
                            const hasUnitsFilter = $('input[name="has_available_units"]:checked').length > 0;
                            if (hasUnitsFilter && response.data.listings && response.data.listings.length > 0) {
                                // Check if all listings are rentals
                                const allRentals = response.data.listings.every(function(listing) {
                                    return listing.type && listing.type.toLowerCase().indexOf('rental') !== -1;
                                });
                                
                                if (allRentals) {
                                    // Change Type filter to "Rental"
                                    $('#filter_listing_type').val('rental');
                                    // Update the filter button text
                                    $('#btn_listing_type').text('Rental');
                                }
                            }
                            
                            // Render active filters chips
                            ListingFilters.renderActiveFilters(filters);
                        }
                    },
                error: function() {
                    $('#listings-grid').html('<div class="error">Error loading listings. Please try again.</div>');
                }
            });
        },
        
        renderActiveFilters: function(filters) {
            // DEBUG: Log filters to console
            console.log('🔍 [DEBUG] renderActiveFilters - Filters object:', filters);
            console.log('🔍 [DEBUG] search_location:', filters.search_location);
            console.log('🔍 [DEBUG] location:', filters.location);
            
            const chips = [];
            const push = (label, remove) => chips.push('<span class="chip" data-remove="'+remove+'">'+label+' <span class="x">×</span></span>');
            
            // Only show non-default filter values
            // Bedrooms - exclude "any" and "show_all"
            (filters.bedrooms_multi||[]).filter(v => v !== 'any' && v !== 'show_all').forEach(v=>push((v==='0'?'Studio':(v==='4+'?'4+BR':v+'BR')),'beds:'+v));
            // Bathrooms - exclude defaults
            (filters.bathrooms_multi||[]).filter(v => v !== 'show_all' && v !== 'any').forEach(v=>push(v + ' Bath','baths:'+v));
            // Income Limits
            (filters.income_limits||[]).forEach(v=>push('Income Limit: ' + v,'income_limits:'+v));
            // Search location (zip code or city from search input) - takes priority
            // ONLY show search_location, never show location to avoid duplicates
            if (filters.search_location) {
                const searchLoc = filters.search_location.trim();
                // Check if it's a zip code
                const isZip = /^\d{5}(-\d{4})?$/.test(searchLoc);
                if (isZip) {
                    push('Zip Code: ' + searchLoc, 'search_location');
                } else {
                    push('Location: ' + searchLoc, 'search_location');
                }
            } else if (filters.location && !filters.search_location) {
                // Only show location filter if search_location is completely not set (to avoid duplicates)
                push(filters.location,'location');
            }
            // Listing Type - exclude empty (Show All)
            if (filters.listing_type && filters.listing_type !== 'show_all') {
                const typeLabel = filters.listing_type.toLowerCase().indexOf('rental') >= 0 ? 'Rental' : 
                                 filters.listing_type.toLowerCase().indexOf('condo') >= 0 ? 'Condo' : filters.listing_type;
                push(typeLabel,'type');
            }
            // Availability filters - exclude "show_all"
            const rentalStatuses = (filters.rental_status||[]).filter(v => v !== 'show_all' && v !== 'any');
            const condoStatuses = (filters.condo_status||[]).filter(v => v !== 'show_all' && v !== 'any');
            if (rentalStatuses.length > 0 || condoStatuses.length > 0) {
                // Show availability filter chips
                rentalStatuses.forEach(v => {
                    const labels = {'1':'Active Rental','2':'Open Lottery','3':'Closed Lottery','4':'Inactive Rental','6':'Upcoming Lottery'};
                    push('Rental: ' + (labels[v] || v), 'rental_status:'+v);
                });
                condoStatuses.forEach(v => {
                    const labels = {'1':'FCFS Condo Sales','2':'Active Condo Lottery','3':'Closed Condo Lottery','4':'Inactive Condo Property','5':'Upcoming Condo'};
                    push('Condo: ' + (labels[v] || v), 'condo_status:'+v);
                });
            }
            if (filters.status) push(filters.status,'status');
            // Has Available Units filter
            if (filters.has_available_units === '1') {
                push('Has Available Units', 'has_units');
            }
            // Available Unit Type filters
            (filters.available_unit_type||[]).forEach(v => {
                const labels = {
                    'studio': 'Studio',
                    '1br': '1BR',
                    '1-bedroom': '1BR',
                    'one bedroom': '1BR',
                    '2br': '2BR',
                    '2-bedroom': '2BR',
                    'two bedroom': '2BR',
                    '3br': '3BR',
                    '3-bedroom': '3BR',
                    'three bedroom': '3BR',
                    '4br': '4+BR',
                    '4+br': '4+BR',
                    '4-bedroom': '4+BR',
                    'four bedroom': '4+BR'
                };
                const label = labels[v.toLowerCase()] || v;
                push('Unit: ' + label, 'available_unit_type:'+v);
            });
            $('#active-filters').html(chips.join(' '));
            
            // Remove handler - use event delegation and handle clicks on chip or X
            $('#active-filters').off('click', '.chip').on('click', '.chip', function(e){
                e.preventDefault();
                e.stopPropagation();
                const $chip = $(this);
                const r = $chip.data('remove');
                if (!r) return;
                
                const [k,v] = String(r).split(':');
                
                // Clear the filter input
                if (k==='beds') {
                    $('input[name="bedroom_options[]"][value="'+v+'"]').prop('checked', false);
                    $('input[name="bedroom_options[]"][value="'+v+'"]').closest('.filter-option-button').removeClass('checked');
                    // If no bedrooms selected, check "Any"
                    if ($('input[name="bedroom_options[]"]:checked').length === 0) {
                        $('input[name="bedroom_options[]"][value="any"]').prop('checked', true);
                        $('input[name="bedroom_options[]"][value="any"]').closest('.filter-option-button').addClass('checked');
                    }
                }
                if (k==='baths') {
                    $('input[name="bathroom_options[]"][value="'+v+'"]').prop('checked', false);
                    $('input[name="bathroom_options[]"][value="'+v+'"]').closest('.filter-option-button').removeClass('checked');
                    if ($('input[name="bathroom_options[]"]:checked').length === 0) {
                        $('input[name="bathroom_options[]"][value="any"]').prop('checked', true);
                        $('input[name="bathroom_options[]"][value="any"]').closest('.filter-option-button').addClass('checked');
                    }
                }
                if (k==='income_limits') {
                    $('input[name="income_limits[]"][value="'+v+'"]').prop('checked', false);
                    $('input[name="income_limits[]"][value="'+v+'"]').closest('.filter-option-button').removeClass('checked');
                }
                if (k==='location') {
                    $('#filter_location').val('');
                    $('#search_location_input').val('');
                }
                if (k==='search_location') {
                    $('#search_location_input').val('').removeData('searchOverride');
                    // Clear search coordinates
                    ListingFilters.lastSearchCoords = null;
                }
                if (k==='type') {
                    $('#filter_listing_type').val('');
                    // Also clear the radio button selection and set to "Any"
                    $('input[name="listing_type_filter"]').prop('checked', false);
                    $('input[name="listing_type_filter"][value="show_all"]').prop('checked', true);
                    // Update button states
                    $('input[name="listing_type_filter"]').closest('.filter-option-button').removeClass('checked');
                    $('input[name="listing_type_filter"][value="show_all"]').closest('.filter-option-button').addClass('checked');
                }
                if (k==='status') $('#filter_status').val('');
                if (k==='rental_status') {
                    $('input[name="rental_status[]"][value="'+v+'"]').prop('checked', false);
                    // Update button state
                    $('input[name="rental_status[]"][value="'+v+'"]').closest('.filter-option-button').removeClass('checked');
                }
                if (k==='condo_status') {
                    $('input[name="condo_status[]"][value="'+v+'"]').prop('checked', false);
                    // Update button state
                    $('input[name="condo_status[]"][value="'+v+'"]').closest('.filter-option-button').removeClass('checked');
                }
                // If no status filters selected, check "Show All"
                if ((k==='rental_status' || k==='condo_status') && 
                    $('input[name="rental_status[]"]:checked').length === 0 && 
                    $('input[name="condo_status[]"]:checked').length === 0) {
                    $('input[name="status_filter[]"][value="show_all"]').prop('checked', true);
                    $('input[name="status_filter[]"][value="show_all"]').closest('.filter-option-button').addClass('checked');
                    // Hide status panels
                    $('.status-options-panel').hide();
                    $('.status-type-btn').removeClass('active');
                    // Uncheck "Any" button state for all status options
                    $('input[name="rental_status[]"], input[name="condo_status[]"]').closest('.filter-option-button').removeClass('checked');
                }
                if (k==='has_units') {
                    $('input[name="has_available_units"]').prop('checked', false);
                    $('input[name="has_available_units"]').closest('.filter-option-button').removeClass('checked');
                }
                if (k==='available_unit_type') {
                    $('input[name="available_unit_type[]"][value="'+v+'"]').prop('checked', false);
                    $('input[name="available_unit_type[]"][value="'+v+'"]').closest('.filter-option-button').removeClass('checked');
                }
                
                // Update summaries immediately
                ListingFilters.refreshSummaries();
                
                // Re-apply filters to update results and active filters
                ListingFilters.applyFilters();
            });
        },
        
        removeAreaSearch: function(e) {
            if (e) e.preventDefault();
            e.stopPropagation();
            
            // Clear area search
            this.activeAreaSearch = null;
            $('#active-filters').html('');
            
            // Reload all listings without area filter
            this.applyFilters(e, 1, false);
        },
        
        clearFilters: function(e) {
            if (e) e.preventDefault();
            
            // Clear all filter inputs
            $('#filter_listing_type, #filter_status, #filter_location, #filter_bedrooms, #filter_bathrooms, #filter_income_level, #search_location_input').val('');
            $('#search_location_input').removeData('searchOverride');
            $('input[name="amenities[]"], input[name="unit_type[]"], input[name="concessions"], input[name="just_listed"], input[name="bedroom_options[]"], input[name="bathroom_options[]"], input[name="income_levels[]"], input[name="has_available_units"], input[name="available_unit_type[]"], input[name="listing_type_filter"], input[name="rental_status[]"], input[name="condo_status[]"], input[name="status_filter[]"]').prop('checked', false);
            $('input[name="bedroom_options[]"][value="any"]').prop('checked', true).closest('.filter-option-button').addClass('checked');
            $('input[name="bathroom_options[]"][value="any"]').prop('checked', true).closest('.filter-option-button').addClass('checked');
            $('input[name="listing_type_filter"][value="show_all"]').prop('checked', true).closest('.filter-option-button').addClass('checked');
            $('input[name="status_filter[]"][value="show_all"]').prop('checked', true).closest('.filter-option-button').addClass('checked');
            
            // Clear area search
            this.activeAreaSearch = null;
            $('#active-filters').html('');
            
            // Clear session storage
            sessionStorage.removeItem('ml_filters_url');
            
            // Clear last search coordinates so map doesn't center on old search
            this.lastSearchCoords = null;
            
            // Reload the page to ensure all URL parameters are cleared (including type and has_units)
            // This ensures a clean state without any lingering parameters
            window.location.href = location.pathname;
        },
        
        searchVisibleArea: function(e) {
            if (e) e.preventDefault();
            
            if (!this.map) {
                return;
            }
            
            // Get current map bounds (visible area)
            const bounds = this.map.getBounds();
            const northEast = bounds.getNorthEast();
            const southWest = bounds.getSouthWest();
            
            // Update button to show it's active
            const $btn = $('#search-visible-area-btn');
            $btn.prop('disabled', true).text('Searching...');
            
            // Get all listings and filter by visible bounds
            const allListings = window.maloneyListingsData || [];
            const visibleListings = allListings.filter(function(listing) {
                const lat = parseFloat(listing.lat);
                const lng = parseFloat(listing.lng);
                
                // Check if listing is within visible bounds
                return lat >= southWest.lat && 
                       lat <= northEast.lat && 
                       lng >= southWest.lng && 
                       lng <= northEast.lng;
            });
            
            // Update map markers to show only visible listings (highlight them)
            // But keep all markers visible, just update the cards
            // Actually, let's just filter the cards, keep all markers on map
            
            // Build filter data for AJAX call
            const filters = {
                listing_type: '',
                status: '',
                location: '',
                search_location: '',
                bedrooms: '',
                bathrooms: '',
                bedrooms_multi: [],
                bathrooms_multi: [],
                income_levels: [],
                unit_type: [],
                amenities: [],
                concessions: '',
                just_listed: '',
                map_bounds: {
                    north: northEast.lat,
                    east: northEast.lng,
                    south: southWest.lat,
                    west: southWest.lng
                },
                page: 1,
                nonce: maloneyListings.nonce
            };
            
            // Make AJAX call to filter listings by map bounds
            $.ajax({
                url: maloneyListings.ajaxUrl,
                type: 'POST',
                data: Object.assign({ action: 'filter_listings' }, filters),
                beforeSend: function() {
                    $('#listings-grid').html('<div class="loading">Loading listings in visible area...</div>');
                },
                success: function(response) {
                    if (response.success) {
                        const count = response.data.found_posts || 0;
                        
                        // If no listings in visible area, expand search to nearby
                        if (count === 0) {
                            // Expand bounds by 50% and search again
                            const expandedBounds = {
                                north: northEast.lat + ((northEast.lat - southWest.lat) * 0.5),
                                east: northEast.lng + ((northEast.lng - southWest.lng) * 0.5),
                                south: southWest.lat - ((northEast.lat - southWest.lat) * 0.5),
                                west: southWest.lng - ((northEast.lng - southWest.lng) * 0.5)
                            };
                            
                            // Retry with expanded bounds
                            filters.map_bounds = expandedBounds;
                            $.ajax({
                                url: maloneyListings.ajaxUrl,
                                type: 'POST',
                                data: Object.assign({ action: 'filter_listings' }, filters),
                                success: function(expandedResponse) {
                                    if (expandedResponse.success) {
                                        const expandedCount = expandedResponse.data.found_posts || 0;
                                        $('#listings-grid').html(expandedResponse.data.html);
                                        
                                        if (expandedResponse.data.pagination !== undefined) {
                                            $('#listings-pagination').html(expandedResponse.data.pagination);
                                        } else {
                                            $('#listings-pagination').html('');
                                        }
                                        
                                        $('#listings-count').text(expandedCount + ' ' + (expandedCount === 1 ? 'Result' : 'Results'));
                                        
                                        const maxPages = expandedResponse.data.max_pages || 1;
                                        if (expandedCount > 0 && maxPages > 1) {
                                            $('#page-info').text('Page 1 of ' + maxPages);
                                        } else {
                                            $('#page-info').text('');
                                        }
                                        
                                        $('#active-filters').html('<span class="active-filter-tag">📍 Area Search (' + expandedCount + ' listings) <button type="button" class="remove-area-search" title="Remove area search filter" style="background: none; border: none; color: white; cursor: pointer; margin-left: 8px; font-size: 14px; font-weight: bold;">×</button></span>');
                                        
                                        // Store active area search bounds for removal (expanded bounds)
                                        ListingFilters.activeAreaSearch = expandedBounds;
                                    } else {
                                        $('#listings-grid').html('<div class="no-listings-found"><p>No listings found in this area.</p><a href="#" class="reset-filters-link" id="reset-filters-link">Reset Filters</a></div>');
                                        $('#active-filters').html('');
                                    }
                                    $btn.prop('disabled', false).text('🔍 Search This Area');
                                },
                                error: function() {
                                    $('#listings-grid').html('<div class="no-listings-found"><p>Error loading listings. Please try again.</p><a href="#" class="reset-filters-link" id="reset-filters-link">Reset Filters</a></div>');
                                    $btn.prop('disabled', false).text('🔍 Search This Area');
                                }
                            });
                            return;
                        }
                        
                        // Normal response with results
                        $('#listings-grid').html(response.data.html);
                        
                        // Update pagination
                        if (response.data.pagination !== undefined) {
                            $('#listings-pagination').html(response.data.pagination);
                        } else {
                            $('#listings-pagination').html('');
                        }
                        
                        // Update results count
                        $('#listings-count').text(count + ' ' + (count === 1 ? 'Result' : 'Results'));
                        
                        // Update page info
                        const maxPages = response.data.max_pages || 1;
                        if (count > 0 && maxPages > 1) {
                            $('#page-info').text('Page 1 of ' + maxPages);
                        } else {
                            $('#page-info').text('');
                        }
                        
                        // Show active filter indicator with remove button
                        $('#active-filters').html('<span class="active-filter-tag">📍 Area Search (' + count + ' listings) <button type="button" class="remove-area-search" title="Remove area search filter" style="background: none; border: none; color: white; cursor: pointer; margin-left: 8px; font-size: 14px; font-weight: bold;">×</button></span>');
                        
                        // Store active area search bounds for removal
                        ListingFilters.activeAreaSearch = {
                            north: northEast.lat,
                            east: northEast.lng,
                            south: southWest.lat,
                            west: southWest.lng
                        };
                    } else {
                        $('#listings-grid').html('<div class="no-listings-found"><p>Error loading listings. Please try again.</p><a href="#" class="reset-filters-link" id="reset-filters-link">Reset Filters</a></div>');
                    }
                    
                    $btn.prop('disabled', false).text('🔍 Search This Area');
                },
                error: function() {
                    $('#listings-grid').html('<div class="no-listings-found"><p>Error loading listings. Please try again.</p><a href="#" class="reset-filters-link" id="reset-filters-link">Reset Filters</a></div>');
                    $btn.prop('disabled', false).text('🔍 Search This Area');
                }
            });
        },
        
        initLocationAutocomplete: function() {
            const input = $('#search_location_input');
            const autocomplete = $('#location-autocomplete');
            const listingData = Array.isArray(window.maloneyListingsData) ? window.maloneyListingsData : [];
            const zipCodes = Array.isArray(window.maloneyZipCodes) ? window.maloneyZipCodes : [];
            const localEntries = [];
            const entryKeys = new Set();
            const remoteZipCache = {};
            let remoteZipRequest = null;
            
            const escapeHtml = function(str) {
                if (!str) return '';
                return str.replace(/&/g, '&amp;')
                          .replace(/</g, '&lt;')
                          .replace(/>/g, '&gt;')
                          .replace(/"/g, '&quot;')
                          .replace(/'/g, '&#039;');
            };
            
            const addEntry = function(key, entry) {
                if (!entry || !entry.label) return;
                const normalizedKey = key.toLowerCase();
                if (entryKeys.has(normalizedKey)) return;
                entryKeys.add(normalizedKey);
                const tokens = [];
                ['label', 'value', 'city', 'zip', 'searchValue'].forEach(field => {
                    if (entry[field]) {
                        tokens.push(entry[field].toString().toLowerCase());
                    }
                });
                entry.searchTokens = tokens;
                localEntries.push(entry);
            };
            
            listingData.forEach(listing => {
                const lat = listing.lat ? parseFloat(listing.lat) : NaN;
                const lng = listing.lng ? parseFloat(listing.lng) : NaN;
                const coords = (!isNaN(lat) && !isNaN(lng)) ? { lat, lng } : {};
                const city = (listing.city || '').trim();
                const cityLabel = (listing.city_label || '').trim();
                const stateLabel = (listing.state || 'MA').trim() || 'MA';
                const zip = (listing.zip || '').trim();
                const address = (listing.address || '').trim();
                
                if (city) {
                    addEntry(`city:${city.toLowerCase()}`, {
                        type: 'city',
                        value: city,
                        searchValue: city,
                        city,
                        label: `${city}, ${stateLabel}`,
                        zip,
                        lat: coords.lat,
                        lng: coords.lng
                    });
                }
                if (cityLabel && cityLabel.toLowerCase() !== city.toLowerCase()) {
                    addEntry(`citylabel:${cityLabel.toLowerCase()}`, {
                        type: 'city',
                        value: cityLabel,
                        searchValue: cityLabel,
                        city: cityLabel,
                        label: cityLabel,
                        zip,
                        lat: coords.lat,
                        lng: coords.lng
                    });
                }
                if (zip) {
                    let zipLabel = zip;
                    if (city) {
                        zipLabel += `, ${city}, ${stateLabel}`;
                    }
                    addEntry(`zip:${zip}`, {
                        type: 'zip',
                        value: zip,
                        searchValue: zip,
                        city,
                        label: zipLabel,
                        zip,
                        lat: coords.lat,
                        lng: coords.lng
                    });
                }
                if (address) {
                    addEntry(`addr:${address.toLowerCase()}`, {
                        type: 'address',
                        value: address,
                        searchValue: address,
                        city,
                        label: address,
                        zip,
                        lat: coords.lat,
                        lng: coords.lng
                    });
                }
            });
            
            zipCodes.forEach(zip => {
                if (!zip) return;
                addEntry(`zip:${zip}`, {
                    type: 'zip',
                    value: zip,
                    searchValue: zip,
                    city: '',
                    label: zip,
                    zip,
                    lat: '',
                    lng: ''
                });
            });
            
            const getMatches = function(query) {
                const qLower = query.toLowerCase();
                if (!qLower) return [];
                
                // Determine which field type matches the query
                const matchingEntries = [];
                const seenLabels = new Set();
                
                localEntries.forEach(entry => {
                    let matches = false;
                    
                    // For city entries: only match if keyword is in the city name/value itself
                    if (entry.type === 'city') {
                        const cityValue = (entry.city || entry.value || entry.searchValue || '').toLowerCase();
                        matches = cityValue.indexOf(qLower) !== -1;
                    }
                    // For zip entries: only match if keyword is in the zip code itself
                    else if (entry.type === 'zip') {
                        const zipValue = (entry.zip || entry.value || entry.searchValue || '').toLowerCase();
                        matches = zipValue.indexOf(qLower) !== -1;
                    }
                    // For address entries: only match if keyword is in the address itself
                    else if (entry.type === 'address') {
                        const addressValue = (entry.value || entry.searchValue || entry.label || '').toLowerCase();
                        matches = addressValue.indexOf(qLower) !== -1;
                    }
                    
                    // Only include if the entry type matches the matching field type
                    if (matches) {
                        // Deduplicate by label
                        const labelKey = entry.label.toLowerCase().trim();
                        if (!seenLabels.has(labelKey)) {
                            seenLabels.add(labelKey);
                            matchingEntries.push(entry);
                        }
                    }
                });
                
                // Sort alphabetically: numbers first, then letters
                matchingEntries.sort(function(a, b) {
                    const labelA = (a.label || '').trim();
                    const labelB = (b.label || '').trim();
                    
                    // Check if label starts with a number
                    const aStartsWithNum = /^\d/.test(labelA);
                    const bStartsWithNum = /^\d/.test(labelB);
                    
                    // Numbers come before letters
                    if (aStartsWithNum && !bStartsWithNum) return -1;
                    if (!aStartsWithNum && bStartsWithNum) return 1;
                    
                    // Both are numbers or both are letters - sort alphabetically
                    return labelA.localeCompare(labelB, undefined, { numeric: true, sensitivity: 'base' });
                });
                
                return matchingEntries.slice(0, 100);
            };
            
            const renderSuggestions = function(items) {
                if (!items || !items.length) {
                    autocomplete.hide();
                    return;
                }
                const icon = '<svg class="suggestion-pin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>';
                let html = '<ul>';
                items.forEach(item => {
                    const label = escapeHtml(item.label);
                    const searchValue = escapeHtml(item.searchValue || item.value || item.label);
                    html += `<li class="location-suggestion" data-type="${escapeHtml(item.type)}" data-value="${escapeHtml(item.value)}" data-city="${escapeHtml(item.city || '')}" data-label="${label}" data-search-value="${searchValue}" data-lat="${item.lat}" data-lng="${item.lng}"><span class="suggestion-icon">${icon}</span><span class="suggestion-primary">${label}</span></li>`;
                });
                html += '</ul>';
                autocomplete.html(html).show();
            };
            
            const fetchRemoteZip = function(zip) {
                if (remoteZipCache[zip] !== undefined) {
                    if (remoteZipCache[zip]) {
                        renderSuggestions([remoteZipCache[zip]]);
                    } else {
                        autocomplete.hide();
                    }
                    return;
                }
                if (remoteZipRequest && remoteZipRequest.readyState !== 4) {
                    remoteZipRequest.abort();
                }
                remoteZipRequest = $.ajax({
                    url: 'https://nominatim.openstreetmap.org/search',
                    data: {
                        postalcode: zip,
                        countrycodes: 'us',
                        format: 'json',
                        addressdetails: 1,
                        limit: 1
                    },
                    headers: {
                        'User-Agent': 'Maloney Affordable Listings'
                    },
                    timeout: 10000, // 10 second timeout
                    success: function(response) {
                        if (response && response.length) {
                            const item = response[0];
                            const address = item.address || {};
                            const city = address.city || address.town || address.village || address.hamlet || '';
                            const state = (address.state_code || address.state || 'MA').toUpperCase();
                            const lat = parseFloat(item.lat) || '';
                            const lng = parseFloat(item.lon) || '';
                            const labelParts = [zip];
                            if (city) labelParts.push(city);
                            if (state) labelParts.push(state);
                            const entry = {
                                type: 'zip',
                                value: zip,
                                searchValue: zip,
                                city: city,
                                label: labelParts.join(', '),
                                zip: zip,
                                lat,
                                lng
                            };
                            remoteZipCache[zip] = entry;
                            addEntry(`zip-remote:${zip}`, entry);
                            renderSuggestions([entry]);
                        } else {
                            remoteZipCache[zip] = false;
                            autocomplete.hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        remoteZipCache[zip] = false;
                        // Silently fail - don't show error to user, just hide autocomplete
                        autocomplete.hide();
                        // Log error for debugging (only in console, not visible to users)
                        if (console && console.warn) {
                            console.warn('Geocoding error for zip ' + zip + ':', status, error);
                        }
                    }
                });
            };
            
            input.on('input', function() {
                const value = $(this).val().trim();
                $(this).removeData('searchOverride');
                if (value.length < 3) {
                    autocomplete.hide();
                    return;
                }
                const matches = getMatches(value);
                if (matches.length > 0) {
                    renderSuggestions(matches);
                } else if (/^\d{5}$/.test(value)) {
                    fetchRemoteZip(value);
                } else {
                    autocomplete.hide();
                }
            });
            
            $(document).on('click', '.location-suggestion', function(e) {
                e.preventDefault();
                const $item = $(this);
                const type = $item.data('type');
                const value = $item.data('value');
                const searchValue = $item.data('searchValue') || value;
                const label = $item.data('label') || value;
                const lat = parseFloat($item.data('lat'));
                const lng = parseFloat($item.data('lng'));
                
                input.val(label);
                input.data('searchOverride', searchValue);
                $('#filter_location').val('');
                autocomplete.hide();
                
                if (!isNaN(lat) && !isNaN(lng) && ListingFilters.map) {
                    const zoomLevel = type === 'address' ? 15 : (type === 'zip' ? 12 : 13);
                    ListingFilters.lastSearchCoords = { lat: lat, lng: lng };
                    ListingFilters.map.setView([lat, lng], zoomLevel);
                }
                
                ListingFilters.applyFilters();
            });
            
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-location').length) {
                    autocomplete.hide();
                }
            });
            
            input.on('keydown', function(e) {
                if (e.key === 'Escape') {
                    autocomplete.hide();
                }
            });
        },
        
        searchLocation: function(location, isZipCode) {
            // Use Nominatim to geocode location and center map
            if (!this.map) return;
            
            // Check if it's a zip code
            const isZip = isZipCode || /^\d{5}(-\d{4})?$/.test(location);
            
            // Add location context to avoid international results
            let searchQuery = location;
            if (!location.toLowerCase().includes('massachusetts') && 
                !location.toLowerCase().includes('ma') && 
                !location.toLowerCase().includes('usa') && 
                !location.toLowerCase().includes('united states')) {
                searchQuery = location + ', Massachusetts, USA';
            }
            
            $.ajax({
                url: 'https://nominatim.openstreetmap.org/search',
                data: {
                    q: searchQuery,
                    format: 'json',
                    limit: 1,
                    countrycodes: 'us', // Restrict to United States
                    addressdetails: 1
                },
                headers: {
                    'User-Agent': 'Maloney Affordable Listings'
                },
                timeout: 10000, // 10 second timeout
                success: function(data) {
                    if (data && data.length > 0) {
                        const result = data[0];
                        // Verify it's in the US
                        if (result.address && result.address.country_code === 'us') {
                            const lat = parseFloat(result.lat);
                            const lng = parseFloat(result.lon);
                            ListingFilters.lastSearchCoords = { lat: lat, lng: lng };
                            
                            // Center map on search location with appropriate zoom
                            // For zip codes, account for overlay width by offsetting center slightly to the right
                            if (isZip) {
                                // Calculate offset to account for overlay (move center point right)
                                const overlayWidth = $('.listings-cards-overlay').outerWidth() || 450;
                                const mapWidth = ListingFilters.map.getSize().x;
                                // Calculate longitude offset (approximate: 1 degree longitude ≈ 69 miles at this latitude)
                                // Move center point right by approximately overlayWidth/2 pixels worth of longitude
                                const offsetDegrees = (overlayWidth / 2) / (mapWidth * 0.0001); // Rough approximation
                                const adjustedLng = lng + offsetDegrees;
                                ListingFilters.map.setView([lat, adjustedLng], 12);
                            } else {
                                ListingFilters.map.setView([lat, lng], 13);
                            }
                            
                            // For zip codes, apply filters after geocoding
                            if (isZip) {
                                ListingFilters.applyFilters();
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
                    // Silently fail - geocoding errors shouldn't break the user experience
                    // The map will just stay at its current position
                    if (console && console.warn) {
                        console.warn('Geocoding error for location "' + location + '":', status, error);
                    }
                }
            });
        },
        
        initMap: function() {
            if (this.mapInitialized && this.map) {
                return;
            }
            
            const mapContainer = $('#listings-map');
            if (!mapContainer.length) {
                return;
            }
            
            // Check if Leaflet is loaded
            if (typeof L === 'undefined') {
                setTimeout(function() {
                    ListingFilters.initMap();
                }, 500);
                return;
            }
            
            try {
                // Clear any loading message
                mapContainer.html('');
                
                // Initialize map with default center (will be adjusted based on markers)
                // Disable default zoom control - we'll add it on the right side
                const map = L.map('listings-map', {
                    zoomControl: false,
                    attributionControl: true
                }).setView([42.3601, -71.0589], 10); // Default to Boston area
                
                // Add zoom control on the right side (since results are on the left)
                L.control.zoom({
                    position: 'topright'
                }).addTo(map);
                
                // Add "Search Visible Area" button (if enabled in settings)
                const enableSearchArea = typeof maloneyListingsSettings !== 'undefined' ? maloneyListingsSettings.enableSearchArea : false;
                if (enableSearchArea) {
                    const searchAreaControl = L.control({ position: 'topright' });
                    searchAreaControl.onAdd = function(map) {
                        const div = L.DomUtil.create('div', 'search-visible-area-control');
                        const btn = L.DomUtil.create('button', 'search-visible-area-btn', div);
                        btn.type = 'button';
                        btn.id = 'search-visible-area-btn';
                        btn.title = 'Search listings in visible map area';
                        btn.textContent = '🔍 Search This Area';
                        
                        // Prevent map interactions on the container, but allow button clicks
                        L.DomEvent.disableClickPropagation(div);
                        L.DomEvent.disableScrollPropagation(div);
                        
                        // Ensure button is clickable - explicitly allow pointer events
                        btn.style.pointerEvents = 'auto';
                        btn.style.cursor = 'pointer';
                        
                        // Add click handler directly to button
                        L.DomEvent.on(btn, 'click', function(e) {
                            e.stopPropagation();
                            ListingFilters.searchVisibleArea(e);
                        });
                        
                        return div;
                    };
                    searchAreaControl.addTo(map);
                }
                
                // Add map legend/color indicators
                const legendRentalColor = (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.rentalColor) 
                    ? maloneyListingsSettings.rentalColor 
                    : '#E86962';
                const legendCondoColor = (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.condoColor) 
                    ? maloneyListingsSettings.condoColor 
                    : '#E4C780';
                
                const legendControl = L.control({ position: 'topright' });
                legendControl.onAdd = function(map) {
                    const container = L.DomUtil.create('div', 'map-legend inline-legend');
                    container.innerHTML = `
                        <div class="inline map-legend-content">
                            <span class="map-legend-title">Property Types:</span>
                            <span class="legend-pin legend-pin-condo" style="background-color: ${legendCondoColor};"></span>
                            <span class="legend-label">Condominiums</span>
                            <span class="legend-pin legend-pin-rental" style="background-color: ${legendRentalColor};"></span>
                            <span class="legend-label">Rentals</span>
                        </div>
                    `;
                    L.DomEvent.disableClickPropagation(container);
                    return container;
                };
                legendControl.addTo(map);
                
                // Add colorful map tiles (CartoDB Voyager - colorful but clean style)
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 19
                }).addTo(map);
                
                // Initialize marker cluster group with helpful UX defaults
                this.markerClusterGroup = L.markerClusterGroup({
                    chunkedLoading: true,
                    chunkDelay: 50,
                    showCoverageOnHover: false,
                    spiderfyOnMaxZoom: true,
                    disableClusteringAtZoom: 16,
                    maxClusterRadius: 50
                });
                map.addLayer(this.markerClusterGroup);
                
                // Don't load all markers automatically - wait for applyFilters() to load filtered results
                // This ensures the map only shows markers matching the current filters (especially when coming from homepage search)
                // The map will be populated when applyFilters() runs (either from initFromUrl or user interaction)
                
                // Make sure map fills container - wait a bit for layout to settle
                setTimeout(function() {
                    map.invalidateSize();
                }, 800);
                
                // Also invalidate size when window resizes
                $(window).off('resize.listings-map').on('resize.listings-map', function() {
                    if (ListingFilters.map) {
                        setTimeout(function() {
                            ListingFilters.map.invalidateSize();
                        }, 100);
                    }
                });
                
                this.map = map;
                this.mapInitialized = true;
            } catch (error) {
                mapContainer.html('<div class="map-error" style="text-align: center; padding: 50px; color: #666;"><p>Error loading map. Please refresh the page.</p></div>');
            }
        },
        
        updateMapMarkers: function(listings, retryCount) {
            retryCount = retryCount || 0;
            if (!this.map || !this.markerClusterGroup) {
                // Map not ready yet, retry after a short delay (max 5 retries = 1 second)
                if (retryCount < 5) {
                    const self = this;
                    setTimeout(function() {
                        self.updateMapMarkers(listings, retryCount + 1);
                    }, 200);
                }
                return;
            }
            
            if (!listings || listings.length === 0) {
                return;
            }
            
            // Clear existing markers
            this.markerClusterGroup.clearLayers();
            
            // Add markers for all listings with coordinates
            const bounds = [];
            let markersAdded = 0;
            
            // Define pin icons by type - colors from settings or defaults
            const rentalColor = (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.rentalColor) 
                ? maloneyListingsSettings.rentalColor 
                : '#E86962';
            const condoColor = (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.condoColor) 
                ? maloneyListingsSettings.condoColor 
                : '#E4C780';
            
            // Use DivIcon with HTML/CSS for better color control
            const iconRental = L.divIcon({
                className: 'custom-marker-icon',
                html: '<div style="background-color: ' + rentalColor + '; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 30],
                popupAnchor: [0, -30]
            });
            const iconCondo = L.divIcon({
                className: 'custom-marker-icon',
                html: '<div style="background-color: ' + condoColor + '; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 30],
                popupAnchor: [0, -30]
            });

            listings.forEach(function(listing) {
                const lat = parseFloat(listing.lat);
                const lng = parseFloat(listing.lng);
                
                // Validate coordinates (not 0,0 and within valid ranges)
                if (!isNaN(lat) && !isNaN(lng) && lat != 0 && lng != 0 && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    try {
                        // Determine if condo or rental - default to rental if type is missing
                        const typeLower = (listing.type || '').toLowerCase();
                        const isCondo = typeLower.indexOf('condo') >= 0 || typeLower.indexOf('condominium') >= 0;
                        const icon = isCondo ? iconCondo : iconRental;
                        const marker = L.marker([lat, lng], { icon: icon });
                        
                        const dirs = 'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng;
                        const street = 'https://www.google.com/maps?q=&layer=c&cbll=' + lat + ',' + lng;
                        
                        // Determine type for badge and button text (reuse typeLower and isCondo from above)
                        const badgeColor = isCondo ? (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.condoColor ? maloneyListingsSettings.condoColor : '#E4C780') : (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.rentalColor ? maloneyListingsSettings.rentalColor : '#E86962');
                        const badgeText = isCondo ? 'CONDO' : 'RENTAL';
                        const viewButtonText = isCondo ? 'View Condo' : 'View Rental Property';
                        
                        // Build popup content with Caritas-style design
                        let popupContent = '<div class="map-popup-custom" data-listing-id="' + listing.id + '" style="width: 320px; max-width: 90vw; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">';
                        
                        // Close button (X) - top right
                        popupContent += '<button class="map-popup-close" style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; font-size: 18px; line-height: 1; z-index: 1000; display: flex; align-items: center; justify-content: center; font-weight: bold;">×</button>';
                        
                        // Image
                        if (listing.image) {
                            popupContent += '<div style="width: 100%; height: 200px; overflow: hidden; position: relative;">';
                            popupContent += '<img src="' + listing.image + '" alt="" style="width: 100%; height: 100%; object-fit: cover;" />';
                            // Badge overlay on image
                            popupContent += '<span style="position: absolute; top: 12px; left: 12px; background: ' + badgeColor + '; color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">' + badgeText + '</span>';
                            popupContent += '</div>';
                        } else {
                            // Badge without image
                            popupContent += '<div style="padding: 12px; background: ' + badgeColor + '; color: white;">';
                            popupContent += '<span style="font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">' + badgeText + '</span>';
                            popupContent += '</div>';
                        }
                        
                        // Content section
                        popupContent += '<div style="padding: 16px;">';
                        
                        // Title
                        popupContent += '<h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: bold; line-height: 1.3;"><a href="' + (listing.url || '#') + '" style="color: #333; text-decoration: none;">' + (listing.title || 'Listing') + '</a></h3>';
                        
                        // Address
                        if (listing.address !== undefined && listing.address !== null) {
                            var addr = String(listing.address).trim();
                            if (addr !== '' && addr !== 'undefined' && addr !== 'null') {
                                popupContent += '<p style="color:#666;font-size:14px;margin:0 0 12px 0;line-height:1.4;">' + addr + '</p>';
                            }
                        }
                        
                        // Status
                        if (listing.status !== undefined && listing.status !== null) {
                            var status = String(listing.status).trim();
                            if (status !== '' && status !== 'undefined' && status !== 'null') {
                                popupContent += '<p style="margin:0 0 12px 0;font-weight:bold;font-size:14px;color:#333;">' + status + '</p>';
                            }
                        }
                        
                        // Available Units - show for rentals (always show, even if 0)
                        if (listing.type && listing.type.toLowerCase().indexOf('rental') !== -1) {
                            var units = '0';
                            if (listing.available_units !== undefined && listing.available_units !== null) {
                                var unitsStr = String(listing.available_units).trim();
                                if (unitsStr !== '' && unitsStr !== 'undefined' && unitsStr !== 'null') {
                                    units = unitsStr;
                                }
                            }
                            // Show "No available units" instead of "0"
                            if (units === '0' || units === '') {
                                popupContent += '<p style="margin:0 0 12px 0;font-size:14px;color:#333;"><strong>Available Units:</strong> No available units</p>';
                            } else {
                                popupContent += '<p style="margin:0 0 12px 0;font-size:14px;color:#333;"><strong>Available Units:</strong> ' + units + '</p>';
                            }
                        } else if (listing.available_units !== undefined && listing.available_units !== null) {
                            // For condos, only show if available_units exists and is not empty
                            var units = String(listing.available_units).trim();
                            if (units !== '' && units !== 'undefined' && units !== 'null' && units !== '0') {
                                popupContent += '<p style="margin:0 0 12px 0;font-size:14px;color:#333;"><strong>Available Units:</strong> ' + units + '</p>';
                            }
                        }
                        
                        // Directions and Street View buttons
                        popupContent += '<div style="display: flex; gap: 8px; margin-bottom: 12px;">';
                        popupContent += '<a href="' + dirs + '" target="_blank" style="flex: 1; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-align: center; text-decoration: none; color: #333; font-size: 14px; font-weight: 500; transition: background 0.2s;">Directions</a>';
                        popupContent += '<a href="' + street + '" target="_blank" style="flex: 1; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-align: center; text-decoration: none; color: #333; font-size: 14px; font-weight: 500; transition: background 0.2s;">Street View</a>';
                        popupContent += '</div>';
                        
                        // View Details button
                        popupContent += '<a href="' + (listing.url || '#') + '" style="display: block; width: 100%; padding: 12px; background: #0073aa; color: white; text-align: center; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 600; transition: background 0.2s;">' + viewButtonText + '</a>';
                        
                        popupContent += '</div>'; // Close content section
                        popupContent += '</div>'; // Close popup
                        
                        // Calculate overlay width to position popup correctly
                        const overlayWidth = $('.listings-cards-overlay').outerWidth() || 450;
                        marker.bindPopup(popupContent, {
                            autoPan: true,
                            autoPanPaddingTopLeft: [overlayWidth + 80, 80],
                            autoPanPaddingBottomRight: [80, 80],
                        });
                        
                        // Handle marker click - clear previous active states and activate this marker
                        marker.on('click', function(e) {
                            // Remove active state from all listing cards
                            $('.listing-card').removeClass('active');
                            
                            // Remove active state from all markers
                            if (ListingFilters.markerClusterGroup) {
                                ListingFilters.markerClusterGroup.eachLayer(function(otherMarker) {
                                    if (otherMarker._icon && otherMarker !== marker) {
                                        otherMarker._icon.classList.remove('marker-active');
                                        const innerDiv = otherMarker._icon.querySelector('div');
                                        if (innerDiv) {
                                            innerDiv.classList.remove('marker-active-inner');
                                        }
                                        otherMarker.setZIndexOffset(0);
                                    }
                                });
                            }
                            
                            // Activate this marker
                            if (marker._icon) {
                                marker._icon.classList.add('marker-active');
                                const innerDiv = marker._icon.querySelector('div');
                                if (innerDiv) {
                                    innerDiv.classList.add('marker-active-inner');
                                }
                                marker.setZIndexOffset(1000);
                            }
                            
                            // Highlight corresponding listing card if it exists
                            if (marker.options && marker.options.listingId) {
                                const listingId = marker.options.listingId;
                                const matchingCard = $('.listing-card[data-listing-id="' + listingId + '"]');
                                if (matchingCard.length > 0) {
                                    matchingCard.addClass('active');
                                    // Scroll card into view if needed
                                    const cardOffset = matchingCard.offset();
                                    const overlay = $('#listings-cards-overlay');
                                    if (cardOffset && overlay.length) {
                                        const overlayTop = overlay.offset().top;
                                        const overlayHeight = overlay.height();
                                        const cardTop = cardOffset.top;
                                        const cardHeight = matchingCard.outerHeight();
                                        
                                        // Check if card is visible in overlay
                                        if (cardTop < overlayTop || cardTop + cardHeight > overlayTop + overlayHeight) {
                                            // Scroll to card
                                            $('html, body').animate({
                                                scrollTop: cardTop - overlayTop - 20
                                            }, 300);
                                        }
                                    }
                                }
                            }
                        });
                        
                        // Add close button functionality after popup is opened
                        marker.on('popupopen', function(e) {
                            const popup = e.popup;
                            const closeBtn = popup._container ? popup._container.querySelector('.map-popup-close') : null;
                            if (closeBtn) {
                                // Remove any existing listeners to avoid duplicates
                                const newCloseBtn = closeBtn.cloneNode(true);
                                closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
                                
                                newCloseBtn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    if (marker && marker._map) {
                                        marker._map.closePopup();
                                    } else if (ListingFilters.map) {
                                        ListingFilters.map.closePopup();
                                    }
                                });
                            }
                        });
                        
                        // Store listing ID on marker for easier lookup
                        marker.options.listingId = listing.id;
                        this.markerClusterGroup.addLayer(marker);
                        bounds.push([lat, lng]);
                        markersAdded++;
                    } catch (error) {
                        // Silently skip invalid markers
                    }
                }
            }.bind(this));
            
            // Fit map to show all markers
            if (bounds.length > 0) {
                // Account for listings-cards-overlay on the left (typically 400-500px wide)
                const overlayWidth = $('.listings-cards-overlay').outerWidth() || 450;
                
                // If we have a small number of markers (10 or fewer), always fit to ALL markers
                // This ensures users can see all results, not just the densest cluster
                if (bounds.length <= 10) {
                    // Create bounds from all markers
                    const allBounds = L.latLngBounds(bounds);
                    this.map.fitBounds(allBounds, { 
                        padding: [50, 50, 50, overlayWidth + 50],
                        maxZoom: 15
                    });
                    // If only one marker, ensure a sensible zoom
                    if (bounds.length === 1) {
                        this.map.setZoom(Math.max(this.map.getZoom(), 14));
                    }
                } else {
                    // For many markers (>10), use clustering logic to find densest area
                    // Group markers by approximate location to find the densest cluster
                    // Use a simple grid-based approach to find the area with most markers
                    const gridSize = 0.5; // ~50km grid cells
                    const clusters = {};
                    let maxClusterCount = 0;
                    let maxClusterKey = null;
                    
                    bounds.forEach(function(coord) {
                        const gridLat = Math.floor(coord[0] / gridSize) * gridSize;
                        const gridLng = Math.floor(coord[1] / gridSize) * gridSize;
                        const key = gridLat + ',' + gridLng;
                        
                        if (!clusters[key]) {
                            clusters[key] = { count: 0, coords: [] };
                        }
                        clusters[key].count++;
                        clusters[key].coords.push(coord);
                    });
                    
                    // Find the cluster with the most markers
                    for (const key in clusters) {
                        if (clusters[key].count > maxClusterCount) {
                            maxClusterCount = clusters[key].count;
                            maxClusterKey = key;
                        }
                    }
                    
                    // Zoom to the densest cluster area
                    if (maxClusterKey && maxClusterCount > 0 && clusters[maxClusterKey].coords.length > 0) {
                        const clusterBounds = L.latLngBounds(clusters[maxClusterKey].coords);
                        // Use a lower threshold for maxZoom to allow better zoom when filtering
                        this.map.fitBounds(clusterBounds, { 
                            padding: [50, 50, 50, overlayWidth + 50], 
                            maxZoom: 15 
                        });
                    } else {
                        // Fallback: fit all markers
                        const allBounds = L.latLngBounds(bounds);
                        this.map.fitBounds(allBounds, { 
                            padding: [50, 50, 50, overlayWidth + 50] 
                        });
                    }
                }
            }
        },
        
        handleVacancyNotification: function(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const formData = {
                action: 'submit_vacancy_notification',
                listing_id: form.find('[name="listing_id"]').val(),
                email: form.find('[name="email"]').val(),
                name: form.find('[name="name"]').val(),
                phone: form.find('[name="phone"]').val(),
                nonce: maloneyListings.nonce
            };
            
            $.ajax({
                url: maloneyListings.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    const messageDiv = $('#vacancy-notify-message');
                    if (response.success) {
                        messageDiv.html('<div class="success">' + response.data + '</div>');
                        form[0].reset();
                    } else {
                        messageDiv.html('<div class="error">' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#vacancy-notify-message').html('<div class="error">Error submitting notification. Please try again.</div>');
                }
            });
        }
    };
    
    // Single listing page functionality
    const SingleListing = {
        init: function() {
            this.loadSimilarListings();
            this.initSingleMap();
        },
        
        loadSimilarListings: function() {
            const listingElement = $('.listing-single');
            const listingId = listingElement.data('listing-id');
            
            if (!listingId) {
                return;
            }
            
            $.ajax({
                url: maloneyListings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_similar_listings',
                    listing_id: listingId,
                    nonce: maloneyListings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#similar-listings').html(response.data.html);
                        // Helper function to get cards per view based on screen size
                        function getCardsPerView() {
                            const width = window.innerWidth;
                            if (width <= 600) return 1; // Mobile: 1 card
                            if (width <= 980) return 2; // Tablet: 2 cards
                            return 3; // Desktop: 3 cards
                        }
                        
                        // Slider controls
                        $('#similar-prev').off('click').on('click', function(){
                            const el = document.getElementById('similar-listings');
                            const cardsPerView = getCardsPerView();
                            const cardWidth = el.clientWidth / cardsPerView;
                            el.scrollBy({ left: -cardWidth, behavior: 'smooth' });
                            setTimeout(updateSliderUI, 300);
                        });
                        $('#similar-next').off('click').on('click', function(){
                            const el = document.getElementById('similar-listings');
                            const cardsPerView = getCardsPerView();
                            const cardWidth = el.clientWidth / cardsPerView;
                            el.scrollBy({ left: cardWidth, behavior: 'smooth' });
                            setTimeout(updateSliderUI, 300);
                        });
                        
                        // Create dots (arrows always enabled per request)
                        function updateSliderUI() {
                            const el = document.getElementById('similar-listings');
                            if (!el || el.scrollWidth === 0 || el.children.length === 0) return;
                            
                            const cardsPerView = getCardsPerView();
                            const cardWidth = el.clientWidth / cardsPerView;
                            const totalCards = el.children.length;
                            const pages = Math.max(1, Math.ceil(totalCards / cardsPerView));
                            const currentScroll = el.scrollLeft;
                            const index = Math.min(pages-1, Math.max(0, Math.floor(currentScroll / cardWidth)));
                            
                            // Dots
                            const dots = $('#similar-dots');
                            let html = '';
                            for (let i=0; i<pages; i++) {
                                html += '<span class="dot'+(i===index?' active':'')+'" data-page="'+i+'"></span>';
                            }
                            dots.html(html);
                            
                            // Make dots clickable
                            dots.find('.dot').off('click').on('click', function() {
                                const pageIndex = parseInt($(this).data('page'));
                                const scrollTo = pageIndex * cardWidth;
                                el.scrollTo({ left: scrollTo, behavior: 'smooth' });
                                setTimeout(updateSliderUI, 300);
                            });
                        }
                        
                        // Update on window resize
                        $(window).off('resize.similar-slider').on('resize.similar-slider', function() {
                            setTimeout(updateSliderUI, 100);
                        });
                        const sliderEl = document.getElementById('similar-listings');
                        sliderEl.addEventListener('scroll', function(){
                            // Debounce scroll updates
                            if (sliderEl.__updateTimer) clearTimeout(sliderEl.__updateTimer);
                            sliderEl.__updateTimer = setTimeout(updateSliderUI, 80);
                        });
                        // Initial update after a short delay to ensure content is loaded
                        setTimeout(updateSliderUI, 100);
                    }
                }
            });
        },
        
        initSingleMap: function() {
            const mapElement = $('#listing-single-map');
            if (mapElement.length && typeof L !== 'undefined') {
                const lat = parseFloat(mapElement.data('lat'));
                const lng = parseFloat(mapElement.data('lng'));
                const typeSlug = String(mapElement.data('type') || '').toLowerCase();
                
                if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                    // Disable scroll zoom, dragging, touch zoom, and double-click zoom
                    // Only allow zoom via zoom controls
                    const map = L.map('listing-single-map', {
                        scrollWheelZoom: false,
                        dragging: false,
                        touchZoom: false,
                        doubleClickZoom: false,
                        boxZoom: false,
                        keyboard: false,
                        zoomControl: false // Disable default zoom control
                    }).setView([lat, lng], 15);
                    
                    // Colorful map style for single listing page
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                        subdomains: 'abcd',
                        maxZoom: 19
                    }).addTo(map);
                    
                    // Add zoom control (only way to zoom now) - positioned top-right
                    const zoomControl = L.control.zoom({
                        position: 'topright'
                    }).addTo(map);
                    
                    // Colored pin by type - colors from settings or defaults
                    const rentalColor = (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.rentalColor) 
                        ? maloneyListingsSettings.rentalColor 
                        : '#E86962';
                    const condoColor = (typeof maloneyListingsSettings !== 'undefined' && maloneyListingsSettings.condoColor) 
                        ? maloneyListingsSettings.condoColor 
                        : '#E4C780';
                    
                    // Use DivIcon with HTML/CSS for better color control
                    const iconRental = L.divIcon({
                        className: 'custom-marker-icon',
                        html: '<div style="background-color: ' + rentalColor + '; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 30],
                        popupAnchor: [0, -30]
                    });
                    const iconCondo = L.divIcon({
                        className: 'custom-marker-icon',
                        html: '<div style="background-color: ' + condoColor + '; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 30],
                        popupAnchor: [0, -30]
                    });

                    // Get title - make sure it's clean and not duplicated
                    let title = $('.listing-single h1.entry-title').first().text().trim() || 
                                $('.listing-single h1').first().text().trim() || 
                                'Listing';
                    // Remove any duplicate text if title appears twice
                    const titleWords = title.split(/\s+/);
                    if (titleWords.length > 0) {
                        const firstHalf = titleWords.slice(0, Math.ceil(titleWords.length / 2)).join(' ');
                        const secondHalf = titleWords.slice(Math.ceil(titleWords.length / 2)).join(' ');
                        if (firstHalf === secondHalf) {
                            title = firstHalf; // Remove duplicate
                        }
                    }
                    
                    // Get full address from the page - try multiple sources
                    let listingAddress = '';
                    
                    // First, try to get from the map element's data attribute (most reliable)
                    // Use both methods to ensure we get it
                    let mapDataAddress = mapElement.attr('data-address');
                    if (!mapDataAddress || mapDataAddress === 'undefined' || mapDataAddress === '') {
                        mapDataAddress = mapElement.data('address');
                    }
                    
                    if (mapDataAddress && mapDataAddress !== 'undefined' && mapDataAddress !== '') {
                        listingAddress = String(mapDataAddress).trim();
                    }
                    
                    // If still empty, try to get from the detail-item (displayed address)
                    if (!listingAddress || listingAddress.length < 5) {
                        const addressEl = $('.listing-single .listing-details .detail-item').filter(function() {
                            const strongText = $(this).find('strong').first().text().trim();
                            return strongText === 'Address:' || strongText === 'Address';
                        });
                        
                        if (addressEl.length) {
                            // Get all text content, then remove the "Address:" label
                            const fullText = addressEl.text().trim();
                            listingAddress = fullText.replace(/^Address:?\s*/i, '').trim();
                            
                            // If that didn't work, try cloning and removing strong tag
                            if (!listingAddress || listingAddress.length < 3) {
                                const addressClone = addressEl.clone();
                                addressClone.find('strong').remove();
                                listingAddress = addressClone.text().trim();
                            }
                        }
                    }
                    
                    // If we still don't have a good address, try Location field as fallback
                    if (!listingAddress || listingAddress.length < 5) {
                        const cityEl = $('.listing-single .listing-details .detail-item').filter(function() {
                            return $(this).find('strong').text().trim() === 'Location:';
                        });
                        if (cityEl.length) {
                            const cityText = cityEl.clone().find('strong').remove().end().text().trim();
                            if (cityText) {
                                listingAddress = cityText;
                            }
                        }
                    }
                    
                    // Don't remove title from address - just show the full address as-is
                    // The address should be the complete address, not just a fragment
                    
                    // Build nicer popup content with better styling
                    let popupContent = '<div class="map-popup" style="padding: 12px 14px; min-width: 200px; max-width: 280px;">';
                    popupContent += '<div style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 6px; line-height: 1.3;">' + title + '</div>';
                    // Show address if it exists and is meaningful
                    if (listingAddress && listingAddress.length > 3) {
                        // Only hide if it's exactly the same as title or just a single word
                        if (listingAddress !== title && listingAddress.split(/\s+/).length > 1) {
                            popupContent += '<div style="font-size: 14px; color: #666; line-height: 1.4;">' + listingAddress + '</div>';
                        } else if (listingAddress !== title && listingAddress.length > 10) {
                            // Show longer addresses even if single "word" (might be a long address string)
                            popupContent += '<div style="font-size: 14px; color: #666; line-height: 1.4;">' + listingAddress + '</div>';
                        }
                    }
                    popupContent += '</div>';
                    
                    const isCondo = (typeSlug.indexOf('condo') === 0);
                    L.marker([lat, lng], { icon: isCondo ? iconCondo : iconRental }).addTo(map).bindPopup(popupContent);
                    
                    // Add Directions and Street View buttons as custom control (if enabled in settings)
                    // Position: top left (instead of top right)
                    const enableDirections = typeof maloneyListingsSettings !== 'undefined' ? maloneyListingsSettings.enableDirections : true;
                    const enableStreetView = typeof maloneyListingsSettings !== 'undefined' ? maloneyListingsSettings.enableStreetView : true;
                    
                    if (enableDirections || enableStreetView) {
                        const directions = 'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng;
                        const street = 'https://www.google.com/maps?q=&layer=c&cbll=' + lat + ',' + lng;
                        
                        const mapLinksControl = L.control({ position: 'topleft' });
                        mapLinksControl.onAdd = function(map) {
                            const div = L.DomUtil.create('div', 'single-map-links-control');
                            let buttonsHtml = '';
                            if (enableDirections) {
                                buttonsHtml += '<a class="single-map-link-btn" href="' + directions + '" target="_blank" title="Get Directions">Directions</a>';
                            }
                            if (enableStreetView) {
                                buttonsHtml += '<a class="single-map-link-btn" href="' + street + '" target="_blank" title="Street View">Street View</a>';
                            }
                            div.innerHTML = buttonsHtml;
                            // Prevent map interactions on the container, but allow link clicks
                            L.DomEvent.disableClickPropagation(div);
                            L.DomEvent.disableScrollPropagation(div);
                            // Ensure buttons are clickable - explicitly allow pointer events on links
                            const links = div.querySelectorAll('a');
                            links.forEach(function(link) {
                                link.style.pointerEvents = 'auto';
                                link.style.cursor = 'pointer';
                                // Ensure clicks work
                                link.addEventListener('click', function(e) {
                                    // Allow default link behavior
                                    e.stopPropagation();
                                });
                            });
                            return div;
                        };
                        mapLinksControl.addTo(map);
                    }
                    
                    setTimeout(function(){ map.invalidateSize(); }, 250);
                }
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        // Initialize if we have the listings map layout or listing filters
        if ($('.listings-map-layout').length || $('.listing-filters').length) {
            ListingFilters.init();
        }
        
        if ($('.listing-single').length) {
            // Robust Leaflet loader: retry for a few seconds
            const start = Date.now();
            (function tryInit(){
                if (typeof L !== 'undefined') {
                    SingleListing.init();
                } else if (Date.now() - start < 8000) {
                    setTimeout(tryInit, 300);
                }
            })();
            // Back to results link - show "Back to Results" if came from listings page, otherwise "View Listings"
            try {
                const filtersUrl = sessionStorage.getItem('ml_filters_url');
                const container = $('<div class="back-to-results" style="margin:10px 0;"></div>');
                
                if (filtersUrl && filtersUrl.length > 0) {
                    // User came from listings page with filters - show "Back to Results" with filters
                    container.append('<a href="'+filtersUrl+'">← Back to Results</a>');
                } else {
                    // User didn't come from listings page - show "View Listings" link to archive
                    const listingsArchiveUrl = typeof maloneyListings !== 'undefined' && maloneyListings.archiveUrl 
                        ? maloneyListings.archiveUrl 
                        : window.location.origin + '/listings/';
                    container.append('<a href="'+listingsArchiveUrl+'">← View Listings</a>');
                }
                
                $('.listing-single').prepend(container);
            } catch(e) {}
        }
    });
    
})(jQuery);
