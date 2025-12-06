<?php
/**
 * Listing Card Template - Matching Housing Navigator Massachusetts Design
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

$post_id = get_the_ID();

// Get taxonomies
$listing_type = get_the_terms($post_id, 'listing_type');
$listing_status = get_the_terms($post_id, 'listing_status');
$location_terms = get_the_terms($post_id, 'location');

// Get address fields
$address = get_post_meta($post_id, 'wpcf-address', true);
if (empty($address)) {
    $address = get_post_meta($post_id, '_listing_address', true);
}
$city = get_post_meta($post_id, 'wpcf-city', true);
if (empty($city)) {
    $city = get_post_meta($post_id, '_listing_city', true);
}
$state = get_post_meta($post_id, 'wpcf-state-1', true);
if (empty($state)) {
    $state = get_post_meta($post_id, '_listing_state', true);
}
// Get zip code - check new field first, then legacy fields for compatibility
$zip = get_post_meta($post_id, 'wpcf-zip-code', true);
if (empty($zip)) {
    $zip = get_post_meta($post_id, 'wpcf-zip', true);
}
if (empty($zip)) {
    $zip = get_post_meta($post_id, '_listing_zip', true);
}

// Build full address with ZIP
// Just use the address field directly, don't build from parts
$full_address = $address;

// Get property features/tags
$features = array();
$champ_property = get_post_meta($post_id, 'wpcf-champ-property', true);
if ($champ_property == '1' || strtolower($champ_property) === 'yes') {
    $features[] = 'CHAMP Property';
}

// Check for Rent Based on Income
$marketing_text = get_post_meta($post_id, 'wpcf-main-marketing-text', true);
if (empty($marketing_text)) {
    $marketing_text = get_post_meta($post_id, '_listing_main_marketing_text', true);
}
if (stripos($marketing_text, 'rent based on income') !== false || 
    stripos($marketing_text, 'income-based') !== false) {
    $features[] = 'Rent Based on Income';
}

// Check for Age Restricted
$eligibility = get_post_meta($post_id, 'wpcf-eligibility', true);
if (empty($eligibility)) {
    $eligibility = get_post_meta($post_id, '_listing_eligibility', true);
}
if (stripos($eligibility, 'age restricted') !== false || 
    stripos($eligibility, '55+') !== false || 
    stripos($eligibility, '60+') !== false) {
    if (stripos($eligibility, '55+') !== false) {
        $features[] = 'Age Restricted 55+';
    } elseif (stripos($eligibility, '60+') !== false || stripos($eligibility, 'disability') !== false) {
        $features[] = 'Age Restricted 60+ and/or Disability';
    } else {
        $features[] = 'Age Restricted';
    }
}

// Get listing type to determine which tags to show
$listing_type_slug = '';
$is_condo = false;
$is_rental = false;
if ($listing_type && !is_wp_error($listing_type)) {
    $listing_type_slug = strtolower($listing_type[0]->slug);
    if (strpos($listing_type_slug, 'condo') !== false || strpos($listing_type_slug, 'condominium') !== false) {
        $is_condo = true;
    } elseif (strpos($listing_type_slug, 'rental') !== false) {
        $is_rental = true;
    }
}

// Check if listing is inactive (waitlist) - status = 4
// If inactive, don't add lottery features (can't have both waitlist and lottery)
$is_inactive_status = false;
if ($is_rental) {
    $rental_status_check = get_post_meta($post_id, 'wpcf-status', true);
    if (empty($rental_status_check) && $rental_status_check !== '0' && $rental_status_check !== 0) {
        $rental_status_check = get_post_meta($post_id, '_listing_rental_status', true);
    }
    $is_inactive_status = ($rental_status_check === '4' || $rental_status_check === 4);
} elseif ($is_condo) {
    $condo_status_check = get_post_meta($post_id, 'wpcf-condo-status', true);
    if (empty($condo_status_check) && $condo_status_check !== '0' && $condo_status_check !== 0) {
        $condo_status_check = get_post_meta($post_id, '_listing_condo_status', true);
    }
    $is_inactive_status = ($condo_status_check === '4' || $condo_status_check === 4);
}

// Get lottery/process information - only if NOT inactive (waitlist)
// You can't have both "Waitlist" and "Lottery" at the same time
$lottery_process = '';
if (!$is_inactive_status) {
    $possible_fields = array(
        'wpcf-lottery-process',
        '_listing_lottery_process',
        'wpcf-process-type',
        '_listing_process_type',
        'wpcf-application-process',
        '_listing_application_process'
    );

    foreach ($possible_fields as $field) {
        $value = get_post_meta($post_id, $field, true);
        if (!empty($value)) {
            $lottery_process = $value;
            break;
        }
    }

    // Also check post content and excerpt for process information
    if (empty($lottery_process)) {
        $content = get_post_field('post_content', $post_id);
        $excerpt = get_post_field('post_excerpt', $post_id);
        $search_text = $content . ' ' . $excerpt;
        
        if (stripos($search_text, 'first come first served') !== false || 
            stripos($search_text, 'first-come') !== false) {
            $lottery_process = 'first come first served';
        } elseif (stripos($search_text, 'lottery closed') !== false) {
            $lottery_process = 'lottery closed';
        } elseif (stripos($search_text, 'lottery') !== false) {
            $lottery_process = 'lottery';
        }
    }
}

// Only add lottery/first come features if NOT inactive (waitlist)
// You can't have both "Waitlist" and "Lottery" at the same time
// $is_inactive_status is already set above
if (!$is_inactive_status && !empty($lottery_process)) {
    // For Rentals: first come first served, closed lottery
    if (strpos($listing_type_slug, 'rental') !== false) {
        $lottery_lower = strtolower($lottery_process);
        if (stripos($lottery_lower, 'first come') !== false || 
            stripos($lottery_lower, 'first-come') !== false ||
            stripos($lottery_lower, 'first come first served') !== false) {
            $features[] = 'First Come, First Served';
        } elseif (stripos($lottery_lower, 'closed') !== false && stripos($lottery_lower, 'lottery') !== false) {
            $features[] = 'Closed Lottery';
        } elseif (stripos($lottery_lower, 'lottery') !== false && stripos($lottery_lower, 'closed') === false) {
            $features[] = 'Lottery';
        }
    }

    // For Condos: lottery closed, for sale, lottery
    if (strpos($listing_type_slug, 'condo') !== false || strpos($listing_type_slug, 'condominium') !== false) {
        // Check if it's for sale (resale)
        $for_sale = get_post_meta($post_id, 'wpcf-for-sale', true);
        if (empty($for_sale)) {
            $for_sale = get_post_meta($post_id, '_listing_for_sale', true);
        }
        if (empty($for_sale)) {
            // Check other possible field names
            $for_sale = get_post_meta($post_id, 'wpcf-resale', true);
        }
        if (empty($for_sale)) {
            $for_sale = get_post_meta($post_id, '_listing_resale', true);
        }
        
        $lottery_lower = strtolower($lottery_process);
        if ($for_sale == '1' || strtolower($for_sale) === 'yes' || 
            stripos($lottery_lower, 'resale') !== false ||
            stripos($lottery_lower, 'for sale') !== false) {
            $features[] = 'For Sale';
        } elseif (stripos($lottery_lower, 'closed') !== false && stripos($lottery_lower, 'lottery') !== false) {
            $features[] = 'Lottery Closed';
        } elseif (stripos($lottery_lower, 'lottery') !== false && stripos($lottery_lower, 'closed') === false) {
            $features[] = 'Lottery';
        }
    }
}

// Get unit sizes/availability from wpcf-unit-sizes field
// This field contains an array of unit sizes like "Studio", "One Bedroom", "Two Bedroom", etc.
$unit_sizes = get_post_meta($post_id, 'wpcf-unit-sizes', true);
if (empty($unit_sizes)) {
    $unit_sizes = get_post_meta($post_id, '_listing_unit_sizes', true);
}
// If it's a serialized array, unserialize it
if (is_string($unit_sizes)) {
    $unit_sizes = maybe_unserialize($unit_sizes);
}
if (!is_array($unit_sizes)) {
    $unit_sizes = array();
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

// Map unit size labels to display format
$unit_size_map = array(
    'Studio' => 'Studio',
    'One Bedroom' => '1BR',
    'Two Bedroom' => '2BR',
    'Three Bedroom' => '3BR',
    'Four Bedroom' => '4+BR',
);

// Get unit types from unit sizes array
$available_units = array();
if (!empty($unit_sizes) && is_array($unit_sizes)) {
    foreach ($unit_sizes as $key => $value) {
        $size_label = '';
        
        // Check if this is a Toolset option key (starts with wpcf-fields-checkboxes-option-)
        if (is_string($key) && strpos($key, 'wpcf-fields-checkboxes-option-') === 0) {
            // Look up the title from the field definition
            if (isset($option_key_to_title[$key])) {
                $size_label = $option_key_to_title[$key];
            }
        } elseif (is_array($value)) {
            // Handle array structure (Toolset checkbox field format)
            // Check for 'title' or 'value' key
            if (isset($value['title'])) {
                $size_label = $value['title'];
            } elseif (isset($value['value'])) {
                $size_label = $value['value'];
            } elseif (isset($value['bedrooms'])) {
                // Numeric bedrooms value
                $bed = intval($value['bedrooms']);
                if ($bed === 0) {
                    $size_label = 'Studio';
                } elseif ($bed >= 1 && $bed <= 3) {
                    $size_label = ($bed === 1 ? 'One' : ($bed === 2 ? 'Two' : 'Three')) . ' Bedroom';
                } elseif ($bed >= 4) {
                    $size_label = 'Four Bedroom';
                }
            } elseif (isset($value['type'])) {
                $size_label = $value['type'];
            }
        } else {
            // Handle string values directly
            $size_label = trim($value);
        }
        
        if ($size_label) {
            // Normalize the label
            $size_label = trim($size_label);
            // Map to display format
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
            } else {
                // Use as-is if no match
                $available_units[] = $size_label;
            }
        }
    }
    
    // Remove duplicates and sort
    $available_units = array_unique($available_units);
    // Sort: Studio first, then 1BR, 2BR, 3BR, 4+BR
    $sort_order = array('Studio' => 0, '1BR' => 1, '2BR' => 2, '3BR' => 3, '4+BR' => 4);
    usort($available_units, function($a, $b) use ($sort_order) {
        $a_order = isset($sort_order[$a]) ? $sort_order[$a] : 999;
        $b_order = isset($sort_order[$b]) ? $sort_order[$b] : 999;
        return $a_order - $b_order;
    });
}

// Get status value based on listing type
// $is_condo and $is_rental are already set above
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

// Map status to display label (use frontend mapping for frontend display)
$status_display = '';
if (!empty($status_value) || $status_value === '0' || $status_value === 0) {
    if (class_exists('Maloney_Listings_Custom_Fields')) {
        // Use frontend mapping which converts "FCFS Condo Sales" to "For Sale"
        $status_display = Maloney_Listings_Custom_Fields::map_status_display_frontend($status_value, $is_condo);
    } else {
        $status_display = $status_value;
    }
}

// Fallback to taxonomy status if no Toolset status found
$status_class = '';
$status_name = '';
if (empty($status_display) && $listing_status && !is_wp_error($listing_status)) {
    $status_class = strtolower($listing_status[0]->slug);
    $status_name = $listing_status[0]->name;
} else {
    $status_name = $status_display;
    $status_class = sanitize_html_class(strtolower($status_display));
}
// Determine waitlist status display
// Status = 4 means inactive (waitlist) for both rentals and condos
// For waitlist items, show "Waitlist Unknown" (not "Waitlist Inactive Condo Property")
$waitlist_status = '';
$show_waitlist_prefix = false;

// Check if status is inactive (waitlist) - status = 4
$status_is_inactive = false;
if ($is_rental) {
    $rental_status_val = get_post_meta($post_id, 'wpcf-status', true);
    if (empty($rental_status_val) && $rental_status_val !== '0' && $rental_status_val !== 0) {
        $rental_status_val = get_post_meta($post_id, '_listing_rental_status', true);
    }
    $status_is_inactive = ($rental_status_val === '4' || $rental_status_val === 4);
} elseif ($is_condo) {
    $condo_status_val = get_post_meta($post_id, 'wpcf-condo-status', true);
    if (empty($condo_status_val) && $condo_status_val !== '0' && $condo_status_val !== 0) {
        $condo_status_val = get_post_meta($post_id, '_listing_condo_status', true);
    }
    $status_is_inactive = ($condo_status_val === '4' || $condo_status_val === 4);
}

if ($status_is_inactive) {
    // For inactive status (waitlist), show "Waitlist Unknown"
    $show_waitlist_prefix = true;
    $waitlist_status = 'Unknown';
} elseif ($status_display) {
    // For all other statuses, just show the status without "Waitlist" prefix
    $waitlist_status = $status_display;
} elseif ($status_class === 'available') {
    $waitlist_status = 'Available';
} elseif ($status_class === 'waitlist') {
    $show_waitlist_prefix = true;
    $waitlist_status = 'Open';
} elseif ($status_name) {
    $waitlist_status = $status_name;
}

// Get coordinates for map interaction
$lat = get_post_meta($post_id, '_listing_latitude', true);
$lng = get_post_meta($post_id, '_listing_longitude', true);

// Check for verified listing and accessibility
$verified = get_post_meta($post_id, 'wpcf-verified', true);
$accessible = get_post_meta($post_id, 'wpcf-accessible', true);
?>

<article class="listing-card" 
         data-listing-id="<?php echo esc_attr($post_id); ?>"
         <?php if ($lat && $lng) : ?>
         data-lat="<?php echo esc_attr($lat); ?>"
         data-lng="<?php echo esc_attr($lng); ?>"
         <?php endif; ?>>
    
    <div class="listing-card-image-wrapper">
        <?php if (has_post_thumbnail()) : ?>
            <?php 
            $thumbnail_id = get_post_thumbnail_id($post_id);
            // Use 'large' size for better quality (typically 1024px wide)
            // Falls back to 'full' if 'large' doesn't exist
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'large');
            if (!$image_url) {
                $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
            }
            ?>
            <a href="<?php the_permalink(); ?>">
                <img 
                    src="<?php echo esc_url($image_url); ?>" 
                    alt="<?php echo esc_attr(get_the_title()); ?>" 
                    class="listing-card-image" 
                    loading="lazy"
                />
            </a>
        <?php else : ?>
            <div class="listing-card-image-placeholder">
                <span><?php _e('No Image', 'maloney-listings'); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Property Type Badge -->
        <?php if ($listing_type && !is_wp_error($listing_type)) : 
            $type_slug = strtolower($listing_type[0]->slug);
            $type_name = $listing_type[0]->name;
            $badge_class = 'listing-type-badge';
            if (strpos($type_slug, 'condo') !== false || strpos($type_slug, 'condominium') !== false) {
                $badge_class .= ' badge-condo';
            } elseif (strpos($type_slug, 'rental') !== false) {
                $badge_class .= ' badge-rental';
            }
            
            // Get total available units for badge (both rentals and condos)
            $total_available_for_badge = 0;
            if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                $total_available_for_badge = Maloney_Listings_Available_Units_Fields::get_total_available($post_id);
            }
            // Also check for condo listings
            if (class_exists('Maloney_Listings_Condo_Listings_Fields')) {
                $condo_total = Maloney_Listings_Condo_Listings_Fields::get_total_available($post_id);
                $total_available_for_badge += $condo_total;
            }
        ?>
            <span class="listing-card-badge <?php echo esc_attr($badge_class); ?>">
                <?php echo esc_html($type_name); ?>
            </span>
            <?php if ($total_available_for_badge > 0) : ?>
                <span class="available-units-badge" style="position: absolute; top: 12px; right: 12px; background: rgba(110, 204, 57, 1); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; z-index: 10; white-space: nowrap;">
                    <strong><?php echo esc_html($total_available_for_badge); ?></strong> <?php echo $total_available_for_badge === 1 ? __('unit available', 'maloney-listings') : __('units available', 'maloney-listings'); ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Verification and Accessibility Icons -->
        <?php if (($verified == '1' || strtolower($verified) === 'yes') || ($accessible == '1' || strtolower($accessible) === 'yes')) : ?>
        <div class="listing-card-image-badges">
            <?php if ($verified == '1' || strtolower($verified) === 'yes') : ?>
                <span class="badge-icon badge-verified" title="Verified Listing">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="8" cy="8" r="7" fill="#2196F3"/>
                        <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            <?php endif; ?>
            <?php if ($accessible == '1' || strtolower($accessible) === 'yes') : ?>
                <span class="badge-icon badge-accessible" title="Accessible">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="8" cy="8" r="7" fill="#2196F3"/>
                        <path d="M8 4v8M5 7h6M8 4L5 7M8 4l3 3M8 12l-2-2M8 12l2-2" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="listing-card-content">
        <h3 class="listing-card-title">
            <a href="<?php the_permalink(); ?>"><?php echo esc_html(get_the_title()); ?></a>
        </h3>
        
        <?php if (!empty($full_address)) : ?>
            <div class="listing-card-address">
                <?php echo esc_html($full_address); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($features)) : ?>
            <div class="listing-card-features">
                <?php 
                $feature_count = 0;
                // If status already shows lottery info (e.g., "Closed Lottery"), don't show "Lottery" in features
                $status_has_lottery = false;
                if ($waitlist_status) {
                    $status_lower = strtolower($waitlist_status);
                    if (strpos($status_lower, 'lottery') !== false || strpos($status_lower, 'closed lottery') !== false) {
                        $status_has_lottery = true;
                    }
                }
                
                foreach ($features as $feature) : 
                    // Skip "Lottery" feature if status already shows lottery info
                    if ($status_has_lottery && strtolower($feature) === 'lottery') {
                        continue;
                    }
                    
                    if ($feature_count < 3) : // Show max 3 features
                        $cls = 'feature-tag';
                        $low = strtolower($feature);
                        if (strpos($low,'lottery') !== false) $cls .= ' badge-lottery';
                        if (strpos($low,'first come') !== false) $cls .= ' badge-firstcome';
                        if (strpos($low,'available') !== false) $cls .= ' badge-available';
                        if (strpos($low,'waitlist') !== false) $cls .= (strpos($low,'open')!==false ? ' badge-waitlist-open' : ' badge-waitlist');
                ?>
                    <span class="<?php echo esc_attr($cls); ?>"><?php echo esc_html($feature); ?></span>
                <?php 
                    $feature_count++;
                    endif;
                endforeach; 
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ($waitlist_status) : ?>
        <div class="waitlist-status <?php echo esc_attr($status_class); ?>">
            <strong>
                <?php if ($show_waitlist_prefix) : ?>
                    Waitlist <?php echo esc_html($waitlist_status); ?>
                <?php else : ?>
                    <?php 
                    // Replace status text with "First Come, First Served" or "Lottery"
                    $display_status = esc_html($waitlist_status);
                    $status_lower = strtolower($display_status);
                    if (stripos($status_lower, 'first come') !== false || stripos($status_lower, 'fcfs') !== false) {
                        $display_status = 'First Come, First Served';
                    } elseif (stripos($status_lower, 'lottery') !== false) {
                        $display_status = 'Lottery';
                    }
                    echo $display_status;
                    ?>
                <?php endif; ?>
            </strong>
        </div>
        <?php endif; ?>
        
        <?php 
        // For rentals, show available units with detailed breakdown
        if ($is_rental) {
            // Get available units using the repetitive field structure
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
                            // Use the full text if it has additional info, otherwise use type (count) format
                            if (strpos($data['display_text'], '(') !== false) {
                                // If it already has parentheses, clean up any spaces before closing paren and make the count bold
                                // First, remove ALL spaces before closing parentheses globally
                                $cleaned_text = preg_replace('/\s+\)/', ')', $data['display_text']);
                                // Then, find and bold the number in parentheses (match the last set with a number)
                                // Pattern: find (number) at the end or before the end
                                $cleaned_text = preg_replace('/\((\d+)\)/', '(<strong>$1</strong>)', $cleaned_text);
                                $display_parts[] = $cleaned_text;
                            } else {
                                // Format: "1-Bedroom (1)" with bold count - no space before closing paren
                                $display_parts[] = $type . ' (<strong>' . $data['count'] . '</strong>)';
                            }
                        }
                        ?>
                        <div class="available-units">
                            <strong><?php _e('Available Units:', 'maloney-listings'); ?></strong>
                            <span class="unit-types">
                                <?php echo implode(', ', $display_parts); ?>
                            </span>
                        </div>
                        <?php
                    }
                } else {
                    // Show "No available units" for rentals with no availability
                    ?>
                    <div class="available-units">
                        <strong><?php _e('Available Units:', 'maloney-listings'); ?></strong>
                        <span class="unit-types"><?php _e('No available units', 'maloney-listings'); ?></span>
                    </div>
                    <?php
                }
                
                // Also show unit types offered (from unit-sizes field) for rentals
                $unit_sizes = get_post_meta($post_id, 'wpcf-unit-sizes', true);
                if (empty($unit_sizes)) {
                    $unit_sizes = get_post_meta($post_id, '_listing_unit_sizes', true);
                }
                if (!empty($unit_sizes)) {
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
                    
                    // Map unit size labels to display format
                    $unit_size_map = array(
                        'Studio' => 'Studio',
                        'One Bedroom' => '1BR',
                        'Two Bedroom' => '2BR',
                        'Three Bedroom' => '3BR',
                        'Four Bedroom' => '4+BR',
                    );
                    
                    // Get unit types from unit sizes array
                    $available_units_offered = array();
                    if (is_string($unit_sizes)) {
                        $unit_sizes = maybe_unserialize($unit_sizes);
                    }
                    if (is_array($unit_sizes) && !empty($unit_sizes)) {
                        foreach ($unit_sizes as $key => $value) {
                            $size_label = '';
                            
                            // Check if this is a Toolset option key
                            if (is_string($key) && strpos($key, 'wpcf-fields-checkboxes-option-') === 0) {
                                if (isset($option_key_to_title[$key])) {
                                    $size_label = $option_key_to_title[$key];
                                }
                            } elseif (is_array($value)) {
                                if (isset($value['title'])) {
                                    $size_label = $value['title'];
                                } elseif (isset($value['value'])) {
                                    $size_label = $value['value'];
                                }
                            } else {
                                $size_label = trim($value);
                            }
                            
                            if ($size_label) {
                                $size_label = trim($size_label);
                                if (isset($unit_size_map[$size_label])) {
                                    $available_units_offered[] = $unit_size_map[$size_label];
                                } elseif (stripos($size_label, 'studio') !== false) {
                                    $available_units_offered[] = 'Studio';
                                } elseif (stripos($size_label, 'one') !== false && stripos($size_label, 'bedroom') !== false) {
                                    $available_units_offered[] = '1BR';
                                } elseif (stripos($size_label, 'two') !== false && stripos($size_label, 'bedroom') !== false) {
                                    $available_units_offered[] = '2BR';
                                } elseif (stripos($size_label, 'three') !== false && stripos($size_label, 'bedroom') !== false) {
                                    $available_units_offered[] = '3BR';
                                } elseif (stripos($size_label, 'four') !== false && stripos($size_label, 'bedroom') !== false) {
                                    $available_units_offered[] = '4+BR';
                                }
                            }
                        }
                        
                        $available_units_offered = array_unique($available_units_offered);
                        $sort_order = array('Studio' => 0, '1BR' => 1, '2BR' => 2, '3BR' => 3, '4+BR' => 4);
                        usort($available_units_offered, function($a, $b) use ($sort_order) {
                            $a_order = isset($sort_order[$a]) ? $sort_order[$a] : 999;
                            $b_order = isset($sort_order[$b]) ? $sort_order[$b] : 999;
                            return $a_order - $b_order;
                        });
                        
                        if (!empty($available_units_offered)) {
                            ?>
                            <div class="available-units-offered">
                                <strong><?php _e('Unit Types:', 'maloney-listings'); ?></strong>
                                <span class="unit-types">
                                    <?php echo esc_html(implode(', ', $available_units_offered)); ?>
                                </span>
                            </div>
                            <?php
                        }
                    }
                }
            }
        } elseif ($is_condo) {
            // For condos, show current condo listings with detailed breakdown
            if (class_exists('Maloney_Listings_Condo_Listings_Fields')) {
                $condo_listings_data = Maloney_Listings_Condo_Listings_Fields::get_condo_listings_data($post_id);
                $total_condo_available = Maloney_Listings_Condo_Listings_Fields::get_total_available($post_id);
                
                if ($total_condo_available > 0) {
                    // Group by unit type and sum counts
                    $units_by_type = array();
                    foreach ($condo_listings_data as $entry) {
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
                            // Use the full text if it has additional info, otherwise use type (count) format
                            if (strpos($data['display_text'], '(') !== false) {
                                // If it already has parentheses, clean up any spaces before closing paren and make the count bold
                                $cleaned_text = preg_replace('/\s+\)/', ')', $data['display_text']);
                                $cleaned_text = preg_replace('/\((\d+)\)/', '(<strong>$1</strong>)', $cleaned_text);
                                $display_parts[] = $cleaned_text;
                            } else {
                                // Format: "1-Bedroom (1)" with bold count
                                $display_parts[] = $type . ' (<strong>' . $data['count'] . '</strong>)';
                            }
                        }
                        ?>
                        <div class="available-units">
                            <strong><?php _e('Current Condo Listings:', 'maloney-listings'); ?></strong>
                            <span class="unit-types">
                                <?php echo implode(', ', $display_parts); ?>
                            </span>
                        </div>
                        <?php
                    }
                } else {
                    // Show "No available units" for condos with no availability
                    ?>
                    <div class="available-units">
                        <strong><?php _e('Current Condo Listings:', 'maloney-listings'); ?></strong>
                        <span class="unit-types"><?php _e('No available units', 'maloney-listings'); ?></span>
                    </div>
                    <?php
                }
            }
        } elseif (!empty($available_units)) {
            ?>
            <div class="available-units">
                <strong><?php _e('Available Units:', 'maloney-listings'); ?></strong>
                <span class="unit-types">
                    <?php 
                    $unit_display = array();
                    foreach ($available_units as $unit) {
                        $unit_display[] = '<span class="unit-type">' . esc_html($unit) . '</span>';
                    }
                    if (!empty($unit_display)) {
                        echo implode(', ', $unit_display);
                    }
                    ?>
                </span>
            </div>
            <?php
        }
        ?>
        
        <!-- Additional Options/Info -->
        <div class="listing-card-options">
            <?php
            // Get price information
            $rent = get_post_meta($post_id, '_listing_rent_price', true);
            $purchase = get_post_meta($post_id, '_listing_purchase_price', true);
            if ($rent || $purchase) {
                $price = $rent ? '$' . number_format($rent) . '/mo' : '$' . number_format($purchase);
                ?>
                <div class="listing-card-option">
                    <span class="listing-card-option-icon">💰</span>
                    <span><?php echo esc_html($price); ?></span>
                </div>
                <?php
            }
            
            // Get income limits and map to display label
            $income_limits = get_post_meta($post_id, 'wpcf-income-limits', true);
            if (empty($income_limits) && $income_limits !== '0' && $income_limits !== 0) {
                $income_limits = get_post_meta($post_id, '_listing_income_limits', true);
            }
            if (!empty($income_limits) || $income_limits === '0' || $income_limits === 0) {
                $income_display = class_exists('Maloney_Listings_Custom_Fields') 
                    ? Maloney_Listings_Custom_Fields::map_income_limits_display($income_limits) 
                    : $income_limits;
                ?>
                <div class="listing-card-option">
                    <span class="listing-card-option-icon">💵</span>
                    <span>Income Limits: <?php echo esc_html($income_display); ?></span>
                </div>
                <?php
            }
            
            // Get AMI percentage
            $ami = get_post_meta($post_id, 'wpcf-ami-percentage', true);
            if (empty($ami)) {
                $ami = get_post_meta($post_id, '_listing_ami_percentage', true);
            }
            if ($ami && $ami !== '') {
                ?>
                <div class="listing-card-option">
                    <span class="listing-card-option-icon">📊</span>
                    <span><?php echo esc_html($ami); ?>% AMI</span>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</article>
