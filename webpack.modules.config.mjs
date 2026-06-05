/**
 * Webpack config for Interactivity API script modules.
 *
 * Compiles src/interactivity/*.ts → build/interactivity/*.js as ES modules,
 * compatible with wp_register_script_module() / WordPress import maps.
 *
 * External dependencies (@wordpress/interactivity, and our own shared modules)
 * are left as bare-specifier imports — WordPress resolves them at runtime via
 * the import map emitted in <head>.
 */

import path from 'path';
import wpConfig from '@wordpress/scripts/config/webpack.config.js';
import { getInteractivityModuleEntries } from './bin/lib/build-manifest.mjs';

export default (env = {}, argv = {}) => {
  const base = typeof wpConfig === 'function' ? wpConfig(env, argv) : wpConfig;
  const template = Array.isArray(base) ? base[0] : base;

  // Start from wp-scripts base for loaders/resolvers, but override entry,
  // output, externals, and plugins completely to avoid block discovery.
  return {
    ...template,
    name: 'modules',
    entry: getInteractivityModuleEntries(),
    output: {
      path: path.resolve(process.cwd(), 'build/interactivity'),
      filename: '[name].js',
      chunkFilename: '[name].js',
      publicPath: '',
      clean: true,
      module: true,
      library: { type: 'module' },
      environment: {
        module: true,
        dynamicImport: true,
      },
    },
    experiments: {
      ...(template.experiments || {}),
      outputModule: true,
    },
    externalsType: 'module',
    externals: {
      '@wordpress/interactivity': '@wordpress/interactivity',
      '@aggressive-apparel/helpers': '@aggressive-apparel/helpers',
      '@aggressive-apparel/scroll-lock': '@aggressive-apparel/scroll-lock',
      '@aggressive-apparel/use-overlay': '@aggressive-apparel/use-overlay',
    },
    optimization: {
      splitChunks: false,
      runtimeChunk: false,
      concatenateModules: true,
      chunkIds: 'named',
      moduleIds: 'named',
    },
    // Keep loaders from wp-scripts (module.rules) but replace plugins.
    // wp-scripts plugins include block-related ones we don't need.
    plugins: (template.plugins || []).filter(
      p =>
        p.constructor.name === 'MiniCssExtractPlugin' ||
        p.constructor.name === 'DependencyExtractionWebpackPlugin'
    ),
    stats: 'minimal',
  };
};
