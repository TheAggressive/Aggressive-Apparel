/**
 * PostCSS Configuration
 *
 * @package
 */

module.exports = {
  plugins: {
    'postcss-nested': {},
    '@tailwindcss/postcss': {
      optimize: false,
    },
    'postcss-lightningcss': {
      minify: true,
    },
  },
};
