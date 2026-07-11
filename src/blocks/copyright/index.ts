/**
 * Copyright Block
 *
 * @package Aggressive_Apparel
 */

import type { BlockConfiguration, BlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import type { CopyrightAttributes, LegacyCopyrightAttributes } from './types';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';

const variations: BlockVariation[] = [
  {
    name: 'default',
    title: __('Copyright', 'aggressive-apparel'),
    description: __(
      'Current year with the site title as owner.',
      'aggressive-apparel'
    ),
    isDefault: true,
    attributes: {
      ownerSource: 'site_title',
      showStartYear: false,
      legalEntity: '',
      suffix: '',
      showLegalLinks: false,
      showSchema: true,
    },
    scope: ['inserter', 'block'],
  },
  {
    name: 'year-range',
    title: __('Copyright (year range)', 'aggressive-apparel'),
    description: __(
      'Start year through the current year, synced to the site title.',
      'aggressive-apparel'
    ),
    attributes: {
      ownerSource: 'site_title',
      showStartYear: true,
      startYear: 2024,
      suffix: '',
    },
    scope: ['inserter', 'block', 'transform'],
  },
  {
    name: 'all-rights-reserved',
    title: __('Copyright with rights reserved', 'aggressive-apparel'),
    description: __(
      'Current year plus an “All rights reserved” suffix.',
      'aggressive-apparel'
    ),
    attributes: {
      ownerSource: 'site_title',
      showStartYear: false,
      suffix: __('. All rights reserved.', 'aggressive-apparel'),
    },
    scope: ['inserter', 'block', 'transform'],
  },
  {
    name: 'legal-footer',
    title: __('Copyright with Privacy & Terms', 'aggressive-apparel'),
    description: __(
      'Legal name owner, rights reserved, and Privacy / Terms links.',
      'aggressive-apparel'
    ),
    attributes: {
      ownerSource: 'legal_name',
      showStartYear: false,
      suffix: __('. All rights reserved.', 'aggressive-apparel'),
      showLegalLinks: true,
      showSchema: true,
    },
    scope: ['inserter', 'block', 'transform'],
  },
];

/**
 * Migrate pre-ownerSource blocks that stored useSiteTitle.
 */
const deprecated: NonNullable<
  BlockConfiguration<CopyrightAttributes>['deprecated']
> = [
  {
    attributes: {
      useSiteTitle: {
        type: 'boolean',
        default: true,
      },
      ownerName: {
        type: 'string',
        default: '',
      },
      legalEntity: {
        type: 'string',
        default: '',
      },
      legalEntityCustom: {
        type: 'string',
        default: '',
      },
      showStartYear: {
        type: 'boolean',
        default: false,
      },
      startYear: {
        type: 'number',
        default: 2024,
      },
      separator: {
        type: 'string',
        default: '–',
      },
      prefix: {
        type: 'string',
        default: '©',
      },
      suffix: {
        type: 'string',
        default: '',
      },
      textAlign: {
        type: 'string',
        default: '',
      },
    },
    isEligible(attributes: LegacyCopyrightAttributes) {
      return (
        Object.prototype.hasOwnProperty.call(attributes, 'useSiteTitle') &&
        !Object.prototype.hasOwnProperty.call(attributes, 'ownerSource')
      );
    },
    migrate(attributes: LegacyCopyrightAttributes): CopyrightAttributes {
      const { useSiteTitle, ...rest } = attributes;
      return {
        ...rest,
        ownerSource: useSiteTitle === false ? 'custom' : 'site_title',
        showLegalLinks: false,
        legalLinksSeparator: '|',
        showSchema: true,
      };
    },
    save: () => null,
  },
];

registerThemeBlock<CopyrightAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
  variations,
  deprecated,
});
