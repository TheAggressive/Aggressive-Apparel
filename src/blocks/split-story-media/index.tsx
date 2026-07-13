/**
 * Split Story — Media column registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import { registerContainerBlock } from '../../utils/register-container-block';

const icon = (
  <svg
    viewBox='0 0 24 24'
    xmlns='http://www.w3.org/2000/svg'
    aria-hidden='true'
  >
    <rect
      x='4'
      y='4'
      width='16'
      height='16'
      rx='2'
      fill='none'
      stroke='currentColor'
      strokeWidth='1.5'
    />
    <circle cx='9' cy='10' r='1.5' fill='currentColor' />
    <path
      d='M5 17l4-4 3 3 3-4 4 5'
      fill='none'
      stroke='currentColor'
      strokeWidth='1.5'
      strokeLinecap='round'
      strokeLinejoin='round'
    />
  </svg>
);

registerContainerBlock(metadata, {
  className: 'aa-split-story__media',
  template: [['core/image', {}]],
  icon,
});
