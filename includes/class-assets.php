<?php

/**
 * Asset Management
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Assets
 */
class Assets
{

	/**
	 * Initialize hooks.
	 */
	public function init()
	{
		add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
		add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
	}

	/**
	 * Enqueue block assets for frontend and editor.
	 */
	public function enqueue_block_assets()
	{
		// Enqueue Dashicons for icon support (needed for timeline icons).
		wp_enqueue_style('dashicons');

		// WordPress automatically loads styles from block.json "style" property.
		// Ensure Dashicons is always loaded for timeline blocks.
		if (has_block('we-timeline/timeline')) {
			wp_enqueue_style('dashicons');
		}
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_block_editor_assets()
	{
		// Enqueue Dashicons for icon support in editor.
		wp_enqueue_style('dashicons');

		// Editor styles.
		$editor_style_path = 'build/timeline/editor.css';
		if (file_exists(WE_TIMELINE_PLUGIN_DIR . $editor_style_path)) {
			wp_enqueue_style(
				'we-timeline-editor',
				WE_TIMELINE_PLUGIN_URL . $editor_style_path,
				array('dashicons'),
				WE_TIMELINE_VERSION
			);
		}

		// Also enqueue frontend styles in editor for ServerSideRender preview.
		$style_path = 'build/timeline/style-index.css';
		if (file_exists(WE_TIMELINE_PLUGIN_DIR . $style_path)) {
			wp_enqueue_style(
				'we-timeline-style-editor',
				WE_TIMELINE_PLUGIN_URL . $style_path,
				array('dashicons'),
				WE_TIMELINE_VERSION
			);
		}
	}
}
