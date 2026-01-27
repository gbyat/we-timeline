<?php

/**
 * Custom Post Type
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Post_Type
 */
class Post_Type
{

    /**
     * Initialize hooks.
     */
    public function init()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_timeline_date'));
    }

    /**
     * Register custom post type.
     */
    public function register_post_type()
    {
        // Only register if enabled in settings.
        if (! Settings::is_post_type_enabled()) {
            return;
        }

        register_post_type(
            'timeline',
            array(
                'labels'              => array(
                    'name'               => __('Timeline Items', 'we-timeline'),
                    'singular_name'      => __('Timeline Item', 'we-timeline'),
                    'menu_name'          => __('Timeline', 'we-timeline'),
                    'add_new'            => __('Add New', 'we-timeline'),
                    'add_new_item'       => __('Add New Timeline Item', 'we-timeline'),
                    'edit_item'          => __('Edit Timeline Item', 'we-timeline'),
                    'new_item'           => __('New Timeline Item', 'we-timeline'),
                    'view_item'          => __('View Timeline Item', 'we-timeline'),
                    'search_items'       => __('Search Timeline Items', 'we-timeline'),
                    'not_found'          => __('No timeline items found', 'we-timeline'),
                    'not_found_in_trash' => __('No timeline items found in Trash', 'we-timeline'),
                ),
                'public'              => true,
                'has_archive'         => true,
                'supports'            => array('title', 'editor', 'thumbnail', 'custom-fields'),
                'show_in_rest'        => true,
                'rest_base'           => 'timeline',
                'menu_icon'           => 'dashicons-calendar-alt',
            )
        );
    }

    /**
     * Add meta boxes.
     */
    public function add_meta_boxes()
    {
        // Only add meta boxes if post type is enabled.
        if (! Settings::is_post_type_enabled()) {
            return;
        }

        add_meta_box(
            'timeline_date',
            __('Timeline Date', 'we-timeline'),
            array($this, 'render_timeline_date_meta_box'),
            'timeline',
            'side',
            'high'
        );
    }

    /**
     * Render timeline date meta box.
     *
     * @param \WP_Post $post Post object.
     */
    public function render_timeline_date_meta_box($post)
    {
        wp_nonce_field('timeline_date_meta_box', 'timeline_date_meta_box_nonce');

        $timeline_date = get_post_meta($post->ID, 'timeline_date', true);
?>
        <p>
            <label for="timeline_date"><?php esc_html_e('Date:', 'we-timeline'); ?></label>
            <input type="date" id="timeline_date" name="timeline_date" value="<?php echo esc_attr($timeline_date); ?>" style="width: 100%;" />
        </p>
<?php
    }

    /**
     * Save timeline date.
     *
     * @param int $post_id Post ID.
     */
    public function save_timeline_date($post_id)
    {
        // Only save if post type is enabled.
        if (! Settings::is_post_type_enabled()) {
            return;
        }

        // Check if this is the timeline post type.
        if (get_post_type($post_id) !== 'timeline') {
            return;
        }

        // Check nonce.
        if (! isset($_POST['timeline_date_meta_box_nonce']) || ! wp_verify_nonce($_POST['timeline_date_meta_box_nonce'], 'timeline_date_meta_box')) {
            return;
        }

        // Check autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions.
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save timeline date.
        if (isset($_POST['timeline_date'])) {
            update_post_meta($post_id, 'timeline_date', sanitize_text_field($_POST['timeline_date']));
        }
    }
}
