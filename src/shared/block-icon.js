/**
 * WE Timeline block icon (saturated orange-red).
 *
 * @package Webentwicklerin\Timeline
 */

import { Circle, Path, SVG } from '@wordpress/primitives';

/** @type {string} Saturated orange-red for block inserter visibility. */
export const WE_TIMELINE_ICON_COLOR = '#E04D2E';

/**
 * Timeline block icon: vertical line with nodes and content bars.
 *
 * @return {JSX.Element} SVG icon element.
 */
export function WeTimelineIcon() {
	const color = WE_TIMELINE_ICON_COLOR;

	return (
		<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
			<Path fill={color} d="M7 2h2v20H7z" />
			<Circle cx="8" cy="6" r="2.5" fill={color} />
			<Circle cx="8" cy="12" r="2.5" fill={color} />
			<Circle cx="8" cy="18" r="2.5" fill={color} />
			<Path fill={color} d="M11.5 5h9v3h-9zm0 7h7v3h-7zm0 7h9v3h-9z" />
		</SVG>
	);
}

/**
 * Child block icon: calendar dot marker.
 *
 * @return {JSX.Element} SVG icon element.
 */
export function WeTimelineItemIcon() {
	const color = WE_TIMELINE_ICON_COLOR;

	return (
		<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
			<Path
				fill={color}
				d="M7 3a1 1 0 0 1 1 1v1h8V4a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V4a1 1 0 0 1 1-1Zm12 8H5v9h14v-9Z"
			/>
			<Circle cx="12" cy="14" r="2.25" fill={color} />
		</SVG>
	);
}
