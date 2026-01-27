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
     * Initialize the class.
     */
    public function init()
    {
        // Filter WordPress post navigation to only show timeline posts.
        add_filter('get_previous_post_where', array($this, 'filter_adjacent_post_where'), 10, 5);
        add_filter('get_next_post_where', array($this, 'filter_adjacent_post_where'), 10, 5);
        add_filter('get_previous_post_sort', array($this, 'filter_adjacent_post_sort'), 10, 3);
        add_filter('get_next_post_sort', array($this, 'filter_adjacent_post_sort'), 10, 3);
    }

    /**
     * Store the timeline page ID and post order for posts displayed in a timeline.
     *
     * @param array $post_ids Array of post IDs in the timeline (in order).
     * @param int   $page_id  The page ID where the timeline block is placed.
     */
    public static function store_timeline_page($post_ids, $page_id)
    {
        if (empty($post_ids) || empty($page_id)) {
            return;
        }

        // Store the post order on the page itself.
        update_post_meta($page_id, self::ORDER_META_KEY, $post_ids);

        foreach ($post_ids as $post_id) {
            // Get existing timeline pages for this post.
            $existing_pages = get_post_meta($post_id, self::META_KEY, false);
            
            // Only add if not already stored.
            if (! in_array($page_id, $existing_pages, true)) {
                add_post_meta($post_id, self::META_KEY, $page_id);
            }
        }
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
