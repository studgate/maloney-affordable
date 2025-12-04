<?php
/**
 * Migration Class
 * Migrates existing condominium and rental posts to unified listing post type
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Migration {
    
    private $source_post_types = array('condominiums', 'rental-properties');
    private $results = array(
        'migrated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'errors' => array(),
    );
    private static $is_migrating = false; // Flag to prevent geocoding during migration
    
    public function run_migration($source_post_types = null) {
        // Set flag to prevent geocoding during migration
        self::$is_migrating = true;
        
        // Use provided post types or default
        if (null === $source_post_types) {
            $source_post_types = $this->source_post_types;
        }
        
        // If form submitted, use selected types
        if (isset($_POST['source_post_types']) && is_array($_POST['source_post_types'])) {
            $source_post_types = array_map('sanitize_text_field', $_POST['source_post_types']);
        }
        
        // Field mapping - customize based on your existing fields
        $field_mapping = $this->get_field_mapping();
        
        foreach ($source_post_types as $post_type) {
            if (!post_type_exists($post_type)) {
                $this->results['errors'][] = "Post type '{$post_type}' does not exist. Skipping.";
                continue;
            }
            
            $posts = get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'any',
            ));
            
            if (empty($posts)) {
                $this->results['errors'][] = "No posts found for post type '{$post_type}'.";
                continue;
            }
            
            foreach ($posts as $old_post) {
                $this->migrate_post($old_post, $post_type, $field_mapping);
            }
        }
        
        // Flush rewrite rules after migration
        flush_rewrite_rules();
        
        // Reset flag
        self::$is_migrating = false;
        
        return $this->results;
    }
    
    /**
     * Check if migration is in progress (used by geocoding class to skip auto-geocoding)
     */
    public static function is_migrating() {
        return self::$is_migrating;
    }
    
    private function migrate_post($old_post, $source_type, $field_mapping) {
        // Check if this post was already migrated (duplicate prevention)
        $existing_migration = get_posts(array(
            'post_type' => 'listing',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_migrated_from_post_id',
                    'value' => $old_post->ID,
                    'compare' => '=',
                ),
            ),
        ));
        
        if (!empty($existing_migration)) {
            $this->results['skipped']++;
            $this->results['errors'][] = "Skipped '{$old_post->post_title}' (ID: {$old_post->ID}) - already migrated as listing ID: {$existing_migration[0]->ID}";
            return;
        }
        
        // Also check if a listing with the same title already exists (additional duplicate check)
        $existing_by_title = get_posts(array(
            'post_type' => 'listing',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'title' => $old_post->post_title,
            'suppress_filters' => false,
        ));
        
        // More reliable title check
        if (empty($existing_by_title)) {
            global $wpdb;
            $existing_title = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'listing' AND post_title = %s LIMIT 1",
                $old_post->post_title
            ));
            
            if ($existing_title) {
                // Check if it's the same source post
                $source_id = get_post_meta($existing_title, '_migrated_from_post_id', true);
                if ($source_id == $old_post->ID) {
                    $this->results['skipped']++;
                    $this->results['errors'][] = "Skipped '{$old_post->post_title}' (ID: {$old_post->ID}) - already migrated as listing ID: {$existing_title}";
                    return;
                }
            }
        } else {
            // Check if existing listing is from the same source
            $source_id = get_post_meta($existing_by_title[0]->ID, '_migrated_from_post_id', true);
            if ($source_id == $old_post->ID) {
                $this->results['skipped']++;
                $this->results['errors'][] = "Skipped '{$old_post->post_title}' (ID: {$old_post->ID}) - already migrated as listing ID: {$existing_by_title[0]->ID}";
                return;
            }
        }
        
        // Determine listing type
        $listing_type_slug = $this->determine_listing_type($source_type);
        
        // Create new listing post
        $new_post_data = array(
            'post_title' => $old_post->post_title,
            'post_content' => $old_post->post_content,
            'post_excerpt' => $old_post->post_excerpt,
            'post_status' => $old_post->post_status,
            'post_type' => 'listing',
            'post_date' => $old_post->post_date,
            'post_author' => $old_post->post_author,
        );
        
        $new_post_id = wp_insert_post($new_post_data);
        
        if (is_wp_error($new_post_id)) {
            $this->results['failed']++;
            $this->results['errors'][] = "Failed to create listing from {$old_post->post_title}: " . $new_post_id->get_error_message();
            return;
        }
        
        // Migrate featured image
        $thumbnail_id = get_post_thumbnail_id($old_post->ID);
        if ($thumbnail_id) {
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }
        
        // Migrate post format
        $post_format = get_post_format($old_post->ID);
        if ($post_format) {
            set_post_format($new_post_id, $post_format);
        }
        
        // Set listing type taxonomy
        $listing_type_term = get_term_by('slug', $listing_type_slug, 'listing_type');
        if ($listing_type_term) {
            wp_set_post_terms($new_post_id, array($listing_type_term->term_id), 'listing_type');
        }
        
        // Migrate custom fields
        $this->migrate_custom_fields($old_post->ID, $new_post_id, $field_mapping);
        
        // Extract zip code from address after migration (but don't geocode yet)
        // Geocoding will be done later through the "Geocode Addresses" tool
        if (class_exists('Maloney_Listings_Zip_Code_Extraction')) {
            Maloney_Listings_Zip_Code_Extraction::extract_and_save_zip($new_post_id, false);
        }

        // Derive and set numeric bedrooms/bathrooms from migrated data
        if (class_exists('Maloney_Listings_Data_Normalization')) {
            $bed = Maloney_Listings_Data_Normalization::derive_number($new_post_id, 'bedrooms');
            if ($bed !== null && $bed !== '') {
                update_post_meta($new_post_id, '_listing_bedrooms', $bed);
            }
            $bath = Maloney_Listings_Data_Normalization::derive_number($new_post_id, 'bathrooms');
            if ($bath !== null && $bath !== '') {
                update_post_meta($new_post_id, '_listing_bathrooms', $bath);
            }
        }

        // NOTE: Geocoding is DISABLED during migration to prevent system slowdown
        // Use the "Geocode Addresses" tool after migration is complete
        
        // Migrate taxonomies
        $this->migrate_taxonomies($old_post->ID, $new_post_id);
        
        // Create redirect from old URL to new URL
        $old_url = get_permalink($old_post->ID);
        $new_url = get_permalink($new_post_id);
        update_post_meta($new_post_id, '_migrated_from_post_id', $old_post->ID);
        update_post_meta($new_post_id, '_migrated_from_url', $old_url);
        
        $this->results['migrated']++;
    }
    
    private function determine_listing_type($source_type) {
        $type_map = array(
            'condominiums' => 'condo',
            'condominium' => 'condo',
            'condo' => 'condo',
            'rental-properties' => 'rental',
            'rental-property' => 'rental',
            'rental' => 'rental',
        );
        
        return isset($type_map[$source_type]) ? $type_map[$source_type] : 'condo';
    }
    
    
    private function migrate_taxonomies($old_post_id, $new_post_id) {
        // Get all taxonomies from old post
        $taxonomies = get_object_taxonomies(get_post_type($old_post_id));
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($old_post_id, $taxonomy);
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $term_ids = wp_list_pluck($terms, 'term_id');
                
                // Try to map to new taxonomy
                $new_taxonomy = $this->map_taxonomy($taxonomy);
                
                if ($new_taxonomy) {
                    wp_set_post_terms($new_post_id, $term_ids, $new_taxonomy);
                }
            }
        }
    }
    
    private function map_taxonomy($old_taxonomy) {
        // Map old taxonomies to new ones
        $taxonomy_map = array(
            'location' => 'location',
            'amenities' => 'amenities',
            'property_status' => 'listing_status',
            'status' => 'listing_status',
        );
        
        return isset($taxonomy_map[$old_taxonomy]) ? $taxonomy_map[$old_taxonomy] : null;
    }
    
    private function get_field_mapping() {
        // Field mapping based on actual Toolset Types fields from rental-properties and condominiums
        
        // Common fields (shared by both post types)
        $common_fields = array(
            // Basic property info
            'wpcf-property-name' => '_listing_property_name',
            'wpcf-city' => '_listing_city',
            'wpcf-state-1' => '_listing_state',
            'wpcf-address' => '_listing_address',
            'wpcf-telephone' => '_listing_telephone',
            'wpcf-email' => '_listing_email',
            
            // Property details
            'wpcf-main-marketing-text' => '_listing_main_marketing_text',
            'wpcf-extra-top-level-info' => '_listing_extra_top_level_info',
            'wpcf-features' => '_listing_features', // TODO: Future task - Migrate Features field values into Amenities taxonomy
            'wpcf-amenities-photo' => '_listing_amenities_photo',
            'wpcf-neighborhood' => '_listing_neighborhood',
            'wpcf-eligibility' => '_listing_eligibility',
            'wpcf-maximum-asset-limits' => '_listing_maximum_asset_limits',
            'wpcf-additional-content' => '_listing_additional_content',
            'wpcf-faq' => '_listing_faq',
            'wpcf-property-photo' => '_listing_property_photo',
            
            // Unit info
            'wpcf-unit-sizes' => '_listing_unit_sizes', // Array field
            'wpcf-income-limits' => '_listing_income_limits',
            
            // Application info
            'wpcf-application-period-starts' => '_listing_application_period_starts',
            'wpcf-application-distribution-ends' => '_listing_application_distribution_ends',
            'wpcf-application-period-ends' => '_listing_application_period_ends',
            'wpcf-application-info' => '_listing_application_info',
            'wpcf-online-application-url' => '_listing_online_application_url',
            'wpcf-lottery-process' => '_listing_lottery_process',
            
            // Other
            'om_disable_all_campaigns' => '_listing_om_disable_all_campaigns',
        );
        
        // Rental-specific fields
        $rental_fields = array(
            'wpcf-status' => '_listing_rental_status', // Rental status
            'wpcf-vacancy-table' => '_listing_vacancy_table', // Ninja Tables shortcode
        );
        
        // Condo-specific fields
        $condo_fields = array(
            'wpcf-condo-status' => '_listing_condo_status', // Condo status
            'wpcf-current-condo-listings-table' => '_listing_current_condo_listings_table', // Ninja Tables shortcode
            'wpcf-form-url' => '_listing_form_url',
        );
        
        // Combine all fields
        return array_merge($common_fields, $rental_fields, $condo_fields);
    }
    
    /**
     * Migrate fields with special handling for arrays and special types
     * This migrates ALL fields including Toolset Types fields
     */
    private function migrate_custom_fields($old_post_id, $new_post_id, $field_mapping) {
        // Get all meta from old post
        global $wpdb;
        $all_meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
            $old_post_id
        ), ARRAY_A);
        
        // Convert to associative array for easier access
        $meta_array = array();
        foreach ($all_meta as $meta) {
            if (!isset($meta_array[$meta['meta_key']])) {
                $meta_array[$meta['meta_key']] = array();
            }
            $meta_array[$meta['meta_key']][] = $meta['meta_value'];
        }
        
        // Get source post type to determine which fields to migrate
        $source_type = get_post_type($old_post_id);
        
        // Use Toolset Types API if available to get fields
        if (function_exists('wpcf_admin_fields_get_groups')) {
            $all_fields = wpcf_admin_fields_get_fields();
            if (!empty($all_fields)) {
                foreach ($all_fields as $field_id => $field) {
                    if (!isset($field['meta_key'])) continue;
                    
                    $field_key = $field['meta_key'];
                    // Try to get field value (Toolset Types can store in different formats)
                    $field_value = get_post_meta($old_post_id, $field_key, false);
                    
                    // Also try with wpcf- prefix
                    if (empty($field_value) && strpos($field_key, 'wpcf-') !== 0) {
                        $field_value = get_post_meta($old_post_id, 'wpcf-' . $field_key, false);
                    }
                    
                    if (!empty($field_value)) {
                        // Handle different field types
                        if (in_array($field['type'], array('checkboxes', 'checkbox'))) {
                            // Multiple values - store all
                            delete_post_meta($new_post_id, $field_key);
                            foreach ($field_value as $val) {
                                add_post_meta($new_post_id, $field_key, $val);
                            }
                        } elseif (in_array($field['type'], array('file', 'image', 'video', 'audio'))) {
                            // File/image fields - preserve IDs
                            if (is_array($field_value)) {
                                foreach ($field_value as $val) {
                                    add_post_meta($new_post_id, $field_key, $val);
                                }
                            } else {
                                update_post_meta($new_post_id, $field_key, $field_value);
                            }
                        } else {
                            // Single value fields
                            $value = is_array($field_value) ? $field_value[0] : $field_value;
                            update_post_meta($new_post_id, $field_key, $value);
                        }
                    }
                }
            }
        }
        
        // Also migrate mapped fields (backup approach)
        foreach ($field_mapping as $old_field => $new_field) {
            // Skip rental-specific fields if migrating from condos
            if ($source_type === 'condominiums' && in_array($old_field, array('wpcf-status', 'wpcf-vacancy-table'))) {
                continue;
            }
            
            // Skip condo-specific fields if migrating from rentals
            if ($source_type === 'rental-properties' && in_array($old_field, array('wpcf-condo-status', 'wpcf-current-condo-listings-table', 'wpcf-form-url'))) {
                continue;
            }
            
            // Check both direct field name and with wpcf- prefix
            $field_keys = array($old_field);
            if (strpos($old_field, 'wpcf-') !== 0) {
                $field_keys[] = 'wpcf-' . $old_field;
            }
            
            foreach ($field_keys as $field_key) {
                if (isset($meta_array[$field_key])) {
                    $value = $meta_array[$field_key];
                    // Handle array of values
                    if (is_array($value) && count($value) === 1) {
                        $value = $value[0];
                    }
                    
                    // Handle array fields
                    if ($old_field === 'wpcf-unit-sizes' || $field_key === 'wpcf-unit-sizes') {
                        $value = maybe_unserialize($value);
                    }
                    
                    update_post_meta($new_post_id, $new_field, $value);
                    break; // Found it, move on
                }
            }
        }
        
        // Migrate ALL remaining wpcf- fields (preserve them as-is)
        // This is critical - we need to preserve ALL Toolset Types fields
        foreach ($meta_array as $key => $values) {
            // Skip if already mapped to new field name
            $already_mapped = false;
            foreach ($field_mapping as $old_field => $new_field) {
                if ($key === $old_field || $key === $new_field) {
                    $already_mapped = true;
                    break;
                }
            }
            if ($already_mapped) {
                continue;
            }
            
            // Skip WordPress internal fields and Toolset field group assignment meta
            // These should NOT be copied as they are post-type specific and managed by Toolset
            $skip_fields = array(
                '_edit_lock', 
                '_edit_last', 
                '_thumbnail_id', 
                '_wp_page_template', 
                '_wp_old_slug', 
                '_wpcf_group_post_types',  // Old Toolset meta key
                '_wp_types_group_post_types',  // Current Toolset field group assignment meta (CRITICAL - causes fatal errors if copied as array)
                '_wp_types_group_terms',  // Toolset taxonomy assignment meta
                '_wp_types_group_templates',  // Toolset template assignment meta
            );
            if (in_array($key, $skip_fields)) {
                continue;
            }
            
            // Also skip any meta key that starts with _wp_types_group_ (Toolset field group assignment meta)
            if (strpos($key, '_wp_types_group_') === 0) {
                continue;
            }
            
            // Preserve ALL wpcf- fields (Toolset Types fields) - this is essential!
            if (strpos($key, 'wpcf-') === 0) {
                if (is_array($values) && count($values) > 1) {
                    // Multiple values - store all
                    delete_post_meta($new_post_id, $key);
                    foreach ($values as $v) {
                        $unserialized = maybe_unserialize($v);
                        add_post_meta($new_post_id, $key, $unserialized);
                    }
                } else {
                    $value = is_array($values) ? $values[0] : $values;
                    $value = maybe_unserialize($value);
                    update_post_meta($new_post_id, $key, $value);
                }
            }
            
            // Also preserve _wpcf- fields (Toolset Types internal fields)
            // BUT skip field group assignment meta which is already handled above
            if (strpos($key, '_wpcf-') === 0 && strpos($key, '_wpcf_group_') !== 0) {
                $value = is_array($values) && count($values) === 1 ? $values[0] : $values;
                $value = maybe_unserialize($value);
                update_post_meta($new_post_id, $key, $value);
            }
            
            // Skip Toolset field group assignment meta (already skipped above, but double-check)
            // These are managed by Toolset and should not be copied
            if (strpos($key, 'types_group_') !== false || strpos($key, 'toolset_group_') !== false) {
                continue;
            }
        }
        
        // Migrate post attachments (photos) - ALL attachments including galleries
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_parent' => $old_post_id,
            'post_status' => 'any',
        ));
        
        foreach ($attachments as $attachment) {
            // Update attachment parent to new post
            wp_update_post(array(
                'ID' => $attachment->ID,
                'post_parent' => $new_post_id,
            ));
        }
        
        // Also check for attachments in custom fields (gallery fields)
        foreach ($meta_array as $key => $values) {
            if (strpos($key, 'wpcf-') === 0) {
                foreach ($values as $value) {
                    $value = maybe_unserialize($value);
                    // Check if value contains attachment IDs
                    if (is_numeric($value)) {
                        $attachment = get_post($value);
                        if ($attachment && $attachment->post_type === 'attachment' && $attachment->post_parent == $old_post_id) {
                            wp_update_post(array(
                                'ID' => $value,
                                'post_parent' => $new_post_id,
                            ));
                        }
                    } elseif (is_array($value)) {
                        // Array of IDs
                        foreach ($value as $att_id) {
                            if (is_numeric($att_id)) {
                                $attachment = get_post($att_id);
                                if ($attachment && $attachment->post_type === 'attachment' && $attachment->post_parent == $old_post_id) {
                                    wp_update_post(array(
                                        'ID' => $att_id,
                                        'post_parent' => $new_post_id,
                                    ));
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
