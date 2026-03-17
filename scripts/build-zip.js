/**
 * Build production zip for Smart Local AI Pro.
 *
 * Usage: node scripts/build-zip.js
 *
 * Creates production/smart-local-ai-pro.zip containing only the files
 * needed to run the plugin — no source, dev config, or node_modules.
 *
 * When unzipped the structure is:
 *   smart-local-ai-pro/
 *     smart-local-ai-pro.php
 *     includes/
 *     build/
 *     ...
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );

const ROOT = path.resolve( __dirname, '..' );
const PLUGIN_SLUG = 'smart-local-ai-pro';
const PRODUCTION_DIR = path.join( ROOT, 'production' );
const STAGING_DIR = path.join( PRODUCTION_DIR, PLUGIN_SLUG );
const ZIP_FILE = path.join( PRODUCTION_DIR, `${ PLUGIN_SLUG }.zip` );

/**
 * Files and directories to include in the zip.
 * Paths are relative to the plugin root.
 */
const INCLUDE = [
	'smart-local-ai-pro.php',
	'uninstall.php',
	'readme.txt',
	'index.php',
	'build/',
	'css/',
	'includes/',
	'Libs/',
	'vendor/',
];

/**
 * Patterns to exclude (matched against relative paths).
 */
const EXCLUDE = [
	'.DS_Store',
	'Thumbs.db',
	'.gitkeep',
	'.claude/',
	'node_modules/',
	'.git/',
];

function cleanDir( dir ) {
	if ( fs.existsSync( dir ) ) {
		fs.rmSync( dir, { recursive: true, force: true } );
	}
	fs.mkdirSync( dir, { recursive: true } );
}

function shouldExclude( relativePath ) {
	return EXCLUDE.some( ( pattern ) => relativePath.includes( pattern ) );
}

function countFiles( dir ) {
	let count = 0;
	const entries = fs.readdirSync( dir );
	for ( const entry of entries ) {
		const fullPath = path.join( dir, entry );
		const stat = fs.statSync( fullPath );
		if ( stat.isDirectory() ) {
			count += countFiles( fullPath );
		} else {
			count++;
		}
	}
	return count;
}

function copyRecursive( src, dest ) {
	const stat = fs.statSync( src );

	if ( stat.isDirectory() ) {
		fs.mkdirSync( dest, { recursive: true } );
		const entries = fs.readdirSync( src );
		for ( const entry of entries ) {
			const srcPath = path.join( src, entry );
			const destPath = path.join( dest, entry );
			const relativePath = path.relative( ROOT, srcPath );

			if ( shouldExclude( relativePath ) ) {
				continue;
			}

			copyRecursive( srcPath, destPath );
		}
	} else {
		fs.mkdirSync( path.dirname( dest ), { recursive: true } );
		fs.copyFileSync( src, dest );
	}
}

// ---- Main ----

console.log( `Building ${ PLUGIN_SLUG } production zip...\n` );

// 1. Run production build.
console.log( '1. Running webpack build...' );
execSync( 'npm run build', { cwd: ROOT, stdio: 'inherit' } );

// 2. Clean and create staging directory.
console.log( '\n2. Preparing staging directory...' );
cleanDir( PRODUCTION_DIR );
fs.mkdirSync( STAGING_DIR, { recursive: true } );

// 3. Copy files.
console.log( '3. Copying production files...' );
let fileCount = 0;

for ( const item of INCLUDE ) {
	const srcPath = path.join( ROOT, item );

	if ( ! fs.existsSync( srcPath ) ) {
		console.log( `   Skipping (not found): ${ item }` );
		continue;
	}

	const destPath = path.join( STAGING_DIR, item );
	copyRecursive( srcPath, destPath );

	const stat = fs.statSync( srcPath );
	if ( stat.isDirectory() ) {
		const count = countFiles( destPath );
		fileCount += count;
		console.log( `   ${ item } (${ count } files)` );
	} else {
		fileCount++;
		console.log( `   ${ item }` );
	}
}

// 4. Create zip.
console.log( `\n4. Creating zip archive (${ fileCount } files)...` );

// Remove old zip if exists.
if ( fs.existsSync( ZIP_FILE ) ) {
	fs.unlinkSync( ZIP_FILE );
}

// Use PowerShell on Windows, zip on Unix.
const isWindows = process.platform === 'win32';

if ( isWindows ) {
	execSync(
		`powershell -Command "Compress-Archive -Path '${ STAGING_DIR }' -DestinationPath '${ ZIP_FILE }'"`,
		{ cwd: PRODUCTION_DIR, stdio: 'inherit' }
	);
} else {
	execSync(
		`cd "${ PRODUCTION_DIR }" && zip -r ${ PLUGIN_SLUG }.zip ${ PLUGIN_SLUG }/`,
		{ stdio: 'inherit' }
	);
}

// 5. Clean up staging directory.
fs.rmSync( STAGING_DIR, { recursive: true, force: true } );

// 6. Report.
const zipSize = fs.statSync( ZIP_FILE ).size;
const sizeMB = ( zipSize / 1024 / 1024 ).toFixed( 2 );

console.log( `\nDone! Production zip created:` );
console.log( `   production/${ PLUGIN_SLUG }.zip (${ sizeMB } MB)` );
console.log( `   ${ fileCount } files included\n` );
