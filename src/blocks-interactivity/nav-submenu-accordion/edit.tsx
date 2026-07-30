/**
 * Nav Accordion Block Edit Component
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
  ToggleControl,
  ToolbarButton,
  ToolbarGroup,
} from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { link as linkIcon } from '@wordpress/icons';
import {
  SUBMENU_BLOCK_NAMES,
  useUniqueBlockId,
} from '../nav-shared/use-unique-block-id';
import type { NavAccordionAttributes } from './types';

const TEMPLATE: [string, Record<string, unknown>][] = [
  ['aggressive-apparel/nav-link', { label: 'Item 1', url: '#' }],
  ['aggressive-apparel/nav-link', { label: 'Item 2', url: '#' }],
];

export default function Edit({
  attributes,
  setAttributes,
  isSelected,
  clientId,
}: BlockEditProps<NavAccordionAttributes>) {
  const { label, url, submenuId, showArrow } = attributes;
  const [isLinkOpen, setIsLinkOpen] = useState(false);
  const linkButtonRef = useRef<HTMLButtonElement>(null);

  useUniqueBlockId({
    attributeName: 'submenuId',
    blockNames: SUBMENU_BLOCK_NAMES,
    clientId,
    currentId: submenuId,
    prefix: 'accordion',
    setId: id => setAttributes({ submenuId: id }),
  });

  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-nav-submenu-accordion',
  });

  const innerBlocksProps = useInnerBlocksProps(
    {
      className:
        'wp-block-aggressive-apparel-nav-submenu-accordion__panel-inner',
      role: 'menu',
    },
    {
      allowedBlocks: ['aggressive-apparel/nav-link'],
      template: TEMPLATE,
      templateLock: false,
    }
  );

  const chevronDown = (
    <svg
      xmlns='http://www.w3.org/2000/svg'
      viewBox='0 0 24 24'
      width='16'
      height='16'
      fill='currentColor'
      aria-hidden='true'
    >
      <path d='M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z' />
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
        <PanelBody title={__('Accordion Settings', 'aggressive-apparel')}>
          <ToggleControl
            label={__('Show arrow icon', 'aggressive-apparel')}
            checked={showArrow}
            onChange={value => setAttributes({ showArrow: value })}
          />
        </PanelBody>
      </InspectorControls>

      <li {...blockProps} role='none'>
        <div className='wp-block-aggressive-apparel-nav-submenu-accordion__trigger'>
          <div
            className='wp-block-aggressive-apparel-nav-submenu-accordion__link'
            role='menuitem'
            aria-expanded='false'
            aria-controls={submenuId}
          >
            <RichText
              tagName='span'
              className='wp-block-aggressive-apparel-nav-submenu-accordion__label'
              value={label}
              onChange={value => setAttributes({ label: value })}
              placeholder={__('Add label…', 'aggressive-apparel')}
              allowedFormats={[]}
            />
            {showArrow && (
              <span className='wp-block-aggressive-apparel-nav-submenu-accordion__arrow'>
                {chevronDown}
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
          className='wp-block-aggressive-apparel-nav-submenu-accordion__panel'
          id={submenuId}
          aria-label={label}
        >
          <ul {...innerBlocksProps}>
            {url && (
              <li role='none'>
                <a
                  className='wp-block-aggressive-apparel-nav-submenu-accordion__view-all'
                  href={url}
                  role='menuitem'
                  onClick={event => event.preventDefault()}
                >
                  {sprintf(
                    /* translators: %s: navigation submenu label. */
                    __('View all in %s', 'aggressive-apparel'),
                    label
                  )}
                </a>
              </li>
            )}
            {innerBlocksProps.children}
          </ul>
        </div>
      </li>
    </>
  );
}
