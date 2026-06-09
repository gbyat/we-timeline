/**
 * Timeline Item Block
 *
 * @package Webentwicklerin\Timeline
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import Edit from './edit';
import metadata from './block.json';
import { WeTimelineItemIcon } from '../shared/block-icon';
import '../timeline/styles.scss';

registerBlockType(metadata.name, {
	...metadata,
	icon: WeTimelineItemIcon,
	edit: Edit,
	save: () => {
		const blockProps = useBlockProps.save({
			className: 'we-timeline__item-inner',
		});
		return (
			<div {...blockProps}>
				<InnerBlocks.Content />
			</div>
		);
	},
});
