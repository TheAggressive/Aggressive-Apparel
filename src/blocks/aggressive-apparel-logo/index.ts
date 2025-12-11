/**
 * Registers the Aggressive Apparel Logo block.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';

import metadata from './block.json';
import Edit from './edit';
import './style.css';

// Custom icon using the Aggressive Apparel logo.
const icon = createElement(
  'svg',
  { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 199 175' },
  createElement('path', {
    d: 'M12.6,75.3c0-12.1,2.4-22,5.1-29.9c6.5-18.9,26.5-33.8,54.7-33.8h106.8L199,0H68.4c-10.8,0-20.5,1.6-29,4.8c-8.5,3.2-15.6,7.9-21.5,14C12.1,24.9,7.7,32.4,4.6,41.3C1.5,50.2,0,60.2,0,71.3V134l44.6,41l-32-43.7V75.3z',
  }),
  createElement('path', {
    d: 'M167.6,17.1h-5.2h-8.5H73.3c-8.8,0-16.6,1.3-23.4,3.9c-6.9,2.6-12.6,6.4-17.3,11.3c-4.7,5-8.3,11-10.8,18.2C19.2,57.6,18,65.7,18,74.8v48.6l27,35.4V88h90.4v70.8l27-35.4V29.7h5.2v101.7l-32,43.7l44.6-41V17.1h-1.8H167.6z M135.4,62.5H46.7c0.5-2.6,1.4-5.1,2.6-7.4c1.2-2.3,3.2-4.2,6-5.8c2.8-1.6,6.7-2.8,11.6-3.7c4.9-0.9,11.4-1.3,19.6-1.3h48.9V62.5z',
  })
);

// @ts-expect-error - block.json types don't perfectly align with BlockConfiguration.
registerBlockType(metadata.name, {
  ...metadata,
  icon,
  edit: Edit,
});
