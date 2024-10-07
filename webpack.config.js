const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        options: './src/blocks/options.js', // Dropdown block entry point
        truefalse: './src/blocks/truefalse.js', // True/False block entry point
    },
    output: {
        filename: '[name].js', // This ensures all entry points are built
        path: __dirname + '/assets/blocks/',
    },
};
