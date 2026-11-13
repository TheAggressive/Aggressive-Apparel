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

const { Features } = require('lightningcss');

/*
 * The theme's browser floor, set by its newest required feature: light-dark()
 * (Chrome/Edge 123, Firefox 120, Safari 17.5) carries every adaptive colour.
 * Everything else it relies on (oklch(), color-mix(), @layer, container
 * queries, :autofill) is older, so it all ships as written; Lightning only
 * adds the vendor prefixes these versions still need.
 *
 * Passed as `browsers` so it is pinned: without it postcss-lightningcss loads
 * any browserslist config it finds (e.g. a future package.json
 * `browserslist`, which wp-scripts also reads for JS), and an older floor
 * would silently lower light-dark() and @layer into fallbacks.
 */
const BROWSERS = [
  'chrome >= 123',
  'edge >= 123',
  'firefox >= 120',
  'safari >= 17.5',
  'ios_saf >= 17.5',
];

/*
 * postcss-import inlines each `@import` where it appears (css-loader would
 * hoist imports above the importing file's own rules, which moves main.css's
 * `@layer` order statement below the layers it orders).
 *
 * Sources are written in native CSS nesting. Lightning CSS flattens it at
 * build time (`include: Features.Nesting`) even though every target supports
 * it, so the shipped CSS is flat and selector specificity is fixed at build.
 */
module.exports = {
  plugins: [
    require('postcss-import'),
    expandCustomMedia(),
    require('postcss-lightningcss')({
      browsers: BROWSERS,
      lightningcssOptions: { minify: true, include: Features.Nesting },
    }),
  ],
};
