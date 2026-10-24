/**
 * Dark Mode Toggle Block Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';
import { legacyTextAlignDeprecation } from '../../utils/legacy-text-align';

// Import styles for webpack to bundle.
import './style.css';

// Block attributes type derived from block.json.
type DarkModeToggleAttributes = {
  label: string;
  labelDark: string;
  showLabel: boolean;
  size: 'small' | 'medium' | 'large';
  iconStyle: 'solid' | 'outline';
  iconStrokeWidth: number;
  iconColor: string;
  iconHoverColor: string;
  toggleBackgroundColor: string;
  toggleBackgroundHoverColor: string;
};

registerThemeBlock<DarkModeToggleAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
  deprecated: [
    // Before native text alignment: a custom `alignment` attribute plus the
    // float-style `align` support, which competed with it.
    legacyTextAlignDeprecation({
      attributes: {
        ...metadata.attributes,
        // No default: an unset `alignment` must not mask a legacy `align`.
        alignment: {
          type: 'string',
          enum: ['left', 'center', 'right'],
        },
      },
      supports: {
        ...metadata.supports,
        align: ['left', 'center', 'right'],
        typography: { fontSize: true, lineHeight: true },
      },
      keys: ['alignment', 'align'],
    }),
  ],
});
