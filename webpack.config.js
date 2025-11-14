/**
 * WordPress Scripts Webpack Configuration
 *
 * Extends the default @wordpress/scripts webpack config
 *
 * @package
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
  ...defaultConfig,
  entry: {
    // Main theme scripts
    'scripts/main': path.resolve( process.cwd(), 'src/scripts', 'main.js' ),
    'scripts/navigation': path.resolve(
      process.cwd(),
      'src/scripts',
      'navigation.js'
    ),
    'scripts/cart': path.resolve( process.cwd(), 'src/scripts', 'cart.js' ),
    'scripts/product': path.resolve(
      process.cwd(),
      'src/scripts',
      'product.js'
    ),

    // Editor scripts
    'scripts/editor': path.resolve( process.cwd(), 'src/scripts', 'editor.js' ),

    // Styles (CSS with PostCSS + Tailwind)
    'styles/main': path.resolve( process.cwd(), 'src/styles', 'main.css' ),
    'styles/woocommerce': path.resolve(
      process.cwd(),
      'src/styles',
      'woocommerce.css'
    ),
    'styles/blocks': path.resolve( process.cwd(), 'src/styles', 'blocks.css' ),
    'styles/navigation': path.resolve(
      process.cwd(),
      'src/styles',
      'navigation.css'
    ),
  },
  output: {
    path: path.resolve( process.cwd(), 'build' ),
    filename: '[name].js',
    clean: true,
  },
};
