const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        index: './src/index.js', // Dropdown block entry point
        truefalse: './src/truefalse.js', // True/False block entry point
    },
    output: {
        filename: '[name].js', // This ensures both index.js and truefalse.js are built
        path: __dirname + '/build',
    },
};
