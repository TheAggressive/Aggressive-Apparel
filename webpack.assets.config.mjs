import wpConfig from '@wordpress/scripts/config/webpack.config.js';
import path from 'path';
import { merge } from 'webpack-merge';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import RemoveEmptyScriptsPlugin from 'webpack-remove-empty-scripts';
import { getAssetWebpackEntries } from './bin/lib/build-manifest.mjs';

export default (env = {}, argv = {}) => {
  const base = typeof wpConfig === 'function' ? wpConfig(env, argv) : wpConfig;
  const template = Array.isArray(base) ? base[0] : base;

  return merge(template, {
    name: 'assets',
    entry: getAssetWebpackEntries(),
    output: {
      path: path.resolve(process.cwd(), 'build'),
      filename: '[name].js',
      chunkFilename: '[name].js',
      publicPath: '',
      clean: false,
    },
    // Completely override optimization settings.
    optimization: {
      splitChunks: false,
      runtimeChunk: false,
      concatenateModules: true,
      // Use named chunks instead of numbered ones.
      chunkIds: 'named',
      moduleIds: 'named',
    },
    // Override CSS output filename to preserve directory structure.
    plugins: [
      new MiniCssExtractPlugin({
        filename: '[name].css',
        chunkFilename: '[name].css',
      }),
      new RemoveEmptyScriptsPlugin({
        stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
      }),
    ],
    stats: 'minimal',
    // Suppress postcss-calc warnings that are harmless.
    ignoreWarnings: [
      warning => {
        return (
          warning.name === 'ModuleWarning' &&
          warning.message.includes('postcss-calc: Lexical error')
        );
      },
    ],
  });
};
