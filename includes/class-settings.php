<?php

/**
 * Plugin Settings
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Settings
 */
class Settings
{

    /**
     * Option name for settings.
     *
     * @var string
     */
    const OPTION_NAME = 'we_timeline_settings';

    /**
     * Initialize hooks.
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_we_timeline_rebuild_exclusion', array($this, 'maybe_rebuild_exclusion_cache'));
        add_action('update_option_' . self::OPTION_NAME, array($this, 'flush_rewrite_rules_on_update'), 10, 2);
    }

    /**
     * Add settings page to admin menu.
     */
    public function add_settings_page()
    {
        add_options_page(
            __('WE Timeline Settings', 'we-timeline'),
            __('Timeline', 'we-timeline'),
            'manage_options',
            'we-timeline-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings.
     */
    public function register_settings()
    {
        register_setting(
            'we_timeline_settings_group',
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default'           => array(
                    'enable_post_type' => false,
                ),
            )
        );

        add_settings_section(
            'we_timeline_general_section',
            __('General Settings', 'we-timeline'),
            array($this, 'render_section_description'),
            'we-timeline-settings'
        );

        add_settings_field(
            'enable_post_type',
            __('Enable Timeline Post Type', 'we-timeline'),
            array($this, 'render_enable_post_type_field'),
            'we-timeline-settings',
            'we_timeline_general_section'
        );

        add_settings_section(
            'we_timeline_exclusion_section',
            __('Exclusion', 'we-timeline'),
            array($this, 'render_exclusion_section_description'),
            'we-timeline-settings'
        );

        add_settings_field(
            'we_timeline_exclusion_rebuild',
            __('Exclusion cache', 'we-timeline'),
            array($this, 'render_exclusion_section'),
            'we-timeline-settings',
            'we_timeline_exclusion_section',
            array('label_for' => '')
        );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Settings input.
     * @return array
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        if (isset($input['enable_post_type'])) {
            $sanitized['enable_post_type'] = (bool) $input['enable_post_type'];
        } else {
            $sanitized['enable_post_type'] = false;
        }

        return $sanitized;
    }

    /**
     * Flush rewrite rules when settings are updated.
     *
     * @param array $old_value Old option value.
     * @param array $value New option value.
     */
    public function flush_rewrite_rules_on_update($old_value, $value)
    {
        // Check if post type setting changed.
        $old_enabled = isset($old_value['enable_post_type']) ? $old_value['enable_post_type'] : false;
        $new_enabled = isset($value['enable_post_type']) ? $value['enable_post_type'] : false;

        if ($old_enabled !== $new_enabled) {
            flush_rewrite_rules(false);
        }
    }

    /**
     * Schedule rewrite rules flush.
     */
    private function schedule_flush_rewrite_rules()
    {
        // This will be called from sanitize_settings, but the actual flush
        // happens in flush_rewrite_rules_on_update to avoid double flushing.
    }

    /**
     * Handle rebuild exclusion cache action (POST to admin-post.php + nonce).
     * Uses admin_post_ so the request does not hit the settings page and trigger other nonce checks.
     */
    public function maybe_rebuild_exclusion_cache()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to manage options.', 'we-timeline'), 403);
        }
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'we_timeline_rebuild_exclusion')) {
            wp_die(esc_html__('The link you followed has expired. Please try again.', 'we-timeline'), 403);
        }
        $exclude = new Exclude();
        $exclude->rebuild_exclusion_cache();
        $url = add_query_arg(
            array(
                'page'                => 'we-timeline-settings',
                'we_timeline_rebuilt' => '1',
            ),
            admin_url('options-general.php')
        );
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Render Exclusion section description.
     */
    public function render_exclusion_section_description()
    {
        echo '<p>' . esc_html__('The exclusion cache is built from timeline blocks. Use the button below to rebuild it immediately when you change "Exclude from Main Loop" or "Exclude from Category Lists" on a block.', 'we-timeline') . '</p>';
    }

    /**
     * Render Exclusion section: current cache (read-only) and rebuild button.
     */
    public function render_exclusion_section()
    {
        $main_loop      = Exclude::get_excluded_entries('excludeFromMainLoop');
        $category_lists = Exclude::get_excluded_entries('excludeFromCategoryLists');

        if (empty($main_loop) && empty($category_lists)) {
            echo '<p>' . esc_html__('Cache is empty (will be built when you save a post or template that contains a timeline block, or use Rebuild below).', 'we-timeline') . '</p>';
        } else {
            echo '<div class="we-timeline-exclusion-cache-list" style="margin-bottom: 1em;">';
            if (! empty($main_loop)) {
                echo '<p><strong>' . esc_html__('Excluded from main loop:', 'we-timeline') . '</strong></p><ul style="list-style: disc; margin-left: 1.5em;">';
                foreach ($main_loop as $entry) {
                    $term = get_term((int) $entry['term_id'], (string) $entry['taxonomy']);
                    $label = $term && ! is_wp_error($term) ? $term->name : sprintf('%s (ID %d)', $entry['taxonomy'], $entry['term_id']);
                    printf(
                        '<li>%s</li>',
                        esc_html(sprintf(
                            /* translators: 1: post type, 2: taxonomy, 3: term name */
                            __('Post type: %1$s, Taxonomy: %2$s, Term: %3$s', 'we-timeline'),
                            $entry['post_type'],
                            $entry['taxonomy'],
                            $label
                        ))
                    );
                }
                echo '</ul>';
            }
            if (! empty($category_lists)) {
                echo '<p><strong>' . esc_html__('Excluded from category lists:', 'we-timeline') . '</strong></p><ul style="list-style: disc; margin-left: 1.5em;">';
                foreach ($category_lists as $entry) {
                    $term = get_term((int) $entry['term_id'], (string) $entry['taxonomy']);
                    $label = $term && ! is_wp_error($term) ? $term->name : sprintf('%s (ID %d)', $entry['taxonomy'], $entry['term_id']);
                    printf(
                        '<li>%s</li>',
                        esc_html(sprintf(
                            /* translators: 1: post type, 2: taxonomy, 3: term name */
                            __('Post type: %1$s, Taxonomy: %2$s, Term: %3$s', 'we-timeline'),
                            $entry['post_type'],
                            $entry['taxonomy'],
                            $label
                        ))
                    );
                }
                echo '</ul>';
            }
            echo '</div>';
        }

        ?>
        <button type="submit" form="we-timeline-rebuild-form" class="button button-secondary"><?php esc_html_e('Rebuild exclusion cache now', 'we-timeline'); ?></button>
        <?php
    }

    /**
     * Render section description.
     */
    public function render_section_description()
    {
        echo '<p>' . esc_html__('Configure WE Timeline plugin settings.', 'we-timeline') . '</p>';
    }

    /**
     * Render enable post type field.
     */
    public function render_enable_post_type_field()
    {
        $settings = $this->get_settings();
        $enabled  = isset($settings['enable_post_type']) ? $settings['enable_post_type'] : false;
?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[enable_post_type]" value="1" <?php checked($enabled, true); ?> />
            <?php esc_html_e('Enable the Timeline custom post type', 'we-timeline'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('If enabled, a custom post type "Timeline" will be registered. You can use any existing post type for timelines, so this is optional.', 'we-timeline'); ?>
        </p>
    <?php
    }

    /**
     * Render settings page.
     */
    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Show success message if settings were saved.
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'we_timeline_messages',
                'we_timeline_message',
                __('Settings saved.', 'we-timeline'),
                'success'
            );
        }

        // Show success message if exclusion cache was rebuilt.
        if (isset($_GET['we_timeline_rebuilt']) && $_GET['we_timeline_rebuilt'] === '1') {
            add_settings_error(
                'we_timeline_messages',
                'we_timeline_rebuilt',
                __('Exclusion cache rebuilt.', 'we-timeline'),
                'success'
            );
        }

        settings_errors('we_timeline_messages');
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form id="we-timeline-rebuild-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: none;">
                <input type="hidden" name="action" value="we_timeline_rebuild_exclusion" />
                <?php wp_nonce_field('we_timeline_rebuild_exclusion'); ?>
            </form>
            <form action="options.php" method="post">
                <?php
                settings_fields('we_timeline_settings_group');
                do_settings_sections('we-timeline-settings');
                submit_button(__('Save Settings', 'we-timeline'));
                ?>
            </form>
        </div>
<?php
    }

    /**
     * Get settings.
     *
     * @return array
     */
    public static function get_settings()
    {
        $defaults = array(
            'enable_post_type' => false,
        );

        $settings = get_option(self::OPTION_NAME, $defaults);

        return wp_parse_args($settings, $defaults);
    }

    /**
     * Check if post type is enabled.
     *
     * @return bool
     */
    public static function is_post_type_enabled()
    {
        $settings = self::get_settings();
        return isset($settings['enable_post_type']) && $settings['enable_post_type'];
    }
}
