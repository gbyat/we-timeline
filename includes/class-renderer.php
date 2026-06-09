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
        $term       = $attributes['term'] ?? 0;
        $date_field = $attributes['dateField'] ?? 'date';
        $sort_order = $attributes['sortOrder'] ?? 'asc';
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

            $posts = self::get_posts($post_type, $taxonomy, $term, $date_field, $sort_order);
        }

        if (empty($posts)) {
            return '<p>' . esc_html__('No timeline items found.', 'we-timeline') . '</p>';
        }

        // Store timeline page reference for each post (only on frontend, not in editor).
        if ('items' !== $content_source && ! is_admin() && ! defined('REST_REQUEST')) {
            $current_page_id = get_the_ID();
            if ($current_page_id) {
                $post_ids = array_column($posts, 'id');
                Timeline_Link::store_timeline_page($post_ids, $current_page_id);
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

        $menu_items = $show_menu ? self::build_menu_items($posts, $menu_granularity) : array();

        ob_start();
?>
        <div <?php echo $wrapper_attributes_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() is escaped. ?>>
            <?php if ($show_menu) : ?>
                <?php
                $menu_separator_char = self::get_menu_separator_char($attributes);
                $menu_separators     = self::sanitize_menu_separators($attributes['menuSeparators'] ?? 'none');
                ?>
                <nav class="<?php echo esc_attr(self::get_menu_classes($attributes)); ?>" data-granularity="<?php echo esc_attr($menu_granularity); ?>" data-decade-suffix="<?php echo esc_attr(self::get_decade_suffix()); ?>" data-timeline-id="<?php echo esc_attr($block_id); ?>" data-menu-separators="<?php echo esc_attr($menu_separators); ?>"<?php echo '' !== $menu_separator_char ? ' data-menu-separator-char="' . esc_attr($menu_separator_char) . '"' : ''; ?> aria-label="<?php echo esc_attr__('Jump to timeline periods', 'we-timeline'); ?>">
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
     * @return array
     */
    private static function get_posts($post_type, $taxonomy, $term, $date_field, $sort_order)
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

        // Set orderby and order for date field.
        if ('date' === $date_field) {
            $args['orderby'] = 'date';
            $args['order']   = strtoupper($sort_order);
        } else {
            // For custom fields, we'll sort after fetching.
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

                // Get content and excerpt directly from post object to avoid filter issues.
                $post_obj = get_post($post_id);
                $excerpt  = $post_obj->post_excerpt;
                if (empty($excerpt)) {
                    $excerpt = wp_trim_words($post_obj->post_content, 55);
                }
                $content = apply_filters('the_content', $post_obj->post_content);

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

        // Sort by date field if custom field.
        if ('date' !== $date_field) {
            usort(
                $posts,
                function ($a, $b) use ($sort_order) {
                    $date_a = strtotime($a['date']);
                    $date_b = strtotime($b['date']);

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

        usort(
            $items,
            function ($a, $b) use ($sort_order) {
                $date_a = self::get_date_timestamp($a['date']);
                $date_b = self::get_date_timestamp($b['date']);

                if ('desc' === $sort_order) {
                    return $date_b <=> $date_a;
                }
                return $date_a <=> $date_b;
            }
        );

        return $items;
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

        $marked = preg_replace(
            '/<(h[1-6])(\s[^>]*)?>/',
            '<$1 id="' . esc_attr($nav_target_id) . '" class="we-timeline__nav-target" tabindex="-1"$2>',
            $content,
            1,
            $count
        );

        if ($count > 0) {
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
        return __('Untitled item', 'we-timeline');
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
            return $timestamp ? gmdate('Y-m-d\TH:i:s', $timestamp) : '';
        }
        $timestamp = strtotime($date_string);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : '';
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
            return PHP_INT_MAX;
        }
        $timestamp = strtotime($normalized);
        return $timestamp ? $timestamp : PHP_INT_MAX;
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
            return $timestamp ? date_i18n('F Y', $timestamp) : $date_string;
        }
        if ('datetime' === $precision) {
            $timestamp = strtotime(str_replace(' ', 'T', $date_string));
            if (! $timestamp) {
                return $date_string;
            }
            return date_i18n(get_option('date_format'), $timestamp) . ' ' . date_i18n(get_option('time_format'), $timestamp);
        }
        $timestamp = strtotime($date_string);
        return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : $date_string;
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
            return $timestamp ? gmdate('Y-m-d\TH:i:s', $timestamp) : $normalized;
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
        /* translators: Suffix for decade labels. E.g. "s" → 1920s (English), "er" → 1920er (German). */
        return _x('s', 'decade suffix', 'we-timeline');
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
     * @param array  $posts      Timeline posts (id, date, title).
     * @param string $granularity Menu granularity: auto, decades, years, months, items.
     * @return array List of menu entries (label, value, type, first_id for groups).
     */
    private static function build_menu_items($posts, $granularity)
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
                    return self::has_displayable_date($post['date'] ?? '');
                }
            )
        );
        $undated_posts = array_values(
            array_filter(
                $posts,
                function ($post) {
                    return ! self::has_displayable_date($post['date'] ?? '');
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
            $ts = self::get_date_timestamp($post['date']);
            $y  = (int) gmdate('Y', $ts);
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
        ksort($groups);

        $type  = 'decades' === $granularity ? 'decade' : ('years' === $granularity ? 'year' : 'month');
        $items = array();
        foreach ($groups as $key => $group_posts) {
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

        foreach ($undated_posts as $post) {
            $items[] = array(
                'label' => self::get_menu_item_label($post),
                'value' => (string) $post['id'],
                'type'  => 'item',
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
            return get_the_date('Y-m-d', $post_id);
        }

        $value = get_post_meta($post_id, $date_field, true);
        return $value ? $value : get_the_date('Y-m-d', $post_id);
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
        if (self::has_displayable_date($item_date_raw)) {
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
                    if ($show_item_dates && self::has_displayable_date($item_date_raw)) :
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
                    <?php if (! empty($post['permalink'])) : ?>
                        <a href="<?php echo esc_url($post['permalink']); ?>" class="we-timeline__item-read-more">
                            <?php echo esc_html__('Read more', 'we-timeline'); ?>
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
