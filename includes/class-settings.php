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
                'default'           => self::get_default_settings(),
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

        add_settings_section(
            'we_timeline_frontend_strings_section',
            __('Frontend text', 'we-timeline'),
            array($this, 'render_frontend_strings_section_description'),
            'we-timeline-settings'
        );

        foreach (self::get_frontend_string_definitions() as $string_key => $definition) {
            add_settings_field(
                'frontend_string_' . $string_key,
                $definition['label'],
                array($this, 'render_frontend_string_field'),
                'we-timeline-settings',
                'we_timeline_frontend_strings_section',
                array(
                    'string_key' => $string_key,
                    'description' => $definition['description'],
                )
            );
        }
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Settings input.
     * @return array
     */
    public function sanitize_settings($input)
    {
        $sanitized = array(
            'enable_post_type' => false,
            'frontend_strings' => array(),
        );

        if (isset($input['enable_post_type'])) {
            $sanitized['enable_post_type'] = (bool) $input['enable_post_type'];
        }

        if (isset($input['frontend_strings']) && is_array($input['frontend_strings'])) {
            foreach (self::get_frontend_string_definitions() as $string_key => $definition) {
                $raw = $input['frontend_strings'][ $string_key ] ?? '';
                $raw = is_string($raw) ? trim(wp_unslash($raw)) : '';
                if ('' !== $raw) {
                    $sanitized['frontend_strings'][ $string_key ] = sanitize_text_field($raw);
                }
            }
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
     * Render frontend strings section description.
     */
    public function render_frontend_strings_section_description()
    {
        echo '<p>' . esc_html__('Override text shown to site visitors on the frontend. Leave a field empty to use the plugin default (still translatable via language files).', 'we-timeline') . '</p>';
    }

    /**
     * Render a single frontend string settings field.
     *
     * @param array $args Field arguments.
     */
    public function render_frontend_string_field($args)
    {
        $string_key  = $args['string_key'] ?? '';
        $description = $args['description'] ?? '';
        $settings    = $this->get_settings();
        $defaults    = self::get_default_frontend_strings();
        $value       = $settings['frontend_strings'][ $string_key ] ?? '';
        $default     = $defaults[ $string_key ] ?? '';
        $field_id    = 'we-timeline-frontend-string-' . $string_key;
        $field_name  = self::OPTION_NAME . '[frontend_strings][' . $string_key . ']';

        if ('' === $string_key || ! isset($defaults[ $string_key ])) {
            return;
        }
        ?>
        <input
            type="text"
            id="<?php echo esc_attr($field_id); ?>"
            name="<?php echo esc_attr($field_name); ?>"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            placeholder="<?php echo esc_attr($default); ?>"
        />
        <?php if ('' !== $description) : ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <p class="description">
            <?php
            printf(
                /* translators: %s: default string value */
                esc_html__('Default: %s', 'we-timeline'),
                esc_html($default)
            );
            ?>
        </p>
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
        $settings = get_option(self::OPTION_NAME, self::get_default_settings());

        return wp_parse_args($settings, self::get_default_settings());
    }

    /**
     * Default plugin settings.
     *
     * @return array
     */
    public static function get_default_settings()
    {
        return array(
            'enable_post_type'   => false,
            'frontend_strings'   => array(),
        );
    }

    /**
     * Frontend string definitions for the settings page.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function get_frontend_string_definitions()
    {
        return array(
            'read_more'       => array(
                'label'       => __('Read more link', 'we-timeline'),
                'description' => __('Shown when a timeline item displays an excerpt and links to the full post.', 'we-timeline'),
            ),
            'decade_suffix'   => array(
                'label'       => __('Decade suffix', 'we-timeline'),
                'description' => __('Appended to decade menu labels, e.g. "s" for 1920s or "er" for 1920er.', 'we-timeline'),
            ),
            'menu_aria_label' => array(
                'label'       => __('Menu accessibility label', 'we-timeline'),
                'description' => __('Screen reader label for the timeline jump navigation.', 'we-timeline'),
            ),
            'untitled_item'   => array(
                'label'       => __('Untitled item label', 'we-timeline'),
                'description' => __('Fallback label in the navigation when an item has no title.', 'we-timeline'),
            ),
            'no_items_found'  => array(
                'label'       => __('Empty timeline message', 'we-timeline'),
                'description' => __('Shown when a timeline block has no items to display.', 'we-timeline'),
            ),
        );
    }

    /**
     * Default visitor-facing strings (translatable).
     *
     * @return array<string, string>
     */
    public static function get_default_frontend_strings()
    {
        return array(
            'read_more'       => __('Read more', 'we-timeline'),
            'decade_suffix'   => _x('s', 'decade suffix', 'we-timeline'),
            'menu_aria_label' => __('Jump to timeline periods', 'we-timeline'),
            'untitled_item'   => __('Untitled item', 'we-timeline'),
            'no_items_found'  => __('No timeline items found.', 'we-timeline'),
        );
    }

    /**
     * Resolve a visitor-facing string (custom setting or default).
     *
     * @param string $key String key from get_default_frontend_strings().
     * @return string
     */
    public static function get_frontend_string($key)
    {
        $defaults = self::get_default_frontend_strings();
        if (! isset($defaults[ $key ])) {
            return '';
        }

        $settings = self::get_settings();
        $custom   = $settings['frontend_strings'][ $key ] ?? '';

        if (is_string($custom) && '' !== trim($custom)) {
            return $custom;
        }

        return $defaults[ $key ];
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
