/**
 * Build hashed JS translation JSON files from existing PO files.
 *
 * Uses WP-CLI i18n make-json command.
 */

const fs = require('fs');
const path = require('path');
const { rootDir, runWp } = require('./wp-cli-utils');

const languagesDir = path.join(rootDir, 'languages');

if (!fs.existsSync(languagesDir)) {
	fs.mkdirSync(languagesDir, { recursive: true });
}

try {
	runWp([
		'i18n',
		'make-json',
		languagesDir,
		'--no-purge',
	]);

	console.log(`JSON translation files updated in: ${languagesDir}`);
	require('./json-handle-aliases');
} catch (error) {
	console.error('WP-CLI JSON build failed.');
	console.error('Ensure WP-CLI is installed and available as "wp".');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
