/**
 * Nav Submenu Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  BlockControls,
  InnerBlocks,
  InspectorControls,
  LinkControl,
  RichText,
  useBlockProps,
  useInnerBlocksProps,
  useSetting,
} from '@wordpress/block-editor';
import type { Color } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  BaseControl,
  ColorPalette,
  PanelBody,
  Popover,
  SelectControl,
  TextControl,
  ToggleControl,
  ToolbarButton,
  ToolbarGroup,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon } from '@wordpress/icons';
import type { MenuType, NavSubmenuAttributes, OpenTrigger } from './types';
import React from 'react';

const TEMPLATE: [string, Record<string, unknown>?][] = [
  ['aggressive-apparel/nav-link', { label: 'Submenu Item 1', url: '#' }],
  ['aggressive-apparel/nav-link', { label: 'Submenu Item 2', url: '#' }],
];

// Generate a unique submenu ID.
function generateSubmenuId(): string {
  return `submenu-${Math.random().toString(36).slice(2, 9)}`;
}

export default function Edit({
  attributes,
  setAttributes,
  isSelected,
}: BlockEditProps<NavSubmenuAttributes>) {
  const {
    label,
    url,
    menuType,
    openOn,
    submenuId,
    showArrow,
    panelBackgroundColor,
    panelTextColor,
  } = attributes;
  const [isLinkOpen, setIsLinkOpen] = useState(false);
  const linkButtonRef = useRef<HTMLButtonElement>(null);

  // Generate submenu ID if not set.
  useEffect(() => {
    if (!submenuId) {
      setAttributes({ submenuId: generateSubmenuId() });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- setAttributes is stable, only run when submenuId is missing
  }, [submenuId]);

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-nav-submenu wp-block-aggressive-apparel-nav-submenu--${menuType}`,
  });

  // Get theme color palette for color controls.
  const colorPalette = useSetting('color.palette') as Color[] | undefined;

  // Determine which blocks to show based on menu type.
  const getAllowedBlocks = () => {
    if (menuType === 'mega') {
      return ['aggressive-apparel/mega-menu-content'];
    }
    return ['aggressive-apparel/nav-link'];
  };

  const getTemplate = (): [string, Record<string, unknown>?][] => {
    if (menuType === 'mega') {
      return [['aggressive-apparel/mega-menu-content']];
    }
    return TEMPLATE;
  };

  // Use useInnerBlocksProps to properly associate inner blocks with this block.
  // Inner blocks are li elements, so the container must be a ul.
  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'wp-block-aggressive-apparel-nav-submenu__panel-inner',
      role: 'menu',
    },
    {
      allowedBlocks: getAllowedBlocks(),
      template: getTemplate(),
      templateLock: false,
      renderAppender: InnerBlocks.ButtonBlockAppender,
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
        <PanelBody title={__('Submenu Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Link URL', 'aggressive-apparel')}
            help={__(
              'Optional URL for the trigger link itself.',
              'aggressive-apparel'
            )}
            value={url}
            onChange={value => setAttributes({ url: value })}
          />
          <SelectControl
            label={__('Menu Type', 'aggressive-apparel')}
            value={menuType}
            options={[
              {
                label: __('Dropdown', 'aggressive-apparel'),
                value: 'dropdown',
              },
              { label: __('Mega Menu', 'aggressive-apparel'), value: 'mega' },
              {
                label: __('Drill-down', 'aggressive-apparel'),
                value: 'drilldown',
              },
            ]}
            onChange={value => setAttributes({ menuType: value as MenuType })}
          />
          <SelectControl
            label={__('Open On', 'aggressive-apparel')}
            help={__(
              'How the submenu opens on desktop. Mobile always uses click.',
              'aggressive-apparel'
            )}
            value={openOn}
            options={[
              { label: __('Hover', 'aggressive-apparel'), value: 'hover' },
              { label: __('Click', 'aggressive-apparel'), value: 'click' },
            ]}
            onChange={value => setAttributes({ openOn: value as OpenTrigger })}
          />
          <ToggleControl
            label={__('Show Arrow Icon', 'aggressive-apparel')}
            checked={showArrow}
            onChange={value => setAttributes({ showArrow: value })}
          />
        </PanelBody>
      </InspectorControls>
      <InspectorControls>
        <PanelBody
          title={__('Panel Colors', 'aggressive-apparel')}
          initialOpen={false}
        >
          <BaseControl
            id='panel-background-color'
            label={__('Background', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={panelBackgroundColor}
              onChange={(value: string | undefined) =>
                setAttributes({ panelBackgroundColor: value })
              }
            />
          </BaseControl>
          <BaseControl
            id='panel-text-color'
            label={__('Text', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={panelTextColor}
              onChange={(value: string | undefined) =>
                setAttributes({ panelTextColor: value })
              }
            />
          </BaseControl>
        </PanelBody>
      </InspectorControls>
      <li {...blockProps} role='none'>
        <div className='wp-block-aggressive-apparel-nav-submenu__trigger'>
          <div
            className='wp-block-aggressive-apparel-nav-submenu__link'
            role='menuitem'
            aria-haspopup='true'
            aria-expanded='false'
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
              <span
                className='wp-block-aggressive-apparel-nav-submenu__arrow'
                aria-hidden='true'
              >
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
                onChange={linkData => {
                  if (linkData && typeof linkData === 'object') {
                    setAttributes({
                      url: linkData.url ?? '',
                    });
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
          style={
            {
              backgroundColor: panelBackgroundColor,
              '--panel-link-color': panelTextColor,
            } as React.CSSProperties
          }
        >
          <ul {...innerBlocksProps} />
        </div>
      </li>
    </>
  );
}
