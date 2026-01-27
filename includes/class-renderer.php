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
     * @param array $attributes Block attributes.
     * @return string
     */
    public static function render($attributes)
    {
        // Ensure attributes is an array.
        if (! is_array($attributes)) {
            $attributes = array();
        }

        $layout       = $attributes['layout'] ?? 'vertical';
        $position     = $attributes['position'] ?? ($layout === 'vertical' ? 'left' : 'top');
        $visible_items = $attributes['visibleItems'] ?? 3;
        $icon         = $attributes['icon'] ?? 'calendar-alt';
        $post_type  = $attributes['postType'] ?? '';
        $taxonomy   = $attributes['taxonomy'] ?? '';
        $term       = $attributes['term'] ?? 0;
        $date_field = $attributes['dateField'] ?? 'date';
        $sort_order = $attributes['sortOrder'] ?? 'asc';
        $show_menu  = $attributes['showMenu'] ?? false;
        $menu_granularity = $attributes['menuGranularity'] ?? 'auto';

        // If post type is not set, try to determine it from taxonomy.
        if (empty($post_type)) {
            if (! empty($taxonomy)) {
                // Get the taxonomy object to find associated post types.
                $taxonomy_obj = get_taxonomy($taxonomy);
                if ($taxonomy_obj && ! empty($taxonomy_obj->object_type)) {
                    // Use the first post type associated with this taxonomy.
                    $post_type = $taxonomy_obj->object_type[0];
                } else {
                    // Default to 'post' if taxonomy is not found or has no post types.
                    $post_type = 'post';
                }
            } else {
                // Default to 'post' if no taxonomy is set.
                $post_type = 'post';
            }
        }

        // Get posts.
        $posts = self::get_posts($post_type, $taxonomy, $term, $date_field, $sort_order);

        if (empty($posts)) {
            return '<p>' . esc_html__('No timeline items found.', 'we-timeline') . '</p>';
        }

        // Store timeline page reference for each post (only on frontend, not in editor).
        if (! is_admin() && ! defined('REST_REQUEST')) {
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

        // Add native WordPress color classes and styles.
        $color_classes = array();
        $color_styles  = array();

        // Background color.
        if (! empty($attributes['backgroundColor'])) {
            $color_classes[] = 'has-background';
            if (is_string($attributes['backgroundColor'])) {
                // Preset color slug.
                $color_classes[] = 'has-' . esc_attr($attributes['backgroundColor']) . '-background-color';
            } elseif (isset($attributes['style']['color']['background'])) {
                // Custom color value.
                $color_styles[] = 'background-color: ' . esc_attr($attributes['style']['color']['background']) . ';';
            }
        }

        // Text color.
        if (! empty($attributes['textColor'])) {
            $color_classes[] = 'has-text-color';
            if (is_string($attributes['textColor'])) {
                // Preset color slug.
                $color_classes[] = 'has-' . esc_attr($attributes['textColor']) . '-color';
            } elseif (isset($attributes['style']['color']['text'])) {
                // Custom color value - apply directly to wrapper
                $color_styles[] = 'color: ' . esc_attr($attributes['style']['color']['text']) . ';';
            }
        } elseif (isset($attributes['style']['color']['text'])) {
            // Custom text color without preset
            $color_classes[] = 'has-text-color';
            $color_styles[] = 'color: ' . esc_attr($attributes['style']['color']['text']) . ';';
        }

        // Link color.
        if (! empty($attributes['style']['color']['link'])) {
            $color_classes[] = 'has-link-color';
            $color_styles[]  = '--wp--preset--color--link: ' . esc_attr($attributes['style']['color']['link']) . ';';
        }

        // Additional color CSS variables for timeline elements
        if (! empty($attributes['style']['color']['link'])) {
            $color_styles[] = '--we-timeline-line-active-color: ' . esc_attr($attributes['style']['color']['link']) . ';';
        }
        if (! empty($attributes['style']['color']['background'])) {
            $color_styles[] = '--we-timeline-item-background: ' . esc_attr($attributes['style']['color']['background']) . ';';
        }

        // Timeline-specific colors from attributes (check both direct attributes and style.color)
        $timeline_line_color = $attributes['timelineLineColor'] ?? $attributes['style']['color']['timelineLine'] ?? '';
        $timeline_line_active_color = $attributes['timelineLineActiveColor'] ?? $attributes['style']['color']['timelineLineActive'] ?? '';
        $item_background_color = $attributes['itemBackgroundColor'] ?? $attributes['style']['color']['itemBackground'] ?? '';
        $icon_color = $attributes['iconColor'] ?? $attributes['style']['color']['icon'] ?? '';
        $date_color = $attributes['dateColor'] ?? $attributes['style']['color']['date'] ?? '';
        
        if (! empty($timeline_line_color)) {
            $color_styles[] = '--we-timeline-line-color: ' . esc_attr($timeline_line_color) . ';';
        }
        if (! empty($timeline_line_active_color)) {
            $color_styles[] = '--we-timeline-line-active-color: ' . esc_attr($timeline_line_active_color) . ';';
        }
        if (! empty($item_background_color)) {
            $color_styles[] = '--we-timeline-item-background: ' . esc_attr($item_background_color) . ';';
        }
        if (! empty($icon_color)) {
            $color_styles[] = '--we-timeline-icon-color: ' . esc_attr($icon_color) . ';';
        }
        if (! empty($date_color)) {
            $color_styles[] = '--we-timeline-date-color: ' . esc_attr($date_color) . ';';
        }
        if (! empty($attributes['itemBorderRadius'])) {
            $color_styles[] = '--we-timeline-item-border-radius: ' . esc_attr($attributes['itemBorderRadius']) . ';';
        }

        $wrapper_classes = array_merge($wrapper_classes, $color_classes);

        $wrapper_attributes = array(
            'class' => implode(' ', $wrapper_classes),
        );

        // Build style attribute.
        $style_parts = array();

        // Add visible items CSS variable for horizontal scroll layouts.
        if ($layout === 'horizontal-scroll') {
            $style_parts[] = '--visible-items: ' . intval($visible_items) . ';';
        }

        // Add color styles.
        if (! empty($color_styles)) {
            $style_parts = array_merge($style_parts, $color_styles);
        }

        if (! empty($style_parts)) {
            $wrapper_attributes['style'] = implode(' ', $style_parts);
        }

        // Generate unique block ID for menu connection.
        if (function_exists('wp_generate_uuid4')) {
            $block_id = 'we-timeline-' . wp_generate_uuid4();
        } else {
            $block_id = 'we-timeline-' . uniqid('', true);
        }
        $wrapper_attributes['id'] = $block_id;
        $wrapper_attributes['data-timeline-id'] = $block_id;

        ob_start();
?>
        <div <?php echo self::build_attributes($wrapper_attributes); ?>>
            <?php if ($show_menu) : ?>
                <div class="we-timeline-menu" data-granularity="<?php echo esc_attr($menu_granularity); ?>" data-timeline-id="<?php echo esc_attr($block_id); ?>">
                    <div class="we-timeline-menu__items"></div>
                </div>
            <?php endif; ?>
            <div class="we-timeline__items">
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

                // Check if post is longer than the displayed excerpt (needs "Read more").
                $excerpt_words  = str_word_count(strip_tags($excerpt));
                $content_words  = str_word_count(strip_tags($post_obj->post_content));
                $has_more       = $content_words > $excerpt_words;

                $posts[] = array(
                    'id'         => $post_id,
                    'title'      => get_the_title($post_id),
                    'excerpt'    => $excerpt,
                    'content'    => $content,
                    'date'       => $date_value,
                    'permalink'  => get_permalink($post_id),
                    'thumbnail'  => get_the_post_thumbnail_url($post_id, 'medium'),
                    'has_more'   => $has_more,
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
        <article class="<?php echo esc_attr($item_class); ?>" data-id="<?php echo esc_attr($post['id']); ?>" data-date="<?php echo esc_attr($post['date']); ?>" data-has-icon="<?php echo $has_icon ? 'true' : 'false'; ?>" data-has-background="<?php echo $has_background ? 'true' : 'false'; ?>" data-icon-size="<?php echo esc_attr($icon_size); ?>">
            <?php if ($has_icon) : ?>
                <div class="we-timeline__item-icon we-timeline__item-icon--<?php echo esc_attr($icon_size); ?>">
                    <?php if ($icon === 'dot') : ?>
                        <span class="we-timeline__item-icon-dot"></span>
                    <?php else : ?>
                        <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="we-timeline__item-content">
                <?php if (! empty($post['thumbnail'])) : ?>
                    <div class="we-timeline__item-thumbnail">
                        <img src="<?php echo esc_url($post['thumbnail']); ?>" alt="<?php echo esc_attr($post['title']); ?>" />
                    </div>
                <?php endif; ?>
                <div class="we-timeline__item-body">
                    <time class="we-timeline__item-date" datetime="<?php echo esc_attr($post['date']); ?>">
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($post['date']))); ?>
                    </time>
                    <h3 class="we-timeline__item-title">
                        <a href="<?php echo esc_url($post['permalink']); ?>">
                            <?php echo esc_html($post['title']); ?>
                        </a>
                    </h3>
                    <div class="we-timeline__item-excerpt">
                        <?php echo wp_kses_post($post['excerpt']); ?>
                    </div>
                    <?php if (! empty($post['has_more'])) : ?>
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
