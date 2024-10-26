const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const glob = require('glob');

const entries = glob.sync('./src/**/*.js').reduce((acc, filePath) => {
    const entryName = filePath.replace('./src/', '').replace('.js', '');
    acc[entryName] = filePath;
    return acc;
}, {});

module.exports = {
    ...defaultConfig,
    entry: entries,
    output: {
        filename: (pathData) => pathData.chunk.name.replace(/\\/g, '/').concat('.js'),
        path: path.resolve(__dirname, 'assets/js'), // Output JavaScript to assets/js
    },
    cache: {
        type: 'filesystem',
        cacheDirectory: path.resolve(__dirname, '.webpack-cache'), // Unique cache directory
        buildDependencies: {
            config: [__filename], // Rebuild cache if webpack config changes
        },
    },
    watchOptions: {
        ignored: /node_modules/, // Ignore node_modules to improve watch performance
        aggregateTimeout: 300,   // Delay rebuild after the first change
        poll: 1000,              // Check for changes every second (optional)
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
                        cacheDirectory: true, // Caches Babel transforms
                    },
                },
            },
            {
                test: /\.scss$/,
                use: [
                    'style-loader',
                    'css-loader',
                    {
                        loader: 'sass-loader',
                        options: {
                            implementation: require('sass'),
                            sassOptions: {
                                quietDeps: true,
                            },
                        },
                    },
                ],
            },
        ],
    },
    resolve: {
        extensions: ['.js', '.jsx'],
    },
};
