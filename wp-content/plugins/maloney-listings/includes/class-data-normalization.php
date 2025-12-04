<?php
/**
 * Data Normalization
 * - Derives normalized numeric fields for bedrooms/bathrooms from multiple sources
 * - Helps filters work even when values live inside Toolset or Ninja Tables
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Data_Normalization {

    public function __construct() {
        // Run after most meta has been saved
        add_action('save_post_listing', array($this, 'normalize_listing_fields'), 30, 3);
    }

    public function normalize_listing_fields($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        $this->maybe_set_numeric_field($post_id, 'bedrooms');
        $this->maybe_set_numeric_field($post_id, 'bathrooms');
    }

    /**
     * Public utility: Normalize a single post on demand
     */
    public static function normalize_post($post_id) {
        $self = new self();
        $self->maybe_set_numeric_field($post_id, 'bedrooms');
        $self->maybe_set_numeric_field($post_id, 'bathrooms');
    }

    /**
     * Public utility: Normalize all listings
     * Returns number of posts processed
     */
    public static function normalize_all() {
        $args = array(
            'post_type' => 'listing',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $ids = get_posts($args);
        foreach ($ids as $id) {
            self::normalize_post($id);
        }
        return is_array($ids) ? count($ids) : 0;
    }

    private function maybe_set_numeric_field($post_id, $field) {
        $key_normalized = "_listing_{$field}";
        $key_toolset   = "wpcf-{$field}";

        $current = get_post_meta($post_id, $key_normalized, true);
        if ($current !== '' && $current !== null) {
            return; // Already normalized
        }

        // 1) Direct Toolset field
        $raw = get_post_meta($post_id, $key_toolset, true);
        $val = self::extract_number($raw);
        if ($val !== null) {
            update_post_meta($post_id, $key_normalized, $val);
            return;
        }

        // 2) Unit sizes array (Toolset complex field)
        $unit_sizes = get_post_meta($post_id, 'wpcf-unit-sizes', true);
        $unit_sizes = maybe_unserialize($unit_sizes);
        if (is_array($unit_sizes) && !empty($unit_sizes)) {
            $numbers = array();
            foreach ($unit_sizes as $unit) {
                if (!is_array($unit)) continue;
                // Common keys that might exist
                $candidates = array(
                    $field,
                    substr($field, 0, 4), // bed, bath
                    $field === 'bathrooms' ? 'baths' : 'beds'
                );
                foreach ($candidates as $cand) {
                    if (isset($unit[$cand])) {
                        $n = self::extract_number($unit[$cand]);
                        if ($n !== null) $numbers[] = $n;
                    }
                }
            }
            if (!empty($numbers)) {
                // Use max so filters like 3+ / 4+ can match
                $derived = max($numbers);
                update_post_meta($post_id, $key_normalized, $derived);
                return;
            }
        }

        // 3) Parse Ninja Tables (vacancy or condo tables)
        $shortcode = get_post_meta($post_id, '_listing_vacancy_table', true);
        if (empty($shortcode)) {
            $shortcode = get_post_meta($post_id, 'wpcf-vacancy-table', true);
        }
        if (empty($shortcode)) {
            $shortcode = get_post_meta($post_id, '_listing_current_condo_listings_table', true);
        }
        if (empty($shortcode)) {
            $shortcode = get_post_meta($post_id, 'wpcf-current-condo-listings-table', true);
        }

        $table_id = self::extract_ninja_table_id($shortcode);
        if ($table_id) {
            $numbers = self::extract_numbers_from_ninja_table($table_id, $field);
            if (!empty($numbers)) {
                update_post_meta($post_id, $key_normalized, max($numbers));
                return;
            }
        }
    }

    protected static function extract_number($value) {
        if ($value === '' || $value === null) return null;
        // Handle arrays - if it's an array, try to get the first element
        if (is_array($value)) {
            if (empty($value)) return null;
            $value = reset($value); // Get first element
            if (is_array($value)) {
                // If still an array, try to get a numeric value from it
                foreach ($value as $v) {
                    if (is_numeric($v)) {
                        return floatval($v);
                    }
                }
                return null;
            }
        }
        // Now $value should be a scalar, convert to string safely
        $value_str = is_scalar($value) ? (string) $value : '';
        if ($value_str === '') return null;
        
        $lower = strtolower($value_str);
        if (strpos($lower, 'studio') !== false) return 0;
        if (is_numeric($value)) return floatval($value);
        // Try to pull the first number (supports 1.5 formats)
        if (preg_match('/(\d+(?:\.\d+)?)/', $value_str, $m)) {
            return floatval($m[1]);
        }
        return null;
    }

    protected static function extract_ninja_table_id($shortcode) {
        if (!is_string($shortcode) || $shortcode === '') return null;
        if (preg_match('/\bid\s*=\s*"?(\d+)"?/i', $shortcode, $m)) {
            return intval($m[1]);
        }
        return null;
    }

    protected static function extract_numbers_from_ninja_table($table_id, $field) {
        global $wpdb;
        $results = array();
        $table = $wpdb->prefix . 'ninja_table_items';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return $results;
        }

        // Detect usable column name
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $data_col = null;
        $candidates = array('data','row_data','item_data','values','json');
        foreach ($candidates as $cand) {
            if (in_array($cand, (array) $columns, true)) { $data_col = $cand; break; }
        }
        if (!$data_col) { return $results; }

        // Get rows for table
        $rows = $wpdb->get_results($wpdb->prepare("SELECT {$data_col} FROM {$table} WHERE table_id = %d", $table_id));
        if (empty($rows)) return $results;

        foreach ($rows as $row) {
            $raw = isset($row->$data_col) ? $row->$data_col : '';
            $data = json_decode($raw, true);
            if (!is_array($data)) continue;
            foreach ($data as $key => $val) {
                // Look for column keys that suggest bed/bath
                $key_l = strtolower($key);
                if ($field === 'bedrooms' && (strpos($key_l, 'bed') !== false || strpos($key_l, 'br') !== false)) {
                    $n = self::extract_number($val);
                    if ($n !== null) $results[] = $n;
                }
                if ($field === 'bathrooms' && (strpos($key_l, 'bath') !== false || strpos($key_l, 'ba') !== false)) {
                    $n = self::extract_number($val);
                    if ($n !== null) $results[] = $n;
                }
            }
        }

        return $results;
    }

    /**
     * Derive number for a field without persisting.
     */
    public static function derive_number($post_id, $field) {
        $key_toolset = "wpcf-{$field}";

        $raw = get_post_meta($post_id, $key_toolset, true);
        $val = self::extract_number($raw);
        if ($val !== null) return $val;

        $unit_sizes = get_post_meta($post_id, 'wpcf-unit-sizes', true);
        $unit_sizes = maybe_unserialize($unit_sizes);
        if (is_array($unit_sizes) && !empty($unit_sizes)) {
            $numbers = array();
            // If Toolset stored option keys (wpcf-fields-checkboxes-option-*) => '1', map to option titles
            $mapped_titles = array();
            if (function_exists('wpcf_admin_fields_get_fields')) {
                $all_fields = wpcf_admin_fields_get_fields();
                foreach ($all_fields as $fid => $f) {
                    if (!empty($f['meta_key']) && $f['meta_key'] === 'wpcf-unit-sizes' && !empty($f['data']['options'])) {
                        foreach ($f['data']['options'] as $okey => $odata) {
                            $mapped_titles[$okey] = isset($odata['title']) ? $odata['title'] : '';
                        }
                        break;
                    }
                }
            }
            foreach ($unit_sizes as $unit) {
                if (is_array($unit)) {
                    $candidates = array($field, substr($field, 0, 4), $field === 'bathrooms' ? 'baths' : 'beds');
                    foreach ($candidates as $cand) {
                        if (isset($unit[$cand])) {
                            $n = self::extract_number($unit[$cand]);
                            if ($n !== null) $numbers[] = $n;
                        }
                    }
                } elseif (is_string($unit)) {
                    $n = self::extract_number($unit);
                    if ($n !== null) $numbers[] = $n;
                }
            }
            // If no numbers found and we have mapped titles, parse titles from option keys present
            if (empty($numbers) && !empty($mapped_titles) && is_array($unit_sizes)) {
                foreach ($unit_sizes as $okey => $present) {
                    if (empty($present) || (is_array($present) && !in_array('1', $present, true))) continue;
                    if (isset($mapped_titles[$okey])) {
                        $title = strtolower($mapped_titles[$okey]);
                        if ($field === 'bedrooms') {
                            if (strpos($title, 'studio') !== false) $numbers[] = 0;
                            elseif (strpos($title, 'one') !== false) $numbers[] = 1;
                            elseif (strpos($title, 'two') !== false) $numbers[] = 2;
                            elseif (strpos($title, 'three') !== false) $numbers[] = 3;
                            elseif (strpos($title, 'four') !== false) $numbers[] = 4;
                        } else if ($field === 'bathrooms') {
                            $n = self::extract_number($title);
                            if ($n !== null) $numbers[] = $n;
                        }
                    }
                }
            }
            if (!empty($numbers)) return max($numbers);
        }

        $shortcode = get_post_meta($post_id, '_listing_vacancy_table', true);
        if (empty($shortcode)) $shortcode = get_post_meta($post_id, 'wpcf-vacancy-table', true);
        if (empty($shortcode)) $shortcode = get_post_meta($post_id, '_listing_current_condo_listings_table', true);
        if (empty($shortcode)) $shortcode = get_post_meta($post_id, 'wpcf-current-condo-listings-table', true);

        $table_id = self::extract_ninja_table_id($shortcode);
        if ($table_id) {
            $numbers = self::extract_numbers_from_ninja_table($table_id, $field);
            if (!empty($numbers)) return max($numbers);
        }

        return null;
    }

    /**
     * Dry-run simulation across all listings: counts missing and would-set.
     */
    public static function simulate_all() {
        $args = array(
            'post_type' => 'listing',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $ids = get_posts($args);
        $stats = array(
            'total' => count($ids),
            'missing_bed' => 0,
            'missing_bath' => 0,
            'would_set_bed' => 0,
            'would_set_bath' => 0,
        );
        foreach ($ids as $id) {
            $bed_cur = get_post_meta($id, '_listing_bedrooms', true);
            $bath_cur = get_post_meta($id, '_listing_bathrooms', true);
            if ($bed_cur === '' || $bed_cur === null) {
                $stats['missing_bed']++;
                $derived = self::derive_number($id, 'bedrooms');
                if ($derived !== null) $stats['would_set_bed']++;
            }
            if ($bath_cur === '' || $bath_cur === null) {
                $stats['missing_bath']++;
                $derived = self::derive_number($id, 'bathrooms');
                if ($derived !== null) $stats['would_set_bath']++;
            }
        }
        return $stats;
    }
}
