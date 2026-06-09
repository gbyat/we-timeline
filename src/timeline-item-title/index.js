/**
 * Timeline Item Navigation Title Block
 *
 * @package Webentwicklerin\Timeline
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType(metadata.name, {
	...metadata,
	edit: ({ attributes, setAttributes }) => {
		const blockProps = useBlockProps({
			className: 'we-timeline__item-title we-timeline__item-navigation-title',
		});

		return (
			<RichText
				{...blockProps}
				tagName="h3"
				value={attributes.title}
				onChange={(value) => setAttributes({ title: value })}
				placeholder={__('Title', 'we-timeline')}
				allowedFormats={[]}
			/>
		);
	},
	save: ({ attributes }) => {
		const blockProps = useBlockProps.save({
			className: 'we-timeline__item-title we-timeline__item-navigation-title',
		});

		return (
			<RichText.Content
				{...blockProps}
				tagName="h3"
				value={attributes.title}
			/>
		);
	},
});
