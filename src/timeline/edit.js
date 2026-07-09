/**
 * Timeline Block Edit Component
 *
 * @package Webentwicklerin\Timeline
 */

import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
	PanelColorSettings,
	InnerBlocks,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	TextControl,
	Notice,
	BaseControl,
	ColorPalette,
	__experimentalUnitControl as UnitControl,
	RangeControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';

const TIMELINE_ITEM_TEMPLATE = [
	['we-timeline/timeline-item', { date: '', title: '' }],
	['we-timeline/timeline-item', { date: '', title: '' }],
];

const ITEM_UNIT_CONTROL_PROPS = {
	units: [
		{ value: 'px', label: 'px', default: 0 },
		{ value: 'rem', label: 'rem', default: 0 },
		{ value: 'em', label: 'em', default: 0 },
		{ value: '%', label: '%', default: 0 },
	],
	isResetValueOnUnitChange: true,
};

/**
 * Build inline CSS custom properties for timeline items container (editor preview).
 *
 * @param {Object} attrs Block attributes.
 * @return {Object} Style object.
 */
function buildTimelineItemsStyle(attrs) {
	const style = {};
	const lengthMap = {
		itemBorderRadius: '--we-timeline-item-border-radius',
		itemBorderWidth: '--we-timeline-item-border-width',
		itemPadding: '--we-timeline-item-padding',
		itemGap: '--we-timeline-items-gap',
	};
	Object.entries(lengthMap).forEach(([attr, prop]) => {
		if (attrs[attr]) {
			style[prop] = attrs[attr];
		}
	});
	if (attrs.itemBorderColor) {
		style['--we-timeline-item-border-color'] = attrs.itemBorderColor;
	}
	if (attrs.itemBorderStyle) {
		style['--we-timeline-item-border-style'] = attrs.itemBorderStyle;
	}
	return style;
}

export default function Edit({ attributes, setAttributes }) {
	const {
		contentSource,
		layout,
		position,
		visibleItems,
		icon,
		iconSize,
		postType,
		taxonomy,
		term,
		dateField,
		sortOrder,
		excerptWordCount,
		showFullContent,
		excludeFromMainLoop,
		excludeFromCategoryLists,
		showItemDates,
		showMenu,
		menuGranularity,
		menuSortOrder,
		menuPosition,
		menuAlign,
		menuStyle,
		menuSeparators,
		menuMobileMode,
		menuGranularityMobile,
		menuMobileLabelFormat,
		menuMobileBreakpoint,
		stickyHeaderSelector,
		itemBorderRadius,
		itemBorderWidth,
		itemBorderColor,
		itemBorderStyle,
		itemPadding,
		itemGap,
		timelineLineColor,
		timelineLineActiveColor,
		itemBackgroundColor,
		iconColor,
		dateColor,
		menuTextColor,
		menuTextColorHover,
		menuBackgroundColor,
		menuHoverColor,
		menuActiveColor,
		menuActiveBackgroundColor,
	} = attributes;

	const isCompactMenu = (menuStyle || 'default') === 'compact';
	const themeColors = useSetting('color.palette') || [];
	const itemBorderStyleValue = itemBorderStyle || 'solid';
	const showItemBorderWidth = itemBorderStyleValue !== 'none';

	const isItemsMode = contentSource === 'items';
	const isStandardPostType = (postType || 'post') === 'post';
	const isTimeGroupedMenu = showMenu && (menuGranularity || 'auto') !== 'items';

	const sortOrderOptions = isItemsMode
		? [
				{ label: __('Timeline order', 'we-timeline'), value: 'manual' },
				{ label: __('By date (oldest first)', 'we-timeline'), value: 'asc' },
				{ label: __('By date (newest first)', 'we-timeline'), value: 'desc' },
			]
		: [
				{ label: __('Oldest first', 'we-timeline'), value: 'asc' },
				{ label: __('Newest first', 'we-timeline'), value: 'desc' },
				...(!isStandardPostType
					? [{ label: __('Menu order', 'we-timeline'), value: 'manual' }]
					: []),
			];

	const sortOrderHelp = isItemsMode
		? __('Timeline order keeps items in the same sequence as in the editor. Date options sort all items by their date field.', 'we-timeline')
		: isStandardPostType
			? __('Sort posts by publish date.', 'we-timeline')
			: __('Sort by publish date, or use the Order field from the post editor (Page attributes).', 'we-timeline');

	// Ensure postType is always set in block attributes (posts mode).
	useEffect(() => {
		if (!isItemsMode && !postType) {
			setAttributes({ postType: 'post' });
		}
	}, [postType, setAttributes, isItemsMode]);

	// Ensure position is set when layout is set.
	useEffect(() => {
		if (layout && !position) {
			const defaultPosition = layout === 'vertical' ? 'left' : (layout === 'horizontal-scroll' ? 'top' : 'left');
			setAttributes({ position: defaultPosition });
		}
	}, [layout, position, setAttributes]);

	const isAlternatingLayout = layout !== 'horizontal-scroll' && position === 'alternating';
	const layoutControlValue = isAlternatingLayout
		? 'vertical-alternating'
		: (layout || 'vertical');

	const layoutClass = layout === 'horizontal-scroll'
		? `we-timeline--horizontal-scroll-${position || 'top'}`
		: isAlternatingLayout
			? 'we-timeline--vertical-alternating'
			: `we-timeline--vertical-${position || 'left'}`;

	const blockProps = useBlockProps({
		className: `we-timeline wp-block-we-timeline-timeline ${isItemsMode ? layoutClass : ''}`,
	});

	const timelineItemsStyle = buildTimelineItemsStyle(attributes);

	// Get post types.
	const postTypes = useSelect((select) => {
		const types = select('core').getPostTypes({ per_page: -1 });
		return types
			? types
				.filter((type) => type.viewable && type.slug !== 'attachment')
				.map((type) => ({
					label: type.name,
					value: type.slug,
				}))
			: [];
	}, []);

	// Get taxonomies for selected post type.
	const taxonomies = useSelect(
		(select) => {
			if (!postType) {
				return [];
			}
			const tax = select('core').getTaxonomies({ per_page: -1 });
			return tax
				? tax
					.filter((t) => t.types.includes(postType))
					.map((t) => ({
						label: t.name,
						value: t.slug,
					}))
				: [];
		},
		[postType]
	);

	// Get terms for selected taxonomy.
	const terms = useSelect(
		(select) => {
			if (!taxonomy) {
				return [];
			}
			const termList = select('core').getEntityRecords('taxonomy', taxonomy, {
				per_page: -1,
			});
			return termList
				? termList.map((t) => ({
					label: t.name,
					value: t.id,
				}))
				: [];
		},
		[taxonomy]
	);

	const timelineColorSettings = [
		{
			label: __('Timeline Line Color', 'we-timeline'),
			value: timelineLineColor || '',
			onChange: (value) => setAttributes({ timelineLineColor: value || '' }),
		},
		{
			label: __('Timeline Line Active Color', 'we-timeline'),
			value: timelineLineActiveColor || '',
			onChange: (value) => setAttributes({ timelineLineActiveColor: value || '' }),
		},
		{
			label: __('Item Background Color', 'we-timeline'),
			value: itemBackgroundColor || '',
			onChange: (value) => setAttributes({ itemBackgroundColor: value || '' }),
		},
		{
			label: __('Icon Color', 'we-timeline'),
			value: iconColor || '',
			onChange: (value) => setAttributes({ iconColor: value || '' }),
		},
		...(showItemDates !== false
			? [
					{
						label: __('Date Color', 'we-timeline'),
						value: dateColor || '',
						onChange: (value) => setAttributes({ dateColor: value || '' }),
					},
			  ]
			: []),
	];

	const menuColorSettings = isCompactMenu
		? [
				{
					label: __('Link Color', 'we-timeline'),
					value: menuTextColor || '',
					onChange: (value) => setAttributes({ menuTextColor: value || '' }),
				},
				{
					label: __('Link Color (Hover)', 'we-timeline'),
					value: menuTextColorHover || '',
					onChange: (value) => setAttributes({ menuTextColorHover: value || '' }),
				},
				{
					label: __('Link Color (Active)', 'we-timeline'),
					value: menuActiveColor || '',
					onChange: (value) => setAttributes({ menuActiveColor: value || '' }),
				},
		  ]
		: [
				{
					label: __('Menu Text Color', 'we-timeline'),
					value: menuTextColor || '',
					onChange: (value) => setAttributes({ menuTextColor: value || '' }),
				},
				{
					label: __('Menu Text Color (Hover)', 'we-timeline'),
					value: menuTextColorHover || '',
					onChange: (value) => setAttributes({ menuTextColorHover: value || '' }),
				},
				{
					label: __('Menu Background Color', 'we-timeline'),
					value: menuBackgroundColor || '',
					onChange: (value) => setAttributes({ menuBackgroundColor: value || '' }),
				},
				{
					label: __('Menu Background Color (Hover)', 'we-timeline'),
					value: menuHoverColor || '',
					onChange: (value) => setAttributes({ menuHoverColor: value || '' }),
				},
				{
					label: __('Menu Text Color (Active)', 'we-timeline'),
					value: menuActiveColor || '',
					onChange: (value) => setAttributes({ menuActiveColor: value || '' }),
				},
				{
					label: __('Menu Background Color (Active)', 'we-timeline'),
					value: menuActiveBackgroundColor || '',
					onChange: (value) => setAttributes({ menuActiveBackgroundColor: value || '' }),
				},
		  ];

	return (
		<>
			<BlockControls group="other" />
			<InspectorControls group="styles">
				{PanelColorSettings && (
					<PanelColorSettings
						title={__('Timeline Colors', 'we-timeline')}
						colorSettings={timelineColorSettings}
					/>
				)}
				{showMenu && PanelColorSettings && (
					<PanelColorSettings
						title={__('Menu Colors', 'we-timeline')}
						colorSettings={menuColorSettings}
					/>
				)}
				<PanelBody title={__('Timeline Styling', 'we-timeline')} initialOpen={false}>
					<UnitControl
						label={__('Item border radius', 'we-timeline')}
						value={itemBorderRadius || ''}
						onChange={(value) => setAttributes({ itemBorderRadius: value || '' })}
						{...ITEM_UNIT_CONTROL_PROPS}
					/>
					<SelectControl
						label={__('Item border style', 'we-timeline')}
						value={itemBorderStyleValue}
						options={[
							{ label: __('Solid', 'we-timeline'), value: 'solid' },
							{ label: __('Dashed', 'we-timeline'), value: 'dashed' },
							{ label: __('Dotted', 'we-timeline'), value: 'dotted' },
							{ label: __('None', 'we-timeline'), value: 'none' },
						]}
						onChange={(value) => setAttributes({ itemBorderStyle: value === 'solid' ? '' : value })}
						help={__('Default is solid.', 'we-timeline')}
					/>
					{showItemBorderWidth && (
						<UnitControl
							label={__('Item border width', 'we-timeline')}
							value={itemBorderWidth || ''}
							onChange={(value) => setAttributes({ itemBorderWidth: value || '' })}
							{...ITEM_UNIT_CONTROL_PROPS}
						/>
					)}
					{showItemBorderWidth && (
						<BaseControl
							id="we-timeline-item-border-color"
							label={__('Item border color', 'we-timeline')}
						>
							<ColorPalette
								colors={themeColors}
								value={itemBorderColor || ''}
								onChange={(value) => setAttributes({ itemBorderColor: value || '' })}
								clearable
							/>
						</BaseControl>
					)}
					<UnitControl
						label={__('Item padding', 'we-timeline')}
						value={itemPadding || ''}
						onChange={(value) => setAttributes({ itemPadding: value || '' })}
						{...ITEM_UNIT_CONTROL_PROPS}
					/>
					<UnitControl
						label={__('Item spacing', 'we-timeline')}
						value={itemGap || ''}
						onChange={(value) => setAttributes({ itemGap: value || '' })}
						{...ITEM_UNIT_CONTROL_PROPS}
						help={__('Vertical gap between timeline items.', 'we-timeline')}
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls>
				<PanelBody title={__('Content Settings', 'we-timeline')} initialOpen={true}>
					<SelectControl
						label={__('Content source', 'we-timeline')}
						value={contentSource || 'posts'}
						options={[
							{ label: __('Posts', 'we-timeline'), value: 'posts' },
							{ label: __('Custom items', 'we-timeline'), value: 'items' },
						]}
						onChange={(value) => setAttributes({ contentSource: value })}
						help={__('Switching source does not migrate content between modes.', 'we-timeline')}
					/>

					<SelectControl
						label={__('Layout', 'we-timeline')}
						value={layoutControlValue}
						options={[
							{ label: __('Vertical', 'we-timeline'), value: 'vertical' },
							{ label: __('Alternating', 'we-timeline'), value: 'vertical-alternating' },
							{ label: __('Horizontal Scroll', 'we-timeline'), value: 'horizontal-scroll' },
						]}
						onChange={(value) => {
							if (value === 'vertical-alternating') {
								setAttributes({ layout: 'vertical', position: 'alternating' });
								return;
							}
							if (value === 'vertical') {
								setAttributes({
									layout: 'vertical',
									position: position === 'alternating' ? 'left' : (position || 'left'),
								});
								return;
							}
							setAttributes({ layout: value, position: 'top' });
						}}
						help={
							isAlternatingLayout
								? __('Cards alternate left and right of a center line with icons. On small screens, items stack on the right of the line.', 'we-timeline')
								: undefined
						}
					/>

					{layout !== 'horizontal-scroll' && !isAlternatingLayout && (
						<SelectControl
							label={__('Position', 'we-timeline')}
							value={position || 'left'}
							options={[
								{ label: __('Left', 'we-timeline'), value: 'left' },
								{ label: __('Right', 'we-timeline'), value: 'right' },
							]}
							onChange={(value) => setAttributes({ position: value })}
						/>
					)}

					{layout === 'horizontal-scroll' && (
						<>
							<SelectControl
								label={__('Position', 'we-timeline')}
								value={position || 'top'}
								options={[
									{ label: __('Top', 'we-timeline'), value: 'top' },
									{ label: __('Bottom', 'we-timeline'), value: 'bottom' },
								]}
								onChange={(value) => setAttributes({ position: value })}
							/>
							<SelectControl
								label={__('Visible Items', 'we-timeline')}
								value={visibleItems || 3}
								options={[
									{ label: '1', value: 1 },
									{ label: '2', value: 2 },
									{ label: '3', value: 3 },
									{ label: '4', value: 4 },
									{ label: '5', value: 5 },
									{ label: '6', value: 6 },
								]}
								onChange={(value) => setAttributes({ visibleItems: parseInt(value) })}
							/>
						</>
					)}

					<SelectControl
						label={__('Icon', 'we-timeline')}
						value={icon || 'calendar-alt'}
						options={[
							{ label: __('Calendar', 'we-timeline'), value: 'calendar-alt' },
							{ label: __('Clock', 'we-timeline'), value: 'clock' },
							{ label: __('Star', 'we-timeline'), value: 'star-filled' },
							{ label: __('Flag', 'we-timeline'), value: 'flag' },
							{ label: __('Marker', 'we-timeline'), value: 'location' },
							{ label: __('Circle', 'we-timeline'), value: 'marker' },
							{ label: __('Arrow down', 'we-timeline'), value: 'arrow-down' },
							{ label: __('Dot', 'we-timeline'), value: 'dot' },
							{ label: __('None', 'we-timeline'), value: '' },
						]}
						onChange={(value) => setAttributes({ icon: value })}
					/>
					{icon && (
						<SelectControl
							label={__('Icon Size', 'we-timeline')}
							value={iconSize || 'medium'}
							options={[
								{ label: __('Small', 'we-timeline'), value: 'small' },
								{ label: __('Medium', 'we-timeline'), value: 'medium' },
								{ label: __('Large', 'we-timeline'), value: 'large' },
							]}
							onChange={(value) => setAttributes({ iconSize: value })}
						/>
					)}

					{!isItemsMode && (
						<>
							<SelectControl
								label={__('Post Type', 'we-timeline')}
								value={postType || 'post'}
								options={postTypes.length > 0 ? postTypes : [{ label: __('Loading...', 'we-timeline'), value: 'post' }]}
								onChange={(value) => {
									setAttributes({ postType: value, taxonomy: '', term: 0 });
								}}
							/>

							{(postType || 'post') && (
								<>
									<SelectControl
										label={__('Taxonomy', 'we-timeline')}
										value={taxonomy}
										options={[
											{ label: __('All Posts', 'we-timeline'), value: '' },
											...taxonomies,
										]}
										onChange={(value) => {
											setAttributes({ taxonomy: value, term: 0 });
										}}
									/>
									{taxonomy && (
										<SelectControl
											label={__('Term', 'we-timeline')}
											value={term}
											options={[
												{ label: __('All Terms', 'we-timeline'), value: 0 },
												...terms,
											]}
											onChange={(value) => setAttributes({ term: parseInt(value) })}
										/>
									)}
								</>
							)}

							<SelectControl
								label={__('Date Field', 'we-timeline')}
								value={dateField}
								options={[
									{ label: __('Post Date', 'we-timeline'), value: 'date' },
									{ label: __('Timeline Date (Custom Field)', 'we-timeline'), value: 'timeline_date' },
								]}
								onChange={(value) => setAttributes({ dateField: value })}
							/>
							<ToggleControl
								label={__('Show full post content', 'we-timeline')}
								checked={!!showFullContent}
								onChange={(value) => setAttributes({ showFullContent: value })}
								help={__(
									'When off, only the excerpt is shown (manual excerpt field, or trimmed text).',
									'we-timeline'
								)}
							/>
							{!showFullContent && (
								<RangeControl
									label={__('Excerpt word count', 'we-timeline')}
									value={excerptWordCount || 55}
									onChange={(value) => setAttributes({ excerptWordCount: value })}
									min={10}
									max={200}
									help={__('Used when the post has no manual excerpt.', 'we-timeline')}
								/>
							)}
						</>
					)}

					<SelectControl
						label={__('Sort order', 'we-timeline')}
						value={sortOrder || 'asc'}
						options={sortOrderOptions}
						onChange={(value) => setAttributes({ sortOrder: value })}
						help={sortOrderHelp}
					/>
				</PanelBody>

				{!isItemsMode && (
					<PanelBody title={__('Exclusion Settings', 'we-timeline')}>
						{taxonomy && term > 0 ? (
							<>
								<ToggleControl
									label={__('Exclude from Main Loop', 'we-timeline')}
									checked={excludeFromMainLoop}
									onChange={(value) => setAttributes({ excludeFromMainLoop: value })}
								/>
								<ToggleControl
									label={__('Exclude from Category Lists', 'we-timeline')}
									checked={excludeFromCategoryLists}
									onChange={(value) => setAttributes({ excludeFromCategoryLists: value })}
								/>
							</>
						) : (
							<p>{__('Select a taxonomy and term to configure exclusion.', 'we-timeline')}</p>
						)}
					</PanelBody>
				)}

				<PanelBody title={__('Menu Settings', 'we-timeline')}>
					<ToggleControl
						label={__('Show dates on items', 'we-timeline')}
						checked={showItemDates !== false}
						onChange={(value) => setAttributes({ showItemDates: value })}
						help={__(
							'When off, dates stay in item settings for sorting and menu navigation but are not shown on the frontend.',
							'we-timeline'
						)}
					/>
					<ToggleControl
						label={__('Show Menu', 'we-timeline')}
						checked={showMenu}
						onChange={(value) => setAttributes({ showMenu: value })}
					/>
					{showMenu && (
						<>
							<SelectControl
								label={__('Menu position', 'we-timeline')}
								value={menuPosition || 'sidebar'}
								options={[
									{ label: __('Sidebar (fixed right)', 'we-timeline'), value: 'sidebar' },
									{ label: __('Top (sticky above timeline)', 'we-timeline'), value: 'top' },
								]}
								onChange={(value) => setAttributes({ menuPosition: value })}
							/>
							{(menuPosition || 'sidebar') === 'top' && (
								<SelectControl
									label={__('Menu alignment', 'we-timeline')}
									value={menuAlign || 'left'}
									options={[
										{ label: __('Left', 'we-timeline'), value: 'left' },
										{ label: __('Center', 'we-timeline'), value: 'center' },
										{ label: __('Right', 'we-timeline'), value: 'right' },
									]}
									onChange={(value) => setAttributes({ menuAlign: value })}
								/>
							)}
							<SelectControl
								label={__('Menu style', 'we-timeline')}
								value={menuStyle || 'default'}
								options={[
									{ label: __('Default', 'we-timeline'), value: 'default' },
									{ label: __('Compact (text links)', 'we-timeline'), value: 'compact' },
								]}
								onChange={(value) => setAttributes({ menuStyle: value })}
							/>
							<SelectControl
								label={__('Separators', 'we-timeline')}
								value={menuSeparators || 'none'}
								disabled={!isCompactMenu}
								options={[
									{ label: __('None', 'we-timeline'), value: 'none' },
									{ label: __('Pipe (|)', 'we-timeline'), value: 'pipe' },
									{ label: __('Middot (·)', 'we-timeline'), value: 'middot' },
									{ label: __('Hyphen (-)', 'we-timeline'), value: 'hyphen' },
								]}
								onChange={(value) => setAttributes({ menuSeparators: value })}
								help={
									isCompactMenu
										? __('Shown between compact menu links only.', 'we-timeline')
										: __('Only applies when menu style is compact.', 'we-timeline')
								}
							/>
							<SelectControl
								label={__('Menu Granularity', 'we-timeline')}
								value={menuGranularity || 'auto'}
								options={[
									{ label: __('Auto', 'we-timeline'), value: 'auto' },
									{ label: __('Decades', 'we-timeline'), value: 'decades' },
									{ label: __('Years', 'we-timeline'), value: 'years' },
									{ label: __('Months', 'we-timeline'), value: 'months' },
									{ label: __('Items', 'we-timeline'), value: 'items' },
								]}
								onChange={(value) => setAttributes({ menuGranularity: value })}
							/>
							{isTimeGroupedMenu && (
								<SelectControl
									label={__('Menu chronological order', 'we-timeline')}
									value={menuSortOrder || 'inherit'}
									options={[
										{ label: __('Match timeline', 'we-timeline'), value: 'inherit' },
										{ label: __('Oldest first', 'we-timeline'), value: 'asc' },
										{ label: __('Newest first', 'we-timeline'), value: 'desc' },
									]}
									onChange={(value) => setAttributes({ menuSortOrder: value })}
									help={__(
										'Applies to decade, year, and month menu groupings only. Item-level menus follow the timeline display order.',
										'we-timeline'
									)}
								/>
							)}
							<SelectControl
								label={__('Mobile menu', 'we-timeline')}
								value={menuMobileMode || 'inherit'}
								options={[
									{ label: __('Same as desktop', 'we-timeline'), value: 'inherit' },
									{ label: __('Coarser granularity', 'we-timeline'), value: 'granularity' },
									{ label: __('Collapsed (dropdown)', 'we-timeline'), value: 'collapsed' },
									{ label: __('Short labels', 'we-timeline'), value: 'short-labels' },
									{ label: __('Scrollable sticky bar', 'we-timeline'), value: 'scroll' },
									{ label: __('Hide menu (desktop only)', 'we-timeline'), value: 'hidden' },
								]}
								onChange={(value) => setAttributes({ menuMobileMode: value })}
								help={
									(menuMobileMode || 'inherit') === 'collapsed'
										? __(
												'Replaces the link list with a dropdown below the breakpoint. Preview on the frontend only.',
												'we-timeline'
										  )
										: (menuMobileMode || 'inherit') === 'hidden'
										  ? __(
													'Hides the menu on viewports below the breakpoint. Preview on the frontend only.',
													'we-timeline'
										    )
										  : __(
													'Optional override below the mobile breakpoint. Preview on the frontend only.',
													'we-timeline'
										    )
								}
							/>
							{(menuMobileMode || 'inherit') === 'collapsed' && (
								<Notice status="info" isDismissible={false}>
									{__(
										'The dropdown appears on the frontend when the viewport is narrower than the mobile breakpoint.',
										'we-timeline'
									)}
								</Notice>
							)}
							<RangeControl
								label={__('Mobile breakpoint (px)', 'we-timeline')}
								value={menuMobileBreakpoint || 768}
								onChange={(value) => setAttributes({ menuMobileBreakpoint: value })}
								min={480}
								max={1200}
								step={1}
								help={__(
									'Viewport width at which the mobile menu override applies.',
									'we-timeline'
								)}
							/>
							{(menuMobileMode || 'inherit') === 'granularity' && (
								<SelectControl
									label={__('Mobile menu granularity', 'we-timeline')}
									value={menuGranularityMobile || 'decades'}
									options={[
										{ label: __('Auto', 'we-timeline'), value: 'auto' },
										{ label: __('Decades', 'we-timeline'), value: 'decades' },
										{ label: __('Years', 'we-timeline'), value: 'years' },
										{ label: __('Months', 'we-timeline'), value: 'months' },
										{ label: __('Items', 'we-timeline'), value: 'items' },
									]}
									onChange={(value) => setAttributes({ menuGranularityMobile: value })}
								/>
							)}
							{(menuMobileMode || 'inherit') === 'short-labels' && (
								<SelectControl
									label={__('Mobile label format', 'we-timeline')}
									value={menuMobileLabelFormat || 'year'}
									options={[
										{ label: __('Year only', 'we-timeline'), value: 'year' },
										{ label: __('Year and title (truncated)', 'we-timeline'), value: 'year-title' },
										{ label: __('Title (truncated)', 'we-timeline'), value: 'title-truncate' },
									]}
									onChange={(value) => setAttributes({ menuMobileLabelFormat: value })}
								/>
							)}
							<TextControl
								label={__('Sticky header selector', 'we-timeline')}
								help={__(
									'CSS selector for your theme’s fixed or sticky header (e.g. header.site-header, #masthead). Height is measured live while scrolling.',
									'we-timeline'
								)}
								value={stickyHeaderSelector || ''}
								onChange={(value) => setAttributes({ stickyHeaderSelector: value })}
							/>
						</>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{isItemsMode ? (
					<>
						{showMenu && (
							<Notice status="info" isDismissible={false}>
								{__('Timeline menu preview is available on the frontend.', 'we-timeline')}
							</Notice>
						)}
						<div className="we-timeline__items" style={timelineItemsStyle}>
							<InnerBlocks
								allowedBlocks={['we-timeline/timeline-item']}
								template={TIMELINE_ITEM_TEMPLATE}
								templateLock={false}
								renderAppender={InnerBlocks.ButtonBlockAppender}
							/>
						</div>
					</>
				) : (
					<ServerSideRender
						block="we-timeline/timeline"
						attributes={{
							...attributes,
							contentSource: 'posts',
							postType: postType || 'post',
							layout: layout || 'vertical',
							position: position || (layout === 'horizontal-scroll' ? 'top' : 'left'),
							visibleItems: visibleItems || 3,
						}}
					/>
				)}
			</div>
		</>
	);
}
