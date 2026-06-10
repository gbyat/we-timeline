<?php

/**
 * Timeline Link Functionality
 *
 * Handles storing timeline page reference in post meta and filtering
 * WordPress post navigation to only show posts from the same timeline.
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Timeline_Link
 */
class Timeline_Link
{

    /**
     * Meta key for storing timeline page ID.
     */
    const META_KEY = '_we_timeline_page_id';

    /**
     * Meta key for storing timeline post order (stored on the page).
     */
    const ORDER_META_KEY = '_we_timeline_post_order';

    /**
     * Post types that can host a timeline block.
     *
     * @var array<string>
     */
    private static $host_post_types = array('post', 'page', 'wp_block', 'wp_template', 'wp_template_part');

    /**
     * Initialize the class.
     */
    public function init()
    {
        // Filter WordPress post navigation to only show timeline posts.
        add_filter('get_previous_post_where', array($this, 'filter_adjacent_post_where'), 10, 5);
        add_filter('get_next_post_where', array($this, 'filter_adjacent_post_where'), 10, 5);
        add_filter('get_previous_post_sort', array($this, 'filter_adjacent_post_sort'), 10, 3);
        add_filter('get_next_post_sort', array($this, 'filter_adjacent_post_sort'), 10, 3);

        add_action('save_post', array($this, 'sync_timeline_links_on_save'), 20, 1);
    }

    /**
     * Store the timeline page ID and post order for posts displayed in a timeline.
     *
     * @param array $post_ids Array of post IDs in the timeline (in order).
     * @param int   $page_id  The page ID where the timeline block is placed.
     */
    public static function store_timeline_page($post_ids, $page_id)
    {
        $post_ids = self::normalize_post_ids($post_ids);
        $page_id  = absint($page_id);

        if (empty($post_ids) || empty($page_id)) {
            return;
        }

        $existing_order = get_post_meta($page_id, self::ORDER_META_KEY, true);
        if (! is_array($existing_order)) {
            $existing_order = array();
        }
        $order_changed = (self::normalize_post_ids($existing_order) !== $post_ids);

        $needs_post_links = false;
        foreach ($post_ids as $post_id) {
            $existing_pages = get_post_meta($post_id, self::META_KEY, false);
            if (! is_array($existing_pages)) {
                $existing_pages = array();
            }
            if (! in_array($page_id, $existing_pages, true)) {
                $needs_post_links = true;
                break;
            }
        }

        if (! $order_changed && ! $needs_post_links) {
            return;
        }

        if ($order_changed) {
            update_post_meta($page_id, self::ORDER_META_KEY, $post_ids);
        }

        if ($needs_post_links) {
            foreach ($post_ids as $post_id) {
                $existing_pages = get_post_meta($post_id, self::META_KEY, false);
                if (! is_array($existing_pages)) {
                    $existing_pages = array();
                }
                if (! in_array($page_id, $existing_pages, true)) {
                    add_post_meta($post_id, self::META_KEY, $page_id);
                }
            }
        }
    }

    /**
     * Persist timeline ↔ post links when a host page/template is saved.
     *
     * @param int $post_id Saved post ID.
     */
    public function sync_timeline_links_on_save($post_id)
    {
        $post = get_post($post_id);
        if (! $post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (! in_array($post->post_type, self::$host_post_types, true)) {
            return;
        }

        $content = $post->post_content ?? '';
        if ('' === $content) {
            delete_post_meta($post_id, self::ORDER_META_KEY);
            return;
        }

        try {
            $blocks = parse_blocks($content);
        } catch (\Throwable $e) {
            return;
        }

        $timeline_blocks = self::collect_timeline_blocks($blocks);
        if (empty($timeline_blocks)) {
            delete_post_meta($post_id, self::ORDER_META_KEY);
            return;
        }

        $merged_ids = array();
        foreach ($timeline_blocks as $block) {
            $attrs = $block['attrs'] ?? array();
            if ('items' === ($attrs['contentSource'] ?? 'posts')) {
                continue;
            }
            $merged_ids = array_merge($merged_ids, Renderer::get_timeline_post_ids_from_attributes($attrs));
        }

        $merged_ids = self::normalize_post_ids($merged_ids);
        if (empty($merged_ids)) {
            delete_post_meta($post_id, self::ORDER_META_KEY);
            return;
        }

        self::store_timeline_page($merged_ids, $post_id);
    }

    /**
     * Normalize a list of post IDs for stable comparison and storage.
     *
     * @param array<int|string> $post_ids Post IDs.
     * @return array<int>
     */
    private static function normalize_post_ids($post_ids)
    {
        if (! is_array($post_ids)) {
            return array();
        }

        $normalized = array_map('absint', $post_ids);
        $normalized = array_values(array_filter($normalized));

        return $normalized;
    }

    /**
     * Recursively collect we-timeline/timeline blocks from parsed block markup.
     *
     * @param array $blocks Parsed blocks.
     * @param int   $depth  Recursion depth (internal).
     * @return array
     */
    private static function collect_timeline_blocks($blocks, $depth = 0)
    {
        $found     = array();
        $max_depth = 15;

        if ($depth > $max_depth || ! is_array($blocks)) {
            return $found;
        }

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && 'we-timeline/timeline' === $block['blockName']) {
                $found[] = $block;
            }

            if (! empty($block['innerBlocks'])) {
                $found = array_merge($found, self::collect_timeline_blocks($block['innerBlocks'], $depth + 1));
                continue;
            }

            if (! empty($block['innerContent']) && is_array($block['innerContent'])) {
                $inner_html = implode('', array_filter($block['innerContent'], 'is_string'));
                if ('' !== $inner_html) {
                    try {
                        $inner_blocks = parse_blocks($inner_html);
                        $found        = array_merge($found, self::collect_timeline_blocks($inner_blocks, $depth + 1));
                    } catch (\Throwable $e) {
                        // Skip unparseable inner content.
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Get all timeline pages for a post.
     *
     * @param int $post_id The post ID.
     * @return array Array of page IDs.
     */
    public static function get_timeline_pages($post_id)
    {
        $page_ids = get_post_meta($post_id, self::META_KEY, false);
        
        // Filter out invalid/deleted pages.
        $valid_pages = array();
        foreach ($page_ids as $page_id) {
            if (get_post_status($page_id) === 'publish') {
                $valid_pages[] = $page_id;
            }
        }
        
        return $valid_pages;
    }

    /**
     * Get the post order for a timeline page.
     *
     * @param int $page_id The timeline page ID.
     * @return array Array of post IDs in order.
     */
    public static function get_timeline_post_order($page_id)
    {
        $post_order = get_post_meta($page_id, self::ORDER_META_KEY, true);
        return is_array($post_order) ? $post_order : array();
    }

    /**
     * Filter the WHERE clause for adjacent posts to only include timeline posts.
     *
     * @param string  $where          The WHERE clause.
     * @param bool    $in_same_term   Whether to retrieve posts in the same term.
     * @param array   $excluded_terms Excluded term IDs.
     * @param string  $taxonomy       Taxonomy.
     * @param WP_Post $post           The current post.
     * @return string Modified WHERE clause.
     */
    public function filter_adjacent_post_where($where, $in_same_term, $excluded_terms, $taxonomy, $post)
    {
        if (! $post) {
            return $where;
        }

        // Get timeline pages for this post.
        $timeline_pages = self::get_timeline_pages($post->ID);
        
        if (empty($timeline_pages)) {
            return $where;
        }

        // Use the first timeline page.
        $page_id = $timeline_pages[0];
        $post_order = self::get_timeline_post_order($page_id);

        if (empty($post_order)) {
            return $where;
        }

        // Get the current post's position in the timeline.
        $current_index = array_search($post->ID, $post_order, true);
        
        if ($current_index === false) {
            return $where;
        }

        // Determine if we're looking for previous or next.
        $is_previous = (current_filter() === 'get_previous_post_where');
        
        if ($is_previous) {
            // Get the previous post ID.
            $adjacent_id = isset($post_order[$current_index - 1]) ? $post_order[$current_index - 1] : null;
        } else {
            // Get the next post ID.
            $adjacent_id = isset($post_order[$current_index + 1]) ? $post_order[$current_index + 1] : null;
        }

        if (! $adjacent_id) {
            // No adjacent post - return impossible condition.
            return "WHERE 1=0";
        }

        // Replace the WHERE clause to only match the specific adjacent post.
        global $wpdb;
        return $wpdb->prepare("WHERE p.ID = %d AND p.post_status = 'publish'", $adjacent_id);
    }

    /**
     * Filter the ORDER BY clause for adjacent posts.
     *
     * @param string $order_by The ORDER BY clause.
     * @param object $post     The current post.
     * @param string $order    Sort order (DESC or ASC).
     * @return string Modified ORDER BY clause.
     */
    public function filter_adjacent_post_sort($order_by, $post, $order)
    {
        if (! $post) {
            return $order_by;
        }

        // Get timeline pages for this post.
        $timeline_pages = self::get_timeline_pages($post->ID);
        
        if (empty($timeline_pages)) {
            return $order_by;
        }

        // We're targeting a specific post ID, so order doesn't matter much.
        // Just return a simple order.
        return "ORDER BY p.ID ASC LIMIT 1";
    }

    /**
     * Remove timeline page reference from a post.
     *
     * @param int $post_id The post ID.
     * @param int $page_id The page ID to remove.
     */
    public static function remove_timeline_page($post_id, $page_id)
    {
        delete_post_meta($post_id, self::META_KEY, $page_id);
    }

    /**
     * Clear all timeline page references for a post.
     *
     * @param int $post_id The post ID.
     */
    public static function clear_timeline_pages($post_id)
    {
        delete_post_meta($post_id, self::META_KEY);
    }
}
