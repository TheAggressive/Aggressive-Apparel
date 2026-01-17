import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps, Template } from '@wordpress/blocks';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { MenuGroupAttributes } from './types';

const ALLOWED = [
  'core/heading',
  'core/paragraph',
  'core/list',
  'core/list-item',
  'core/buttons',
  'core/button',
  'core/navigation-link',
  'core/social-links',
  'core/image',
  'core/group',
];

const TEMPLATE: Template[] = [
  [
    'core/heading',
    { level: 4, content: __('Group Title', 'aggressive-apparel') },
  ],
  [
    'core/list',
    {},
    [
      ['core/list-item', { content: __('Menu Item 1', 'aggressive-apparel') }],
      ['core/list-item', { content: __('Menu Item 2', 'aggressive-apparel') }],
      ['core/list-item', { content: __('Menu Item 3', 'aggressive-apparel') }],
    ],
  ],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<MenuGroupAttributes>) {
  const { title, layout, columns, gap, showTitle } = attributes;
  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-menu-group wp-block-aggressive-apparel-menu-group--${layout}`,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Group Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Title', 'aggressive-apparel')}
            value={title ?? ''}
            onChange={v => setAttributes({ title: v })}
            help={__(
              'Optional title for this menu group',
              'aggressive-apparel'
            )}
          />
          <ToggleControl
            label={__('Show Title', 'aggressive-apparel')}
            checked={showTitle}
            onChange={v => setAttributes({ showTitle: v })}
          />
        </PanelBody>
        <PanelBody title={__('Layout', 'aggressive-apparel')}>
          <SelectControl
            label={__('Layout', 'aggressive-apparel')}
            value={layout}
            options={[
              { label: 'Vertical', value: 'vertical' },
              { label: 'Horizontal', value: 'horizontal' },
              { label: 'Grid', value: 'grid' },
            ]}
            onChange={v => setAttributes({ layout: v as typeof layout })}
          />
          {layout === 'grid' && (
            <RangeControl
              label={__('Columns', 'aggressive-apparel')}
              value={columns}
              onChange={v => setAttributes({ columns: v ?? 2 })}
              min={1}
              max={6}
            />
          )}
          <TextControl
            label={__('Gap', 'aggressive-apparel')}
            value={gap ?? ''}
            onChange={v => setAttributes({ gap: v })}
            help={__(
              'Space between items (e.g., 1rem, 10px)',
              'aggressive-apparel'
            )}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        {showTitle && title && (
          <h4 className='wp-block-aggressive-apparel-menu-group__title'>
            {title}
          </h4>
        )}
        <div className='wp-block-aggressive-apparel-menu-group__content'>
          <InnerBlocks
            allowedBlocks={ALLOWED}
            template={TEMPLATE}
            templateLock={false}
          />
        </div>
      </div>
    </>
  );
}
