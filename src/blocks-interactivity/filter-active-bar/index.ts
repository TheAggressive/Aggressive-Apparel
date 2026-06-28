/**
 * Active Filter Bar Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

registerThemeBlock(metadata, {
  edit: Edit,
});
