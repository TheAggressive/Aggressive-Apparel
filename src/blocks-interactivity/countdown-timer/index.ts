/**
 * Sale Countdown Timer Block Registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import Save from './save';
import {
  getCountdownVariations,
  type CountdownTimerAttributes,
} from './display-styles';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './editor.css';
import './style.css';

registerThemeBlock<CountdownTimerAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
  save: Save,
  variations: getCountdownVariations(blockIcon),
});
