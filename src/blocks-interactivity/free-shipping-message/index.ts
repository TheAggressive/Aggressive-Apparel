/**
 * Free Shipping Message Block Registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit, { type FreeShippingMessageAttributes } from './edit';
import Save from './save';
import { isDefaultEmphasis } from './cart-data';
import { defaultWording } from './wording';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';
import './editor.css';
import '../../blocks/icon/style.css';

registerThemeBlock(metadata, {
  icon: blockIcon,
  edit: Edit,
  save: Save,
  deprecated: [
    {
      // Before wording: a custom `emphasisText` phrase inside fixed copy.
      // Opening such a block in the editor moves that phrase into the
      // block's own wording, where it stays editable. (Blocks never opened
      // keep rendering the phrase server-side.)
      attributes: metadata.attributes,
      supports: metadata.supports,
      save: Save,
      isEligible: (attributes: Record<string, unknown>) =>
        !isDefaultEmphasis(String(attributes.emphasisText ?? '').trim()),
      migrate: (attributes: Record<string, unknown>) => ({
        ...(attributes as FreeShippingMessageAttributes),
        useCustomWording: true,
        progressText: defaultWording(
          'progress',
          String(attributes.emphasisText)
        ),
        unlockedText: defaultWording(
          'unlocked',
          String(attributes.emphasisText)
        ),
        emphasisText: '',
      }),
    },
  ],
});
