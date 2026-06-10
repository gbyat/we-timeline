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
	 * Editor script handles (stable, like we-subscribe-to-posts).
	 */
	const EDITOR_SCRIPT_HANDLES = array(
		'we-timeline-editor'             => 'build/timeline/index.js',
		'we-timeline-item-editor'        => 'build/timeline-item/index.js',
		'we-timeline-item-title-editor'  => 'build/timeline-item-title/index.js',
	);

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
		wp_enqueue_style('dashicons');

		if (has_block('we-timeline/timeline')) {
			wp_enqueue_style('dashicons');
		}
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * Mirrors we-subscribe-to-posts: manual wp_enqueue_script + wp_set_script_translations
	 * in one place. block.json editorScript is intentionally omitted so WordPress does not
	 * override the languages path with build/{block}/languages.
	 */
	public function enqueue_block_editor_assets()
	{
		wp_enqueue_style('dashicons');

		$editor_style_path = 'build/timeline/editor.css';
		if (file_exists(WE_TIMELINE_PLUGIN_DIR . $editor_style_path)) {
			wp_enqueue_style(
				'we-timeline-editor',
				WE_TIMELINE_PLUGIN_URL . $editor_style_path,
				array('dashicons'),
				WE_TIMELINE_VERSION
			);
		}

		$style_path = 'build/timeline/style-index.css';
		if (file_exists(WE_TIMELINE_PLUGIN_DIR . $style_path)) {
			wp_enqueue_style(
				'we-timeline-style-editor',
				WE_TIMELINE_PLUGIN_URL . $style_path,
				array('dashicons'),
				WE_TIMELINE_VERSION
			);
		}

		$languages_path = WE_TIMELINE_PLUGIN_DIR . 'languages';

		foreach (self::EDITOR_SCRIPT_HANDLES as $handle => $script_path) {
			$this->enqueue_editor_script($handle, $script_path, $languages_path);
		}
	}

	/**
	 * Register, enqueue, and translate one block editor script.
	 *
	 * @param string $handle         Script handle.
	 * @param string $script_path    Path relative to plugin root (e.g. build/timeline/index.js).
	 * @param string $languages_path Absolute path to languages directory.
	 */
	private function enqueue_editor_script($handle, $script_path, $languages_path)
	{
		$script_file = WE_TIMELINE_PLUGIN_DIR . $script_path;
		if (! file_exists($script_file)) {
			return;
		}

		$asset_file = WE_TIMELINE_PLUGIN_DIR . preg_replace('/\.js$/', '.asset.php', $script_path);
		$version    = WE_TIMELINE_VERSION;
		$deps       = array('wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components');

		if (file_exists($asset_file)) {
			$asset = include $asset_file;
			if (is_array($asset)) {
				$version = $asset['version'] ?? $version;
				$deps    = $asset['dependencies'] ?? $deps;
			}
		}

		wp_enqueue_script(
			$handle,
			WE_TIMELINE_PLUGIN_URL . $script_path,
			$deps,
			$version,
			true
		);

		wp_set_script_translations($handle, 'we-timeline', $languages_path);
	}
}
