const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const glob = require('glob');

// Dynamically get all JavaScript files from the src directory
const entries = glob.sync('./src/**/*.js').reduce((acc, filePath) => {
    const entryName = filePath.replace('./src/', '').replace('.js', '');
    acc[entryName] = filePath;
    return acc;
}, {});

module.exports = {
    ...defaultConfig,
    entry: entries,
    output: {
        filename: (pathData) => {
            // Maintain the same directory structure as src
            return pathData.chunk.name.replace(/\\/g, '/').concat('.js');
        },
        path: path.resolve(__dirname, 'assets'),
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: [
                            '@wordpress/babel-preset-default',
                            '@babel/preset-react'
                        ],
                    },
                },
            },
            {
                test: /\.scss$/,
                use: [
                    'style-loader', // Injects CSS into the DOM
                    'css-loader',   // Translates CSS into CommonJS
                    {
                        loader: 'sass-loader', // Compiles Sass to CSS
                        options: {
                            implementation: require('sass'),
                            sassOptions: {
                                quietDeps: true, // Suppress deprecation warnings from dependencies
                            },
                        },
                    },
                ],
            },
        ],
    },
    resolve: {
        extensions: ['.js', '.jsx'], // Resolve these extensions
    },
};
