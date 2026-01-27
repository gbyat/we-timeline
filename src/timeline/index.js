/**
 * Timeline Block
 *
 * @package Webentwicklerin\Timeline
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import Edit from './edit';
import metadata from './block.json';
import './styles.scss';
import './view.js';
import './timeline-progress.js';

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: () => {
        const blockProps = useBlockProps.save();
        return null; // Server-side rendered
    },
});
