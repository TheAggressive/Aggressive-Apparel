/**
 * Wishlist Page Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import Save from './save';
import { registerThemeBlock } from '../../utils/register-theme-block';
import './style.css';
import type { WishlistPageAttributes } from './types';

registerThemeBlock<WishlistPageAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
  save: Save,
  deprecated: [
    {
      // Before Block spacing: a free-text `gap` attribute. Opening such a
      // block moves the value to the Dimensions panel. (Blocks never opened
      // keep rendering it server-side.)
      attributes: {
        ...metadata.attributes,
        gap: { type: 'string', default: '' },
      },
      supports: {
        ...metadata.supports,
        spacing: { margin: true, padding: true },
      },
      save: Save,
      isEligible: (attributes: Record<string, unknown>) => !!attributes.gap,
      migrate: ({
        gap,
        ...attributes
      }: Record<string, unknown>): WishlistPageAttributes => {
        const style = (attributes.style ?? {}) as Record<string, unknown>;
        const migrated: Record<string, unknown> = {
          ...attributes,
          style: {
            ...style,
            spacing: {
              ...((style.spacing ?? {}) as Record<string, unknown>),
              blockGap: gap,
            },
          },
        };
        return migrated as WishlistPageAttributes;
      },
    },
  ],
});
