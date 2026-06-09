/**
 * Flexible timeline date parsing and display (editor preview).
 *
 * @package Webentwicklerin\Timeline
 */

/**
 * @param {string} dateStr Raw date string.
 * @return {'year'|'month'|'day'|'datetime'|'unknown'}
 */
export function getDatePrecision(dateStr) {
	if (!dateStr) {
		return 'unknown';
	}
	const trimmed = String(dateStr).trim();
	if (/^\d{4}$/.test(trimmed)) {
		return 'year';
	}
	if (/^\d{4}-\d{2}$/.test(trimmed)) {
		return 'month';
	}
	if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
		return 'day';
	}
	if (/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/.test(trimmed)) {
		return 'datetime';
	}
	return 'unknown';
}

/**
 * @param {string} dateStr Raw date string.
 * @return {string} Label for editor preview (raw if unknown).
 */
export function formatDatePreview(dateStr) {
	if (!dateStr) {
		return '';
	}
	const trimmed = String(dateStr).trim();
	const precision = getDatePrecision(trimmed);

	if (precision === 'year') {
		return trimmed;
	}
	if (precision === 'month') {
		const date = new Date(`${trimmed}-01T00:00:00`);
		return Number.isNaN(date.getTime())
			? trimmed
			: date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
	}
	if (precision === 'day') {
		const date = new Date(`${trimmed}T00:00:00`);
		return Number.isNaN(date.getTime()) ? trimmed : date.toLocaleDateString();
	}
	if (precision === 'datetime') {
		const normalized = trimmed.replace(' ', 'T');
		const date = new Date(normalized);
		return Number.isNaN(date.getTime()) ? trimmed : date.toLocaleString();
	}
	return trimmed;
}
