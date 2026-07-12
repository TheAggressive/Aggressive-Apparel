/**
 * Split Story — Content column registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import { registerColumnBlock } from '../split-story-shared/column';

const icon = (
  <svg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg' aria-hidden='true'>
    <path
      d='M5 6h14M5 10h14M5 14h10M5 18h8'
      fill='none'
      stroke='currentColor'
      strokeWidth='1.5'
      strokeLinecap='round'
    />
  </svg>
);

registerColumnBlock(metadata, {
  className: 'aa-split-story__content',
  template: [
    ['core/heading', { level: 2, placeholder: 'Story heading…' }],
    ['core/paragraph', { placeholder: 'Tell the story…' }],
  ],
  icon,
});
