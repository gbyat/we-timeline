<?php

/**
 * Timeline icon helpers.
 *
 * @package Webentwicklerin\Timeline
 */

namespace Webentwicklerin\Timeline;

/**
 * Class Icons
 */
class Icons
{

    /**
     * Custom SVG icons keyed by block attribute value.
     *
     * @var array<string, string>
     */
    const CUSTOM_ICONS = array(
        'arrow-down' => 'arrow-down.svg',
    );

    /**
     * Render markup for a timeline item icon.
     *
     * @param string $icon Icon attribute value.
     * @return string
     */
    public static function render_timeline_icon($icon)
    {
        if ('dot' === $icon) {
            return '<span class="we-timeline__item-icon-dot"></span>';
        }

        if (isset(self::CUSTOM_ICONS[ $icon ])) {
            return self::render_svg_icon(self::CUSTOM_ICONS[ $icon ]);
        }

        return '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>';
    }

    /**
     * Load and wrap a plugin SVG asset.
     *
     * @param string $filename File name inside assets/icons/.
     * @return string
     */
    private static function render_svg_icon($filename)
    {
        $path = WE_TIMELINE_PLUGIN_DIR . 'assets/icons/' . $filename;
        if (! file_exists($path)) {
            return '';
        }

        $svg = file_get_contents($path);
        if (false === $svg || '' === trim($svg)) {
            return '';
        }

        $svg = preg_replace('/<\?xml.*?\?>/', '', $svg);

        return '<span class="we-timeline__item-icon-svg" aria-hidden="true">' . $svg . '</span>';
    }
}
