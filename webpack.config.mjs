import wpConfig from '@wordpress/scripts/config/webpack.config.js';

/**
 * Custom module specifiers resolved at runtime via the WordPress import map.
 * Mark them as externals so Webpack leaves bare imports in ESM output.
 */
const THEME_MODULE_EXTERNALS = {
  '@aggressive-apparel/helpers': '@aggressive-apparel/helpers',
  '@aggressive-apparel/scroll-lock': '@aggressive-apparel/scroll-lock',
  '@aggressive-apparel/use-overlay': '@aggressive-apparel/use-overlay',
};

function withThemeExternals(config) {
  if (Array.isArray(config)) {
    return config.map(withThemeExternals);
  }
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

export default (env = {}, argv = {}) => {
  const base = typeof wpConfig === 'function' ? wpConfig(env, argv) : wpConfig;
  return withThemeExternals(base);
};
