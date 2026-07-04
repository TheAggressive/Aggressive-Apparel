import { createRequire } from 'node:module';
import wpConfig from '@wordpress/scripts/config/webpack.config.js';

// Resolve the plugin through @wordpress/scripts' own dependency tree so the
// exact instance wp-scripts uses is extended (pnpm does not hoist it here).
const require = createRequire(import.meta.url);
const wpScriptsRequire = createRequire(
  require.resolve('@wordpress/scripts/package.json')
);
const DependencyExtractionWebpackPlugin = wpScriptsRequire(
  '@wordpress/dependency-extraction-webpack-plugin'
);

/**
 * Custom module specifiers resolved at runtime via the WordPress import map.
 *
 * For module (ESM) builds these are externalized through
 * DependencyExtractionWebpackPlugin so they are BOTH left as bare imports AND
 * recorded in the generated *.asset.php dependency list — WordPress only adds
 * an import-map entry for declared dependencies of enqueued modules, so a
 * plain `externals` mapping would leave the bare import unresolvable at
 * runtime unless another module happened to declare the same dependency.
 */
const THEME_MODULE_IDS = [
  '@aggressive-apparel/helpers',
  '@aggressive-apparel/scroll-lock',
  '@aggressive-apparel/use-overlay',
];

/** Legacy script-build externals (non-ESM output never imports these). */
const THEME_MODULE_EXTERNALS = Object.fromEntries(
  THEME_MODULE_IDS.map(id => [id, id])
);

function withThemeExternals(config) {
  if (Array.isArray(config)) {
    return config.map(withThemeExternals);
  }

  const isModuleBuild = Boolean(config.output && config.output.module);

  if (!isModuleBuild) {
    const existing = config.externals;
    const asArray = Array.isArray(existing)
      ? existing
      : existing
      ? [existing]
      : [];
    return {
      ...config,
      externals: [...asArray, THEME_MODULE_EXTERNALS],
    };
  }

  const plugins = (config.plugins || []).map(plugin => {
    if (plugin.constructor?.name !== 'DependencyExtractionWebpackPlugin') {
      return plugin;
    }

    return new DependencyExtractionWebpackPlugin({
      ...(plugin.options || {}),
      requestToExternalModule(request) {
        if (THEME_MODULE_IDS.includes(request)) {
          return request;
        }
        // Returning undefined defers to the plugin's default handling
        // (@wordpress/*, react, etc.).
      },
    });
  });

  return { ...config, plugins };
}

export default (env = {}, argv = {}) => {
  const base = typeof wpConfig === 'function' ? wpConfig(env, argv) : wpConfig;
  return withThemeExternals(base);
};
