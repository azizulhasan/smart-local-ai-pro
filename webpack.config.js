const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'pro-tracker': path.resolve( __dirname, 'src/pro-tracker.js' ),
		'pro-wc-tracker': path.resolve( __dirname, 'src/pro-wc-tracker.js' ),
		'pro-admin-sections': path.resolve( __dirname, 'src/pro-admin-sections.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
