<?php

/**
 * Plugin Name: WE Timeline
 * Plugin URI: https://github.com/gbyat/we-timeline
 * Description: A WordPress plugin with Gutenberg blocks for creating timelines with various layouts, flexible content sources, and dynamic navigation.
 * Version: 1.0.0
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

namespace Webentwicklerin\Timeline;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('WE_TIMELINE_VERSION', '1.0.0');
define('WE_TIMELINE_PLUGIN_FILE', __FILE__);
define('WE_TIMELINE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WE_TIMELINE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WE_TIMELINE_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
