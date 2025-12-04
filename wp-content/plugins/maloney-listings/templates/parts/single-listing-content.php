<?php
/**
 * Single Listing Content (no header/footer - theme handles layout)
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */
$post_id = get_the_ID();
$listing_type = get_the_terms($post_id, 'listing_type');
$listing_status = get_the_terms($post_id, 'listing_status');
$location = get_the_terms($post_id, 'location');
$amenities = get_the_terms($post_id, 'amenities');
$bathrooms = get_post_meta($post_id, '_listing_bathrooms', true);
$square_feet = get_post_meta($post_id, '_listing_square_feet', true);
$rent_price = get_post_meta($post_id, '_listing_rent_price', true);
$purchase_price = get_post_meta($post_id, '_listing_purchase_price', true);
$income_level_min = get_post_meta($post_id, '_listing_income_level_min', true);
$income_level_max = get_post_meta($post_id, '_listing_income_level_max', true);
$address = get_post_meta($post_id, '_listing_address', true);
$latitude = get_post_meta($post_id, '_listing_latitude', true);
$longitude = get_post_meta($post_id, '_listing_longitude', true);
$availability_date = get_post_meta($post_id, '_listing_availability_date', true);
$type_class = $listing_type && !is_wp_error($listing_type) ? strtolower($listing_type[0]->slug) : '';
$status_class = $listing_status && !is_wp_error($listing_status) ? strtolower($listing_status[0]->slug) : '';
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('listing-single'); ?> data-listing-id="<?php echo esc_attr($post_id); ?>">
    
    <?php if (has_post_thumbnail()) : ?>
        <div class="listing-featured-image">
            <?php the_post_thumbnail('large'); ?>
        </div>
    <?php endif; ?>
    
    <h1 class="entry-title"><?php the_title(); ?></h1>
    
    <div class="listing-header-badges">
        <?php if ($listing_type && !is_wp_error($listing_type)) : ?>
            <span class="listing-type-badge <?php echo esc_attr($type_class); ?>">
                <?php echo esc_html($listing_type[0]->name); ?>
            </span>
        <?php endif; ?>
        
        <?php
        // Get status from Toolset fields (numeric values) and map to display labels
        $status_value = '';
        $is_condo = false;
        $is_rental = false;
        
        // Determine listing type
        if ($listing_type && !is_wp_error($listing_type)) {
            $type_slug = strtolower($listing_type[0]->slug);
            if (strpos($type_slug, 'condo') !== false || strpos($type_slug, 'condominium') !== false) {
                $is_condo = true;
            } elseif (strpos($type_slug, 'rental') !== false) {
                $is_rental = true;
            }
        }
        
        // Get status value based on listing type
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
        if (empty($status_display) && $listing_status && !is_wp_error($listing_status)) {
            $status_display = $listing_status[0]->name;
            $status_class = strtolower($listing_status[0]->slug);
        } else {
            $status_class = sanitize_html_class(strtolower($status_display));
        }
        
        if (!empty($status_display)) :
        ?>
            <span class="listing-status-badge <?php echo esc_attr($status_class); ?>">
                <?php echo esc_html($status_display); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <?php if ($rent_price || $purchase_price) : ?>
        <div class="listing-price">
            <?php if ($rent_price) : ?>
                <span class="price-amount">$<?php echo number_format($rent_price); ?>/mo</span>
            <?php elseif ($purchase_price) : ?>
                <span class="price-amount">$<?php echo number_format($purchase_price); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="listing-details">
        <?php
        // Get unit sizes/availability from wpcf-unit-sizes field
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
                    if (isset($value['title'])) {
                        $size_label = $value['title'];
                    } elseif (isset($value['value'])) {
                        $size_label = $value['value'];
                    } elseif (isset($value['bedrooms'])) {
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
                    $size_label = trim($size_label);
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
                        $available_units[] = $size_label;
                    }
                }
            }
            
            // Remove duplicates and sort
            $available_units = array_unique($available_units);
            $sort_order = array('Studio' => 0, '1BR' => 1, '2BR' => 2, '3BR' => 3, '4+BR' => 4);
            usort($available_units, function($a, $b) use ($sort_order) {
                $a_order = isset($sort_order[$a]) ? $sort_order[$a] : 999;
                $b_order = isset($sort_order[$b]) ? $sort_order[$b] : 999;
                return $a_order - $b_order;
            });
        }
        ?>
        
        <?php 
        // For rentals, show available units count; for condos, show unit types
        if ($is_rental) {
            // Get available units using the flexible structure
            if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                $available_units_display = Maloney_Listings_Available_Units_Fields::format_availability_display($post_id);
                
                if (!empty($available_units_display)) : ?>
                <div class="detail-item">
                    <strong><?php _e('Available Units:', 'maloney-listings'); ?></strong>
                    <span class="unit-types">
                        <?php echo esc_html($available_units_display); ?>
                    </span>
                </div>
                <?php endif;
            }
        } elseif (!empty($available_units)) : ?>
            <div class="detail-item">
                <strong><?php _e('Available Units:', 'maloney-listings'); ?></strong>
                <span class="unit-types">
                    <?php echo esc_html(implode(', ', $available_units)); ?>
                </span>
            </div>
        <?php endif; ?>
        
        <div class="detail-row">
            <?php if ($bathrooms) : ?>
                <div class="detail-item">
                    <strong><?php _e('Bathrooms:', 'maloney-listings'); ?></strong>
                    <?php echo esc_html($bathrooms); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($square_feet) : ?>
                <div class="detail-item">
                    <strong><?php _e('Square Feet:', 'maloney-listings'); ?></strong>
                    <?php echo number_format($square_feet); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($address) : ?>
            <div class="detail-item">
                <strong><?php _e('Address:', 'maloney-listings'); ?></strong>
                <?php echo esc_html($address); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($location && !is_wp_error($location)) : ?>
            <div class="detail-item">
                <strong><?php _e('Location:', 'maloney-listings'); ?></strong>
                <?php
                $location_names = array();
                foreach ($location as $loc) {
                    $location_names[] = $loc->name;
                }
                echo esc_html(implode(', ', $location_names));
                ?>
            </div>
        <?php endif; ?>
        
        <?php
        // Get income limits from Toolset field and map to display label
        $income_limits = get_post_meta($post_id, 'wpcf-income-limits', true);
        if (empty($income_limits) && $income_limits !== '0' && $income_limits !== 0) {
            $income_limits = get_post_meta($post_id, '_listing_income_limits', true);
        }
        
        if (!empty($income_limits) || $income_limits === '0' || $income_limits === 0) :
            $income_limits_display = class_exists('Maloney_Listings_Custom_Fields') 
                ? Maloney_Listings_Custom_Fields::map_income_limits_display($income_limits) 
                : $income_limits;
        ?>
            <div class="detail-item">
                <strong><?php _e('Income Limits:', 'maloney-listings'); ?></strong>
                <?php echo esc_html($income_limits_display); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($income_level_min || $income_level_max) : ?>
            <div class="detail-item">
                <strong><?php _e('Income Level:', 'maloney-listings'); ?></strong>
                <?php
                if ($income_level_min && $income_level_max) {
                    echo '$' . number_format($income_level_min) . ' - $' . number_format($income_level_max);
                } elseif ($income_level_min) {
                    echo '$' . number_format($income_level_min) . '+';
                } elseif ($income_level_max) {
                    echo 'Up to $' . number_format($income_level_max);
                }
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ($availability_date) : ?>
            <div class="detail-item">
                <strong><?php _e('Availability Date:', 'maloney-listings'); ?></strong>
                <?php echo date_i18n(get_option('date_format'), strtotime($availability_date)); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($amenities && !is_wp_error($amenities)) : ?>
        <div class="listing-amenities">
            <h3><?php _e('Amenities', 'maloney-listings'); ?></h3>
            <ul>
                <?php foreach ($amenities as $amenity) : ?>
                    <li><?php echo esc_html($amenity->name); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="listing-content">
        <?php the_content(); ?>
    </div>
    
    <?php if ($latitude && $longitude) : ?>
        <div class="listing-map" id="listing-single-map" data-lat="<?php echo esc_attr($latitude); ?>" data-lng="<?php echo esc_attr($longitude); ?>" style="height: 400px; width: 100%; margin: 30px 0;"></div>
    <?php endif; ?>
    
    <?php if ($listing_status && !is_wp_error($listing_status) && $listing_status[0]->slug !== 'available') : ?>
        <div class="vacancy-notification-form">
            <h3><?php _e('Notify Me When Available', 'maloney-listings'); ?></h3>
            <form id="vacancy-notify-form">
                <input type="hidden" name="listing_id" value="<?php echo esc_attr($post_id); ?>" />
                <div class="form-group">
                    <label for="notify_email"><?php _e('Email Address', 'maloney-listings'); ?> *</label>
                    <input type="email" id="notify_email" name="email" required />
                </div>
                <div class="form-group">
                    <label for="notify_name"><?php _e('Name', 'maloney-listings'); ?></label>
                    <input type="text" id="notify_name" name="name" />
                </div>
                <div class="form-group">
                    <label for="notify_phone"><?php _e('Phone', 'maloney-listings'); ?></label>
                    <input type="tel" id="notify_phone" name="phone" />
                </div>
                <button type="submit" class="vacancy-notify-button"><?php _e('Notify Me', 'maloney-listings'); ?></button>
                <div id="vacancy-notify-message"></div>
            </form>
        </div>
    <?php endif; ?>
    
</article>

<!-- Similar Properties -->
<div class="similar-properties">
    <h2><?php _e('Similar Properties', 'maloney-listings'); ?></h2>
    <div id="similar-listings" class="listings-grid">
        <div class="loading"><?php _e('Loading similar properties...', 'maloney-listings'); ?></div>
    </div>
</div>

