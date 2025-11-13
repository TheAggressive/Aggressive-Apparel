/**
 * Block Editor Scripts
 *
 * @package
 */

import { registerBlockStyle, unregisterBlockStyle } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';

/**
 * Register custom block styles
 */
domReady( () => {
  // Button styles
  registerBlockStyle( 'core/button', {
    name: 'secondary',
    label: 'Secondary',
  } );

  registerBlockStyle( 'core/button', {
    name: 'outline',
    label: 'Outline',
  } );

  // Heading styles
  registerBlockStyle( 'core/heading', {
    name: 'uppercase',
    label: 'Uppercase',
  } );

  // Image styles
  registerBlockStyle( 'core/image', {
    name: 'rounded',
    label: 'Rounded',
  } );

  // Remove default block styles we don't want
  unregisterBlockStyle( 'core/button', 'outline' );
  unregisterBlockStyle( 'core/button', 'squared' );
} );
