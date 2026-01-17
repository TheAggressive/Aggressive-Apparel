/**
 * Navigation Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { NavigationAttributes } from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/menu-toggle',
  'aggressive-apparel/navigation-panel',
  'aggressive-apparel/nav-menu',
  'core/site-logo',
  'core/site-title',
  'core/search',
  'core/social-links',
  'core/buttons',
  'core/group',
];

const TEMPLATE: [string, Record<string, unknown>?][] = [
  ['aggressive-apparel/menu-toggle'],
  ['aggressive-apparel/nav-menu'],
  ['aggressive-apparel/navigation-panel'],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<NavigationAttributes>) {
  const { breakpoint, ariaLabel, openOn, navId } = attributes;

  // Ensure unique ID exists for context sharing.
  if (!navId) {
    // simplified ID generation for editor persistence
    const newId = `nav-${Math.random().toString(36).slice(2, 9)}`;
    setAttributes({ navId: newId });
  }

  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-navigation--editor',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Responsive Settings', 'aggressive-apparel')}>
          <RangeControl
            label={__('Mobile Breakpoint (px)', 'aggressive-apparel')}
            help={__(
              'Screen width below which mobile navigation is shown.',
              'aggressive-apparel'
            )}
            value={breakpoint}
            onChange={value => setAttributes({ breakpoint: value ?? 1024 })}
            min={320}
            max={1920}
            step={1}
          />
          <SelectControl
            label={__('Open Submenus On', 'aggressive-apparel')}
            help={__(
              'How submenus open on desktop. Mobile always uses click/tap.',
              'aggressive-apparel'
            )}
            value={openOn}
            options={[
              { label: __('Hover', 'aggressive-apparel'), value: 'hover' },
              { label: __('Click', 'aggressive-apparel'), value: 'click' },
            ]}
            onChange={value =>
              setAttributes({ openOn: value as 'hover' | 'click' })
            }
          />
        </PanelBody>
        <PanelBody
          title={__('Accessibility', 'aggressive-apparel')}
          initialOpen={false}
        >
          <TextControl
            label={__('Navigation Label', 'aggressive-apparel')}
            help={__(
              'Accessible label for screen readers.',
              'aggressive-apparel'
            )}
            value={ariaLabel}
            onChange={value => setAttributes({ ariaLabel: value })}
          />
        </PanelBody>
      </InspectorControls>
      <nav {...blockProps} aria-label={ariaLabel}>
        <InnerBlocks
          allowedBlocks={ALLOWED_BLOCKS}
          template={TEMPLATE}
          templateLock={false}
        />
      </nav>
    </>
  );
}
