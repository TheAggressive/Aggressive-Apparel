/**
 * Nav Drilldown Block Edit Component
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
  SelectControl,
  ToggleControl,
  ToolbarButton,
  ToolbarGroup,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon } from '@wordpress/icons';
import type { AnimationStyle, NavDrilldownAttributes } from './types';

const TEMPLATE: [string, Record<string, unknown>][] = [
  ['aggressive-apparel/nav-link', { label: 'Item 1', url: '#' }],
  ['aggressive-apparel/nav-link', { label: 'Item 2', url: '#' }],
];

function generateId(): string {
  return `drilldown-${Math.random().toString(36).slice(2, 9)}`;
}

export default function Edit({
  attributes,
  setAttributes,
  isSelected,
}: BlockEditProps<NavDrilldownAttributes>) {
  const { label, url, submenuId, showArrow, animationStyle } = attributes;
  const [isLinkOpen, setIsLinkOpen] = useState(false);
  const linkButtonRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!submenuId) {
      setAttributes({ submenuId: generateId() });
    }
  }, [setAttributes, submenuId]);

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-nav-submenu-drilldown wp-block-aggressive-apparel-nav-submenu-drilldown--${animationStyle}`,
  });

  const innerBlocksProps = useInnerBlocksProps(
    {
      className:
        'wp-block-aggressive-apparel-nav-submenu-drilldown__panel-inner',
      role: 'menu',
    },
    {
      allowedBlocks: [
        'aggressive-apparel/nav-link',
        'aggressive-apparel/nav-submenu-drilldown',
      ],
      template: TEMPLATE,
      templateLock: false,
    }
  );

  const chevronRight = (
    <svg
      xmlns='http://www.w3.org/2000/svg'
      viewBox='0 0 24 24'
      width='16'
      height='16'
      fill='currentColor'
      aria-hidden='true'
    >
      <path d='M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z' />
    </svg>
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
        <PanelBody title={__('Drilldown Settings', 'aggressive-apparel')}>
          <SelectControl
            label={__('Animation style', 'aggressive-apparel')}
            value={animationStyle}
            options={[
              {
                label: __('Overlay (slide over)', 'aggressive-apparel'),
                value: 'overlay',
              },
              {
                label: __('Push (slide push)', 'aggressive-apparel'),
                value: 'push',
              },
            ]}
            onChange={value =>
              setAttributes({ animationStyle: value as AnimationStyle })
            }
          />
          <ToggleControl
            label={__('Show arrow icon', 'aggressive-apparel')}
            checked={showArrow}
            onChange={value => setAttributes({ showArrow: value })}
          />
        </PanelBody>
      </InspectorControls>

      <li {...blockProps} role='none'>
        <div className='wp-block-aggressive-apparel-nav-submenu-drilldown__trigger'>
          <div
            className='wp-block-aggressive-apparel-nav-submenu-drilldown__link'
            role='menuitem'
            aria-haspopup='menu'
            aria-expanded='false'
            aria-controls={submenuId}
          >
            <RichText
              tagName='span'
              className='wp-block-aggressive-apparel-nav-submenu-drilldown__label'
              value={label}
              onChange={value => setAttributes({ label: value })}
              placeholder={__('Add label…', 'aggressive-apparel')}
              allowedFormats={[]}
            />
            {showArrow && (
              <span className='wp-block-aggressive-apparel-nav-submenu-drilldown__arrow'>
                {chevronRight}
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

        {/* Panel shown expanded in editor — no slide animation */}
        <div
          className='wp-block-aggressive-apparel-nav-submenu-drilldown__panel'
          id={submenuId}
          role='region'
          aria-label={label}
        >
          {/* Back button hidden in editor */}
          <ul {...innerBlocksProps} />
        </div>
      </li>
    </>
  );
}
