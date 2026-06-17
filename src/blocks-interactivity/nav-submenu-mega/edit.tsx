/**
 * Nav Mega Menu Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  BlockControls,
  InspectorControls,
  LinkControl,
  RichText,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  Popover,
  RangeControl,
  SelectControl,
  ToggleControl,
  ToolbarButton,
  ToolbarGroup,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon } from '@wordpress/icons';
import type { NavMegaAttributes, NavMegaContext, OpenTrigger } from './types';
import type React from 'react';

const MEGA_ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-link',
  'core/columns',
  'core/column',
  'core/group',
  'core/heading',
  'core/paragraph',
  'core/image',
  'core/buttons',
  'core/separator',
];

const TEMPLATE: [string, Record<string, unknown>][] = [
  ['aggressive-apparel/nav-link', { label: 'Column Item 1', url: '#' }],
  ['aggressive-apparel/nav-link', { label: 'Column Item 2', url: '#' }],
  ['aggressive-apparel/nav-link', { label: 'Column Item 3', url: '#' }],
  ['aggressive-apparel/nav-link', { label: 'Column Item 4', url: '#' }],
];

function generateId(): string {
  return `mega-${Math.random().toString(36).slice(2, 9)}`;
}

export default function Edit({
  attributes,
  setAttributes,
  isSelected,
  context,
}: BlockEditProps<NavMegaAttributes> & { context: NavMegaContext }) {
  const { label, url, submenuId, showArrow, openOn, columns } = attributes;
  const [isLinkOpen, setIsLinkOpen] = useState(false);
  const linkButtonRef = useRef<HTMLButtonElement>(null);

  // Get panel styling from parent navigation context.
  const panelBackgroundColor = context['aggressive-apparel/submenuBackgroundColor'];
  const panelTextColor = context['aggressive-apparel/submenuTextColor'];
  const panelBorderWidth = context['aggressive-apparel/submenuBorderWidth'];
  const panelBorderColor = context['aggressive-apparel/submenuBorderColor'];
  const panelBorderStyle = context['aggressive-apparel/submenuBorderStyle'];

  useEffect(() => {
    if (!submenuId) {
      setAttributes({ submenuId: generateId() });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [submenuId]);

  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-nav-submenu-mega',
    style: { '--mega-columns': columns } as React.CSSProperties,
  });

  // Build panel styles from context.
  const panelStyle: Record<string, string | number> = {
    '--mega-columns': columns,
  };
  if (panelBackgroundColor) panelStyle.backgroundColor = panelBackgroundColor;
  if (panelTextColor) panelStyle.color = panelTextColor;
  if (panelBorderWidth) panelStyle['--panel-border-width'] = panelBorderWidth;
  if (panelBorderColor) panelStyle['--panel-border-color'] = panelBorderColor;
  if (panelBorderStyle && panelBorderStyle !== 'solid') {
    panelStyle['--panel-border-style'] = panelBorderStyle;
  }

  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'wp-block-aggressive-apparel-nav-submenu__panel-inner',
    },
    {
      allowedBlocks: MEGA_ALLOWED_BLOCKS,
      template: TEMPLATE,
      templateLock: false,
    }
  );

  return (
    <>
      <BlockControls>
        <ToolbarGroup>
          <ToolbarButton
            ref={linkButtonRef}
            icon={linkIcon}
            title={__('Edit link', 'aggressive-apparel')}
            onClick={() => setIsLinkOpen(prev => !prev)}
            isPressed={isLinkOpen}
          />
        </ToolbarGroup>
      </BlockControls>

      <InspectorControls>
        <PanelBody title={__('Mega Menu Settings', 'aggressive-apparel')}>
          <RangeControl
            label={__('Columns', 'aggressive-apparel')}
            value={columns}
            onChange={value => setAttributes({ columns: value ?? 4 })}
            min={1}
            max={6}
          />
          <SelectControl
            label={__('Open on', 'aggressive-apparel')}
            help={__('How the mega menu opens. Hover also responds to focus-in.', 'aggressive-apparel')}
            value={openOn}
            options={[
              { label: __('Hover', 'aggressive-apparel'), value: 'hover' },
              { label: __('Click', 'aggressive-apparel'), value: 'click' },
            ]}
            onChange={value => setAttributes({ openOn: value as OpenTrigger })}
          />
          <ToggleControl
            label={__('Show arrow icon', 'aggressive-apparel')}
            checked={showArrow}
            onChange={value => setAttributes({ showArrow: value })}
          />
        </PanelBody>
      </InspectorControls>

      <li {...blockProps} role='none'>
        <div className='wp-block-aggressive-apparel-nav-submenu__trigger'>
          <div
            className='wp-block-aggressive-apparel-nav-submenu__link'
            role='menuitem'
            aria-haspopup='menu'
            aria-expanded='false'
            aria-controls={submenuId}
          >
            <RichText
              tagName='span'
              className='wp-block-aggressive-apparel-nav-submenu__label'
              value={label}
              onChange={value => setAttributes({ label: value })}
              placeholder={__('Add label…', 'aggressive-apparel')}
              allowedFormats={[]}
            />
            {showArrow && (
              <span className='wp-block-aggressive-apparel-nav-submenu__arrow' aria-hidden='true'>
                <svg
                  xmlns='http://www.w3.org/2000/svg'
                  viewBox='0 0 24 24'
                  width='16'
                  height='16'
                  fill='currentColor'
                >
                  <path d='M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z' />
                </svg>
              </span>
            )}
          </div>

          {isSelected && isLinkOpen && (
            <Popover
              placement='bottom'
              onClose={() => setIsLinkOpen(false)}
              anchor={linkButtonRef.current}
              focusOnMount='firstElement'
            >
              <LinkControl
                value={{ url }}
                onChange={data => {
                  if (data && typeof data === 'object') {
                    setAttributes({ url: data.url ?? '' });
                  }
                }}
                onRemove={() => {
                  setAttributes({ url: '' });
                  setIsLinkOpen(false);
                }}
              />
            </Popover>
          )}
        </div>

        <div
          className='wp-block-aggressive-apparel-nav-submenu__panel'
          aria-label={label}
          style={panelStyle as React.CSSProperties}
        >
          <div className='wp-block-aggressive-apparel-nav-submenu__panel-content'>
            <ul {...innerBlocksProps} />
          </div>
        </div>
      </li>
    </>
  );
}
