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
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	__experimentalInputControl as InputControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
	const {
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
		excludeFromMainLoop,
		excludeFromCategoryLists,
		showMenu,
		menuGranularity,
		timelineLineColor,
		timelineLineActiveColor,
		itemBackgroundColor,
		itemBorderRadius,
		iconColor,
		dateColor,
	} = attributes;

	// Ensure postType is always set in block attributes.
	useEffect(() => {
		if (!postType) {
			setAttributes({ postType: 'post' });
		}
	}, [postType, setAttributes]);

	// Ensure position is set when layout is set.
	useEffect(() => {
		if (layout && !position) {
			const defaultPosition = layout === 'vertical' ? 'left' : (layout === 'horizontal-scroll' ? 'top' : 'left');
			setAttributes({ position: defaultPosition });
		}
	}, [layout, position, setAttributes]);

	const blockProps = useBlockProps({
		className: 'we-timeline',
		style: {
			// Colors are handled by WordPress block supports via useBlockProps
		},
	});

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

	// Get block editor settings for color palette
	const settings = useSelect((select) => {
		return select('core/block-editor').getSettings();
	}, []);

	// Prepare color settings for PanelColorSettings
	const colorSettings = [
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
		{
			label: __('Date Color', 'we-timeline'),
			value: dateColor || '',
			onChange: (value) => setAttributes({ dateColor: value || '' }),
		},
	];

	return (
		<>
			<BlockControls group="other" />
			<InspectorControls group="styles">
				{PanelColorSettings && (
					<PanelColorSettings
						title={__('Timeline Colors', 'we-timeline')}
						colorSettings={colorSettings}
					/>
				)}
				<PanelBody title={__('Timeline Styling', 'we-timeline')} initialOpen={false}>
					<InputControl
						label={__('Item Border Radius', 'we-timeline')}
						type="text"
						value={itemBorderRadius || ''}
						onChange={(value) => setAttributes({ itemBorderRadius: value || '' })}
						placeholder="e.g., 8px, 0.5rem, 0"
						help={__('Enter a CSS value (e.g., 8px, 0.5rem, 0)', 'we-timeline')}
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls>
				<PanelBody title={__('Content Settings', 'we-timeline')} initialOpen={true}>
					<SelectControl
						label={__('Layout', 'we-timeline')}
						value={layout || 'vertical'}
						options={[
							{ label: __('Vertical', 'we-timeline'), value: 'vertical' },
							{ label: __('Horizontal Scroll', 'we-timeline'), value: 'horizontal-scroll' },
						]}
						onChange={(value) => {
							// Reset position to default when layout changes
							const defaultPosition = value === 'vertical' ? 'left' : 'top';
							setAttributes({ layout: value, position: defaultPosition });
						}}
					/>

					{layout !== 'horizontal-scroll' && (
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

					<SelectControl
						label={__('Sort Order', 'we-timeline')}
						value={sortOrder}
						options={[
							{ label: __('Ascending', 'we-timeline'), value: 'asc' },
							{ label: __('Descending', 'we-timeline'), value: 'desc' },
						]}
						onChange={(value) => setAttributes({ sortOrder: value })}
					/>
				</PanelBody>

				<PanelBody title={__('Exclusion Settings', 'we-timeline')}>
					{taxonomy && term > 0 && (
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
					)}
				</PanelBody>

				<PanelBody title={__('Menu Settings', 'we-timeline')}>
					<ToggleControl
						label={__('Show Menu', 'we-timeline')}
						checked={showMenu}
						onChange={(value) => setAttributes({ showMenu: value })}
					/>
					{showMenu && (
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
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<ServerSideRender
					block="we-timeline/timeline"
					attributes={{
						...attributes,
						postType: postType || 'post',
						layout: layout || 'vertical',
						position: position || (layout === 'horizontal-scroll' ? 'top' : 'left'),
						visibleItems: visibleItems || 3,
					}}
				/>
			</div>
		</>
	);
}
