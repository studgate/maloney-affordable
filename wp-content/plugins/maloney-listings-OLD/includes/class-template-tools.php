<?php
/**
 * Template Tools — safely replace Toolset blocks/shortcodes in Content Templates
 */

if (!defined('ABSPATH')) { exit; }

class Maloney_Listings_Template_Tools {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_tools_page'));
        add_action('admin_init', array($this, 'handle_quick_replace_request'));
    }

    public function add_tools_page() {
        add_submenu_page(
            'edit.php?post_type=listing',
            __('Template Blocks (Beta)', 'maloney-listings'),
            __('Template Blocks (Beta)', 'maloney-listings'),
            'manage_options',
            'ml-template-blocks',
            array($this, 'render_tools_page')
        );
    }

    private function get_templates($scope = 'all', $single_id = 0) {
        $args = array(
            'post_type'      => 'view-template',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        );
        if ($single_id) {
            $args['p'] = intval($single_id);
        }
        $templates = get_posts($args);
        if ($scope === 'rental') {
            // Heuristic: filter by title/content hints
            $templates = array_filter($templates, function($p){
                $t = strtolower($p->post_title . ' ' . $p->post_content);
                return (strpos($t, 'rental') !== false) || (strpos($t, 'rent') !== false);
            });
        }
        return $templates;
    }

    private function block_contains_pattern($block, $pattern) {
        $pat = strtolower($pattern);
        $hay = strtolower($block['innerHTML'] ?? '');
        if ($hay && strpos($hay, $pat) !== false) return true;
        // Also scan block attrs if present
        $attrs = isset($block['attrs']) ? wp_json_encode($block['attrs']) : '';
        if ($attrs && strpos(strtolower($attrs), $pat) !== false) return true;
        // Some Toolset blocks store text in innerContent array
        if (!empty($block['innerContent']) && is_array($block['innerContent'])) {
            foreach ($block['innerContent'] as $ic) {
                if (is_string($ic) && strpos(strtolower($ic), $pat) !== false) return true;
            }
        }
        return false;
    }

    private function replace_blocks($content, $pattern, $new_shortcode, &$touched) {
        if (!function_exists('parse_blocks') || !function_exists('serialize_blocks')) {
            // Fallback: simple string replacement
            $replaced = $content;
            $replaced = preg_replace("/\[types\s+field=['\"]" . preg_quote($pattern, '/') . "['\"]\](?:\[\/types\])?/i", $new_shortcode, $replaced);
            $replaced = preg_replace("/\[wpv-post-field\s+name=['\"]" . preg_quote($pattern, '/') . "['\"]\]/i", $new_shortcode, $replaced);
            if ($replaced !== $content) $touched = true;
            return $replaced;
        }

        $blocks = parse_blocks($content);
        $changed = false;

        $walker = function($blocks) use (&$walker, $pattern, $new_shortcode, &$changed) {
            $out = array();
            foreach ($blocks as $b) {
                if (!empty($b['blockName']) && $b['blockName'] === 'toolset-blocks/fields-and-text') {
                    if ($this->block_contains_pattern($b, $pattern)) {
                        // Replace entire block with a core/shortcode block
                        $shortcode_block = array(
                            'blockName'   => 'core/shortcode',
                            'attrs'       => array(),
                            'innerBlocks' => array(),
                            'innerHTML'   => $new_shortcode,
                            'innerContent'=> array($new_shortcode),
                        );
                        $out[] = $shortcode_block;
                        $changed = true;
                        continue;
                    }
                }
                if (!empty($b['innerBlocks'])) {
                    $b['innerBlocks'] = $walker($b['innerBlocks']);
                }
                $out[] = $b;
            }
            return $out;
        };

        $new_blocks = $walker($blocks);
        if ($changed) {
            $touched = true;
            return serialize_blocks($new_blocks);
        }
        // As a safety net, try string replace for shortcode variants even if block traversal didn't match
        $replaced = $content;
        // Match [types ... field="pattern" ...][/types] with any extra attributes
        $replaced = preg_replace("/\[types[^\]]*field=(['\"])" . preg_quote($pattern, '/') . "\\1[^\]]*\](?:\[\/types\])?/i", $new_shortcode, $replaced);
        // Match [wpv-post-field ... name="pattern" ...]
        $replaced = preg_replace("/\[wpv-post-field[^\]]*name=(['\"])" . preg_quote($pattern, '/') . "\\1[^\]]*\]/i", $new_shortcode, $replaced);
        if ($replaced !== $content) $touched = true;
        return $replaced;
    }

    public function handle_quick_replace_request() {
        if (!current_user_can('manage_options')) return;
        if (!isset($_GET['ml_replace_blocks'])) return;

        $pattern = isset($_GET['pattern']) ? sanitize_text_field($_GET['pattern']) : '';
        $new_sc  = isset($_GET['shortcode']) ? wp_kses_post($_GET['shortcode']) : '';
        $scope   = isset($_GET['scope']) ? sanitize_text_field($_GET['scope']) : 'all';
        $single  = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
        $commit  = isset($_GET['commit']);
        if (!$pattern || !$new_sc) wp_die('Missing pattern or shortcode.');

        $templates = $this->get_templates($scope, $single);
        $checked = 0; $modified = 0;
        echo '<div class="wrap"><h1>Block Replacement (parse_blocks)</h1>';
        echo $commit ? '<p><strong>COMMIT mode</strong></p>' : '<p><strong>Dry run</strong> — add &commit=1 to write.</p>';
        foreach ($templates as $t) {
            $checked++;
            $content = $t->post_content;
            $touched = false;
            $new = $this->replace_blocks($content, $pattern, $new_sc, $touched);
            if ($touched && $new !== $content) {
                $modified++;
                echo '<p><strong>Would replace in:</strong> '.esc_html($t->post_title).' (ID '.intval($t->ID).')</p>';
                if ($commit) {
                    wp_update_post(array('ID' => $t->ID, 'post_content' => $new));
                    echo '<p>Saved.</p>';
                }
            }
        }
        echo '<p>Checked '.intval($checked).' template(s); '.($commit ? 'Modified ' : 'Would modify ').intval($modified).'.</p>';
        echo '</div>';
        exit;
    }

    public function render_tools_page() {
        if (!current_user_can('manage_options')) { wp_die('No permission'); }
        $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'all';
        $pattern = isset($_POST['pattern']) ? sanitize_text_field($_POST['pattern']) : 'vacancy-table';
        $shortcode = isset($_POST['shortcode']) ? wp_kses_post($_POST['shortcode']) : '[maloney_listing_availability]';
        $single_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $commit = isset($_POST['commit']);

        if (isset($_POST['run'])) {
            // Run through the same logic as the quick endpoint
            $templates = $this->get_templates($scope, $single_id);
            $checked = 0; $modified = 0;
            echo '<div class="notice notice-info"><p>Running ' . ($commit ? 'COMMIT' : 'Dry run') . '…</p></div>';
            foreach ($templates as $t) {
                $checked++;
                $content = $t->post_content;
                $touched = false;
                $new = $this->replace_blocks($content, $pattern, $shortcode, $touched);
                if ($touched && $new !== $content) {
                    $modified++;
                    if ($commit) {
                        wp_update_post(array('ID' => $t->ID, 'post_content' => $new));
                    }
                }
            }
            echo '<div class="notice notice-success"><p>Checked '.intval($checked).' template(s). ' . ($commit ? 'Modified ' : 'Would modify ') . intval($modified) . '.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Manage Template Blocks', 'maloney-listings'); ?></h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">Scope</th>
                        <td>
                            <label><input type="radio" name="scope" value="all" <?php checked($scope,'all'); ?>/> All templates</label>
                            <label style="margin-left:12px"><input type="radio" name="scope" value="rental" <?php checked($scope,'rental'); ?>/> Heuristic: Rental templates</label>
                            <label style="margin-left:12px">Single Template ID: <input type="number" name="template_id" value="<?php echo esc_attr($single_id); ?>" style="width:120px"></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Block content pattern</th>
                        <td><input type="text" name="pattern" value="<?php echo esc_attr($pattern); ?>" class="regular-text"> <p class="description">Text to search within toolset-blocks/fields-and-text (e.g. <code>vacancy-table</code>).</p></td>
                    </tr>
                    <tr>
                        <th scope="row">New Shortcode</th>
                        <td><input type="text" name="shortcode" value="<?php echo esc_attr($shortcode); ?>" class="regular-text"> <p class="description">Example: <code>[maloney_listing_availability]</code></p></td>
                    </tr>
                    <tr>
                        <th scope="row">Mode</th>
                        <td>
                            <label><input type="checkbox" name="commit" value="1" <?php checked($commit); ?>/> Commit (write changes). Leave unchecked for dry run.</label>
                        </td>
                    </tr>
                </table>
                <p>
                    <button class="button button-primary" name="run" value="1">Run Replacement</button>
                </p>
            </form>
            <hr>
            <h2>Quick URL</h2>
            <p>Admin endpoint (dry run): <code>wp-admin/?ml_replace_blocks=1&amp;pattern=vacancy-table&amp;shortcode=%5Bmaloney_listing_availability%5D</code></p>
            <p>Commit: add <code>&amp;commit=1</code>. Limit scope: add <code>&amp;scope=rental</code>. Single template: add <code>&amp;template_id=123</code>.</p>
        </div>
        <?php
    }
}
