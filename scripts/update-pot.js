/**
 * Build POT file from PHP sources and built block scripts.
 *
 * Run after `npm run build` so JS references match enqueued build/*.js paths.
 */

const fs = require('fs');
const path = require('path');
const { rootDir, runWp } = require('./wp-cli-utils');

const languagesDir = path.join(rootDir, 'languages');
const textDomain = 'we-timeline';
const potFile = path.join(languagesDir, `${textDomain}.pot`);

if (!fs.existsSync(languagesDir)) {
	fs.mkdirSync(languagesDir, { recursive: true });
}

try {
	runWp([
		'i18n',
		'make-pot',
		'.',
		potFile,
		`--domain=${textDomain}`,
		'--exclude=node_modules,vendor,scripts,src',
	]);

	console.log(`POT file updated: ${potFile}`);
} catch (error) {
	console.error('WP-CLI POT build failed.');
	console.error('Ensure WP-CLI is installed and available as "wp".');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
