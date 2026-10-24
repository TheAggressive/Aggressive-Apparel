/**
 * Aggressive Icon Block
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit, { type IconBlockAttributes } from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';
import { legacyTextAlignDeprecation } from '../../utils/legacy-text-align';

import './style.css';
import './editor.css';

// Supports before alignment moved to native `typography.textAlign`.
const { typography: _typography, ...SUPPORTS_BEFORE_TEXT_ALIGN } =
  metadata.supports;

registerThemeBlock<IconBlockAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
  deprecated: [
    legacyTextAlignDeprecation({
      attributes: {
        ...metadata.attributes,
        textAlign: { type: 'string', default: '' },
      },
      supports: SUPPORTS_BEFORE_TEXT_ALIGN,
      keys: ['textAlign'],
    }),
  ],
});
