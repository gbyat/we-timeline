<?php

/**
 * Category Exclusion
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Exclude
 */
class Exclude
{

    /**
     * Option name for exclusion data (keyed by post/template ID, updated on save).
     */
    const OPTION_KEY = 'we_timeline_exclusion';

    /**
     * Post types that can contain timeline blocks and participate in exclusion.
     *
     * @var array<string>
     */
    private static $allowed_post_types = array('post', 'page', 'wp_block', 'wp_template', 'wp_template_part');

    /**
     * Pending exclusion entries for the next posts_where filter (main query only).
     *
     * @var array<array{post_type: string, taxonomy: string, term_id: int}>
     */
    private $pending_exclusion_entries = array();

    /**
     * Initialize hooks: exclusion updated on save_post, removed on delete/trash.
     */
    public function init()
    {
        add_action('pre_get_posts', array($this, 'exclude_from_main_loop'));
        add_filter('the_posts', array($this, 'filter_excluded_from_posts'), 10, 2);
        add_filter('get_terms', array($this, 'exclude_from_category_lists'), 10, 2);
        add_action('save_post', array($this, 'update_exclusion_for_post'), 10, 1);
        add_action('trash_post', array($this, 'remove_exclusion_for_post'), 10, 1);
        add_action('before_delete_post', array($this, 'remove_exclusion_for_post'), 10, 1);
    }

    /**
     * Update exclusion data for a single post/template when it is saved.
     *
     * @param int $post_id Post ID.
     */
    public function update_exclusion_for_post($post_id)
    {
        $post = get_post($post_id);
        if (! $post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        $post_type = $post->post_type ?? '';
        if (! in_array($post_type, self::$allowed_post_types, true)) {
            return;
        }
        if (! post_type_exists($post_type)) {
            return;
        }
        $key = $post_type . '_' . $post_id;
        $content = $post->post_content ?? '';
        $entries = $this->extract_exclusion_entries_from_content($content);
        $main_loop = self::deduplicate_exclusion_entries(array_values($entries['main_loop']));
        $category_lists = self::deduplicate_exclusion_entries(array_values($entries['category_lists']));

        $option = get_option(self::OPTION_KEY, array());
        if (! is_array($option)) {
            $option = array();
        }
        $option[ $key ] = array(
            'excludeFromMainLoop'      => $main_loop,
            'excludeFromCategoryLists' => $category_lists,
        );
        update_option(self::OPTION_KEY, $option);
    }

    /**
     * Remove exclusion data for a post/template when it is trashed or deleted.
     *
     * @param int $post_id Post ID.
     */
    public function remove_exclusion_for_post($post_id)
    {
        $post = get_post($post_id);
        if (! $post) {
            return;
        }
        $post_type = $post->post_type ?? '';
        if (! in_array($post_type, self::$allowed_post_types, true)) {
            return;
        }
        $key = $post_type . '_' . $post_id;
        $option = get_option(self::OPTION_KEY, array());
        if (! is_array($option) || ! isset($option[ $key ])) {
            return;
        }
        unset($option[ $key ]);
        update_option(self::OPTION_KEY, $option);
    }

    /**
     * Exclude posts from main query when the query lists that post type.
     * Works for any post type (post, custom post types) – only applies if a timeline block
     * has “Exclude from Main Loop” for the same post type.
     *
     * @param \WP_Query $query Query object.
     */
    public function exclude_from_main_loop($query)
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }
        if ($query->is_singular()) {
            return;
        }
        // Only on main blog loop (home) or post type archive. Not on category, tag, date archives.
        if (! $query->is_home() && ! $query->is_post_type_archive()) {
            return;
        }

        $query_post_types = $this->normalize_query_post_types($query->get('post_type'));
        $excluded_entries = self::get_excluded_entries('excludeFromMainLoop');
        $applicable = array_filter($excluded_entries, function ($entry) use ($query_post_types) {
            return in_array($entry['post_type'], $query_post_types, true);
        });
        if (empty($applicable)) {
            return;
        }

        $this->pending_exclusion_entries = $applicable;
        add_filter('posts_where', array($this, 'exclude_only_single_term_posts_where'), 10, 2);
    }

    /**
     * Add WHERE clause to exclude posts that have an excluded term AND no other term in that taxonomy.
     * Runs once for the main query; removes itself after use so pagination (found_posts) is correct.
     *
     * @param string   $where Current WHERE clause.
     * @param \WP_Query $query The query object.
     * @return string
     */
    public function exclude_only_single_term_posts_where($where, $query)
    {
        if (empty($this->pending_exclusion_entries) || ! $query->is_main_query()) {
            return $where;
        }

        remove_filter('posts_where', array($this, 'exclude_only_single_term_posts_where'), 10);

        global $wpdb;
        $taxonomy = $wpdb->term_taxonomy;
        $rel      = $wpdb->term_relationships;
        $posts    = $wpdb->posts;

        $exclude_ids = array();
        $by_tax = array();
        foreach ($this->pending_exclusion_entries as $entry) {
            $by_tax[ $entry['taxonomy'] ][] = (int) $entry['term_id'];
        }
        $this->pending_exclusion_entries = array();

        foreach ($by_tax as $taxonomy_name => $term_ids) {
            if (! get_taxonomy($taxonomy_name)) {
                continue;
            }
            $term_ids_in = implode(',', array_map('absint', $term_ids));
            // Subquery: post IDs that have one of the excluded terms AND have exactly one term in this taxonomy.
            $sub = $wpdb->prepare(
                "SELECT tr.object_id FROM {$rel} tr INNER JOIN {$taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id "
                . "WHERE tt.taxonomy = %s AND tt.term_id IN ({$term_ids_in}) "
                . "AND tr.object_id IN ("
                . "SELECT tr2.object_id FROM {$rel} tr2 INNER JOIN {$taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id "
                . "WHERE tt2.taxonomy = %s GROUP BY tr2.object_id HAVING COUNT(*) = 1"
                . ")",
                $taxonomy_name,
                $taxonomy_name
            );
            $exclude_ids[] = $sub;
        }
        if (empty($exclude_ids)) {
            return $where;
        }
        $combined = implode(' UNION ', $exclude_ids);
        $where .= " AND {$posts}.ID NOT IN ({$combined}) ";

        return $where;
    }

    /**
     * Filter excluded terms from the posts array (fallback when pre_get_posts doesn’t apply,
     * e.g. theme uses custom query or overwrites tax_query).
     *
     * @param array     $posts Array of post objects.
     * @param \WP_Query $query The query object.
     * @return array
     */
    public function filter_excluded_from_posts($posts, $query)
    {
        if (is_admin() || ! $query->is_main_query()) {
            return $posts;
        }
        if ($query->is_singular() || empty($posts)) {
            return $posts;
        }
        // Only on main blog loop or post type archive. Not on category, tag, or other archives.
        if (! $query->is_home() && ! $query->is_post_type_archive()) {
            return $posts;
        }

        $query_post_types = $this->normalize_query_post_types($query->get('post_type'));
        $excluded_entries = self::get_excluded_entries('excludeFromMainLoop');
        $applicable = array_filter($excluded_entries, function ($entry) use ($query_post_types) {
            return in_array($entry['post_type'], $query_post_types, true);
        });
        if (empty($applicable)) {
            return $posts;
        }

        $excluded_by_tax = array();
        foreach ($applicable as $entry) {
            $excluded_by_tax[ $entry['taxonomy'] ][] = $entry['term_id'];
        }

        // Exclude only posts that have an excluded term AND no other term in that taxonomy.
        return array_filter($posts, function ($post) use ($excluded_by_tax) {
            $post_id = $post->ID ?? 0;
            if (! $post_id) {
                return true;
            }
            foreach ($excluded_by_tax as $taxonomy => $term_ids) {
                $terms = wp_get_object_terms($post_id, $taxonomy);
                if (is_wp_error($terms) || empty($terms)) {
                    continue;
                }
                $post_term_ids = array_map(function ($t) {
                    return (int) $t->term_id;
                }, $terms);
                $has_excluded = ! empty(array_intersect($post_term_ids, $term_ids));
                $only_excluded = $has_excluded && count($post_term_ids) === 1;
                if ($only_excluded) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Normalize query post_type to a list of post type strings (supports any post type).
     *
     * @param string|array $post_type Value from WP_Query::get('post_type').
     * @return array List of post type slugs.
     */
    private function normalize_query_post_types($post_type)
    {
        if (is_array($post_type)) {
            return array_values(array_filter($post_type, 'is_string'));
        }
        if ($post_type === '' || $post_type === null) {
            return array('post');
        }
        return array((string) $post_type);
    }

    /**
     * Exclude posts from category lists/archives.
     *
     * @param array $terms Terms array.
     * @param array $taxonomies Taxonomies.
     * @return array
     */
    public function exclude_from_category_lists($terms, $taxonomies)
    {
        if (is_admin() || ! in_array('category', $taxonomies, true)) {
            return $terms;
        }

        $excluded_entries = self::get_excluded_entries('excludeFromCategoryLists');
        $excluded_term_ids = array();
        foreach ($excluded_entries as $entry) {
            if ('category' === $entry['taxonomy']) {
                $excluded_term_ids[] = $entry['term_id'];
            }
        }

        if (! empty($excluded_term_ids)) {
            $terms = array_filter(
                $terms,
                function ($term) use ($excluded_term_ids) {
                    if (is_object($term)) {
                        return ! in_array($term->term_id, $excluded_term_ids, true);
                    }
                    return true;
                }
            );
        }

        return $terms;
    }

    /**
     * Get excluded entries from option (post_type, taxonomy, term_id). Merges all keys and deduplicates.
     *
     * @param string $attribute Block attribute name (excludeFromMainLoop or excludeFromCategoryLists).
     * @return array<array{post_type: string, taxonomy: string, term_id: int}>
     */
    public static function get_excluded_entries($attribute)
    {
        $data = get_option(self::OPTION_KEY, array());
        if (! is_array($data)) {
            return array();
        }
        $merged = array();
        foreach ($data as $key => $entry) {
            if (! is_array($entry) || ! isset($entry[ $attribute ]) || ! is_array($entry[ $attribute ])) {
                continue;
            }
            $merged = array_merge($merged, $entry[ $attribute ]);
        }
        return self::deduplicate_exclusion_entries($merged);
    }

    /**
     * Rebuild exclusion option by scanning posts, pages, reusable blocks, and FSE templates.
     * Used by Settings "Rebuild exclusion cache now" for migration or repair.
     */
    public function rebuild_exclusion_cache()
    {
        $option = array();

        $post_types = array('post', 'page');
        if (post_type_exists('wp_block')) {
            $post_types[] = 'wp_block';
        }
        if (post_type_exists('wp_template')) {
            $post_types[] = 'wp_template';
        }
        if (post_type_exists('wp_template_part')) {
            $post_types[] = 'wp_template_part';
        }
        $query = new \WP_Query(
            array(
                'post_type'              => $post_types,
                'posts_per_page'        => -1,
                'post_status'           => 'publish',
                'no_found_rows'         => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post    = get_post();
                $content = $post ? $post->post_content : '';
                $key     = $post->post_type . '_' . $post->ID;
                $entries = $this->extract_exclusion_entries_from_content($content);
                $option[ $key ] = array(
                    'excludeFromMainLoop'      => self::deduplicate_exclusion_entries(array_values($entries['main_loop'])),
                    'excludeFromCategoryLists' => self::deduplicate_exclusion_entries(array_values($entries['category_lists'])),
                );
            }
            wp_reset_postdata();
        }

        if (function_exists('get_block_templates')) {
            $template_types = array('wp_template', 'wp_template_part');
            foreach ($template_types as $template_type) {
                $templates = get_block_templates(array(), $template_type);
                foreach ($templates as $template) {
                    $content = isset($template->content) ? $template->content : '';
                    $template_key = isset($template->wp_id) && $template->wp_id
                        ? $template_type . '_' . $template->wp_id
                        : $template_type . '_' . md5(isset($template->id) ? $template->id : (string) $template_type);
                    $entries = $content !== '' ? $this->extract_exclusion_entries_from_content($content) : array('main_loop' => array(), 'category_lists' => array());
                    $option[ $template_key ] = array(
                        'excludeFromMainLoop'      => self::deduplicate_exclusion_entries(array_values($entries['main_loop'])),
                        'excludeFromCategoryLists' => self::deduplicate_exclusion_entries(array_values($entries['category_lists'])),
                    );
                }
            }
        }

        update_option(self::OPTION_KEY, $option);
    }

    /**
     * Deduplicate exclusion entries by (post_type, taxonomy, term_id).
     *
     * @param array $entries List of entries (post_type, taxonomy, term_id).
     * @return array Deduplicated list.
     */
    public static function deduplicate_exclusion_entries($entries)
    {
        if (! is_array($entries)) {
            return array();
        }
        $by_key = array();
        foreach ($entries as $e) {
            if (! is_array($e) || ! isset($e['post_type'], $e['taxonomy'], $e['term_id'])) {
                continue;
            }
            $k = $e['post_type'] . ':' . $e['taxonomy'] . ':' . (int) $e['term_id'];
            $by_key[ $k ] = $e;
        }
        return array_values($by_key);
    }

    /**
     * Parse content’s exclusion settings into the cache when the block is rendered.
     * Ensures exclusion works even when the block lives in a template the scanner doesn’t find.
     * Call from the timeline block’s render callback.
     *
     * @param array $attrs Block attributes (e.g. from render callback).
     */
    /**
     * Parse content and return exclusion entries from we-timeline/timeline blocks.
     *
     * @param string $content Block markup (post_content or template content).
     * @return array{main_loop: array, category_lists: array}
     */
    private function extract_exclusion_entries_from_content($content)
    {
        $main_loop      = array();
        $category_lists = array();
        try {
            $blocks = parse_blocks($content);
        } catch (\Throwable $e) {
            return array('main_loop' => $main_loop, 'category_lists' => $category_lists);
        }
        $timeline_blocks = $this->collect_timeline_blocks($blocks);

        foreach ($timeline_blocks as $block) {
            $attrs = $block['attrs'] ?? array();
            if (empty($attrs['term'])) {
                continue;
            }
            $post_type = ! empty($attrs['postType']) ? (string) $attrs['postType'] : 'post';
            $taxonomy  = ! empty($attrs['taxonomy']) ? (string) $attrs['taxonomy'] : 'category';
            $term_id   = (int) $attrs['term'];
            $key       = $post_type . ':' . $taxonomy . ':' . $term_id;
            $entry     = array(
                'post_type' => $post_type,
                'taxonomy'  => $taxonomy,
                'term_id'   => $term_id,
            );

            if (! empty($attrs['excludeFromMainLoop'])) {
                $main_loop[ $key ] = $entry;
            }
            if (! empty($attrs['excludeFromCategoryLists'])) {
                $category_lists[ $key ] = $entry;
            }
        }

        return array('main_loop' => $main_loop, 'category_lists' => $category_lists);
    }

    /**
     * Recursively collect all we-timeline/timeline blocks (including nested).
     * Also parses innerContent when innerBlocks is empty (some contexts don’t fill innerBlocks).
     *
     * @param array $blocks Parsed blocks.
     * @param int   $depth  Current recursion depth (internal use).
     * @return array
     */
    private function collect_timeline_blocks($blocks, $depth = 0)
    {
        $found = array();
        $max_depth = 15;
        if ($depth > $max_depth || ! is_array($blocks)) {
            return $found;
        }
        foreach ($blocks as $block) {
            if (isset($block['blockName']) && 'we-timeline/timeline' === $block['blockName']) {
                $found[] = $block;
            }
            if (! empty($block['innerBlocks'])) {
                $found = array_merge($found, $this->collect_timeline_blocks($block['innerBlocks'], $depth + 1));
            } elseif (! empty($block['innerContent']) && is_array($block['innerContent'])) {
                $inner_html = implode('', array_filter($block['innerContent'], 'is_string'));
                if ($inner_html !== '') {
                    try {
                        $inner_blocks = parse_blocks($inner_html);
                        $found = array_merge($found, $this->collect_timeline_blocks($inner_blocks, $depth + 1));
                    } catch (\Throwable $e) {
                        // Skip blocks that fail to parse.
                    }
                }
            }
        }
        return $found;
    }
}
