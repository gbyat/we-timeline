/**
 * Merge new POT strings into existing PO files.
 */

const fs = require('fs');
const path = require('path');
const { rootDir, runWp } = require('./wp-cli-utils');

const languagesDir = path.join(rootDir, 'languages');
const potFile = path.join(languagesDir, 'we-timeline.pot');

if (!fs.existsSync(potFile)) {
	console.error(`POT file not found: ${potFile}`);
	console.error('Run "npm run pot" first (after "npm run build").');
	process.exit(1);
}

try {
	runWp([
		'i18n',
		'update-po',
		potFile,
		languagesDir,
	]);

	console.log(`PO files updated from: ${potFile}`);
} catch (error) {
	console.error('WP-CLI PO update failed.');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
