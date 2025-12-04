/**
 * Conditional Fields - Show/hide Toolset Types fields based on listing type
 * Comprehensive solution that works with Toolset's native conditional display
 */

(function($) {
    'use strict';
    
    // Field group patterns to match
    const GROUP_PATTERNS = {
        property: ['property-info', 'property info'],
        condo: ['condo-lotteries', 'condo lotteries', 'condominiums', 'condominium'],
        rental: ['rental-lotteries', 'rental lotteries', 'rental-properties', 'rental properties', 'current rental availability', 'rental-availability', 'rental availability']
    };
    
    // Explicit group IDs (if known)
    const EXPLICIT_GROUPS = {
        property: ['#wpcf-group-property-info'],
        condo: ['#wpcf-group-condo-lotteries', '#wpcf-group-condominiums'],
        rental: ['#wpcf-group-rental-lotteries', '#wpcf-group-rental-properties', '#wpcf-group-current-rental-availability', '#listing_availability']
    };
    
    function getSelectedListingType() {
        // First, check unit_type dropdown (most reliable)
        const unitType = $('#listing_unit_type').val();
        if (unitType && (unitType === 'condo' || unitType === 'rental')) {
            return unitType;
        }
        
        // Check URL parameter
        try {
            const params = new URLSearchParams(window.location.search);
            const urlType = params.get('unit_type');
            if (urlType && (urlType === 'condo' || urlType === 'rental')) {
                return urlType;
            }
        } catch(e) {}
        
        // Check taxonomy checkboxes
        const checked = $('#listing_typechecklist input[type="checkbox"]:checked, #listing_typechecklist input[type="radio"]:checked');
        if (checked.length > 0) {
            // Check label text first (most reliable)
            const labelText = checked.first().closest('label').text().trim().toLowerCase();
            if (labelText.includes('condo') || labelText.includes('condominium')) {
                return 'condo';
            }
            if (labelText.includes('rental')) {
                return 'rental';
            }
        }
        
        return '';
    }
    
    function findFieldGroupsByPattern(patterns) {
        let $groups = $();
        $('.postbox').each(function() {
            const $box = $(this);
            const boxId = ($box.attr('id') || '').toLowerCase();
            const boxTitle = $box.find('.hndle, .postbox-header h2, h2.hndle').first().text().toLowerCase();
            const ariaLabel = ($box.attr('aria-label') || '').toLowerCase();
            
            // Check if this is a Toolset group
            const isToolsetGroup = boxId.includes('wpcf-group') || 
                                  boxId.includes('types-field-group') ||
                                  $box.hasClass('wpcf-postbox') || 
                                  $box.find('[data-wpcf-group]').length > 0 ||
                                  $box.find('.wpcf-field').length > 0;
            
            if (!isToolsetGroup) return;
            
            // Check against patterns
            const allText = (boxId + ' ' + boxTitle + ' ' + ariaLabel).toLowerCase();
            for (let i = 0; i < patterns.length; i++) {
                if (allText.includes(patterns[i])) {
                    $groups = $groups.add($box);
                    break; // Found a match, move to next box
                }
            }
        });
        return $groups;
    }
    
    function findFieldGroupsById(ids) {
        let $groups = $();
        ids.forEach(function(id) {
            const $group = $(id);
            if ($group.length) {
                $groups = $groups.add($group);
            }
        });
        return $groups;
    }
    
    function toggleToolsetFields() {
        const selectedType = getSelectedListingType();
        
        // Find all field groups
        const $propertyGroups = findFieldGroupsById(EXPLICIT_GROUPS.property)
            .add(findFieldGroupsByPattern(GROUP_PATTERNS.property));
        const $condoGroups = findFieldGroupsById(EXPLICIT_GROUPS.condo)
            .add(findFieldGroupsByPattern(GROUP_PATTERNS.condo));
        let $rentalGroups = findFieldGroupsById(EXPLICIT_GROUPS.rental)
            .add(findFieldGroupsByPattern(GROUP_PATTERNS.rental));
        
        // Also specifically target the listing_availability fieldset/postbox
        const $listingAvailability = $('#listing_availability');
        if ($listingAvailability.length) {
            // Add it to rental groups if it exists
            $rentalGroups = $rentalGroups.add($listingAvailability);
        }
        
        // Always show Property Info
        $propertyGroups.show();
        
        if (!selectedType) {
            // No type selected - hide all conditional groups
            $condoGroups.hide();
            $rentalGroups.hide();
            if ($listingAvailability.length) {
                $listingAvailability.hide();
            }
        } else if (selectedType === 'condo') {
            // Show condo groups, hide rental groups
            $condoGroups.show();
            $rentalGroups.hide();
            if ($listingAvailability.length) {
                $listingAvailability.hide();
            }
        } else if (selectedType === 'rental') {
            // Show rental groups, hide condo groups
            $rentalGroups.show();
            $condoGroups.hide();
            if ($listingAvailability.length) {
                $listingAvailability.show();
            }
        }
        
        // Also trigger Toolset's native conditional display
        if (typeof wpcf !== 'undefined' && wpcf.conditional) {
            try {
                if (typeof wpcf.conditional.init === 'function') {
                    wpcf.conditional.init();
                }
                // Also try triggering on taxonomy change
                if (typeof wpcf.conditional.check === 'function') {
                    wpcf.conditional.check();
                }
            } catch(e) {
                // Silently handle errors
            }
        }
    }
    
    // Expose function globally so it can be called from other scripts
    window.toggleToolsetFields = toggleToolsetFields;
    
    function ensureTaxonomyIsSet() {
        const selectedType = getSelectedListingType();
        if (!selectedType) return;
        
        // Check if taxonomy checkbox is checked
        const $checked = $('#listing_typechecklist input:checked');
        let needsCheck = true;
        
        if ($checked.length > 0) {
            const labelText = $checked.first().closest('label').text().toLowerCase();
            if ((selectedType === 'condo' && (labelText.includes('condo') || labelText.includes('condominium'))) ||
                (selectedType === 'rental' && labelText.includes('rental'))) {
                needsCheck = false;
            }
        }
        
        if (needsCheck) {
            // Find and check the correct taxonomy checkbox
            $('#listing_typechecklist input').each(function() {
                const $input = $(this);
                const labelText = $input.closest('label').text().toLowerCase();
                const shouldCheck = (selectedType === 'condo' && (labelText.includes('condo') || labelText.includes('condominium'))) ||
                                   (selectedType === 'rental' && labelText.includes('rental'));
                
                if (shouldCheck) {
                    $input.prop('checked', true);
                    // Trigger events to notify Toolset
                    $input.trigger('change').trigger('click');
                }
            });
        }
    }
    
    $(document).ready(function() {
        // Initial setup - ensure taxonomy is set from URL or dropdown
        setTimeout(function() {
            ensureTaxonomyIsSet();
            toggleToolsetFields();
        }, 200);
        
        // Watch for changes in listing type taxonomy
        $(document).on('change', '#listing_typechecklist input', function() {
            setTimeout(function() {
                ensureTaxonomyIsSet();
                toggleToolsetFields();
            }, 100);
        });
        
        // Watch unit_type dropdown
        $(document).on('change', '#listing_unit_type', function() {
            setTimeout(function() {
                ensureTaxonomyIsSet();
                toggleToolsetFields();
            }, 100);
        });
        
        // Initial check - wait for Toolset to load
        function initConditionalFields() {
            // Check if Toolset is loaded
            if (typeof wpcf !== 'undefined' && wpcf.conditional) {
                // Toolset is loaded, trigger our function
                ensureTaxonomyIsSet();
                toggleToolsetFields();
                // Also trigger Toolset's conditional display
                if (typeof wpcf.conditional.init === 'function') {
                    wpcf.conditional.init();
                }
            } else {
                // Wait a bit more for Toolset to load (up to 5 seconds)
                if (typeof initConditionalFields.attempts === 'undefined') {
                    initConditionalFields.attempts = 0;
                }
                initConditionalFields.attempts++;
                if (initConditionalFields.attempts < 25) { // 5 seconds max
                    setTimeout(initConditionalFields, 200);
                } else {
                    // Toolset not loaded, but still apply our visibility
                    ensureTaxonomyIsSet();
                    toggleToolsetFields();
                }
            }
        }
        
        // Start initialization after a short delay
        setTimeout(initConditionalFields, 300);
        
        // Also check when page fully loads
        $(window).on('load', function() {
            setTimeout(function() {
                ensureTaxonomyIsSet();
                toggleToolsetFields();
            }, 500);
        });
        
        // Check when Gutenberg editor loads
        if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
            wp.data.subscribe(function() {
                setTimeout(function() {
                    ensureTaxonomyIsSet();
                    toggleToolsetFields();
                }, 100);
            });
        }
        
        // Watch for Toolset Types field groups being added dynamically
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(function(mutations) {
                let shouldUpdate = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        // Check if any added nodes are Toolset field groups
                        for (let i = 0; i < mutation.addedNodes.length; i++) {
                            const node = mutation.addedNodes[i];
                            if (node.nodeType === 1) { // Element node
                                const $node = $(node);
                                if ($node.hasClass('postbox') || $node.find('.postbox').length > 0 || 
                                    ($node.attr('id') && $node.attr('id').includes('wpcf-group'))) {
                                    shouldUpdate = true;
                                    break;
                                }
                            }
                        }
                    }
                });
                if (shouldUpdate) {
                    setTimeout(function() {
                        ensureTaxonomyIsSet();
                        toggleToolsetFields();
                    }, 200);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
    
})(jQuery);
