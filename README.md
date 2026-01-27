# WE Timeline

**Contributors:** webentwicklerin  
**Tags:** timeline, blocks, gutenberg, history, chronology  
**Requires at least:** 6.0  
**Tested up to:** 6.9  
**Requires PHP:** 7.4  
**Stable tag:** 1.0.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin with Gutenberg blocks for creating timelines with various layouts, flexible content sources, and dynamic navigation.

## Description

WE Timeline provides a powerful Gutenberg block for creating beautiful, responsive timelines from any post type. Perfect for displaying company history, project milestones, event chronologies, and more.

### Key Features

* **Multiple Layouts**: Vertical (left/right alignment) and horizontal scrollable timelines
* **Flexible Content Sources**: Use any post type, filter by taxonomy and term
* **Custom Post Type**: Optional "Timeline" post type with custom date field
* **Dynamic Navigation**: Sticky menu with configurable granularity (decades, years, months, items)
* **Progressive Timeline Line**: Timeline line colors in as you scroll
* **Custom Colors**: Configure timeline colors, icon colors, date colors, and item backgrounds
* **Timeline Post Navigation**: Navigate between posts within the same timeline
* **Responsive Design**: Mobile-friendly layouts with proper touch support
* **Native Block Alignment**: Support for wide and full-width alignments

## Installation

### Manual Installation

1. Download the plugin zip file
2. Go to **Plugins > Add New** in your WordPress admin
3. Click **Upload Plugin**
4. Choose the zip file and click **Install Now**
5. Activate the plugin

### Via Git

```bash
cd wp-content/plugins
git clone https://github.com/gbyat/we-timeline.git
cd we-timeline
npm install
npm run build
```

Then activate the plugin through the WordPress admin.

## Usage

### Timeline Block

1. Add the "Timeline" block to your page
2. Configure content source:
   - **Post Type**: Select which post type to display
   - **Taxonomy**: Optionally filter by taxonomy
   - **Term**: Optionally filter by specific term
3. Select layout:
   - **Vertical**: Left or right aligned timeline
   - **Horizontal Scroll**: Scrollable horizontal timeline
4. Configure colors in the Styles panel:
   - Timeline Line Color
   - Timeline Line Active Color
   - Item Background Color
   - Icon Color
   - Date Color
5. Optionally enable navigation menu with granularity settings

### Block Settings

**Layout Options:**
- Vertical with left or right alignment
- Horizontal scroll with top or bottom line position
- Configurable number of visible items (horizontal)

**Content Settings:**
- Post type selection
- Taxonomy and term filtering
- Date field (post date or custom timeline_date)
- Sort order (ascending/descending)

**Menu Settings:**
- Show/hide navigation menu
- Granularity: Auto, Decades, Years, Months, or Items
- Fixed position on right side (responsive to sticky on mobile)

**Color Settings:**
- Timeline line color (inactive)
- Timeline line active color (scrolled portion)
- Item background color
- Icon color
- Date color
- Item border radius

### Custom Post Type

Enable the optional "Timeline" custom post type in **Settings > WE Timeline**:
- Standard post fields (title, editor, thumbnail)
- Custom `timeline_date` field for timeline-specific dates
- REST API support

### Timeline Post Navigation

Posts that appear in a timeline automatically get filtered post navigation:
- Previous/Next links only show posts from the same timeline
- Works with your theme's existing post navigation styling

## Requirements

* WordPress 6.0 or higher
* PHP 7.4 or higher
* Node.js and npm (for development)

## Frequently Asked Questions

### Does this work with custom post types?

Yes! The block works with any public post type registered in WordPress.

### Can I use multiple timelines on one site?

Yes! Each timeline block operates independently. Posts can even appear in multiple timelines.

### Will the navigation menu update automatically?

Yes! The menu automatically generates based on the dates of posts in your timeline.

### Can I customize the timeline appearance?

Yes! Use the color settings in the Styles panel, or add custom CSS for more advanced customization.

## Screenshots

1. Vertical timeline with left alignment
2. Horizontal scrollable timeline
3. Timeline with navigation menu
4. Block settings in the editor

## Changelog

### 1.0.0
* Initial release
* Vertical and horizontal timeline layouts
* Flexible content source selection
* Navigation menu with granularity options
* Progressive timeline line coloring
* Timeline post navigation filtering
* Custom color settings
* Responsive design

## Development

### Building

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Start development mode with watch
npm start

# Generate POT file for translations
npm run generate-pot

# Sync version across files
npm run sync-version

# Create a release (patch/minor/major)
npm run release patch
```

### File Structure

```
we-timeline/
├── build/               # Built block files
├── includes/            # PHP classes
│   ├── class-assets.php
│   ├── class-exclude.php
│   ├── class-post-type.php
│   ├── class-renderer.php
│   ├── class-rest-api.php
│   ├── class-settings.php
│   └── class-timeline-link.php
├── languages/           # Translation files
├── scripts/             # Build scripts
│   ├── generate-pot.js
│   ├── release.js
│   └── sync-version.js
├── src/                 # Source files
│   └── timeline/
└── we-timeline.php
```

## Support

For issues, feature requests, or contributions, please visit the [GitHub repository](https://github.com/gbyat/we-timeline).

## Credits

**Author:** webentwicklerin, Gabriele Laesser  
**Author URI:** https://webentwicklerin.at

## License

GPLv2 or later
