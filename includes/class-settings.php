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

        settings_errors('we_timeline_messages');
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
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
