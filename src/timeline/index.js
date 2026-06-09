/**
 * Timeline Block
 *
 * @package Webentwicklerin\Timeline
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import metadata from './block.json';
import { WeTimelineIcon } from '../shared/block-icon';
import './styles.scss';

const TIMELINE_ITEM_TEMPLATE = [
	['we-timeline/timeline-item', { date: '', title: '' }],
	['we-timeline/timeline-item', { date: '', title: '' }],
];

registerBlockType(metadata.name, {
	...metadata,
	icon: WeTimelineIcon,
	edit: Edit,
	save: ({ attributes }) => {
		if (attributes.contentSource === 'items') {
			const blockProps = useBlockProps.save({
				className: 'we-timeline__inner-blocks',
			});
			return (
				<div {...blockProps}>
					<InnerBlocks.Content />
				</div>
			);
		}
		return null;
	},
	variations: [
		{
			name: 'custom-items',
			title: __('WE Timeline (Custom Items)', 'we-timeline'),
			description: __('Build a WE Timeline from manual items instead of posts.', 'we-timeline'),
			icon: WeTimelineIcon,
			attributes: {
				contentSource: 'items',
			},
			innerBlocks: TIMELINE_ITEM_TEMPLATE,
			scope: ['inserter'],
			isActive: (blockAttributes) => blockAttributes.contentSource === 'items',
		},
	],
});
