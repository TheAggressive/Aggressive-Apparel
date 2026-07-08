/**
 * Lookbook Block — Registration.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit, { type LookbookAttributes } from './edit';
import Save from './save';
import { registerThemeBlock } from '../../utils/register-theme-block';

registerThemeBlock<LookbookAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
  save: Save,
});
