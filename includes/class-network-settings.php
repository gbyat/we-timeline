<?php

/**
 * Network-wide settings (multisite).
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Network_Settings
 */
class Network_Settings
{

    /**
     * Site option name for network settings.
     *
     * @var string
     */
    const OPTION_NAME = 'we_timeline_network_settings';

    /**
     * Initialize hooks.
     */
    public function init()
    {
        if (! is_multisite()) {
            return;
        }

        add_action('network_admin_menu', array($this, 'add_settings_page'));
        add_action('network_admin_edit_we_timeline_network_settings', array($this, 'save_settings'));
    }

    /**
     * Whether GitHub update checks are enabled network-wide.
     *
     * @return bool
     */
    public static function is_github_updates_enabled()
    {
        if (! is_multisite()) {
            return true;
        }

        $settings = get_site_option(self::OPTION_NAME, self::get_default_settings());

        return ! empty($settings['enable_github_updates']);
    }

    /**
     * Default network settings.
     *
     * @return array<string, bool>
     */
    public static function get_default_settings()
    {
        return array(
            'enable_github_updates' => true,
        );
    }

    /**
     * Add settings page under Network Admin → Settings.
     */
    public function add_settings_page()
    {
        add_submenu_page(
            'settings.php',
            __('WE Timeline Network Settings', 'we-timeline'),
            __('Timeline', 'we-timeline'),
            'manage_network_options',
            'we-timeline-network',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Save network settings.
     */
    public function save_settings()
    {
        check_admin_referer('we_timeline_network_settings');

        if (! current_user_can('manage_network_options')) {
            wp_die(esc_html__('You do not have permission to manage network options.', 'we-timeline'));
        }

        $settings = array(
            'enable_github_updates' => isset($_POST['enable_github_updates']),
        );

        update_site_option(self::OPTION_NAME, $settings);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'we-timeline-network',
                    'updated' => 'true',
                ),
                network_admin_url('settings.php')
            )
        );
        exit;
    }

    /**
     * Render network settings page.
     */
    public function render_settings_page()
    {
        if (! current_user_can('manage_network_options')) {
            return;
        }

        $settings = get_site_option(self::OPTION_NAME, self::get_default_settings());
        $enabled  = ! empty($settings['enable_github_updates']);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (isset($_GET['updated'])) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <p><?php esc_html_e('Network settings saved.', 'we-timeline'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=we_timeline_network_settings')); ?>">
                <?php wp_nonce_field('we_timeline_network_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Plugin updates', 'we-timeline'); ?></th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="enable_github_updates"
                                    value="1"
                                    <?php checked($enabled, true); ?>
                                />
                                <?php esc_html_e('Check for GitHub updates from Network Admin', 'we-timeline'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, update checks run only in Network Admin—not on individual subsite dashboards. Disable this if you manage plugin updates manually.', 'we-timeline'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Network Settings', 'we-timeline')); ?>
            </form>
        </div>
        <?php
    }
}
