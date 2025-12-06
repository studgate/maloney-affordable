<?php
/**
 * Shared helpers for Toolset Types integration
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Toolset_Helpers {

    /**
     * Return an array of available Toolset field groups for posts.
     * Each item: [id, name, description, types, group_object]
     */
    public static function get_field_groups() {
        $groups = array();

        // New API (Toolset Blocks/Types)
        if (function_exists('toolset_get_field_groups')) {
            try {
                $toolset_groups = toolset_get_field_groups(array('domain' => 'posts'));
                foreach ($toolset_groups as $group) {
                    $groups[] = array(
                        'id' => $group->get_slug(),
                        'name' => $group->get_display_name(),
                        'description' => $group->get_description(),
                        'types' => $group->get_assigned_to_types(),
                        'group_object' => $group,
                    );
                }
                return $groups;
            } catch (Exception $e) {
                // fall through
            }
        }

        // Legacy API
        if (function_exists('wpcf_admin_fields_get_groups')) {
            $legacy_groups = wpcf_admin_fields_get_groups();
            foreach ($legacy_groups as $group_id => $group) {
                $groups[] = array(
                    'id' => $group_id,
                    'name' => isset($group['name']) ? $group['name'] : '',
                    'description' => isset($group['description']) ? $group['description'] : '',
                    'types' => isset($group['types']) ? $group['types'] : array(),
                    'group_object' => $group,
                );
            }
        }

        return $groups;
    }
}

