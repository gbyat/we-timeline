<?php

/**
 * Uninstall WE Timeline – remove all plugin data when the plugin is deleted.
 *
 * Runs only when the plugin is deleted (not on deactivate).
 *
 * @package Webentwicklerin\Timeline
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('we_timeline_exclusion');
delete_option('we_timeline_settings');
delete_transient('we_timeline_exclusion');
