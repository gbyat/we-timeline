const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		'timeline/index': './src/timeline/index.js',
		'timeline/view': './src/timeline/view.js',
		'timeline-item/index': './src/timeline-item/index.js',
		'timeline-item-title/index': './src/timeline-item-title/index.js',
	},
};
