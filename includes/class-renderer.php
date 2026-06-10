<?php

/**
 * Server-side Rendering
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Renderer
 */
class Renderer
{

    /**
     * Resolve published post IDs for a posts-mode timeline from block attributes.
     *
     * Used when saving host pages (no full render required).
     *
     * @param array $attributes Block attributes.
     * @return array<int>
     */
    public static function get_timeline_post_ids_from_attributes($attributes)
    {
        if (! is_array($attributes)) {
            $attributes = array();
        }

        if ('items' === ($attributes['contentSource'] ?? 'posts')) {
            return array();
        }

        $post_type  = $attributes['postType'] ?? '';
        $taxonomy   = $attributes['taxonomy'] ?? '';
        $term       = absint($attributes['term'] ?? 0);
        $date_field = $attributes['dateField'] ?? 'date';
        $sort_order = self::sanitize_sort_order($attributes['sortOrder'] ?? 'asc');

        if (empty($post_type)) {
            if (! empty($taxonomy)) {
                $taxonomy_obj = get_taxonomy($taxonomy);
                if ($taxonomy_obj && ! empty($taxonomy_obj->object_type)) {
                    $post_type = $taxonomy_obj->object_type[0];
                } else {
                    $post_type = 'post';
                }
            } else {
                $post_type = 'post';
            }
        }

        $posts = self::get_posts($post_type, $taxonomy, $term, $date_field, $sort_order);

        return array_column($posts, 'id');
    }

    /**
     * Render timeline block.
     *
     * @param array         $attributes Block attributes.
     * @param \WP_Block|null $block      Block instance (required for items mode).
     * @return string
     */
    public static function render($attributes, $block = null)
    {
        // Ensure attributes is an array.
        if (! is_array($attributes)) {
            $attributes = array();
        }

        $layout       = $attributes['layout'] ?? 'vertical';
        $position     = $attributes['position'] ?? ($layout === 'vertical' ? 'left' : 'top');
        $visible_items = $attributes['visibleItems'] ?? 3;
        $icon         = $attributes['icon'] ?? 'calendar-alt';
        $content_source = $attributes['contentSource'] ?? 'posts';
        $post_type  = $attributes['postType'] ?? '';
        $taxonomy   = $attributes['taxonomy'] ?? '';
        $term       = absint($attributes['term'] ?? 0);
        $date_field = $attributes['dateField'] ?? 'date';
        $sort_order = self::sanitize_sort_order($attributes['sortOrder'] ?? 'asc');
        $show_menu  = $attributes['showMenu'] ?? false;
        $menu_granularity = $attributes['menuGranularity'] ?? 'auto';

        if ('items' === $content_source) {
            $posts = self::get_items_from_inner_blocks($block, $sort_order);
        } else {
            // If post type is not set, try to determine it from taxonomy.
            if (empty($post_type)) {
                if (! empty($taxonomy)) {
                    $taxonomy_obj = get_taxonomy($taxonomy);
                    if ($taxonomy_obj && ! empty($taxonomy_obj->object_type)) {
                        $post_type = $taxonomy_obj->object_type[0];
                    } else {
                        $post_type = 'post';
                    }
                } else {
                    $post_type = 'post';
                }
            }

            $show_full_content  = ! empty($attributes['showFullContent']);
            $excerpt_word_count = isset($attributes['excerptWordCount']) ? absint($attributes['excerptWordCount']) : 55;
            $posts                = self::get_posts($post_type, $taxonomy, $term, $date_field, $sort_order, $excerpt_word_count, $show_full_content);
        }

        if (empty($posts)) {
            return '<p>' . esc_html(Settings::get_frontend_string('no_items_found')) . '</p>';
        }

        // Sync timeline ↔ post links on the frontend only when meta is stale (e.g. new published posts).
        if ('items' !== $content_source && ! is_admin() && ! (defined('REST_REQUEST') && REST_REQUEST)) {
            $current_page_id = get_the_ID();
            if ($current_page_id) {
                Timeline_Link::store_timeline_page(array_column($posts, 'id'), $current_page_id);
            }
        }

        // Build wrapper classes.
        $wrapper_classes = array(
            'wp-block-we-timeline-timeline',
            'we-timeline',
            'we-timeline--' . esc_attr($layout),
        );

        // Add position class (for backward compatibility, handle old 'alternating' layout).
        if ($layout === 'alternating') {
            // Old block format - treat as vertical-alternating.
            $wrapper_classes[] = 'we-timeline--vertical-alternating';
            $layout = 'vertical';
            $position = 'alternating';
        } else {
            // Ensure position is set correctly
            if (empty($position)) {
                $position = ($layout === 'vertical') ? 'left' : 'top';
            }
            $wrapper_classes[] = 'we-timeline--' . esc_attr($layout) . '-' . esc_attr($position);
        }

        // Generate unique block ID for menu connection.
        if (function_exists('wp_generate_uuid4')) {
            $block_id = 'we-timeline-' . wp_generate_uuid4();
        } else {
            $block_id = 'we-timeline-' . uniqid('', true);
        }

        $wrapper_style_parts = array();
        $items_style_parts   = array();

        if ($layout === 'horizontal-scroll') {
            $wrapper_style_parts[] = '--visible-items: ' . intval($visible_items) . ';';
        }

        $item_length_vars = array(
            'itemBorderRadius' => '--we-timeline-item-border-radius',
            'itemBorderWidth'  => '--we-timeline-item-border-width',
            'itemPadding'      => '--we-timeline-item-padding',
            'itemGap'          => '--we-timeline-items-gap',
        );
        foreach ($item_length_vars as $attr_key => $css_var) {
            if (! empty($attributes[ $attr_key ])) {
                $items_style_parts[] = $css_var . ': ' . esc_attr($attributes[ $attr_key ]) . ';';
            }
        }

        $item_border_style = isset($attributes['itemBorderStyle']) ? (string) $attributes['itemBorderStyle'] : '';
        if (in_array($item_border_style, array('solid', 'dashed', 'dotted', 'none'), true)) {
            $items_style_parts[] = '--we-timeline-item-border-style: ' . esc_attr($item_border_style) . ';';
        }

        $item_border_color = $attributes['itemBorderColor'] ?? '';
        if (! empty($item_border_color)) {
            $items_style_parts[] = '--we-timeline-item-border-color: ' . esc_attr($item_border_color) . ';';
        }

        $timeline_line_color        = $attributes['timelineLineColor'] ?? '';
        $timeline_line_active_color = $attributes['timelineLineActiveColor'] ?? '';
        $item_background_color      = $attributes['itemBackgroundColor'] ?? '';
        $icon_color                 = $attributes['iconColor'] ?? '';
        $date_color                 = $attributes['dateColor'] ?? '';
        $menu_text_color            = $attributes['menuTextColor'] ?? '';
        $menu_text_color_hover      = $attributes['menuTextColorHover'] ?? '';
        $menu_background_color      = $attributes['menuBackgroundColor'] ?? '';
        $menu_hover_color           = $attributes['menuHoverColor'] ?? '';
        $menu_active_color              = $attributes['menuActiveColor'] ?? '';
        $menu_active_background_color   = $attributes['menuActiveBackgroundColor'] ?? '';

        if (! empty($timeline_line_color)) {
            $wrapper_style_parts[] = '--we-timeline-line-color: ' . esc_attr($timeline_line_color) . ';';
        }
        if (! empty($timeline_line_active_color)) {
            $wrapper_style_parts[] = '--we-timeline-line-active-color: ' . esc_attr($timeline_line_active_color) . ';';
        }
        if (! empty($item_background_color)) {
            $wrapper_style_parts[] = '--we-timeline-item-background: ' . esc_attr($item_background_color) . ';';
        }
        if (! empty($icon_color)) {
            $wrapper_style_parts[] = '--we-timeline-icon-color: ' . esc_attr($icon_color) . ';';
        }
        if (! empty($date_color)) {
            $wrapper_style_parts[] = '--we-timeline-date-color: ' . esc_attr($date_color) . ';';
        }
        if (! empty($menu_text_color)) {
            $wrapper_style_parts[] = '--we-timeline-menu-text-color: ' . esc_attr($menu_text_color) . ';';
        }
        if (! empty($menu_text_color_hover)) {
            $wrapper_style_parts[] = '--we-timeline-menu-text-color-hover: ' . esc_attr($menu_text_color_hover) . ';';
        }
        if (! empty($menu_background_color)) {
            $wrapper_style_parts[] = '--we-timeline-menu-background-color: ' . esc_attr($menu_background_color) . ';';
        }
        if (! empty($menu_hover_color)) {
            $wrapper_style_parts[] = '--we-timeline-menu-hover-color: ' . esc_attr($menu_hover_color) . ';';
        }
        if (! empty($menu_active_color)) {
            $wrapper_style_parts[] = '--we-timeline-menu-active-color: ' . esc_attr($menu_active_color) . ';';
        }
        if (! empty($menu_active_background_color)) {
            $wrapper_style_parts[] = '--we-timeline-menu-active-background-color: ' . esc_attr($menu_active_background_color) . ';';
        }

        $sticky_header_selector = isset($attributes['stickyHeaderSelector']) ? trim((string) $attributes['stickyHeaderSelector']) : '';

        $wrapper_extra = array(
            'class'            => implode(' ', $wrapper_classes),
            'id'               => $block_id,
            'data-timeline-id' => $block_id,
        );
        if ('' !== $sticky_header_selector) {
            $wrapper_extra['data-sticky-header-selector'] = $sticky_header_selector;
        }
        if (! empty($wrapper_style_parts)) {
            $wrapper_extra['style'] = implode(' ', $wrapper_style_parts);
        }

        $items_style_attr = ! empty($items_style_parts) ? implode(' ', $items_style_parts) : '';

        if ($block instanceof \WP_Block && function_exists('get_block_wrapper_attributes')) {
            $wrapper_attributes_html = get_block_wrapper_attributes($wrapper_extra, $block);
        } else {
            $wrapper_attributes_html = self::build_attributes($wrapper_extra);
        }

        $menu_sort_order = self::sanitize_menu_sort_order($attributes['menuSortOrder'] ?? 'inherit');
        $menu_items      = $show_menu ? self::build_menu_items($posts, $menu_granularity, $sort_order, $menu_sort_order) : array();

        ob_start();
?>
        <div <?php echo $wrapper_attributes_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() is escaped. ?>>
            <?php if ($show_menu) : ?>
                <?php
                $menu_separator_char      = self::get_menu_separator_char($attributes);
                $menu_separators          = self::sanitize_menu_separators($attributes['menuSeparators'] ?? 'none');
                $menu_mobile_mode         = self::sanitize_menu_mobile_mode($attributes['menuMobileMode'] ?? 'inherit');
                $menu_granularity_mobile  = self::sanitize_menu_granularity($attributes['menuGranularityMobile'] ?? 'decades');
                $menu_mobile_label_format = self::sanitize_menu_mobile_label_format($attributes['menuMobileLabelFormat'] ?? 'year');
                $menu_mobile_breakpoint   = self::sanitize_menu_mobile_breakpoint($attributes['menuMobileBreakpoint'] ?? 768);
                ?>
                <nav class="<?php echo esc_attr(self::get_menu_classes($attributes)); ?>" data-granularity="<?php echo esc_attr($menu_granularity); ?>" data-sort-order="<?php echo esc_attr($sort_order); ?>" data-menu-sort-order="<?php echo esc_attr($menu_sort_order); ?>" data-decade-suffix="<?php echo esc_attr(self::get_decade_suffix()); ?>" data-timeline-id="<?php echo esc_attr($block_id); ?>" data-menu-separators="<?php echo esc_attr($menu_separators); ?>" data-menu-mobile-mode="<?php echo esc_attr($menu_mobile_mode); ?>" data-menu-granularity-mobile="<?php echo esc_attr($menu_granularity_mobile); ?>" data-menu-mobile-label-format="<?php echo esc_attr($menu_mobile_label_format); ?>" data-menu-mobile-breakpoint="<?php echo esc_attr((string) $menu_mobile_breakpoint); ?>"<?php echo '' !== $menu_separator_char ? ' data-menu-separator-char="' . esc_attr($menu_separator_char) . '"' : ''; ?> aria-label="<?php echo esc_attr(Settings::get_frontend_string('menu_aria_label')); ?>">
                    <div class="we-timeline-menu__items">
                        <?php foreach ($menu_items as $menu_index => $menu_item) : ?>
                            <?php if ($menu_index > 0 && '' !== $menu_separator_char) : ?>
                                <span class="we-timeline-menu__separator" aria-hidden="true"><?php echo esc_html($menu_separator_char); ?></span>
                            <?php endif; ?>
                            <button type="button" class="we-timeline-menu__item" data-value="<?php echo esc_attr($menu_item['value']); ?>" data-type="<?php echo esc_attr($menu_item['type']); ?>"<?php echo ! empty($menu_item['first_id']) ? ' data-first-id="' . esc_attr($menu_item['first_id']) . '"' : ''; ?>><?php echo esc_html($menu_item['label']); ?></button>
                        <?php endforeach; ?>
                    </div>
                </nav>
            <?php endif; ?>
            <div class="we-timeline__items"<?php echo '' !== $items_style_attr ? ' style="' . esc_attr($items_style_attr) . '"' : ''; ?>>
                <?php
                $icon_size = $attributes['iconSize'] ?? 'medium';
                foreach ($posts as $index => $post) {
                    self::render_item($post, $layout, $position, $icon, $index, $attributes);
                }
                ?>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Get posts for timeline.
     *
     * @param string $post_type Post type.
     * @param string $taxonomy Taxonomy.
     * @param int    $term Term ID.
     * @param string $date_field Date field.
     * @param string $sort_order Sort order.
     * @param int    $excerpt_word_count Words when no manual excerpt is set.
     * @param bool   $show_full_content  Whether to render full post content instead of excerpt.
     * @return array
     */
    private static function get_posts($post_type, $taxonomy, $term, $date_field, $sort_order, $excerpt_word_count = 55, $show_full_content = false)
    {
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        // Add taxonomy filter if taxonomy and term are set.
        if ($taxonomy && $term > 0) {
            // Ensure term is an integer.
            $term_id = absint($term);
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            );
        }

        // Set query order: manual uses menu_order; date field uses publish date; custom meta sorts after fetch.
        if ('manual' === $sort_order) {
            $args['orderby'] = 'menu_order';
            $args['order']   = 'ASC';
        } elseif ('date' === $date_field) {
            $args['orderby'] = 'date';
            $args['order']   = 'ASC' === $sort_order ? 'ASC' : 'DESC';
        } else {
            $args['orderby'] = 'date';
            $args['order']   = 'ASC';
        }

        $query = new \WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $date_value = self::get_date_value($post_id, $date_field);

                $post_obj           = get_post($post_id);
                $excerpt_word_count = max(1, min(500, absint($excerpt_word_count)));
                $excerpt            = '';
                $content            = '';

                if ($show_full_content) {
                    $content = apply_filters('the_content', $post_obj->post_content);
                } else {
                    $manual_excerpt = trim((string) $post_obj->post_excerpt);
                    if ('' !== $manual_excerpt) {
                        $excerpt = apply_filters('the_excerpt', $manual_excerpt);
                    } else {
                        $excerpt = wp_trim_words(
                            wp_strip_all_tags($post_obj->post_content),
                            $excerpt_word_count,
                            '&hellip;'
                        );
                    }
                }

                $posts[] = array(
                    'id'         => $post_id,
                    'title'      => get_the_title($post_id),
                    'excerpt'    => $excerpt,
                    'content'    => $content,
                    'date'       => $date_value,
                    'permalink'  => get_permalink($post_id),
                    'thumbnail'  => get_the_post_thumbnail_url($post_id, 'medium'),
                );
            }
            wp_reset_postdata();
        }

        // Sort by custom date meta when not using publish date or manual menu order.
        if ('manual' !== $sort_order && 'date' !== $date_field) {
            usort(
                $posts,
                function ($a, $b) use ($sort_order) {
                    $valid_a = self::is_sortable_date($a['date'] ?? '');
                    $valid_b = self::is_sortable_date($b['date'] ?? '');

                    if (! $valid_a && ! $valid_b) {
                        return 0;
                    }
                    if (! $valid_a) {
                        return 1;
                    }
                    if (! $valid_b) {
                        return -1;
                    }

                    $date_a = self::get_date_timestamp($a['date']);
                    $date_b = self::get_date_timestamp($b['date']);

                    if ('desc' === $sort_order) {
                        return $date_b <=> $date_a;
                    }
                    return $date_a <=> $date_b;
                }
            );
        }

        return $posts;
    }

    /**
     * Build timeline items from inner timeline-item blocks.
     *
     * @param \WP_Block|null $block      Parent timeline block.
     * @param string         $sort_order Sort order (asc or desc).
     * @return array<array{id: string, title: string, excerpt: string, content: string, date: string, permalink: string, thumbnail: string}>
     */
    private static function get_items_from_inner_blocks($block, $sort_order)
    {
        $items = array();

        if (! $block instanceof \WP_Block || empty($block->inner_blocks)) {
            return $items;
        }

        foreach ($block->inner_blocks as $inner_block) {
            if ('we-timeline/timeline-item' !== $inner_block->name) {
                continue;
            }

            $attrs     = $inner_block->attributes;
            $date      = isset($attrs['date']) ? (string) $attrs['date'] : '';
            $title     = isset($attrs['title']) ? (string) $attrs['title'] : '';
            $image_id  = isset($attrs['imageId']) ? absint($attrs['imageId']) : 0;
            $excerpt   = isset($attrs['excerpt']) ? (string) $attrs['excerpt'] : '';
            $link      = isset($attrs['link']) ? (string) $attrs['link'] : '';
            $anchor    = isset($attrs['anchor']) ? (string) $attrs['anchor'] : '';

            $inner_content = '';
            if (! empty($inner_block->inner_blocks)) {
                foreach ($inner_block->inner_blocks as $content_block) {
                    $inner_content .= $content_block->render();
                }
            }

            // Legacy featured image attribute: inline into content when not yet migrated to core/image.
            if (
                $image_id
                && ! self::has_image_block_in_inner_blocks($inner_block->inner_blocks)
            ) {
                $legacy_image = wp_get_attachment_image(
                    $image_id,
                    'medium',
                    false,
                    array( 'class' => 'we-timeline__legacy-featured-image' )
                );
                if ($legacy_image) {
                    $inner_content = $legacy_image . $inner_content;
                }
            }

            $has_nav_heading = self::has_navigation_heading_in_inner_blocks($inner_block->inner_blocks);
            $nav_title       = self::extract_navigation_title_from_inner_blocks($inner_block->inner_blocks);
            if ('' === $nav_title) {
                $nav_title = $title;
            }

            if ('' === $anchor) {
                $anchor = 'item-' . substr(md5($date . $nav_title . wp_json_encode($attrs)), 0, 12);
            }

            $legacy_title = '';
            if (! $has_nav_heading && '' !== trim($title)) {
                $legacy_title = $title;
            }

            $nav_target_id = self::get_nav_target_id($anchor);
            $inner_content = self::mark_navigation_target_in_content($inner_content, $nav_target_id);

            $items[] = array(
                'id'           => $anchor,
                'title'        => $nav_title,
                'excerpt'      => $excerpt,
                'content'      => $inner_content,
                'date'         => $date,
                'permalink'    => $link,
                'thumbnail'    => '',
                'free_layout'   => true,
                'legacy_title'  => $legacy_title,
                'nav_target_id' => $nav_target_id,
            );
        }

        if ('manual' !== $sort_order) {
            usort(
                $items,
                function ($a, $b) use ($sort_order) {
                    $valid_a = self::is_sortable_date($a['date'] ?? '');
                    $valid_b = self::is_sortable_date($b['date'] ?? '');

                    if (! $valid_a && ! $valid_b) {
                        return 0;
                    }
                    if (! $valid_a) {
                        return 1;
                    }
                    if (! $valid_b) {
                        return -1;
                    }

                    $date_a = self::get_date_timestamp($a['date']);
                    $date_b = self::get_date_timestamp($b['date']);

                    if ('desc' === $sort_order) {
                        return $date_b <=> $date_a;
                    }
                    return $date_a <=> $date_b;
                }
            );
        }

        return $items;
    }

    /**
     * Sanitize timeline item sort order.
     *
     * @param string $sort_order Raw sort order.
     * @return string asc, desc, or manual.
     */
    private static function sanitize_sort_order($sort_order)
    {
        $allowed    = array('asc', 'desc', 'manual');
        $sort_order = is_string($sort_order) ? strtolower(trim($sort_order)) : 'asc';

        if (! in_array($sort_order, $allowed, true)) {
            return 'asc';
        }

        return $sort_order;
    }

    /**
     * Read navigation label from the first heading block in item content (searches nested inner blocks).
     *
     * @param \WP_Block_List|null $inner_blocks Inner blocks of a timeline item.
     * @return string Plain-text title for menu and data attributes.
     */
    private static function extract_navigation_title_from_inner_blocks($inner_blocks)
    {
        if (empty($inner_blocks)) {
            return '';
        }

        foreach ($inner_blocks as $content_block) {
            if ('we-timeline/timeline-item-title' === $content_block->name) {
                $block_title = isset($content_block->attributes['title'])
                    ? (string) $content_block->attributes['title']
                    : '';
                if ('' !== trim($block_title)) {
                    return wp_strip_all_tags($block_title);
                }
                return wp_strip_all_tags($content_block->render());
            }

            if ('core/heading' === $content_block->name) {
                $heading_content = isset($content_block->attributes['content'])
                    ? (string) $content_block->attributes['content']
                    : '';
                if ('' !== trim($heading_content)) {
                    return wp_strip_all_tags($heading_content);
                }
                return wp_strip_all_tags($content_block->render());
            }

            if (! empty($content_block->inner_blocks)) {
                $nested = self::extract_navigation_title_from_inner_blocks($content_block->inner_blocks);
                if ('' !== $nested) {
                    return $nested;
                }
            }
        }

        return '';
    }

    /**
     * Whether an image block exists in inner blocks (including nested).
     *
     * @param \WP_Block_List|null $inner_blocks Inner blocks of a timeline item.
     * @return bool
     */
    private static function has_image_block_in_inner_blocks($inner_blocks)
    {
        if (empty($inner_blocks)) {
            return false;
        }

        foreach ($inner_blocks as $content_block) {
            if ('core/image' === $content_block->name || 'core/cover' === $content_block->name) {
                return true;
            }
            if (! empty($content_block->inner_blocks) && self::has_image_block_in_inner_blocks($content_block->inner_blocks)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a navigation heading exists in inner blocks (core/heading or legacy title block).
     *
     * @param \WP_Block_List|null $inner_blocks Inner blocks of a timeline item.
     * @return bool
     */
    private static function has_navigation_heading_in_inner_blocks($inner_blocks)
    {
        if (empty($inner_blocks)) {
            return false;
        }

        foreach ($inner_blocks as $content_block) {
            if ('we-timeline/timeline-item-title' === $content_block->name || 'core/heading' === $content_block->name) {
                return true;
            }
            if (! empty($content_block->inner_blocks) && self::has_navigation_heading_in_inner_blocks($content_block->inner_blocks)) {
                return true;
            }
        }

        return false;
    }

    /**
     * DOM id for the in-item scroll/focus target (heading preferred).
     *
     * @param string $item_id Timeline item anchor/id.
     * @return string
     */
    private static function get_nav_target_id($item_id)
    {
        $item_id = (string) $item_id;
        $safe    = sanitize_html_class('we-timeline-nav-' . $item_id);
        if ('' === $safe) {
            $safe = 'we-timeline-nav-' . substr(md5($item_id), 0, 12);
        }
        return $safe;
    }

    /**
     * Mark the first heading in rendered block HTML as the navigation scroll target.
     *
     * @param string $content     Rendered inner HTML.
     * @param string $nav_target_id Element id to assign.
     * @return string
     */
    private static function mark_navigation_target_in_content($content, $nav_target_id)
    {
        $content = (string) $content;
        if ('' === trim($content)) {
            return $content;
        }

        if (false !== strpos($content, 'we-timeline__nav-target')) {
            return $content;
        }

        $count  = 0;
        $marked = preg_replace_callback(
            '/<(h[1-6])(\s[^>]*)?>/',
            function ($matches) use ($nav_target_id, &$count) {
                if ($count > 0) {
                    return $matches[0];
                }
                ++$count;

                $tag   = $matches[1];
                $attrs = isset($matches[2]) ? (string) $matches[2] : '';

                if (! preg_match('/\sid=(["\']).*?\1/', $attrs)) {
                    $attrs .= ' id="' . esc_attr($nav_target_id) . '"';
                }

                if (preg_match('/\sclass=(["\'])([^"\']*)\1/', $attrs, $class_match)) {
                    $classes = trim($class_match[2]);
                    if (false === strpos($classes, 'we-timeline__nav-target')) {
                        $classes .= ' we-timeline__nav-target';
                    }
                    $attrs = preg_replace(
                        '/\sclass=(["\'])([^"\']*)\1/',
                        ' class="' . esc_attr(trim($classes)) . '"',
                        $attrs,
                        1
                    );
                } else {
                    $attrs .= ' class="we-timeline__nav-target"';
                }

                if (! preg_match('/\stabindex=(["\']).*?\1/', $attrs)) {
                    $attrs .= ' tabindex="-1"';
                }

                return '<' . $tag . $attrs . '>';
            },
            $content,
            1
        );

        if ($count > 0 && is_string($marked)) {
            return $marked;
        }

        return $content;
    }

    /**
     * Whether rendered item HTML already contains a navigation target marker.
     *
     * @param array $post Timeline item data.
     * @return bool
     */
    private static function item_has_nav_target_in_content($post)
    {
        return isset($post['content']) && false !== strpos((string) $post['content'], 'we-timeline__nav-target');
    }

    /**
     * Menu label for a timeline item (title fallback when empty).
     *
     * @param array $post Timeline item data.
     * @return string
     */
    private static function get_menu_item_label($post)
    {
        $label = trim((string) ($post['title'] ?? ''));
        if ('' !== $label) {
            return $label;
        }
        return Settings::get_frontend_string('untitled_item');
    }

    /**
     * Whether a date string can be used for sorting and menu grouping.
     *
     * @param string $date_string Raw date string.
     * @return bool
     */
    private static function is_sortable_date($date_string)
    {
        if (self::is_wordpress_empty_date($date_string)) {
            return false;
        }

        $year = self::get_timeline_year_from_date($date_string);
        return null !== $year;
    }

    /**
     * Whether a raw date string is a WordPress "empty" date placeholder.
     *
     * @param string $date_string Raw date string.
     * @return bool
     */
    private static function is_wordpress_empty_date($date_string)
    {
        $date_string = trim((string) $date_string);
        if ('' === $date_string) {
            return true;
        }

        return (bool) preg_match('/^0000[-/]00[-/]00/', $date_string);
    }

    /**
     * Extract a calendar year from a flexible date string.
     *
     * @param string $date_string Raw date string.
     * @return int|null Year or null when not parseable.
     */
    private static function get_timeline_year_from_date($date_string)
    {
        $date_string = trim((string) $date_string);
        if ('' === $date_string) {
            return null;
        }

        $normalized = self::normalize_date_for_sort($date_string);
        if ('' === $normalized) {
            return null;
        }

        if (preg_match('/^(\d{4})/', $normalized, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 1000 && $year <= 9999) {
                return $year;
            }
        }

        $timestamp = strtotime($normalized);
        if (false === $timestamp) {
            return null;
        }

        $year = (int) gmdate('Y', $timestamp);
        if ($year < 1000 || $year > 9999) {
            return null;
        }

        return $year;
    }

    /**
     * Whether a date string is set and displayable.
     *
     * @param string $date_string Raw date string.
     * @return bool
     */
    private static function has_displayable_date($date_string)
    {
        return '' !== trim((string) $date_string);
    }

    /**
     * Detect precision of a flexible date string.
     *
     * @param string $date_string Raw date string.
     * @return string year|month|day|datetime|unknown
     */
    private static function get_date_precision($date_string)
    {
        $date_string = trim((string) $date_string);
        if ('' === $date_string) {
            return 'unknown';
        }
        if (preg_match('/^\d{4}$/', $date_string)) {
            return 'year';
        }
        if (preg_match('/^\d{4}-\d{2}$/', $date_string)) {
            return 'month';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_string)) {
            return 'day';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $date_string)) {
            return 'datetime';
        }
        return 'unknown';
    }

    /**
     * Normalize a flexible date string to an ISO value for sorting and data-date.
     *
     * @param string $date_string Year, YYYY-MM, YYYY-MM-DD, or datetime.
     * @return string ISO date/datetime or empty string.
     */
    private static function normalize_date_for_sort($date_string)
    {
        $date_string = trim((string) $date_string);
        if ('' === $date_string) {
            return '';
        }
        $precision = self::get_date_precision($date_string);
        if ('year' === $precision) {
            return $date_string . '-07-01';
        }
        if ('month' === $precision) {
            return $date_string . '-01';
        }
        if ('day' === $precision) {
            return $date_string;
        }
        if ('datetime' === $precision) {
            $normalized = str_replace(' ', 'T', $date_string);
            $timestamp  = strtotime($normalized);
            return false !== $timestamp ? gmdate('Y-m-d\TH:i:s', $timestamp) : '';
        }
        $timestamp = strtotime($date_string);
        return false !== $timestamp ? gmdate('Y-m-d', $timestamp) : '';
    }

    /**
     * Unix timestamp for sorting (empty dates sort last in ascending order).
     *
     * @param string $date_string Raw date string.
     * @return int
     */
    private static function get_date_timestamp($date_string)
    {
        $normalized = self::normalize_date_for_sort($date_string);
        if ('' === $normalized) {
            return 0;
        }
        $timestamp = strtotime($normalized);
        return false !== $timestamp ? $timestamp : 0;
    }

    /**
     * Format a flexible date string for display.
     *
     * @param string $date_string Raw date string.
     * @return string
     */
    private static function format_date_for_display($date_string)
    {
        $date_string = trim((string) $date_string);
        if ('' === $date_string) {
            return '';
        }
        $precision = self::get_date_precision($date_string);
        if ('year' === $precision) {
            return $date_string;
        }
        if ('month' === $precision) {
            $timestamp = strtotime($date_string . '-01');
            return false !== $timestamp ? date_i18n('F Y', $timestamp) : $date_string;
        }
        if ('day' === $precision) {
            $timestamp = strtotime($date_string);
            return false !== $timestamp ? date_i18n(get_option('date_format'), $timestamp) : $date_string;
        }
        if ('datetime' === $precision) {
            $timestamp = strtotime(str_replace(' ', 'T', $date_string));
            if (false === $timestamp) {
                return $date_string;
            }
            return date_i18n(get_option('date_format'), $timestamp) . ' ' . date_i18n(get_option('time_format'), $timestamp);
        }
        $timestamp = strtotime($date_string);
        return false !== $timestamp ? date_i18n(get_option('date_format'), $timestamp) : $date_string;
    }

    /**
     * Value for the datetime attribute on <time>.
     *
     * @param string $date_string Raw date string.
     * @return string
     */
    private static function format_date_for_datetime_attr($date_string)
    {
        $date_string = trim((string) $date_string);
        if ('' === $date_string) {
            return '';
        }
        $precision = self::get_date_precision($date_string);
        if ('year' === $precision) {
            return $date_string;
        }
        if ('month' === $precision) {
            return $date_string;
        }
        if ('datetime' === $precision) {
            $normalized = str_replace(' ', 'T', $date_string);
            $timestamp  = strtotime($normalized);
            return false !== $timestamp ? gmdate('Y-m-d\TH:i:s', $timestamp) : $normalized;
        }
        $normalized = self::normalize_date_for_sort($date_string);
        return $normalized ? $normalized : $date_string;
    }

    /**
     * Get translatable suffix for decade labels (e.g. "s" for 1920s, "er" for 1920er).
     *
     * @return string
     */
    public static function get_decade_suffix()
    {
        return Settings::get_frontend_string('decade_suffix');
    }

    /**
     * Sanitize compact menu separator setting.
     *
     * @param string $separators Raw separator mode.
     * @return string
     */
    private static function sanitize_menu_separators($separators)
    {
        $allowed = array('none', 'pipe', 'middot', 'hyphen');
        $separators = is_string($separators) ? strtolower(trim($separators)) : 'none';

        if (! in_array($separators, $allowed, true)) {
            return 'none';
        }

        return $separators;
    }

    /**
     * Sanitize mobile menu mode.
     *
     * @param string $mode Raw mode.
     * @return string
     */
    private static function sanitize_menu_mobile_mode($mode)
    {
        $allowed = array('inherit', 'granularity', 'collapsed', 'short-labels', 'scroll', 'hidden');
        $mode    = is_string($mode) ? strtolower(trim($mode)) : 'inherit';

        if (! in_array($mode, $allowed, true)) {
            return 'inherit';
        }

        return $mode;
    }

    /**
     * Sanitize menu granularity value.
     *
     * @param string $granularity Raw granularity.
     * @return string
     */
    private static function sanitize_menu_granularity($granularity)
    {
        $allowed     = array('auto', 'decades', 'years', 'months', 'items');
        $granularity = is_string($granularity) ? strtolower(trim($granularity)) : 'auto';

        if (! in_array($granularity, $allowed, true)) {
            return 'auto';
        }

        return $granularity;
    }

    /**
     * Sanitize grouped menu sort order.
     *
     * @param string $menu_sort_order Raw menu sort order.
     * @return string inherit, asc, or desc.
     */
    private static function sanitize_menu_sort_order($menu_sort_order)
    {
        $allowed         = array('inherit', 'asc', 'desc');
        $menu_sort_order = is_string($menu_sort_order) ? strtolower(trim($menu_sort_order)) : 'inherit';

        if (! in_array($menu_sort_order, $allowed, true)) {
            return 'inherit';
        }

        return $menu_sort_order;
    }

    /**
     * Resolve grouped menu key sort direction.
     *
     * @param string $menu_sort_order     Menu sort setting.
     * @param string $timeline_sort_order Timeline sort order.
     * @param string $granularity         Resolved menu granularity.
     * @return string asc, desc, or timeline.
     */
    private static function resolve_menu_group_sort_direction($menu_sort_order, $timeline_sort_order, $granularity)
    {
        if ('items' === $granularity) {
            return 'timeline';
        }

        $menu_sort_order     = self::sanitize_menu_sort_order($menu_sort_order);
        $timeline_sort_order = self::sanitize_sort_order($timeline_sort_order);

        if ('inherit' === $menu_sort_order) {
            if ('manual' === $timeline_sort_order) {
                return 'timeline';
            }

            return 'desc' === $timeline_sort_order ? 'desc' : 'asc';
        }

        return 'desc' === $menu_sort_order ? 'desc' : 'asc';
    }

    /**
     * Earliest timeline index for a grouped menu bucket.
     *
     * @param array $group_posts       Posts in one menu group.
     * @param array $post_order_index  Post ID => timeline index map.
     * @return int
     */
    private static function get_menu_group_timeline_index($group_posts, $post_order_index)
    {
        $min_index = PHP_INT_MAX;

        foreach ($group_posts as $post) {
            $post_id = (string) ( $post['id'] ?? '' );
            if ('' === $post_id) {
                continue;
            }

            if (isset($post_order_index[ $post_id ])) {
                $min_index = min($min_index, (int) $post_order_index[ $post_id ]);
            }
        }

        return PHP_INT_MAX === $min_index ? 0 : $min_index;
    }

    /**
     * Sort grouped menu keys chronologically or by timeline display order.
     *
     * @param array  $groups              Grouped posts keyed by decade/year/month.
     * @param array  $posts               Timeline posts in display order.
     * @param string $sort_direction      asc, desc, or timeline.
     * @return array<int|string>
     */
    private static function sort_menu_group_keys($groups, $posts, $sort_direction)
    {
        $keys = array_keys($groups);

        if ('timeline' === $sort_direction) {
            $post_order_index = array();
            foreach ($posts as $index => $post) {
                $post_order_index[ (string) ( $post['id'] ?? '' ) ] = $index;
            }

            usort(
                $keys,
                function ($key_a, $key_b) use ($groups, $post_order_index) {
                    $index_a = self::get_menu_group_timeline_index($groups[ $key_a ], $post_order_index);
                    $index_b = self::get_menu_group_timeline_index($groups[ $key_b ], $post_order_index);

                    return $index_a <=> $index_b;
                }
            );

            return $keys;
        }

        if ('desc' === $sort_direction) {
            rsort($keys, SORT_NATURAL);
            return $keys;
        }

        sort($keys, SORT_NATURAL);
        return $keys;
    }

    /**
     * Sanitize mobile menu label format.
     *
     * @param string $format Raw format.
     * @return string
     */
    private static function sanitize_menu_mobile_label_format($format)
    {
        $allowed = array('year', 'year-title', 'title-truncate');
        $format  = is_string($format) ? strtolower(trim($format)) : 'year';

        if (! in_array($format, $allowed, true)) {
            return 'year';
        }

        return $format;
    }

    /**
     * Sanitize mobile menu breakpoint in pixels.
     *
     * @param mixed $breakpoint Raw breakpoint.
     * @return int
     */
    private static function sanitize_menu_mobile_breakpoint($breakpoint)
    {
        $breakpoint = absint($breakpoint);
        if ($breakpoint < 480) {
            return 480;
        }
        if ($breakpoint > 1200) {
            return 1200;
        }

        return $breakpoint;
    }

    /**
     * Build CSS modifier classes for the timeline navigation menu.
     *
     * @param array $attributes Block attributes.
     * @return string Space-separated class list.
     */
    private static function get_menu_classes($attributes)
    {
        $allowed_positions = array('sidebar', 'top');
        $allowed_aligns    = array('left', 'center', 'right');
        $allowed_styles    = array('default', 'compact');

        $position = isset($attributes['menuPosition']) ? (string) $attributes['menuPosition'] : 'sidebar';
        if (! in_array($position, $allowed_positions, true)) {
            $position = 'sidebar';
        }

        $align = isset($attributes['menuAlign']) ? (string) $attributes['menuAlign'] : 'left';
        if (! in_array($align, $allowed_aligns, true)) {
            $align = 'left';
        }

        $style = isset($attributes['menuStyle']) ? (string) $attributes['menuStyle'] : 'default';
        if (! in_array($style, $allowed_styles, true)) {
            $style = 'default';
        }

        $classes = array(
            'we-timeline-menu',
            'we-timeline-menu--' . $position,
        );

        if ('top' === $position) {
            $classes[] = 'we-timeline-menu--align-' . $align;
        }

        if ('compact' === $style) {
            $classes[] = 'we-timeline-menu--compact';
        }

        return implode(' ', $classes);
    }

    /**
     * Separator character for compact menu items (between links only).
     *
     * @param array $attributes Block attributes.
     * @return string Empty when separators are disabled.
     */
    private static function get_menu_separator_char($attributes)
    {
        if ('compact' !== ($attributes['menuStyle'] ?? 'default')) {
            return '';
        }

        $separators = self::sanitize_menu_separators($attributes['menuSeparators'] ?? 'none');
        if ('pipe' === $separators) {
            return '|';
        }
        if ('middot' === $separators) {
            return '·';
        }
        if ('hyphen' === $separators) {
            return '-';
        }

        return '';
    }

    /**
     * Build menu items from posts (same grouping logic as view.js for editor and initial frontend output).
     *
     * @param array  $posts           Timeline posts (id, date, title).
     * @param string $granularity     Menu granularity: auto, decades, years, months, items.
     * @param string $sort_order      Timeline display sort order.
     * @param string $menu_sort_order Grouped menu sort order.
     * @return array List of menu entries (label, value, type, first_id for groups).
     */
    private static function build_menu_items($posts, $granularity, $sort_order = 'asc', $menu_sort_order = 'inherit')
    {
        if (empty($posts)) {
            return array();
        }

        $granularity = $granularity ?: 'auto';
        $granularity = strtolower(trim($granularity));

        $dated_posts = array_values(
            array_filter(
                $posts,
                function ($post) {
                    return self::is_sortable_date($post['date'] ?? '');
                }
            )
        );

        // Without dates, each item is listed by title in the menu.
        if (empty($dated_posts)) {
            $items = array();
            foreach ($posts as $post) {
                $items[] = array(
                    'label' => self::get_menu_item_label($post),
                    'value' => (string) $post['id'],
                    'type'  => 'item',
                );
            }
            return $items;
        }

        if ('auto' === $granularity) {
            $timestamps = array_map(
                function ($p) {
                    return self::get_date_timestamp($p['date']);
                },
                $dated_posts
            );
            $min_ts     = min($timestamps);
            $max_ts     = max($timestamps);
            $span_years = ($max_ts - $min_ts) / (365 * 24 * 60 * 60);
            if ($span_years < 1) {
                $granularity = 'items';
            } elseif ($span_years <= 5) {
                $granularity = 'months';
            } else {
                $granularity = 'years';
            }
        }

        if ('items' === $granularity) {
            $items = array();
            foreach ($posts as $post) {
                $items[] = array(
                    'label' => self::get_menu_item_label($post),
                    'value' => (string) $post['id'],
                    'type'  => 'item',
                );
            }
            return $items;
        }

        $groups = array();
        foreach ($dated_posts as $post) {
            $year = self::get_timeline_year_from_date($post['date']);
            if (null === $year) {
                continue;
            }
            $ts = self::get_date_timestamp($post['date']);
            $y  = $year;
            $m  = (int) gmdate('n', $ts);
            if ('decades' === $granularity) {
                $key = (int) (floor($y / 10) * 10);
            } elseif ('years' === $granularity) {
                $key = $y;
            } else {
                $key = $y . '-' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            }
            if (! isset($groups[ $key ])) {
                $groups[ $key ] = array();
            }
            $groups[ $key ][] = $post;
        }

        $sort_direction = self::resolve_menu_group_sort_direction($menu_sort_order, $sort_order, $granularity);
        $sorted_keys    = self::sort_menu_group_keys($groups, $posts, $sort_direction);

        $type  = 'decades' === $granularity ? 'decade' : ('years' === $granularity ? 'year' : 'month');
        $items = array();
        foreach ($sorted_keys as $key) {
            $group_posts = $groups[ $key ];
            $first = $group_posts[0];
            $ts    = self::get_date_timestamp($first['date']);
            if ('decades' === $granularity) {
                $label = $key . self::get_decade_suffix();
            } elseif ('years' === $granularity) {
                $label = (string) $key;
            } else {
                $label = date_i18n('F Y', $ts);
            }
            $items[] = array(
                'label'    => $label,
                'value'    => (string) $key,
                'type'     => $type,
                'first_id' => (string) $first['id'],
            );
        }

        return $items;
    }

    /**
     * Get date value from post.
     *
     * @param int    $post_id Post ID.
     * @param string $date_field Date field name.
     * @return string
     */
    private static function get_date_value($post_id, $date_field)
    {
        if ('date' === $date_field) {
            return self::get_sortable_post_date($post_id);
        }

        $meta = trim((string) get_post_meta($post_id, $date_field, true));
        if ('' !== $meta) {
            return $meta;
        }

        return self::get_sortable_post_date($post_id);
    }

    /**
     * Post publish date when it is a valid, non-sentinel timeline date.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    private static function get_sortable_post_date($post_id)
    {
        $post = get_post($post_id);
        if (! $post instanceof \WP_Post) {
            return '';
        }

        if (self::is_wordpress_empty_date($post->post_date)) {
            return '';
        }

        $date = get_the_date('Y-m-d', $post_id);
        if ('' === $date || ! self::is_sortable_date($date)) {
            return '';
        }

        return $date;
    }

    /**
     * Render timeline item.
     *
     * @param array  $post Post data.
     * @param string $layout Layout type.
     * @param string $position Position type.
     * @param string $icon Icon name.
     * @param int    $index Item index.
     */
    private static function render_item($post, $layout, $position = 'left', $icon = 'calendar-alt', $index = 0, $attributes = array())
    {
        $item_class = 'we-timeline__item';

        // Determine position class based on layout and position setting.
        if ($layout === 'vertical') {
            if ($position === 'alternating') {
                $item_class .= ($index % 2 === 0) ? ' we-timeline__item--left' : ' we-timeline__item--right';
            } elseif ($position === 'right') {
                $item_class .= ' we-timeline__item--right';
            } else {
                $item_class .= ' we-timeline__item--left';
            }
        } elseif ($layout === 'horizontal-scroll') {
            if ($position === 'alternating') {
                $item_class .= ($index % 2 === 0) ? ' we-timeline__item--top' : ' we-timeline__item--bottom';
            } elseif ($position === 'bottom') {
                $item_class .= ' we-timeline__item--bottom';
            } else {
                $item_class .= ' we-timeline__item--top';
            }
        }

        $has_icon = ! empty($icon);
        $icon_size = $attributes['iconSize'] ?? 'medium';
        // Check if background is set (not transparent or empty)
        $has_background = false;
        if (! empty($attributes['style']['color']['background'])) {
            $bg = $attributes['style']['color']['background'];
            $has_background = ($bg !== 'transparent' && $bg !== 'rgba(0,0,0,0)' && ! empty($bg));
        } elseif (! empty($attributes['backgroundColor'])) {
            $has_background = true;
        }
?>
        <?php
        $item_date_raw   = isset($post['date']) ? (string) $post['date'] : '';
        $item_date_sort  = self::normalize_date_for_sort($item_date_raw);
        $item_date_label = self::format_date_for_display($item_date_raw);
        $item_date_attr  = self::format_date_for_datetime_attr($item_date_raw);
        ?>
        <?php
        $nav_target_id   = isset($post['nav_target_id'])
            ? (string) $post['nav_target_id']
            : self::get_nav_target_id((string) $post['id']);
        $has_nav_in_html = self::item_has_nav_target_in_content($post);

        $item_data_attrs = array(
            'class'               => $item_class,
            'data-id'             => (string) $post['id'],
            'data-nav-target'     => $nav_target_id,
            'data-has-icon'       => $has_icon ? 'true' : 'false',
            'data-has-background' => $has_background ? 'true' : 'false',
            'data-icon-size'      => $icon_size,
        );
        if (self::is_sortable_date($item_date_raw)) {
            $item_date_sort  = self::normalize_date_for_sort($item_date_raw);
            $item_data_attrs['data-date'] = $item_date_sort ? $item_date_sort : $item_date_raw;
        }
        ?>
        <article <?php echo self::build_attributes($item_data_attrs); ?>>
            <?php if ($has_icon) : ?>
                <div class="we-timeline__item-icon we-timeline__item-icon--<?php echo esc_attr($icon_size); ?>">
                    <?php if ($icon === 'dot') : ?>
                        <span class="we-timeline__item-icon-dot"></span>
                    <?php else : ?>
                        <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php $free_layout = ! empty($post['free_layout']); ?>
            <div class="we-timeline__item-content<?php echo $free_layout ? ' we-timeline__item-content--free' : ''; ?>">
                <?php if (! $free_layout && ! empty($post['thumbnail'])) : ?>
                    <div class="we-timeline__item-thumbnail">
                        <img src="<?php echo esc_url($post['thumbnail']); ?>" alt="<?php echo esc_attr($post['title']); ?>" />
                    </div>
                <?php endif; ?>
                <div class="we-timeline__item-body<?php echo $free_layout ? ' we-timeline__item-body--free' : ''; ?>">
                    <?php
                    $show_item_dates = ! isset($attributes['showItemDates']) || $attributes['showItemDates'];
                    if ($show_item_dates && self::is_sortable_date($item_date_raw)) :
                        ?>
                        <time
                            class="we-timeline__item-date<?php echo ($free_layout && ! $has_nav_in_html && empty($post['legacy_title'])) ? ' we-timeline__nav-target' : ''; ?>"
                            datetime="<?php echo esc_attr($item_date_attr); ?>"
                            <?php echo ($free_layout && ! $has_nav_in_html && empty($post['legacy_title'])) ? 'id="' . esc_attr($nav_target_id) . '" tabindex="-1"' : ''; ?>
                        >
                            <?php echo esc_html($item_date_label); ?>
                        </time>
                    <?php endif; ?>
                    <?php if ($free_layout && ! empty($post['legacy_title'])) : ?>
                        <h3
                            class="we-timeline__item-title<?php echo ! $has_nav_in_html ? ' we-timeline__nav-target' : ''; ?>"
                            <?php echo ! $has_nav_in_html ? 'id="' . esc_attr($nav_target_id) . '" tabindex="-1"' : ''; ?>
                        >
                            <?php echo esc_html($post['legacy_title']); ?>
                        </h3>
                    <?php endif; ?>
                    <?php if (! $free_layout) : ?>
                        <h3 class="we-timeline__item-title we-timeline__nav-target" id="<?php echo esc_attr($nav_target_id); ?>" tabindex="-1">
                            <?php if (! empty($post['permalink'])) : ?>
                                <a href="<?php echo esc_url($post['permalink']); ?>">
                                    <?php echo esc_html($post['title']); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($post['title']); ?>
                            <?php endif; ?>
                        </h3>
                        <?php if (! empty($post['excerpt'])) : ?>
                            <div class="we-timeline__item-excerpt">
                                <?php echo wp_kses_post($post['excerpt']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (! empty($post['content'])) : ?>
                        <div class="<?php echo $free_layout ? 'we-timeline__item-inner' : 'we-timeline__item-extra'; ?>">
                            <?php echo $post['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks. ?>
                        </div>
                    <?php endif; ?>
                    <?php if (! empty($post['permalink']) && empty($attributes['showFullContent'])) : ?>
                        <a href="<?php echo esc_url($post['permalink']); ?>" class="we-timeline__item-read-more">
                            <?php echo esc_html(Settings::get_frontend_string('read_more')); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
<?php
    }


    /**
     * Build HTML attributes string.
     *
     * @param array $attributes Attributes array.
     * @return string
     */
    private static function build_attributes($attributes)
    {
        $output = array();
        foreach ($attributes as $key => $value) {
            $output[] = esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        return implode(' ', $output);
    }
}
