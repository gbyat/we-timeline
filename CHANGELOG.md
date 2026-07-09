# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2026-07-09

- Implement duplicate meta cleanup for timeline links; add normalization method for page IDs. Enhance timeline block with alternating layout option and update styles accordingly. Update translations for new layout feature.


## [1.3.6] - 2026-06-10

- Refactor date string regex in Renderer class for improved readability and consistency.


## [1.3.5] - 2026-06-10

- Enhance GitHub update handling for multisite support; add methods to manage cached release data and update checks based on network admin context. Improve heading tag processing in Renderer class to assign IDs and classes for navigation targets.


## [1.3.4] - 2026-06-10

- Add 'menuSortOrder' attribute to timeline block; update asset versions and styles for improved mobile menu functionality.


## [1.3.3] - 2026-06-10

- Remove REST API functionality by deleting the Rest_Api class; update README to reflect this change. Enhance Renderer class with a new method to resolve post IDs from block attributes and improve timeline link synchronization on post save.


## [1.3.2] - 2026-06-10

- Update asset versions and improve mobile menu functionality in timeline block; modify translations for clarity and consistency. Change "Collapsed (select)" to "Collapsed (dropdown)" and "Hidden (desktop only)" to "Hide menu (desktop only)" for better user understanding.


## [1.3.1] - 2026-06-10

- Update package.json scripts for translation generation; modify release workflow to include PHP setup and streamline translation file generation. Remove unused editorScript reference in block.json files and update asset versions for timeline and timeline-item components.


## [1.3.0] - 2026-06-10

- Enhance timeline block with new mobile menu attributes; add options for mobile mode, granularity, label format, and breakpoint. Update asset versions for timeline and timeline-item components.


## [1.2.3] - 2026-06-09

- Update timeline styles and asset versions; enhance padding, background, and shadow effects for improved visual presentation. Adjust asset versioning for both timeline and timeline-item components.


## [1.2.2] - 2026-06-09

- Update asset versions and enhance date handling in timeline renderer; ensure proper parsing of date strings and improve sorting logic for timeline items.


## [1.2.1] - 2026-06-09

- Version update


## [1.2.0] - 2026-06-09

- Enhance timeline block with new attributes for content source and item display options; update block metadata and styles for improved customization. Register additional block types for item and title. Update asset versions and ensure compatibility with WordPress 7.0.
- Update .gitignore to include TASK-LOG.md and .cursor/ directories


## [1.1.0] - 2026-01-30

- Enhance timeline functionality and styling; add menu color options, rebuild exclusion cache on activation, and update asset versions. Remove unused single post styles.


## [1.0.2] - 2026-01-30

- Refactor timeline rendering and styles; remove unnecessary 'Read more' condition and improve button alignment. Update asset versions for build files.


## [1.0.1] - 2026-01-27

- Update POT-Creation-Date in we-timeline.pot to reflect the latest timestamp
- init


## [1.0.0] - 2026-01-27

### Added
- Initial release of WE Timeline
- Vertical timeline layouts (left/right alignment)
- Horizontal scrollable timeline layout
- Flexible content source selection (any post type, taxonomy, term)
- Navigation menu with configurable granularity (auto, decades, years, months, items)
- Progressive timeline line coloring on scroll
- Custom color settings (line, active line, background, icon, date)
- Timeline post navigation filtering
- Optional "Timeline" custom post type with custom date field
- Icon selection with size options
- Responsive design with mobile support
- "Read more" button for longer posts
- Item border radius setting

[1.0.0]: https://github.com/gbyat/we-timeline/releases/tag/v1.0.0
[1.0.1]: https://github.com/gbyat/we-timeline/releases/tag/v1.0.1
[1.0.2]: https://github.com/gbyat/we-timeline/releases/tag/v1.0.2
[1.1.0]: https://github.com/gbyat/we-timeline/releases/tag/v1.1.0
[1.2.0]: https://github.com/gbyat/we-timeline/releases/tag/v1.2.0
[1.2.1]: https://github.com/gbyat/we-timeline/releases/tag/v1.2.1
[1.2.2]: https://github.com/gbyat/we-timeline/releases/tag/v1.2.2
[1.2.3]: https://github.com/gbyat/we-timeline/releases/tag/v1.2.3
[1.3.0]: https://github.com/gbyat/we-timeline/releases/tag/v1.3.0
[1.3.1]: https://github.com/gbyat/we-timeline/releases/tag/v1.3.1
[1.3.2]: https://github.com/gbyat/we-timeline/releases/tag/v1.3.2
[1.3.3]: https://github.com/gbyat/we-timeline/releases/tag/v1.3.3
[1.3.4]: https://github.com/gbyat/we-timeline/releases/tag/v1.3.4
[1.3.5]: https://github.com/gbyat/we-timeline/releases/tag/v1.3.5
[1.3.6]: https://github.com/gbyat/we-timeline/releases/tag/v1.3.6
[1.4.0]: https://github.com/gbyat/we-timeline/releases/tag/v1.4.0
