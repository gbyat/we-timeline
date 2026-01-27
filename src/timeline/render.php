<?php

/**
 * Server-side render callback for Timeline block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content Block content.
 * @param WP_Block $block Block instance.
 * @return string
 */

if (! defined('ABSPATH')) {
	exit;
}

// Check if Renderer class exists.
if (! class_exists('\Webentwicklerin\Timeline\Renderer')) {
	return '<p>' . esc_html__('Error: Renderer class not found.', 'we-timeline') . '</p>';
}

return \Webentwicklerin\Timeline\Renderer::render($attributes);
