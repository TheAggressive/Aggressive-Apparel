/**
 * Card Flip — Front face registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import { registerFaceBlock } from '../card-flip-shared/face';

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
    <path d='M8 9h8M8 12h8M8 15h5' stroke='currentColor' strokeWidth='1.5' />
  </svg>
);

registerFaceBlock(metadata, 'front', icon);
