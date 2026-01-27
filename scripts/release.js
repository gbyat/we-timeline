const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

// Get release type from command line argument (patch, minor, major)
const releaseType = process.argv[2] || 'patch';

if (!['patch', 'minor', 'major'].includes(releaseType)) {
    console.error('❌ Invalid release type. Use: patch, minor, or major');
    process.exit(1);
}

console.log(`🚀 Creating ${releaseType} release for WE Timeline...`);

try {
    const packagePath = path.join(__dirname, '..', 'package.json');
    const packageData = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
    const currentVersion = packageData.version;

    console.log(`⬆️  Bumping ${releaseType} version from ${currentVersion}...`);
    execSync(`npm version ${releaseType} --no-git-tag-version`, { stdio: 'inherit' });

    const newPackageData = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
    const newVersion = newPackageData.version;
    console.log(`✅ New version: ${newVersion}`);

    console.log('🔄 Syncing version to plugin file...');
    execSync('node scripts/sync-version.js', { stdio: 'inherit' });

    console.log('📦 Adding all changes to git...');
    execSync('git add -A', { stdio: 'inherit' });

    console.log('💾 Committing changes...');
    try {
        execSync(`git commit -m "Release v${newVersion}"`, { stdio: 'inherit' });
    } catch (e) {
        console.log("ℹ️  Nothing to commit (that's okay)");
    }

    try {
        console.log('🗑️  Removing existing tag if it exists...');
        execSync(`git tag -d v${newVersion}`, { stdio: 'pipe' });
        execSync(`git push origin :refs/tags/v${newVersion}`, { stdio: 'pipe' });
    } catch (e) {
        // Tag doesn't exist, that's fine
    }

    console.log('🏷️  Creating tag...');
    execSync(`git tag -a "v${newVersion}" -m "Release v${newVersion}"`, { stdio: 'inherit' });

    let branch = 'main';
    try {
        branch = execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();
    } catch (e) {
        // Fallback to main
    }

    console.log('🔍 Checking if GitHub repository exists...');
    try {
        execSync('git ls-remote origin', { stdio: 'pipe' });
        console.log('✅ Repository is accessible');
    } catch (e) {
        console.error('');
        console.error('❌ ==========================================');
        console.error('❌ ERROR: GitHub repository not found!');
        console.error('❌ ==========================================');
        console.error('');
        console.error('The remote repository does not exist or is not accessible.');
        console.error('');
        console.error('Please check:');
        console.error('  1. Does the repository exist on GitHub?');
        console.error('  2. Is the remote URL correct? (Check with: git remote -v)');
        console.error('  3. Do you have access to the repository?');
        console.error('');
        console.error('To remove the remote, use: git remote remove origin');
        console.error('To add a new remote, use: git remote add origin <url>');
        console.error('');
        process.exit(1);
    }

    console.log('⬆️  Pushing to GitHub...');
    console.log(`   Pushing branch: ${branch}`);
    console.log(`   Pushing tag: v${newVersion}`);

    execSync(`git push origin ${branch}`, { stdio: 'inherit' });
    execSync(`git push origin v${newVersion}`, { stdio: 'inherit' });

    console.log('');
    console.log('✅ ==========================================');
    console.log(`✅ Release v${newVersion} successfully created!`);
    console.log('✅ ==========================================');
    console.log('');
    console.log('🎉 GitHub Actions will now:');
    console.log('   1. Build the plugin');
    console.log('   2. Generate POT file');
    console.log('   3. Create we-timeline.zip');
    console.log(`   4. Create GitHub Release v${newVersion}`);
    console.log('   5. Attach the ZIP file to the release');
    console.log('');
    console.log('🔗 Check progress at:');
    console.log('   https://github.com/gbyat/we-timeline/actions');
    console.log('');

} catch (error) {
    console.error('❌ Error during release:', error.message);
    process.exit(1);
}
