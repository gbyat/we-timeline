const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Read package.json
const packagePath = path.join(__dirname, '..', 'package.json');
const packageData = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
const version = packageData.version;

console.log(`📦 Syncing version to ${version}...`);

// Read plugin file
const pluginPath = path.join(__dirname, '..', 'we-timeline.php');
let pluginContent = fs.readFileSync(pluginPath, 'utf8');

// Update version in plugin file header
pluginContent = pluginContent.replace(
    /Version:\s*\d+\.\d+\.\d+/,
    `Version: ${version}`
);

// Update WE_TIMELINE_VERSION constant
pluginContent = pluginContent.replace(
    /define\('WE_TIMELINE_VERSION',\s*'[^']*'\);/,
    `define('WE_TIMELINE_VERSION', '${version}');`
);

// Write updated plugin file
fs.writeFileSync(pluginPath, pluginContent);
console.log(`✅ Updated we-timeline.php`);

// Update README.md stable tag
const readmePath = path.join(__dirname, '..', 'README.md');
if (fs.existsSync(readmePath)) {
    let readmeContent = fs.readFileSync(readmePath, 'utf8');

    // Update Stable tag line (supports both **Stable tag:** and Stable tag: formats)
    readmeContent = readmeContent.replace(
        /(\*\*Stable tag:\*\*|Stable tag:)\s*\d+\.\d+\.\d+/,
        `$1 ${version}`
    );

    fs.writeFileSync(readmePath, readmeContent);
    console.log(`✅ Updated README.md stable tag`);
}

// Update CHANGELOG.md
const changelogPath = path.join(__dirname, '..', 'CHANGELOG.md');
if (!fs.existsSync(changelogPath)) {
    // Create initial CHANGELOG.md
    const initialContent = `# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [${version}] - ${new Date().toISOString().split('T')[0]}

### Added
- Initial release of WE Timeline

`;
    fs.writeFileSync(changelogPath, initialContent);
    console.log(`📝 Created CHANGELOG.md`);
} else {
    let changelogContent = fs.readFileSync(changelogPath, 'utf8');

    // Check if this version already exists in changelog
    const versionPattern = new RegExp(`## \\[${version.replace(/\./g, '\\.')}\\]`);
    if (!versionPattern.test(changelogContent)) {
        // Get current date
        const dateStr = new Date().toISOString().split('T')[0];

        // Extract all commit messages already in CHANGELOG to avoid duplicates
        const existingCommits = new Set();
        const changelogLines = changelogContent.split('\n');
        changelogLines.forEach(line => {
            // Match lines that start with "- " (changelog entries)
            const match = line.match(/^-\s+(.+)$/);
            if (match && !line.startsWith('  ')) {
                const commitMsg = match[1].trim();
                existingCommits.add(commitMsg);
            }
        });

        // Get git commits since last tag
        let gitLog = '';
        try {
            let lastTag = '';
            try {
                lastTag = execSync('git describe --tags --abbrev=0', {
                    encoding: 'utf8',
                    stdio: ['pipe', 'pipe', 'ignore']
                }).trim();
            } catch (e) {
                lastTag = '';
            }

            const separator = '|||COMMIT_SEPARATOR|||';
            const gitCommand = lastTag
                ? `git log ${lastTag}..HEAD --pretty=format:"${separator}%B" --no-merges`
                : `git log -20 --pretty=format:"${separator}%B" --no-merges`;

            let commitMessages = execSync(gitCommand, {
                encoding: 'utf8',
                stdio: ['pipe', 'pipe', 'ignore']
            }).trim();

            let allCommits = commitMessages.split(separator)
                .map(commit => commit.trim())
                .filter(commit => commit.length > 0)
                .map(commit => {
                    const lines = commit.split('\n');
                    const cleaned = lines.filter(line => line.trim().length > 0);
                    return cleaned.join('\n').trim();
                })
                .filter(commit => {
                    const trimmed = commit.trim();
                    return trimmed &&
                        !trimmed.match(/^Release v\d+\.\d+\.\d+$/i) &&
                        !trimmed.match(/^Bump version/i) &&
                        !trimmed.match(/^Version update$/i);
                });

            const newCommits = allCommits.filter(commit => {
                const firstLine = commit.split('\n')[0].trim();
                return !existingCommits.has(firstLine);
            });

            if (newCommits.length > 0) {
                gitLog = newCommits.map(commit => {
                    const lines = commit.split('\n');
                    const subject = lines[0].trim();
                    const body = lines.slice(1).filter(l => l.trim().length > 0);

                    if (body.length > 0) {
                        return `- ${subject}\n  ${body.map(l => l.trim()).join('\n  ')}`;
                    } else {
                        return `- ${subject}`;
                    }
                }).join('\n');
            } else {
                gitLog = '';
            }
        } catch (e) {
            gitLog = '- Version update';
        }

        const unreleasedMatch = changelogContent.match(/## \[Unreleased\]([\s\S]*?)(?=## \[|$)/);
        let unreleasedContent = '';
        if (unreleasedMatch && unreleasedMatch[1]) {
            unreleasedContent = unreleasedMatch[1].trim();
        }

        let changelogEntry = '';
        if (unreleasedContent) {
            changelogEntry = unreleasedContent;
        } else if (gitLog) {
            changelogEntry = gitLog;
        } else {
            changelogEntry = '- Version update';
        }

        const newEntry = `## [${version}] - ${dateStr}

${changelogEntry}

`;

        const lines = changelogContent.split('\n');
        const firstHeadingIndex = lines.findIndex(line => line.startsWith('## ['));

        if (firstHeadingIndex !== -1) {
            lines.splice(firstHeadingIndex, 0, newEntry);
            changelogContent = lines.join('\n');
        } else {
            changelogContent = changelogContent.replace(
                /(# Changelog.*?\n\n)/s,
                `$1${newEntry}`
            );
        }

        changelogContent = changelogContent.replace(/## \[Unreleased\][\s\S]*?(?=## \[|$)/, '');

        if (!changelogContent.includes(`[${version}]:`)) {
            const releaseLink = `\n[${version}]: https://github.com/gbyat/we-timeline/releases/tag/v${version}\n`;
            changelogContent = changelogContent.trim() + releaseLink;
        }

        fs.writeFileSync(changelogPath, changelogContent);
        console.log(`📝 Updated CHANGELOG.md with version ${version}`);
    } else {
        console.log(`ℹ️  Version ${version} already exists in CHANGELOG.md`);
    }
}

console.log(`✅ Version synchronized to ${version}`);
