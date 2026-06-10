/**
 * Duplicate MD5 JSON files using script handle filenames.
 *
 * WordPress looks for {domain}-{locale}-{handle}.json before the MD5 variant
 * in the plugin languages directory (see wp-includes/l10n.php).
 */

const fs = require('fs');
const path = require('path');
const { rootDir } = require('./wp-cli-utils');

const languagesDir = path.join(rootDir, 'languages');

/** @type {Record<string, string>} */
const SOURCE_TO_HANDLE = {
	'build/timeline/index.js': 'we-timeline-editor',
	'build/timeline-item/index.js': 'we-timeline-item-editor',
	'build/timeline-item-title/index.js': 'we-timeline-item-title-editor',
};

if (!fs.existsSync(languagesDir)) {
	process.exit(0);
}

let created = 0;

fs.readdirSync(languagesDir)
	.filter((name) => /^we-timeline-[a-z]{2}_[A-Z]{2}(?:_[a-z]+)?-[a-f0-9]{32}\.json$/.test(name))
	.forEach((filename) => {
		const match = filename.match(/^we-timeline-([a-z]{2}_[A-Z]{2}(?:_[a-z]+)?)-[a-f0-9]{32}\.json$/);
		if (!match) {
			return;
		}

		const locale = match[1];
		const parsed = JSON.parse(fs.readFileSync(path.join(languagesDir, filename), 'utf8'));
		const source = (parsed.source || '').replace(/\\/g, '/');
		const handle = SOURCE_TO_HANDLE[source];

		if (!handle) {
			return;
		}

		const aliasName = `we-timeline-${locale}-${handle}.json`;
		fs.writeFileSync(path.join(languagesDir, aliasName), JSON.stringify(parsed));
		created += 1;
		console.log(`Created ${aliasName}`);
	});

console.log(`Handle-based JSON aliases: ${created} file(s).`);
