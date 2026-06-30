/**
 * PostCSS Configuration
 *
 * @package Aggressive_Apparel
 */

/**
 * Expands @custom-media to literal queries before minification.
 *
 * Lightning CSS does not support var() in @media and preserves @custom-media
 * for native-only output — this plugin inlines aliases for all browsers.
 */
function expandCustomMedia() {
  return {
    postcssPlugin: 'postcss-expand-custom-media',
    Once(root) {
      /** @type {Map<string, string>} */
      const definitions = new Map();

      root.walkAtRules('custom-media', (rule) => {
        const params = rule.params.trim();
        const space = params.indexOf(' ');

        if (-1 === space) {
          return;
        }

        const name = params.slice(0, space).trim();
        const query = params.slice(space + 1).trim();
        definitions.set(name, query);
        rule.remove();
      });

      if (0 === definitions.size) {
        return;
      }

      root.walkAtRules('media', (rule) => {
        const params = rule.params.trim();
        const match = params.match(/^\(\s*(--[\w-]+)\s*\)$/);

        if (!match) {
          return;
        }

        const expanded = definitions.get(match[1]);

        if (expanded) {
          rule.params = expanded;
        }
      });
    },
  };
}

expandCustomMedia.postcss = true;

module.exports = {
  plugins: [
    require('postcss-nested'),
    require('@tailwindcss/postcss')({ optimize: false }),
    expandCustomMedia(),
    require('postcss-lightningcss')({ minify: true }),
  ],
};
