<?php

/**
 * Plugin Name: WE Timeline
 * Plugin URI: https://github.com/gbyat/we-timeline
 * Description: A WordPress plugin with Gutenberg blocks for creating timelines with various layouts, flexible content sources, and dynamic navigation.
 * Version: 1.0.2
 * Author: webentwicklerin, Gabriele Laesser
 * Author URI: https://webentwicklerin.at
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: we-timeline
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline {

    // Exit if accessed directly.
    if (! defined('ABSPATH')) {
        exit;
    }

    // Define plugin constants.
    define('WE_TIMELINE_VERSION', '1.0.2');
    define('WE_TIMELINE_PLUGIN_FILE', __FILE__);
    define('WE_TIMELINE_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('WE_TIMELINE_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('WE_TIMELINE_PLUGIN_BASENAME', plugin_basename(__FILE__));
    define('WE_TIMELINE_GITHUB_REPO', 'gbyat/we-timeline');

    /**
     * Autoloader for plugin classes.
     *
     * @param string $class_name The class name to load.
     */
    spl_autoload_register(
        function ($class_name) {
            // Only load classes from this namespace.
            if (strpos($class_name, __NAMESPACE__ . '\\') !== 0) {
                return;
            }

            // Remove namespace prefix.
            $class_name = str_replace(__NAMESPACE__ . '\\', '', $class_name);

            // Convert class name to file path.
            $class_name = str_replace('_', '-', strtolower($class_name));
            $file_path  = WE_TIMELINE_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';

            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    );

    /**
     * Initialize the plugin.
     */
    function init()
    {
        // Load text domain.
        load_plugin_textdomain(
            'we-timeline',
            false,
            dirname(WE_TIMELINE_PLUGIN_BASENAME) . '/languages'
        );

        // Initialize classes.
        $assets = new Assets();
        $assets->init();

        $rest_api = new Rest_Api();
        $rest_api->init();

        $exclude = new Exclude();
        $exclude->init();

        $settings = new Settings();
        $settings->init();

        $timeline_link = new Timeline_Link();
        $timeline_link->init();

        // Register blocks.
        add_action('init', __NAMESPACE__ . '\\register_blocks');

        // Optionally register custom post type if enabled in settings.
        if (Settings::is_post_type_enabled()) {
            $post_type = new Post_Type();
            $post_type->init();
        }
    }

    /**
     * Register Gutenberg blocks.
     */
    function register_blocks()
    {
        // Register timeline block.
        $block_path = WE_TIMELINE_PLUGIN_DIR . 'build/timeline';
        $render_file = $block_path . '/render.php';

        register_block_type(
            $block_path,
            array(
                'render_callback' => function ($attributes, $content, $block) use ($render_file) {
                    if (file_exists($render_file)) {
                        return include $render_file;
                    }
                    return '<p>' . esc_html__('Error: render.php not found.', 'we-timeline') . '</p>';
                },
            )
        );
    }

    // Initialize plugin.
    add_action('plugins_loaded', __NAMESPACE__ . '\\init');

    // Rebuild exclusion cache on activation so existing timeline blocks are applied.
    register_activation_hook(
        WE_TIMELINE_PLUGIN_FILE,
        function () {
            $exclude = new Exclude();
            $exclude->rebuild_exclusion_cache();
        }
    );
}

// GitHub Update System (in global namespace, after plugin initialization)
namespace {
    class WE_Timeline_GitHub_Updater
    {
        private $file;
        private $plugin;
        private $basename;
        private $active;
        private $github_response;
        private $plugin_headers;

        public function __construct($file)
        {
            add_action('admin_init', array($this, 'set_plugin_properties'));
            add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
            add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
            add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
            add_action('upgrader_process_complete', array($this, 'purge'), 10, 2);
            add_action('admin_init', array($this, 'get_github_response'));

            $this->file = $file;
            $this->basename = plugin_basename($this->file);
            $this->active = is_plugin_active($this->basename);
        }

        public function set_plugin_properties()
        {
            $this->plugin = get_plugin_data($this->file);
            $this->plugin_headers = array(
                'Name' => $this->plugin['Name'],
                'Version' => $this->plugin['Version'],
                'TextDomain' => $this->plugin['TextDomain'],
            );
        }

        public function get_github_response()
        {
            // For public repositories, no token needed
            $args = array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                ),
            );

            $response = wp_remote_get('https://api.github.com/repos/' . WE_TIMELINE_GITHUB_REPO . '/releases/latest', $args);
            if (is_wp_error($response)) {
                return;
            }

            $this->github_response = json_decode(wp_remote_retrieve_body($response));
        }

        public function modify_transient($transient)
        {
            if (!$this->github_response || !$this->active) {
                return $transient;
            }

            $current_version = $this->plugin['Version'];
            $new_version = ltrim($this->github_response->tag_name, 'v');

            if (version_compare($current_version, $new_version, '>=')) {
                return $transient;
            }

            $plugin_data = array(
                'slug' => $this->basename,
                'new_version' => $new_version,
                'url' => $this->plugin['PluginURI'],
                'package' => $this->github_response->zipball_url,
            );

            $transient->response[$this->basename] = (object) $plugin_data;
            return $transient;
        }

        public function plugin_popup($result, $action, $args)
        {
            if ($action !== 'plugin_information') {
                return $result;
            }

            if (!isset($args->slug) || $args->slug !== $this->basename) {
                return $result;
            }

            if (!$this->github_response) {
                return $result;
            }

            $changelog = '';
            $changelog_file = WE_TIMELINE_PLUGIN_DIR . 'CHANGELOG.md';
            if (file_exists($changelog_file)) {
                $changelog_content = file_get_contents($changelog_file);
                if ($changelog_content) {
                    $changelog = $this->format_changelog_for_popup($changelog_content);
                }
            }

            if (empty($changelog)) {
                $changelog = $this->github_response->body ?: esc_html__('No changelog available.', 'we-timeline');
            }

            $description = $this->plugin['Description'];
            $readme_file = WE_TIMELINE_PLUGIN_DIR . 'README.md';
            if (file_exists($readme_file)) {
                $readme_content = file_get_contents($readme_file);
                if ($readme_content) {
                    $description = $this->format_readme_for_popup($readme_content);
                }
            }

            $plugin_data = array(
                'name' => $this->plugin['Name'],
                'slug' => $this->basename,
                'version' => $this->github_response->tag_name,
                'author' => $this->plugin['AuthorName'],
                'author_profile' => $this->plugin['AuthorURI'],
                'last_updated' => $this->github_response->published_at,
                'homepage' => $this->plugin['PluginURI'],
                'short_description' => $this->plugin['Description'],
                'sections' => array(
                    'description' => $description,
                    'changelog' => $changelog,
                    'installation' => $this->get_installation_instructions(),
                ),
                'download_link' => $this->github_response->zipball_url,
                'requires' => '6.0',
                'tested' => '6.9',
                'requires_php' => '7.4',
            );

            return (object) $plugin_data;
        }

        private function format_changelog_for_popup($changelog_content)
        {
            $changelog = $changelog_content;
            $changelog = preg_replace('/^### (.*)$/m', '<strong>$1</strong>', $changelog);
            $changelog = preg_replace('/^## (.*)$/m', '<strong>$1</strong>', $changelog);
            $changelog = preg_replace('/^# (.*)$/m', '<strong>$1</strong>', $changelog);
            $changelog = preg_replace('/^- (.*)$/m', '<li>$1</li>', $changelog);
            $changelog = preg_replace('/^\* (.*)$/m', '<li>$1</li>', $changelog);
            $changelog = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog);
            $changelog = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $changelog);
            $changelog = preg_replace('/`(.*?)`/', '<code>$1</code>', $changelog);
            $changelog = preg_replace('/.*changed files.*\n?/i', '', $changelog);
            $changelog = preg_replace('/\n{3,}/', "\n\n", $changelog);
            $changelog = preg_replace('/(<\/ul>)\n/', "$1\n\n", $changelog);
            $changelog = nl2br($changelog);
            return $changelog;
        }

        private function format_readme_for_popup($readme_content)
        {
            $readme = $readme_content;
            $readme = preg_replace('/^### (.*)$/m', '<strong>$1</strong>', $readme);
            $readme = preg_replace('/^## (.*)$/m', '<strong>$1</strong>', $readme);
            $readme = preg_replace('/^# (.*)$/m', '<strong>$1</strong>', $readme);
            $readme = preg_replace('/^- (.*)$/m', '<li>$1</li>', $readme);
            $readme = preg_replace('/^\* (.*)$/m', '<li>$1</li>', $readme);
            $readme = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $readme);
            $readme = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $readme);
            $readme = preg_replace('/`(.*?)`/', '<code>$1</code>', $readme);
            $readme = preg_replace('/\n{3,}/', "\n\n", $readme);
            $readme = nl2br($readme);
            return $readme;
        }

        private function get_installation_instructions()
        {
            return '<strong>' . esc_html__('Installation', 'we-timeline') . '</strong><br><br>
        <ol>
            <li>' . esc_html__('Upload the plugin files to the /wp-content/plugins/we-timeline directory, or install the plugin through the WordPress plugins screen directly.', 'we-timeline') . '</li>
            <li>' . esc_html__('Activate the plugin through the \'Plugins\' screen in WordPress.', 'we-timeline') . '</li>
            <li>' . esc_html__('Add the Timeline block to your pages or posts.', 'we-timeline') . '</li>
        </ol>';
        }

        public function after_install($response, $hook_extra, $result)
        {
            global $wp_filesystem;
            $install_directory = plugin_dir_path($this->file);
            $wp_filesystem->move($result['destination'], $install_directory);
            $result['destination'] = $install_directory;

            $this->set_plugin_properties();

            if ($this->active) {
                $activate = activate_plugin($this->basename);
            }

            return $result;
        }

        public function purge()
        {
            if ($this->active) {
                delete_transient('we_timeline_github_updater_' . $this->basename);
            }
        }
    }

    // Initialize GitHub Updater
    new WE_Timeline_GitHub_Updater(WE_TIMELINE_PLUGIN_FILE);
}
