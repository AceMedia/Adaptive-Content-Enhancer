const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        index: './src/index.js',
    },
    output: {
        filename: '[name].js', // This ensures both index.js and view.js are built
        path: __dirname + '/build',
    },
};
