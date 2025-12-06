<?php
/**
 * Single Listing Template
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

get_header(); ?>

<div id="main-content">
    <div class="container">
        <div id="content-area" class="clearfix">
            <div id="left-area">
                
                <?php while (have_posts()) : the_post(); ?>
                    <?php
                    $post_id = get_the_ID();
                    $listing_type = get_the_terms($post_id, 'listing_type');
                    $listing_status = get_the_terms($post_id, 'listing_status');
                    $location = get_the_terms($post_id, 'location');
                    $amenities = get_the_terms($post_id, 'amenities');
                    
                    // Determine if this is a rental
                    $is_rental = false;
                    if ($listing_type && !is_wp_error($listing_type)) {
                        $type_slug = strtolower($listing_type[0]->slug);
                        if (strpos($type_slug, 'rental') !== false) {
                            $is_rental = true;
                        }
                    }
                    
                    // Get fields from Toolset first, then fallback to standard meta
                    $bathrooms = get_post_meta($post_id, 'wpcf-bathrooms', true);
                    if (empty($bathrooms)) {
                        $bathrooms = get_post_meta($post_id, '_listing_bathrooms', true);
                    }
                    
                    $square_feet = get_post_meta($post_id, 'wpcf-square-feet', true);
                    if (empty($square_feet)) {
                        $square_feet = get_post_meta($post_id, '_listing_square_feet', true);
                    }
                    
                    $rent_price = get_post_meta($post_id, 'wpcf-rent-price', true);
                    if (empty($rent_price)) {
                        $rent_price = get_post_meta($post_id, '_listing_rent_price', true);
                    }
                    
                    $purchase_price = get_post_meta($post_id, 'wpcf-purchase-price', true);
                    if (empty($purchase_price)) {
                        $purchase_price = get_post_meta($post_id, '_listing_purchase_price', true);
                    }
                    
                    $income_level_min = get_post_meta($post_id, '_listing_income_level_min', true);
                    $income_level_max = get_post_meta($post_id, '_listing_income_level_max', true);
                    
                    // Get address - check Toolset first, then build from parts
                    $address = get_post_meta($post_id, 'wpcf-address', true);
                    if (empty($address)) {
                        $address = get_post_meta($post_id, '_listing_address', true);
                    }
                    if (empty($address)) {
                        // Use ONLY the address field - no longer combines with city/town
                        $address = get_post_meta($post_id, 'wpcf-address', true);
                        if (empty($address)) {
                            $address = get_post_meta($post_id, '_listing_address', true);
                        }
                        $address = !empty($address) ? trim($address) : '';
                    }
                    
                    $latitude = get_post_meta($post_id, '_listing_latitude', true);
                    $longitude = get_post_meta($post_id, '_listing_longitude', true);
                    $availability_date = get_post_meta($post_id, '_listing_availability_date', true);
                    $type_class = $listing_type && !is_wp_error($listing_type) ? strtolower($listing_type[0]->slug) : '';
                    ?>
                    
                    <div class="listing-single-container">
                        <article id="post-<?php the_ID(); ?>" <?php post_class('listing-single'); ?> data-listing-id="<?php echo esc_attr($post_id); ?>">
                            
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
                            // Unit Types section removed - now handled by shortcode
                            ?>
                        
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
                            <?php $type_slug = ($listing_type && !is_wp_error($listing_type)) ? strtolower($listing_type[0]->slug) : ''; ?>
                            <div class="listing-map" id="listing-single-map" data-lat="<?php echo esc_attr($latitude); ?>" data-lng="<?php echo esc_attr($longitude); ?>" data-type="<?php echo esc_attr($type_slug); ?>" data-address="<?php echo esc_attr($address); ?>" style="height: 400px; width: 100%; margin: 30px 0;"></div>
                            <?php
                                // Note: Directions and Street View buttons are now added as map controls via JavaScript
                                // They appear on top of the map near the zoom controls
                            ?>
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
                            <div class="similar-slider-controls" style="text-align:right; margin:6px 0; padding-right: 12px;">
                                <button type="button" id="similar-prev" class="button">&#8592;</button>
                                <button type="button" id="similar-next" class="button">&#8594;</button>
                            </div>
                            <div id="similar-listings" class="similar-slider">
                                <?php
                                // Load similar listings via AJAX
                                ?>
                                <div class="loading"><?php _e('Loading similar properties...', 'maloney-listings'); ?></div>
                            </div>
                            <div id="similar-dots" class="similar-dots" aria-hidden="true"></div>
                        </div>
                    </div>
                    
                <?php endwhile; ?>
                
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
