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
     * Flag to prevent infinite loops.
     *
     * @var bool
     */
    private static $getting_excluded_terms = false;

    /**
     * Initialize hooks.
     */
    public function init()
    {
        add_action('pre_get_posts', array($this, 'exclude_from_main_loop'));
        add_filter('get_terms', array($this, 'exclude_from_category_lists'), 10, 2);
    }

    /**
     * Exclude posts from main query.
     *
     * @param \WP_Query $query Query object.
     */
    public function exclude_from_main_loop($query)
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        // Get all timeline blocks with excludeFromMainLoop enabled.
        $excluded_terms = $this->get_excluded_terms('excludeFromMainLoop');

        if (! empty($excluded_terms)) {
            $tax_query = $query->get('tax_query');
            if (! is_array($tax_query)) {
                $tax_query = array();
            }

            $tax_query[] = array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $excluded_terms,
                'operator' => 'NOT IN',
            );

            $query->set('tax_query', $tax_query);
        }
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
        // Prevent infinite loops.
        if (self::$getting_excluded_terms) {
            return $terms;
        }

        if (is_admin() || ! in_array('category', $taxonomies, true)) {
            return $terms;
        }

        // Get all timeline blocks with excludeFromCategoryLists enabled.
        $excluded_terms = $this->get_excluded_terms('excludeFromCategoryLists');

        if (! empty($excluded_terms)) {
            $terms = array_filter(
                $terms,
                function ($term) use ($excluded_terms) {
                    if (is_object($term)) {
                        return ! in_array($term->term_id, $excluded_terms, true);
                    }
                    return true;
                }
            );
        }

        return $terms;
    }

    /**
     * Get excluded terms from timeline blocks.
     *
     * @param string $attribute Block attribute name.
     * @return array
     */
    private function get_excluded_terms($attribute)
    {
        // Prevent infinite loops.
        if (self::$getting_excluded_terms) {
            return array();
        }

        self::$getting_excluded_terms = true;

        $excluded_terms = array();

        // Query all posts/pages with timeline blocks.
        $query = new \WP_Query(
            array(
                'post_type'      => 'any',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $content = get_the_content();

                // Remove filters that might cause loops.
                remove_filter('get_terms', array($this, 'exclude_from_category_lists'), 10);

                $blocks = parse_blocks($content);

                foreach ($blocks as $block) {
                    if ('we-timeline/timeline' === $block['blockName']) {
                        $attrs = $block['attrs'] ?? array();
                        if (! empty($attrs[$attribute]) && ! empty($attrs['term'])) {
                            $excluded_terms[] = $attrs['term'];
                        }
                    }
                }

                // Re-add filter.
                add_filter('get_terms', array($this, 'exclude_from_category_lists'), 10, 2);
            }
            wp_reset_postdata();
        }

        self::$getting_excluded_terms = false;

        return array_unique($excluded_terms);
    }
}
