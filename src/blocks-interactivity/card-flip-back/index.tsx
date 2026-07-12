/**
 * Card Flip — Back face registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import { registerFaceBlock } from '../card-flip-shared/face';

const icon = (
  <svg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg' aria-hidden='true'>
    <rect
      x='4'
      y='4'
      width='16'
      height='16'
      rx='2'
      fill='none'
      stroke='currentColor'
      strokeWidth='1.5'
      strokeDasharray='3 2'
    />
    <path
      d='M14 8l3 4-3 4'
      fill='none'
      stroke='currentColor'
      strokeWidth='1.5'
      strokeLinecap='round'
      strokeLinejoin='round'
    />
  </svg>
);

registerFaceBlock(metadata, 'back', icon);
