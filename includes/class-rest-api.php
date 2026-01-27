<?php

/**
 * REST API Endpoints
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Rest_Api
 */
class Rest_Api
{

    /**
     * Initialize hooks.
     */
    public function init()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes.
     */
    public function register_routes()
    {
        register_rest_route(
            'we-timeline/v1',
            '/posts',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_posts'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'post_type'      => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                    'taxonomy'        => array(
                        'type' => 'string',
                    ),
                    'term'            => array(
                        'type' => 'integer',
                    ),
                    'date_field'      => array(
                        'type'    => 'string',
                        'default' => 'date',
                    ),
                    'sort_order'      => array(
                        'type'    => 'string',
                        'default' => 'asc',
                        'enum'    => array('asc', 'desc'),
                    ),
                    'per_page'        => array(
                        'type'    => 'integer',
                        'default' => 100,
                    ),
                ),
            )
        );

        register_rest_route(
            'we-timeline/v1',
            '/menu-items',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_menu_items'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'post_ids'        => array(
                        'type'  => 'array',
                        'items' => array('type' => 'integer'),
                    ),
                    'date_field'      => array(
                        'type'    => 'string',
                        'default' => 'date',
                    ),
                    'granularity'     => array(
                        'type'    => 'string',
                        'default' => 'auto',
                        'enum'    => array('auto', 'years', 'months', 'items'),
                    ),
                ),
            )
        );
    }

    /**
     * Get posts for timeline.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_posts($request)
    {
        $post_type  = $request->get_param('post_type');
        $taxonomy   = $request->get_param('taxonomy');
        $term       = $request->get_param('term');
        $date_field = $request->get_param('date_field');
        $sort_order = $request->get_param('sort_order');
        $per_page   = $request->get_param('per_page');

        $args = array(
            'post_type'      => $post_type ? $post_type : 'post',
            'posts_per_page' => $per_page,
            'post_status'    => 'publish',
            'order'          => strtoupper($sort_order),
        );

        // Add taxonomy query if taxonomy and term are provided.
        if ($taxonomy && $term > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term,
                ),
            );
        }

        $query = new \WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Get date value.
                $date_value = $this->get_date_value($post_id, $date_field);

                $posts[] = array(
                    'id'         => $post_id,
                    'title'      => get_the_title(),
                    'excerpt'    => get_the_excerpt(),
                    'content'    => get_the_content(),
                    'date'       => $date_value,
                    'date_field' => $date_field,
                    'permalink'  => get_permalink(),
                    'thumbnail'  => get_the_post_thumbnail_url($post_id, 'medium'),
                    'author'     => get_the_author(),
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

        return rest_ensure_response($posts);
    }

    /**
     * Get menu items for timeline navigation.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_menu_items($request)
    {
        $post_ids    = $request->get_param('post_ids');
        $date_field  = $request->get_param('date_field');
        $granularity = $request->get_param('granularity');

        if (empty($post_ids)) {
            return rest_ensure_response(array());
        }

        $dates = array();
        foreach ($post_ids as $post_id) {
            $date_value = $this->get_date_value($post_id, $date_field);
            if ($date_value) {
                $dates[] = strtotime($date_value);
            }
        }

        if (empty($dates)) {
            return rest_ensure_response(array());
        }

        $min_date = min($dates);
        $max_date = max($dates);
        $span     = $max_date - $min_date;

        // Auto-determine granularity.
        if ('auto' === $granularity) {
            $years = $span / (365 * 24 * 60 * 60);
            if ($years < 1) {
                $granularity = 'items';
            } elseif ($years <= 5) {
                $granularity = 'months';
            } else {
                $granularity = 'years';
            }
        }

        $menu_items = array();

        switch ($granularity) {
            case 'years':
                $current = $min_date;
                while ($current <= $max_date) {
                    $year = date('Y', $current);
                    $menu_items[] = array(
                        'label' => $year,
                        'value' => $year,
                        'type'  => 'year',
                    );
                    $current = strtotime('+1 year', $current);
                }
                break;

            case 'months':
                $current = $min_date;
                while ($current <= $max_date) {
                    $year_month = date('Y-m', $current);
                    $menu_items[] = array(
                        'label' => date('F Y', $current),
                        'value' => $year_month,
                        'type'  => 'month',
                    );
                    $current = strtotime('+1 month', $current);
                }
                break;

            case 'items':
            default:
                foreach ($post_ids as $post_id) {
                    $date_value = $this->get_date_value($post_id, $date_field);
                    if ($date_value) {
                        $menu_items[] = array(
                            'label' => get_the_title($post_id),
                            'value' => $post_id,
                            'type'  => 'item',
                            'date'  => $date_value,
                        );
                    }
                }
                break;
        }

        return rest_ensure_response($menu_items);
    }

    /**
     * Get date value from post.
     *
     * @param int    $post_id Post ID.
     * @param string $date_field Date field name.
     * @return string|null
     */
    private function get_date_value($post_id, $date_field)
    {
        if ('date' === $date_field) {
            return get_the_date('Y-m-d', $post_id);
        }

        $value = get_post_meta($post_id, $date_field, true);
        if ($value) {
            return $value;
        }

        return null;
    }
}
