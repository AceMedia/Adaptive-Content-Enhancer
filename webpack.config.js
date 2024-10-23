const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        options: './src/blocks/options.js', // Dropdown block entry point
        truefalse: './src/blocks/truefalse.js', // True/False block entry point
        ajax: './src/ajax.js', // AJAX scripts
        index: './src/index.js', // React settings page entry point
        settings: './src/components/settings.js', // React settings page entry point
    },
    output: {
        filename: '[name].js', // Generates separate files for each entry point
        path: path.resolve(__dirname, 'assets/blocks'), // Ensures all files are placed in this directory
    },
};
