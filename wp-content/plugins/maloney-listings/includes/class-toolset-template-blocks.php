<?php
/**
 * Toolset Template Blocks Manager
 * 
 * Allows programmatic insertion and management of Gutenberg blocks in Toolset Content Templates
 * 
 * Developer: Ralph Francois
 * Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Toolset_Template_Blocks {
    
    /**
     * Insert a block into a Toolset Content Template
     * 
     * @param int|string $template_id Template ID or slug
     * @param string $block_name Block name (e.g., 'maloney-listings/availability-block')
     * @param array $block_attributes Block attributes
     * @param string $position Where to insert: 'before', 'after', 'replace', 'append', 'prepend'
     * @param string $anchor_block_name Optional: Block name or content pattern to insert before/after
     * @param bool $search_by_content If true, search anchor by content pattern instead of block name
     * @return bool|WP_Error Success or error
     */
    public static function insert_block($template_id, $block_name, $block_attributes = array(), $position = 'append', $anchor_block_name = '', $search_by_content = false) {
        // Get the template
        $template = self::get_template($template_id);
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found.', 'maloney-listings'));
        }
        
        // Get current content
        $content = $template->post_content;
        
        // Parse blocks
        $blocks = parse_blocks($content);
        
        // Create new block
        $new_block = array(
            'blockName' => $block_name,
            'attrs' => $block_attributes,
            'innerContent' => array(),
            'innerBlocks' => array(),
        );
        
        // If it's a shortcode block, we need the shortcode content
        // This should be passed via $block_attributes['shortcode'] or handled by caller
        if ($block_name === 'core/shortcode' && isset($block_attributes['shortcode'])) {
            $new_block['innerContent'] = array($block_attributes['shortcode']);
        }
        
        // Insert block based on position
        switch ($position) {
            case 'prepend':
                array_unshift($blocks, $new_block);
                break;
                
            case 'append':
                $blocks[] = $new_block;
                break;
                
            case 'before':
                if (empty($anchor_block_name)) {
                    return new WP_Error('anchor_required', __('Anchor block name required for "before" position.', 'maloney-listings'));
                }
                $blocks = self::insert_block_before_recursive($blocks, $new_block, $anchor_block_name, $search_by_content);
                break;
                
            case 'after':
                if (empty($anchor_block_name)) {
                    return new WP_Error('anchor_required', __('Anchor block name required for "after" position.', 'maloney-listings'));
                }
                $blocks = self::insert_block_after_recursive($blocks, $new_block, $anchor_block_name, $search_by_content);
                break;
                
            case 'replace':
                if (empty($anchor_block_name)) {
                    return new WP_Error('anchor_required', __('Anchor block name required for "replace" position.', 'maloney-listings'));
                }
                $blocks = self::replace_anchor_block($blocks, $new_block, $anchor_block_name);
                break;
                
            default:
                return new WP_Error('invalid_position', __('Invalid position. Use: prepend, append, before, after, or replace.', 'maloney-listings'));
        }
        
        // Serialize blocks back to content
        $new_content = serialize_blocks($blocks);
        
        // Update template
        return self::update_template_content($template->ID, $new_content);
    }
    
    /**
     * Check if a block exists in a template
     * 
     * @param int|string $template_id Template ID or slug
     * @param string $block_name Block name to search for
     * @return bool
     */
    public static function block_exists($template_id, $block_name) {
        $template = self::get_template($template_id);
        if (!$template) {
            return false;
        }
        
        $blocks = parse_blocks($template->post_content);
        return self::find_block($blocks, $block_name) !== null;
    }
    
    /**
     * Remove a block from a template
     * 
     * @param int|string $template_id Template ID or slug
     * @param string $block_name Block name to remove
     * @return bool|WP_Error
     */
    public static function remove_block($template_id, $block_name) {
        $template = self::get_template($template_id);
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found.', 'maloney-listings'));
        }
        
        $blocks = parse_blocks($template->post_content);
        $filtered_blocks = self::remove_block_recursive($blocks, $block_name);
        
        $new_content = serialize_blocks($filtered_blocks);
        return self::update_template_content($template->ID, $new_content);
    }
    
    /**
     * Replace a block in a template
     * 
     * @param int|string $template_id Template ID or slug
     * @param string $old_block_name Block name to replace
     * @param string $new_block_name New block name
     * @param array $new_block_attributes New block attributes
     * @param string $new_shortcode Optional: If replacing with shortcode block, the shortcode content
     * @return bool|WP_Error
     */
    public static function replace_block($template_id, $old_block_name, $new_block_name, $new_block_attributes = array(), $new_shortcode = '') {
        $template = self::get_template($template_id);
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found.', 'maloney-listings'));
        }
        
        // Check if template has content
        if (empty($template->post_content)) {
            return new WP_Error('block_not_found', __('Template has no content.', 'maloney-listings'));
        }
        
        $blocks = parse_blocks($template->post_content);
        $replaced = false;
        $debug_info = array(); // For debugging
        
        // Replace blocks recursively
        $blocks = self::replace_block_recursive($blocks, $old_block_name, $new_block_name, $new_block_attributes, $new_shortcode, $replaced, $debug_info);
        
        if (!$replaced) {
            // Block not found - return error with code 'block_not_found' so caller can skip it
            return new WP_Error('block_not_found', sprintf(__('Block "%s" not found in template.', 'maloney-listings'), $old_block_name));
        }
        
        $new_content = serialize_blocks($blocks);
        return self::update_template_content($template->ID, $new_content);
    }
    
    /**
     * Replace blocks recursively
     * 
     * @param array $blocks Blocks array
     * @param string $old_block_name Block name to replace
     * @param string $new_block_name New block name
     * @param array $new_block_attributes New block attributes
     * @param string $new_shortcode Optional shortcode content
     * @param bool $replaced Reference to track if replacement occurred
     * @return array Modified blocks array
     */
    private static function replace_block_recursive($blocks, $old_block_name, $new_block_name, $new_block_attributes, $new_shortcode, &$replaced, &$debug_info = array()) {
        foreach ($blocks as $index => $block) {
            // Check if this block matches (by name or by content pattern)
            $matches = false;
            
            // Get block content from various possible locations
            // Toolset blocks often store content in innerHTML, so check that first
            $block_content = '';
            
            // Method 1: Check innerHTML (most common for Toolset blocks)
            // Toolset blocks store content like: <div class="tb-fields-and-text"><p> </p> [types field='vacancy-table'][/types] <p> </p></div>
            if (isset($block['innerHTML'])) {
                $raw_html = $block['innerHTML'];
                
                // CRITICAL: First check if raw HTML contains the search pattern directly
                // This is the most reliable check - if searching for "vacancy-table", check if it's in the HTML
                if (!empty($old_block_name) && stripos($raw_html, $old_block_name) !== false) {
                    // The pattern is in the HTML - extract the shortcode
                    if (preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\](.*?)\[\/types\]/s', $raw_html, $sc_matches)) {
                        $field_name = $sc_matches[1];
                        $block_content = $sc_matches[0]; // Full shortcode like [types field='vacancy-table'][/types]
                    } else {
                        // Pattern found but couldn't extract shortcode - use raw HTML
                        $block_content = $raw_html;
                    }
                } elseif (preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\](.*?)\[\/types\]/s', $raw_html, $sc_matches)) {
                    // Found a [types] shortcode - extract the field name and full shortcode
                    $field_name = $sc_matches[1];
                    $block_content = $sc_matches[0]; // Full shortcode like [types field='vacancy-table'][/types]
                } elseif (preg_match('/<div[^>]*>(.*?)<\/div>/s', $raw_html, $div_matches)) {
                    // Extract content from div wrapper, then strip HTML tags but keep shortcodes
                    $div_content = $div_matches[1];
                    // Try to extract shortcode from within the div content
                    if (preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\](.*?)\[\/types\]/s', $div_content, $sc_matches)) {
                        $block_content = $sc_matches[0];
                    } else {
                        // Strip tags but preserve shortcodes
                        $block_content = preg_replace('/<[^>]+>/', '', $div_content);
                        $block_content = trim($block_content);
                    }
                } else {
                    // Use raw HTML and strip tags, but preserve shortcodes
                    $block_content = preg_replace('/<[^>]+>/', '', $raw_html);
                    $block_content = trim($block_content);
                }
            }
            
            // Method 2: Check innerContent array
            if (empty($block_content) && isset($block['innerContent']) && is_array($block['innerContent'])) {
                // innerContent is an array, join it
                $joined = implode('', $block['innerContent']);
                // Try to extract shortcode first
                if (preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\](.*?)\[\/types\]/s', $joined, $sc_matches)) {
                    $block_content = $sc_matches[0];
                } else {
                    // Remove HTML tags to get just the text/shortcode
                    $block_content = strip_tags($joined);
                }
            }
            
            // Method 3: Check first innerContent element
            if (empty($block_content) && isset($block['innerContent'][0])) {
                $first_content = $block['innerContent'][0];
                // Try to extract shortcode
                if (preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\](.*?)\[\/types\]/s', $first_content, $sc_matches)) {
                    $block_content = $sc_matches[0];
                } else {
                    $block_content = strip_tags($first_content);
                }
            }
            
            // Clean up the content - remove extra whitespace
            $block_content = trim($block_content);
            
            // Store extracted field name for later use
            $extracted_field_name = '';
            if (preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\]/i', $block_content, $field_matches)) {
                $extracted_field_name = trim(strtolower($field_matches[1]));
            } elseif (isset($block['innerHTML']) && preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\]/i', $block['innerHTML'], $field_matches)) {
                $extracted_field_name = trim(strtolower($field_matches[1]));
            }
            
            // Store debug info for Toolset blocks
            if (isset($block['blockName']) && stripos($block['blockName'], 'toolset') !== false) {
                $debug_content = $block_content;
                if (empty($debug_content) && isset($block['innerHTML'])) {
                    $debug_content = substr($block['innerHTML'], 0, 150);
                }
                $debug_info[] = 'Block: ' . $block['blockName'] . ', Content: ' . substr($debug_content, 0, 80) . ', Field: ' . $extracted_field_name;
            }
            
            // Match by exact block name
            // BUT: For Toolset blocks, we need to verify content matches too (since all Toolset shortcode blocks use the same name)
            if (isset($block['blockName']) && $block['blockName'] === $old_block_name) {
                // If it's a Toolset block, we must verify content matches
                if (stripos($block['blockName'], 'toolset') !== false || 
                    stripos($block['blockName'], 'fields-and-text') !== false) {
                    // For Toolset blocks, only match if content also matches the pattern
                    if (stripos($old_block_name, 'vacancy-table') !== false) {
                        if (stripos($block_content, 'vacancy-table') !== false) {
                            $matches = true;
                        }
                    } elseif (stripos($old_block_name, 'availability') !== false ||
                              stripos($old_block_name, 'rental') !== false) {
                        if (stripos($block_content, 'availability') !== false ||
                            stripos($block_content, 'rental') !== false) {
                            $matches = true;
                        }
                    } elseif (!empty($block_content) && stripos($block_content, $old_block_name) !== false) {
                        // Generic match - pattern appears in content
                        $matches = true;
                    }
                    // If no content pattern specified, don't match Toolset blocks by name alone
                } else {
                    // For non-Toolset blocks, exact name match is fine
                    $matches = true;
                }
            }
            
            // Match Toolset blocks (toolset-blocks/fields-and-text) by CONTENT ONLY
            // We must match by content, not just block name, since all Toolset shortcode blocks use the same block name
            if (!$matches && isset($block['blockName']) && 
                (stripos($block['blockName'], 'toolset') !== false || 
                 stripos($block['blockName'], 'fields-and-text') !== false)) {
                
                // Use the extracted field name we already found (from earlier in the function)
                $field_name_in_content = $extracted_field_name;
                
                // Normalize the search pattern to lowercase for comparison
                $search_pattern = strtolower(trim($old_block_name));
                
                // Get raw HTML for checking (most reliable)
                $raw_html_check = isset($block['innerHTML']) ? $block['innerHTML'] : '';
                
                // CRITICAL: Only match if the CONTENT contains the EXACT pattern we're looking for
                // This prevents replacing ALL Toolset blocks - only the ones with matching content
                
                // STRICT MATCHING: For "vacancy-table", we need to match the [types field='vacancy-table'] shortcode
                // Priority: 1) Exact field name match, 2) Shortcode pattern in HTML, 3) Pattern in content
                
                // For "vacancy-table" specifically, check for the exact field name in the shortcode
                if ($search_pattern === 'vacancy-table') {
                    // Must have the exact field name in the shortcode
                    if ($field_name_in_content === 'vacancy-table') {
                        $matches = true;
                    }
                    // Or check if the shortcode pattern exists in HTML: [types field='vacancy-table']
                    elseif (!empty($raw_html_check) && preg_match('/\[types[^\]]*field=[\'"]?vacancy-table[\'"]?[^\]]*\]/i', $raw_html_check)) {
                        $matches = true;
                    }
                    // Or check if block content contains the exact shortcode
                    elseif (!empty($block_content) && preg_match('/\[types[^\]]*field=[\'"]?vacancy-table[\'"]?[^\]]*\]/i', $block_content)) {
                        $matches = true;
                    }
                }
                elseif ($search_pattern === 'current-condo-listings-table' || stripos($search_pattern, 'condo-listings-table') !== false) {
                    if ($field_name_in_content === 'current-condo-listings-table') {
                        $matches = true;
                    } elseif (!empty($raw_html_check) && preg_match('/\[types[^\]]*field=[\'"]?current-condo-listings-table[\'"]?[^\]]*\]/i', $raw_html_check)) {
                        $matches = true;
                    } elseif (!empty($block_content) && preg_match('/\[types[^\]]*field=[\'"]?current-condo-listings-table[\'"]?[^\]]*\]/i', $block_content)) {
                        $matches = true;
                    }
                }
                // For other patterns, use generic matching but still be careful
                elseif (!empty($search_pattern)) {
                    // Check extracted field name first (most precise)
                    if (!empty($field_name_in_content) && $field_name_in_content === $search_pattern) {
                        $matches = true;
                    }
                    // Check if shortcode pattern exists in HTML
                    elseif (!empty($raw_html_check) && preg_match('/\[types[^\]]*field=[\'"]?' . preg_quote($search_pattern, '/') . '[\'"]?[^\]]*\]/i', $raw_html_check)) {
                        $matches = true;
                    }
                }
                // REMOVED: All text-based matching that could match titles or descriptions
                // We ONLY match based on [types field='...'] shortcode patterns, not text content
            }
            
            // DO NOT match by exact block name alone for Toolset blocks
            // This would replace ALL toolset-blocks/fields-and-text blocks, not just the ones we want
            
            // Match by shortcode content patterns (core/shortcode blocks)
            if (!$matches && isset($block['blockName']) && $block['blockName'] === 'core/shortcode') {
                // Use specific pattern matching - only match exact phrases, not generic "availability"
                $old_block_lower = strtolower(trim($old_block_name));
                
                // Check for specific patterns first (most specific to least specific)
                if (stripos($old_block_lower, 'current rental availability') !== false) {
                    // Match only blocks with "current rental availability" (exact phrase)
                    if (stripos($block_content, 'current rental availability') !== false) {
                        $matches = true;
                    }
                } elseif (stripos($old_block_lower, 'rental availability') !== false) {
                    // Match only blocks with "rental availability" (exact phrase)
                    if (stripos($block_content, 'rental availability') !== false) {
                        $matches = true;
                    }
                } elseif ($old_block_lower === 'vacancy-table' || stripos($old_block_lower, 'vacancy-table') !== false) {
                    // Match only blocks with "vacancy-table" (exact term)
                    if (stripos($block_content, 'vacancy-table') !== false) {
                        $matches = true;
                    }
                } elseif ($old_block_lower === 'current-condo-listings-table' || stripos($old_block_lower, 'condo-listings-table') !== false) {
                    if (stripos($block_content, 'current-condo-listings-table') !== false) {
                        $matches = true;
                    }
                } elseif (stripos($old_block_lower, 'vacancy') !== false && stripos($old_block_lower, 'availability') === false) {
                    // If searching for "vacancy" (but not "availability"), match only "vacancy-table"
                    if (stripos($block_content, 'vacancy-table') !== false) {
                        $matches = true;
                    }
                } else {
                    // For other patterns, match by exact content or if old_block_name pattern matches content
                    if (stripos($block_content, $old_block_name) !== false ||
                        stripos($old_block_name, $block_content) !== false) {
                        $matches = true;
                    }
                }
            }
            
            // Generic pattern matching - ONLY for non-shortcode blocks and be very specific
            if (!$matches && !empty($block_content) && (!isset($block['blockName']) || $block['blockName'] !== 'core/shortcode')) {
                $old_block_lower = strtolower(trim($old_block_name));
                
                if ($old_block_lower === 'vacancy-table' || stripos($old_block_lower, 'vacancy-table') !== false) {
                    if (stripos($block_content, 'vacancy-table') !== false) {
                        $matches = true;
                    }
                } elseif ($old_block_lower === 'current-condo-listings-table' || stripos($old_block_lower, 'condo-listings-table') !== false) {
                    if (stripos($block_content, 'current-condo-listings-table') !== false) {
                        $matches = true;
                    }
                } elseif (stripos($old_block_lower, 'current rental availability') !== false) {
                    // Match only "current rental availability"
                    if (stripos($block_content, 'current rental availability') !== false) {
                        $matches = true;
                    }
                } elseif (stripos($old_block_lower, 'rental availability') !== false) {
                    // Match only "rental availability"
                    if (stripos($block_content, 'rental availability') !== false) {
                        $matches = true;
                    }
                }
                // DO NOT match generic "availability" - it's too broad and will match unwanted blocks
            }
            
            if ($matches) {
                // Create new block
                $new_block = array(
                    'blockName' => $new_block_name,
                    'attrs' => $new_block_attributes,
                    'innerContent' => array(),
                    'innerBlocks' => array(),
                );
                
                // If it's a shortcode block, set the innerContent
                if ($new_block_name === 'core/shortcode' && !empty($new_shortcode)) {
                    $new_block['innerContent'] = array($new_shortcode);
                }
                
                $blocks[$index] = $new_block;
                $replaced = true;
            } else {
                // Process inner blocks
                if (!empty($block['innerBlocks'])) {
                    $blocks[$index]['innerBlocks'] = self::replace_block_recursive(
                        $block['innerBlocks'],
                        $old_block_name,
                        $new_block_name,
                        $new_block_attributes,
                        $new_shortcode,
                        $replaced,
                        $debug_info
                    );
                }
            }
        }
        
        return $blocks;
    }
    
    /**
     * Replace blocks in all templates for a post type
     * 
     * @param string $post_type Post type slug
     * @param string $old_block_name Block name to replace
     * @param string $new_block_name New block name
     * @param array $new_block_attributes New block attributes
     * @param string $new_shortcode Optional shortcode content
     * @return array Results array with success/error for each template
     */
    public static function replace_block_for_post_type($post_type, $old_block_name, $new_block_name, $new_block_attributes = array(), $new_shortcode = '') {
        $templates = self::get_templates_for_post_type($post_type);
        $results = array();
        
        foreach ($templates as $template_id) {
            $result = self::replace_block($template_id, $old_block_name, $new_block_name, $new_block_attributes, $new_shortcode);
            $results[$template_id] = is_wp_error($result) ? $result : true;
        }
        
        return $results;
    }
    
    /**
     * Get all blocks from a template
     * 
     * @param int|string $template_id Template ID or slug
     * @return array|WP_Error
     */
    public static function get_blocks($template_id) {
        $template = self::get_template($template_id);
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found.', 'maloney-listings'));
        }
        
        return parse_blocks($template->post_content);
    }
    
    /**
     * Get a list of all blocks in a template (for debugging)
     * 
     * @param int|string $template_id Template ID or slug
     * @return array Array of block info
     */
    public static function list_blocks($template_id) {
        $blocks = self::get_blocks($template_id);
        if (is_wp_error($blocks)) {
            return $blocks;
        }
        
        $block_list = array();
        self::extract_block_info($blocks, $block_list);
        
        return $block_list;
    }
    
    /**
     * Extract block information recursively
     * 
     * @param array $blocks Blocks array
     * @param array $block_list Reference to array to populate
     * @param int $depth Current depth
     */
    private static function extract_block_info($blocks, &$block_list, $depth = 0) {
        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue; // Skip empty blocks
            }
            
            $content = '';
            if (isset($block['innerContent']) && is_array($block['innerContent'])) {
                $content = implode('', $block['innerContent']);
            } elseif (isset($block['innerHTML'])) {
                $content = $block['innerHTML'];
            }
            
            $block_list[] = array(
                'blockName' => $block['blockName'],
                'content' => $content,
                'depth' => $depth,
            );
            
            // Process inner blocks
            if (!empty($block['innerBlocks'])) {
                self::extract_block_info($block['innerBlocks'], $block_list, $depth + 1);
            }
        }
    }
    
    /**
     * Get a Toolset Content Template
     * 
     * @param int|string $template_id Template ID or slug
     * @return WP_Post|null
     */
    public static function get_template($template_id) {
        // Try by ID first
        if (is_numeric($template_id)) {
            $template = get_post(intval($template_id));
            if ($template && $template->post_type === 'view-template') {
                return $template;
            }
        }
        
        // Try by slug
        $template = get_page_by_path($template_id, OBJECT, 'view-template');
        if ($template) {
            return $template;
        }
        
        // Try using Toolset API if available
        if (class_exists('WPV_Content_Template')) {
            try {
                $ct = WPV_Content_Template::get_instance($template_id);
                if ($ct) {
                    return get_post($ct->id);
                }
            } catch (Exception $e) {
                // Fall through
            }
        }
        
        return null;
    }
    
    /**
     * Update template content
     * 
     * @param int $template_id Template ID
     * @param string $content New content
     * @return bool|WP_Error
     */
    private static function update_template_content($template_id, $content) {
        $result = wp_update_post(array(
            'ID' => $template_id,
            'post_content' => $content,
        ), true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Clear any caches
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('posts');
        }
        
        return true;
    }
    
    /**
     * Find a block in the blocks array
     * 
     * @param array $blocks Blocks array
     * @param string $block_name Block name to find
     * @return array|null Block or null if not found
     */
    private static function find_block($blocks, $block_name) {
        foreach ($blocks as $block) {
            if (isset($block['blockName']) && $block['blockName'] === $block_name) {
                return $block;
            }
            
            // Search in inner blocks
            if (!empty($block['innerBlocks'])) {
                $found = self::find_block($block['innerBlocks'], $block_name);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find block index in blocks array
     * 
     * @param array $blocks Blocks array
     * @param string $block_name Block name to find, or content pattern (e.g., "neighborhood")
     * @param bool $search_by_content If true, search by content pattern instead of block name
     * @return int|false Index or false if not found
     */
    public static function find_block_index($blocks, $block_name, $search_by_content = false) {
        foreach ($blocks as $index => $block) {
            // If searching by content pattern
            if ($search_by_content) {
                $block_content = '';
                
                // Get content from innerHTML
                if (isset($block['innerHTML'])) {
                    $block_content = $block['innerHTML'];
                }
                // Get content from innerContent array
                elseif (isset($block['innerContent']) && is_array($block['innerContent'])) {
                    $block_content = implode('', $block['innerContent']);
                }
                // Get content from first innerContent element
                elseif (isset($block['innerContent'][0])) {
                    $block_content = $block['innerContent'][0];
                }
                
                // Check if content contains the pattern (case-insensitive)
                if (!empty($block_content) && stripos($block_content, $block_name) !== false) {
                    return $index;
                }
                
                // Also check for Toolset field shortcodes
                if (preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\]/i', $block_content, $matches)) {
                    $field_name = strtolower(trim($matches[1]));
                    if ($field_name === strtolower(trim($block_name))) {
                        return $index;
                    }
                }
            } else {
                // Search by block name
                if (isset($block['blockName']) && $block['blockName'] === $block_name) {
                    return $index;
                }
            }
            
            // Search in inner blocks recursively
            if (!empty($block['innerBlocks'])) {
                $inner_index = self::find_block_index($block['innerBlocks'], $block_name, $search_by_content);
                if ($inner_index !== false) {
                    // Return a path or handle nested blocks differently
                    // For now, we'll search recursively at the top level
                }
            }
        }
        
        return false;
    }
    
    
    /**
     * Insert block after anchor block (recursive version)
     * 
     * @param array $blocks Blocks array
     * @param array $new_block Block to insert
     * @param string $anchor_block_name Anchor block name or content pattern
     * @param bool $search_by_content If true, search by content pattern instead of block name
     * @return array Modified blocks array
     */
    private static function insert_block_after_recursive($blocks, $new_block, $anchor_block_name, $search_by_content = false) {
        $found = false;
        $blocks = self::insert_block_after_recursive_helper($blocks, $new_block, $anchor_block_name, $search_by_content, $found);
        
        // If anchor not found, append
        if (!$found) {
            $blocks[] = $new_block;
        }
        
        return $blocks;
    }
    
    /**
     * Helper function to recursively insert block after anchor
     * 
     * @param array $blocks Blocks array
     * @param array $new_block Block to insert
     * @param string $anchor_block_name Anchor block name or content pattern
     * @param bool $search_by_content If true, search by content pattern
     * @param bool $found Reference to track if anchor was found
     * @return array Modified blocks array
     */
    private static function insert_block_after_recursive_helper($blocks, $new_block, $anchor_block_name, $search_by_content, &$found) {
        foreach ($blocks as $index => $block) {
            $matches = false;
            
            if ($search_by_content) {
                // Search by content pattern
                $block_content = '';
                
                if (isset($block['innerHTML'])) {
                    $block_content = $block['innerHTML'];
                } elseif (isset($block['innerContent']) && is_array($block['innerContent'])) {
                    $block_content = implode('', $block['innerContent']);
                } elseif (isset($block['innerContent'][0])) {
                    $block_content = $block['innerContent'][0];
                }
                
                // Check if content contains the pattern (case-insensitive)
                if (!empty($block_content) && stripos($block_content, $anchor_block_name) !== false) {
                    $matches = true;
                }
                
                // Also check for Toolset field shortcodes
                if (!$matches && preg_match('/\[types[^\]]*field=[\'"]?([^\'"\]]+)[\'"]?[^\]]*\]/i', $block_content, $matches_field)) {
                    $field_name = strtolower(trim($matches_field[1]));
                    if ($field_name === strtolower(trim($anchor_block_name))) {
                        $matches = true;
                    }
                }
            } else {
                // Search by block name
                if (isset($block['blockName']) && $block['blockName'] === $anchor_block_name) {
                    $matches = true;
                }
            }
            
            if ($matches) {
                // Found the anchor block - insert after it
                array_splice($blocks, $index + 1, 0, array($new_block));
                $found = true;
                return $blocks;
            }
            
            // Search in inner blocks
            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::insert_block_after_recursive_helper($block['innerBlocks'], $new_block, $anchor_block_name, $search_by_content, $found);
                $blocks[$index] = $block;
                if ($found) {
                    return $blocks;
                }
            }
        }
        
        return $blocks;
    }
    
    /**
     * Insert block after anchor block (non-recursive, for backward compatibility)
     * 
     * @param array $blocks Blocks array
     * @param array $new_block Block to insert
     * @param string $anchor_block_name Anchor block name or content pattern
     * @param bool $search_by_content If true, search by content pattern instead of block name
     * @return array Modified blocks array
     */
    private static function insert_block_after($blocks, $new_block, $anchor_block_name, $search_by_content = false) {
        return self::insert_block_after_recursive($blocks, $new_block, $anchor_block_name, $search_by_content);
    }
    
    /**
     * Replace anchor block with new block (helper for insert_block position='replace')
     * 
     * @param array $blocks Blocks array
     * @param array $new_block Block to insert
     * @param string $anchor_block_name Anchor block name
     * @return array Modified blocks array
     */
    private static function replace_anchor_block($blocks, $new_block, $anchor_block_name) {
        $index = self::find_block_index($blocks, $anchor_block_name);
        
        if ($index !== false) {
            $blocks[$index] = $new_block;
        } else {
            // If anchor not found, append
            $blocks[] = $new_block;
        }
        
        return $blocks;
    }
    
    /**
     * Remove block recursively
     * 
     * @param array $blocks Blocks array
     * @param string $block_name Block name to remove
     * @return array Filtered blocks array
     */
    private static function remove_block_recursive($blocks, $block_name) {
        $filtered = array();
        
        foreach ($blocks as $block) {
            // Skip this block if it matches
            if (isset($block['blockName']) && $block['blockName'] === $block_name) {
                continue;
            }
            
            // Process inner blocks
            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::remove_block_recursive($block['innerBlocks'], $block_name);
            }
            
            $filtered[] = $block;
        }
        
        return $filtered;
    }
    
    /**
     * Insert block into all templates for a post type
     * 
     * @param string $post_type Post type slug
     * @param string $block_name Block name
     * @param array $block_attributes Block attributes
     * @param string $position Position to insert
     * @param string $anchor_block_name Optional anchor block name or content pattern
     * @param bool $search_by_content If true, search anchor by content pattern
     * @return array Results array with success/error for each template
     */
    public static function insert_block_for_post_type($post_type, $block_name, $block_attributes = array(), $position = 'append', $anchor_block_name = '', $search_by_content = false) {
        $templates = self::get_templates_for_post_type($post_type);
        $results = array();
        
        foreach ($templates as $template_id) {
            $result = self::insert_block($template_id, $block_name, $block_attributes, $position, $anchor_block_name, $search_by_content);
            $results[$template_id] = is_wp_error($result) ? $result : true;
        }
        
        return $results;
    }
    
    /**
     * Insert map block after neighborhood block in all listing templates
     * 
     * @param bool $all_templates If true, process all Toolset Content Templates, not just those assigned to listing post type
     * @return array Results array with success/error for each template
     */
    public static function insert_map_after_neighborhood($all_templates = false) {
        if ($all_templates) {
            // Get ALL Toolset Content Templates
            $templates_query = new WP_Query(array(
                'post_type' => 'view-template',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC',
            ));
            $templates = array();
            if ($templates_query->have_posts()) {
                while ($templates_query->have_posts()) {
                    $templates_query->the_post();
                    $templates[] = get_the_ID();
                }
                wp_reset_postdata();
            }
        } else {
            // Get only templates assigned to listing post type
            $templates = self::get_templates_for_post_type('listing');
        }
        
        $results = array();
        
        foreach ($templates as $template_id) {
            $template = self::get_template($template_id);
            if (!$template) {
                $results[$template_id] = new WP_Error('template_not_found', __('Template not found.', 'maloney-listings'));
                continue;
            }
            
            // Check if map block already exists (check for both shortcode and existing map divs)
            $blocks = self::get_blocks($template_id);
            if (!is_wp_error($blocks)) {
                $has_map = false;
                
                // Recursively check all blocks for map
                $check_for_map = function($blocks) use (&$check_for_map, &$has_map) {
                    foreach ($blocks as $block) {
                        // Check for map shortcode
                        if (isset($block['blockName']) && $block['blockName'] === 'core/shortcode') {
                            $content = '';
                            if (isset($block['innerContent']) && is_array($block['innerContent'])) {
                                $content = implode('', $block['innerContent']);
                            } elseif (isset($block['innerContent'][0])) {
                                $content = $block['innerContent'][0];
                            }
                            if (stripos($content, 'maloney_listing_map') !== false || 
                                stripos($content, 'listing-single-map') !== false) {
                                $has_map = true;
                                return;
                            }
                        }
                        
                        // Check for existing map div in HTML
                        if (isset($block['innerHTML'])) {
                            if (stripos($block['innerHTML'], 'listing-single-map') !== false ||
                                stripos($block['innerHTML'], 'id="listing-map') !== false ||
                                (stripos($block['innerHTML'], 'class="listing-map') !== false && 
                                 stripos($block['innerHTML'], 'data-lat') !== false)) {
                                $has_map = true;
                                return;
                            }
                        }
                        
                        // Check inner blocks
                        if (!empty($block['innerBlocks'])) {
                            $check_for_map($block['innerBlocks']);
                            if ($has_map) return;
                        }
                    }
                };
                
                $check_for_map($blocks);
                
                if ($has_map) {
                    $results[$template_id] = new WP_Error('block_exists', __('Map block already exists in this template.', 'maloney-listings'));
                    continue;
                }
            }
            
            // Insert map block after neighborhood TEXT block (not the heading)
            // Look for blocks containing neighborhood description text patterns
            $result = self::insert_block_after_neighborhood_text($template_id, 'core/shortcode', array('shortcode' => '[maloney_listing_map height="400"]'));
            
            // If insertion failed, try appending instead
            if (is_wp_error($result) && $result->get_error_code() === 'anchor_not_found') {
                // Neighborhood text block not found - append to end
                $result = self::insert_block($template_id, 'core/shortcode', array('shortcode' => '[maloney_listing_map height="400"]'), 'append');
            }
            
            $results[$template_id] = is_wp_error($result) ? $result : true;
        }
        
        return $results;
    }
    
    /**
     * Insert block after the neighborhood text block (after the description, not the heading)
     * 
     * @param int|string $template_id Template ID
     * @param string $block_name Block name to insert
     * @param array $block_attributes Block attributes
     * @return bool|WP_Error
     */
    private static function insert_block_after_neighborhood_text($template_id, $block_name, $block_attributes = array()) {
        $template = self::get_template($template_id);
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found.', 'maloney-listings'));
        }
        
        $blocks = parse_blocks($template->post_content);
        if (empty($blocks)) {
            return new WP_Error('no_blocks', __('Template has no blocks.', 'maloney-listings'));
        }
        
        // Create new block
        $new_block = array(
            'blockName' => $block_name,
            'attrs' => $block_attributes,
            'innerContent' => array(),
            'innerBlocks' => array(),
        );
        
        // If it's a shortcode block, set the innerContent
        if ($block_name === 'core/shortcode' && isset($block_attributes['shortcode'])) {
            $new_block['innerContent'] = array($block_attributes['shortcode']);
        }
        
        // Patterns that indicate neighborhood description text (not just the heading)
        $neighborhood_text_patterns = array(
            'residents at',
            'enjoy a central location',
            'thriving',
            'medical area',
            'steps from',
            'world class hospitals',
            'brigham',
            'children\'s hospital',
            'harvard medical',
            'longwood',
        );
        
        // Strategy: Find the "NEIGHBORHOOD" heading, then find the next text block after it
        $neighborhood_heading_index = -1;
        $insert_after_index = -1;
        $found = false;
        
        // Toolset blocks heading data attribute (consistent across templates)
        $toolset_neighborhood_heading_id = '6cd577bebf8ea4bf960b67a0baa44753';
        
        // First pass: Find the neighborhood heading
        // Option 1: Search for specific Toolset data attribute (most reliable)
        foreach ($blocks as $index => $block) {
            $block_content = '';
            
            if (isset($block['innerHTML'])) {
                $block_content = $block['innerHTML'];
            } elseif (isset($block['innerContent']) && is_array($block['innerContent'])) {
                $block_content = implode('', $block['innerContent']);
            } elseif (isset($block['innerContent'][0])) {
                $block_content = $block['innerContent'][0];
            }
            
            // Primary method: Check for specific Toolset data attribute
            if (stripos($block_content, 'data-toolset-blocks-heading="' . $toolset_neighborhood_heading_id . '"') !== false) {
                // Verify it's a heading (must have <h tag or tb-heading class)
                if (stripos($block_content, '<h') !== false || stripos($block_content, 'tb-heading') !== false) {
                    $neighborhood_heading_index = $index;
                    break;
                }
            }
        }
        
        // Fallback: If data attribute not found, use text-based search (must be in heading)
        if ($neighborhood_heading_index < 0) {
            foreach ($blocks as $index => $block) {
                $block_content = '';
                
                if (isset($block['innerHTML'])) {
                    $block_content = $block['innerHTML'];
                } elseif (isset($block['innerContent']) && is_array($block['innerContent'])) {
                    $block_content = implode('', $block['innerContent']);
                } elseif (isset($block['innerContent'][0])) {
                    $block_content = $block['innerContent'][0];
                }
                
                // Look for "NEIGHBORHOOD" text (case-insensitive)
                if (stripos($block_content, 'neighborhood') !== false) {
                    // CRITICAL: Must be a heading - check for heading tags/classes first
                    // This ensures we never match "neighborhood" text within a paragraph
                    $is_heading = (
                        stripos($block_content, '<h') !== false || 
                        stripos($block_content, 'tb-heading') !== false ||
                        (stripos($block['blockName'] ?? '', 'heading') !== false)
                    );
                    
                    // Only proceed if it's confirmed to be a heading
                    if ($is_heading) {
                        // Additional check: verify it contains "NEIGHBORHOOD" as the main text
                        if (preg_match('/NEIGHBORHOOD/i', $block_content)) {
                            $neighborhood_heading_index = $index;
                            break;
                        }
                    }
                }
            }
        }
        
        // Second pass: If we found the heading, look for the next substantial text block after it
        if ($neighborhood_heading_index >= 0) {
            // Start searching from the block after the heading
            for ($i = $neighborhood_heading_index + 1; $i < count($blocks); $i++) {
                $block = $blocks[$i];
                $block_content = '';
                
                // Get block content
                if (isset($block['innerHTML'])) {
                    $block_content = $block['innerHTML'];
                } elseif (isset($block['innerContent']) && is_array($block['innerContent'])) {
                    $block_content = implode('', $block['innerContent']);
                } elseif (isset($block['innerContent'][0])) {
                    $block_content = $block['innerContent'][0];
                }
                
                // Skip empty blocks
                $clean_content = strip_tags($block_content);
                $clean_content = trim($clean_content);
                
                if (empty($clean_content)) {
                    continue;
                }
                
                // Skip if it's another heading
                if (stripos($block_content, '<h') !== false && 
                    (stripos($block_content, 'FEATURES') !== false || 
                     stripos($block_content, 'AMENITIES') !== false ||
                     stripos($block_content, 'AVAILABILITY') !== false)) {
                    // We've hit the next section, stop here
                    break;
                }
                
                // Check if this block contains description text patterns OR is a substantial text block
                $has_description_text = false;
                foreach ($neighborhood_text_patterns as $pattern) {
                    if (stripos($block_content, $pattern) !== false || stripos($clean_content, $pattern) !== false) {
                        $has_description_text = true;
                        break;
                    }
                }
                
                // Check if it's a paragraph or text block
                $is_text_block = (
                    stripos($block['blockName'] ?? '', 'paragraph') !== false ||
                    stripos($block['blockName'] ?? '', 'text') !== false ||
                    stripos($block['blockName'] ?? '', 'toolset') !== false ||
                    stripos($block_content, '<p') !== false
                );
                
                // If it's a substantial text block (more than 100 characters), use it
                // OR if it has description text and is more than 30 characters
                if (strlen($clean_content) > 100) {
                    // Substantial text block - insert after it
                    $insert_after_index = $i;
                    $found = true;
                    // Continue to find the LAST such block (in case there are multiple paragraphs)
                } elseif ($has_description_text && strlen($clean_content) > 30) {
                    // Has description text and is substantial
                    $insert_after_index = $i;
                    $found = true;
                }
            }
        }
        
        // If we found a block to insert after, do it
        if ($found && $insert_after_index >= 0) {
            // Insert after the found block
            array_splice($blocks, $insert_after_index + 1, 0, array($new_block));
            
            // Serialize and update
            $new_content = serialize_blocks($blocks);
            return self::update_template_content($template_id, $new_content);
        }
        
        // Fallback: If we found the heading but no text block, insert right after the heading
        if ($neighborhood_heading_index >= 0 && !$found) {
            array_splice($blocks, $neighborhood_heading_index + 1, 0, array($new_block));
            $new_content = serialize_blocks($blocks);
            return self::update_template_content($template_id, $new_content);
        }
        
        return new WP_Error('anchor_not_found', __('Neighborhood text block not found in template.', 'maloney-listings'));
    }
    
    /**
     * Get all template IDs for a post type
     * 
     * This gets all Toolset Content Templates that are assigned to the post type,
     * including default templates, conditional templates, and any custom templates.
     * 
     * @param string $post_type Post type slug
     * @return array Array of template IDs
     */
    private static function get_templates_for_post_type($post_type) {
        global $WPV_settings;
        
        $template_ids = array();
        
        // Get default template
        if (class_exists('WPV_Settings')) {
            if (!isset($WPV_settings)) {
                $WPV_settings = WPV_Settings::get_instance();
            }
            
            $default_key = 'views_template_for_' . $post_type;
            if (isset($WPV_settings[$default_key]) && $WPV_settings[$default_key] > 0) {
                $template_ids[] = $WPV_settings[$default_key];
            }
        }
        
        // Get conditional templates
        $conditions_key = 'views_template_conditions_for_' . $post_type;
        if (isset($WPV_settings[$conditions_key]) && is_array($WPV_settings[$conditions_key])) {
            foreach ($WPV_settings[$conditions_key] as $condition) {
                if (isset($condition['template_id']) && $condition['template_id'] > 0) {
                    $template_ids[] = $condition['template_id'];
                }
            }
        }
        
        // Also get ALL published Content Templates (view-template post type)
        // This ensures we catch any custom templates that might be used
        $all_templates = get_posts(array(
            'post_type' => 'view-template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        
        if (!empty($all_templates)) {
            $template_ids = array_merge($template_ids, $all_templates);
        }
        
        // Remove duplicates and ensure all are integers
        $template_ids = array_unique(array_map('intval', $template_ids));
        
        return $template_ids;
    }
    
    /**
     * Create a shortcode block
     * 
     * @param string $shortcode Shortcode (e.g., '[maloney_listing_availability]')
     * @return array Block array
     */
    public static function create_shortcode_block($shortcode) {
        return array(
            'blockName' => 'core/shortcode',
            'attrs' => array(),
            'innerContent' => array($shortcode),
            'innerBlocks' => array(),
        );
    }
    
    public static function replace_ninja_table_3596($post_type = 'listing', $dry_run = false) {
        $templates = self::get_templates_for_post_type($post_type);
        $results = array();
        $replacement_shortcode = '[maloney_listing_condo_listings]';
        
        foreach ($templates as $template_id) {
            if ($dry_run) {
                $blocks = self::get_blocks($template_id);
                if (is_wp_error($blocks)) {
                    $results[$template_id] = array(
                        'success' => false,
                        'error' => $blocks->get_error_message(),
                        'replaced' => false,
                    );
                    continue;
                }
                
                $found = false;
                self::check_for_pattern_recursive($blocks, 'current-condo-listings-table', $found);
                
                $results[$template_id] = array(
                    'success' => true,
                    'found' => $found,
                    'replaced' => false,
                );
            } else {
                $result = self::replace_block(
                    $template_id,
                    'current-condo-listings-table',
                    'core/shortcode',
                    array(),
                    $replacement_shortcode
                );
                
                if (is_wp_error($result)) {
                    $results[$template_id] = array(
                        'success' => false,
                        'error' => $result->get_error_message(),
                        'replaced' => false,
                    );
                } else {
                    $results[$template_id] = array(
                        'success' => true,
                        'replaced' => $result,
                    );
                }
            }
        }
        
        return $results;
    }
    
    private static function check_for_pattern_recursive($blocks, $pattern, &$found) {
        foreach ($blocks as $block) {
            $block_content = '';
            if (isset($block['innerHTML'])) {
                $block_content = $block['innerHTML'];
            } elseif (isset($block['innerContent']) && is_array($block['innerContent'])) {
                $block_content = implode('', $block['innerContent']);
            }
            
            if (stripos($block_content, $pattern) !== false) {
                $found = true;
                return;
            }
            
            if (!empty($block['innerBlocks'])) {
                self::check_for_pattern_recursive($block['innerBlocks'], $pattern, $found);
                if ($found) {
                    return;
                }
            }
        }
    }
    
    /**
     * Create a custom block
     * 
     * @param string $block_name Block name (e.g., 'maloney-listings/availability-block')
     * @param array $attributes Block attributes
     * @return array Block array
     */
    public static function create_custom_block($block_name, $attributes = array()) {
        return array(
            'blockName' => $block_name,
            'attrs' => $attributes,
            'innerContent' => array(),
            'innerBlocks' => array(),
        );
    }
}

