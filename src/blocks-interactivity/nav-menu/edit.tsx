/**
 * Nav Menu Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { MenuOrientation, NavMenuAttributes } from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-link',
  'aggressive-apparel/nav-submenu',
];

const TEMPLATE: [string, Record<string, unknown>?][] = [
  ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
  ['aggressive-apparel/nav-link', { label: 'About', url: '/about' }],
  ['aggressive-apparel/nav-link', { label: 'Contact', url: '/contact' }],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<NavMenuAttributes>) {
  const { orientation } = attributes;

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-nav-menu wp-block-aggressive-apparel-nav-menu--${orientation}`,
  });

  // Use useInnerBlocksProps to render inner blocks directly inside the ul
  // without extra wrapper divs that break ul > li structure.
  const innerBlocksProps = useInnerBlocksProps(
    { ...blockProps, role: 'menubar' },
    {
      allowedBlocks: ALLOWED_BLOCKS,
      template: TEMPLATE,
      templateLock: false,
      orientation,
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Menu Settings', 'aggressive-apparel')}>
          <SelectControl
            label={__('Orientation', 'aggressive-apparel')}
            help={__(
              'Horizontal for desktop, vertical for mobile panels.',
              'aggressive-apparel'
            )}
            value={orientation}
            options={[
              {
                label: __('Horizontal', 'aggressive-apparel'),
                value: 'horizontal',
              },
              {
                label: __('Vertical', 'aggressive-apparel'),
                value: 'vertical',
              },
            ]}
            onChange={value =>
              setAttributes({ orientation: value as MenuOrientation })
            }
          />
        </PanelBody>
      </InspectorControls>
      <ul {...innerBlocksProps} />
    </>
  );
}
