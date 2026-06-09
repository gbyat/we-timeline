/**
 * Timeline Item Block Edit Component
 *
 * @package Webentwicklerin\Timeline
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { createBlock } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	InnerBlocks,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';

const DATE_HELP = __(
	'Optional. Year (1977), month (1977-06), date (1977-06-15), or date and time (1977-06-15 14:30). Used for sorting and menu navigation. Frontend display is controlled in the parent Timeline block (Show dates on items).',
	'we-timeline'
);

const INNER_BLOCKS_TEMPLATE = [
	['core/heading', { level: 3, content: '' }],
];

const NAV_HEADING_BLOCKS = ['core/heading', 'we-timeline/timeline-item-title'];
const IMAGE_BLOCKS = ['core/image', 'core/cover'];

function hasBlockInTree(blocks, blockNames) {
	return blocks.some((block) => {
		if (blockNames.includes(block.name)) {
			return true;
		}
		if (block.innerBlocks?.length) {
			return hasBlockInTree(block.innerBlocks, blockNames);
		}
		return false;
	});
}

function mapBlocksToCoreHeading(blocks) {
	return blocks.map((block) => {
		if (block.name === 'we-timeline/timeline-item-title') {
			return createBlock('core/heading', {
				content: block.attributes?.title || '',
				level: 3,
			});
		}
		if (block.innerBlocks?.length) {
			return createBlock(
				block.name,
				block.attributes,
				mapBlocksToCoreHeading(block.innerBlocks)
			);
		}
		return createBlock(block.name, block.attributes);
	});
}

export default function Edit({ attributes, setAttributes, clientId }) {
	const { date, title, imageId, link } = attributes;

	const { replaceInnerBlocks } = useDispatch(blockEditorStore);
	const didMigrate = useRef(false);

	const innerBlocks = useSelect(
		(select) => select(blockEditorStore).getBlocks(clientId),
		[clientId]
	);

	const allowedBlocks = useSelect((select) => {
		return select('core/blocks')
			.getBlockTypes()
			.filter(
				(block) =>
					block.name !== 'we-timeline/timeline' &&
					block.name !== 'we-timeline/timeline-item' &&
					block.name !== 'we-timeline/timeline-item-title'
			)
			.map((block) => block.name);
	}, []);

	// Migrate legacy title/image attributes and old title block into InnerBlocks.
	useEffect(() => {
		if (didMigrate.current) {
			return;
		}

		let nextBlocks = innerBlocks;
		let changed = false;

		if (nextBlocks.some((block) => block.name === 'we-timeline/timeline-item-title')) {
			nextBlocks = mapBlocksToCoreHeading(nextBlocks);
			changed = true;
		}

		if (!hasBlockInTree(nextBlocks, NAV_HEADING_BLOCKS) && title) {
			nextBlocks = [
				createBlock('core/heading', { content: title, level: 3 }),
				...nextBlocks,
			];
			changed = true;
		}

		if (imageId > 0 && !hasBlockInTree(nextBlocks, IMAGE_BLOCKS)) {
			const imageBlock = createBlock('core/image', { id: imageId });
			const headingIndex = nextBlocks.findIndex((block) => block.name === 'core/heading');
			if (headingIndex >= 0) {
				nextBlocks = [
					...nextBlocks.slice(0, headingIndex + 1),
					imageBlock,
					...nextBlocks.slice(headingIndex + 1),
				];
			} else {
				nextBlocks = [imageBlock, ...nextBlocks];
			}
			changed = true;
			setAttributes({ imageId: 0 });
		}

		if (changed) {
			replaceInnerBlocks(clientId, nextBlocks, false);
		}

		didMigrate.current = true;
	}, [clientId, innerBlocks, title, imageId, replaceInnerBlocks, setAttributes]);

	const blockProps = useBlockProps({
		className: 'we-timeline__item we-timeline__item--editor',
	});

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Item Settings', 'we-timeline')} initialOpen={true}>
					<TextControl
						label={__('Date', 'we-timeline')}
						value={date || ''}
						onChange={(value) => setAttributes({ date: value || '' })}
						help={DATE_HELP}
						placeholder={__('e.g. 1977 or 1977-06-15 14:30', 'we-timeline')}
					/>
					<TextControl
						label={__('Link URL', 'we-timeline')}
						type="url"
						value={link || ''}
						onChange={(value) => setAttributes({ link: value || '' })}
						help={__('Optional. Enables “Read more” link at the end of the item.', 'we-timeline')}
					/>
				</PanelBody>
			</InspectorControls>

			<article {...blockProps}>
				<InnerBlocks
					allowedBlocks={allowedBlocks}
					template={INNER_BLOCKS_TEMPLATE}
					templateLock={false}
					renderAppender={InnerBlocks.ButtonBlockAppender}
				/>
			</article>
		</>
	);
}
