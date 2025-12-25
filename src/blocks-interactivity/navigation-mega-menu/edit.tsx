import {
  // @ts-ignore - Experimental API that may not be exported in current version
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown, // eslint-disable-line @wordpress/no-unsafe-wp-apis
  InnerBlocks,
  InspectorControls,
  MediaUpload,
  MediaUploadCheck,
  RichText,
  useBlockProps,
  // @ts-ignore - Experimental API that may not be exported in current version
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  BoxControl,
  Button,
  RangeControl,
  ToggleControl,
  __experimentalToolsPanel as ToolsPanel, // eslint-disable-line @wordpress/no-unsafe-wp-apis
  __experimentalToolsPanelItem as ToolsPanelItem, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import React from 'react';

import type { NavigationMegaMenuAttributes } from '../navigation/types';

/**
 * Allowed inner blocks for mega menu
 */
const ALLOWED_BLOCKS = [
  'core/columns',
  'core/column',
  'core/group',
  'core/heading',
  'core/paragraph',
  'core/image',
  'core/list',
  'aggressive-apparel/navigation-item',
];

/**
 * Default template for mega menu
 */
const TEMPLATE: any[] = [
  [
    'core/columns',
    { columns: 4 },
    [
      [
        'core/column',
        {},
        [
          ['core/heading', { level: 4, content: 'Category 1' }],
          ['aggressive-apparel/navigation-item', { label: 'Link 1', url: '#' }],
          ['aggressive-apparel/navigation-item', { label: 'Link 2', url: '#' }],
          ['aggressive-apparel/navigation-item', { label: 'Link 3', url: '#' }],
        ],
      ],
      [
        'core/column',
        {},
        [
          ['core/heading', { level: 4, content: 'Category 2' }],
          ['aggressive-apparel/navigation-item', { label: 'Link 1', url: '#' }],
          ['aggressive-apparel/navigation-item', { label: 'Link 2', url: '#' }],
        ],
      ],
      [
        'core/column',
        {},
        [
          ['core/heading', { level: 4, content: 'Category 3' }],
          ['aggressive-apparel/navigation-item', { label: 'Link 1', url: '#' }],
          ['aggressive-apparel/navigation-item', { label: 'Link 2', url: '#' }],
        ],
      ],
      ['core/column', {}, [['core/image', { sizeSlug: 'medium' }]]],
    ],
  ],
];

/**
 * Navigation mega menu edit component
 */
export default function Edit({
  attributes,
  setAttributes,
  clientId,
  isSelected,
}: BlockEditProps<NavigationMegaMenuAttributes>) {
  const {
    label,
    columns,
    showFeaturedImage,
    featuredImage,
    contentBackgroundColor,
    contentTextColor,
    contentPadding,
    contentMargin,
  } = attributes;

  const [isOpen, setIsOpen] = useState(false);
  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  // Check if this block or any of its children are selected
  const isBlockOrChildSelected = useSelect(
    select => {
      const { hasSelectedInnerBlock } = select('core/block-editor') as any;
      return isSelected || hasSelectedInnerBlock(clientId, true);
    },
    [clientId, isSelected]
  );

  // Keep mega menu open when block or children are selected
  useEffect(() => {
    if (isBlockOrChildSelected) {
      setIsOpen(true);
    }
  }, [isBlockOrChildSelected]);

  // Handle BoxControl values (padding/margin)
  const getBoxStyles = (value: any) => {
    if (!value) return {};
    const styles: any = {};
    if (value.top) styles.paddingTop = value.top;
    if (value.right) styles.paddingRight = value.right;
    if (value.bottom) styles.paddingBottom = value.bottom;
    if (value.left) styles.paddingLeft = value.left;
    return styles;
  };

  const blockProps = useBlockProps({
    className: 'aa-navigation-mega-menu',
  });

  const contentStyles = {
    backgroundColor: contentBackgroundColor || undefined,
    color: contentTextColor || undefined,
    ...(contentPadding ? getBoxStyles(contentPadding) : {}),
    ...(contentMargin
      ? {
          margin: `${contentMargin.top || 0} ${contentMargin.right || 0} ${contentMargin.bottom || 0} ${contentMargin.left || 0}`,
        }
      : {}),
  } as React.CSSProperties;

  return (
    <>
      <InspectorControls>
        <ToolsPanel
          label={__('Mega Menu Settings', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              columns: 4,
              showFeaturedImage: false,
              featuredImage: undefined,
            });
          }}
        >
          <ToolsPanelItem
            label={__('Columns', 'aggressive-apparel')}
            hasValue={() => columns !== 4}
            onDeselect={() => setAttributes({ columns: 4 })}
            isShownByDefault
          >
            <RangeControl
              label={__('Columns', 'aggressive-apparel')}
              value={columns}
              onChange={value => setAttributes({ columns: value })}
              min={1}
              max={6}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Featured Image', 'aggressive-apparel')}
            hasValue={() => showFeaturedImage}
            onDeselect={() =>
              setAttributes({
                showFeaturedImage: false,
                featuredImage: undefined,
              })
            }
            isShownByDefault
          >
            <ToggleControl
              label={__('Show Featured Image', 'aggressive-apparel')}
              checked={showFeaturedImage}
              onChange={value => setAttributes({ showFeaturedImage: value })}
            />
          </ToolsPanelItem>

          {showFeaturedImage && (
            <ToolsPanelItem
              label={__('Image Selection', 'aggressive-apparel')}
              hasValue={() => !!featuredImage}
              onDeselect={() => setAttributes({ featuredImage: undefined })}
              isShownByDefault
            >
              <MediaUploadCheck>
                <MediaUpload
                  onSelect={media =>
                    setAttributes({
                      featuredImage: {
                        id: media.id,
                        url: media.url,
                        alt: media.alt || '',
                      },
                    })
                  }
                  allowedTypes={['image']}
                  value={featuredImage?.id}
                  render={({ open }) => (
                    <div className='aa-media-upload-container'>
                      {featuredImage?.url ? (
                        <div className='aa-navigation-mega-menu__featured-preview'>
                          <img
                            src={featuredImage.url}
                            alt={featuredImage.alt || ''}
                            style={{
                              maxWidth: '100%',
                              height: 'auto',
                              marginBottom: '10px',
                            }}
                          />
                          <Button
                            variant='secondary'
                            isDestructive
                            onClick={() =>
                              setAttributes({ featuredImage: undefined })
                            }
                          >
                            {__('Remove Image', 'aggressive-apparel')}
                          </Button>
                        </div>
                      ) : (
                        <Button variant='secondary' onClick={open} isSmall>
                          {__('Select Image', 'aggressive-apparel')}
                        </Button>
                      )}
                    </div>
                  )}
                />
              </MediaUploadCheck>
            </ToolsPanelItem>
          )}
        </ToolsPanel>

        <ToolsPanel
          label={__('Content Styles', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              contentBackgroundColor: undefined,
              contentTextColor: undefined,
              contentPadding: undefined,
              contentMargin: undefined,
            });
          }}
        >
          <ToolsPanelItem
            label={__('Background Color', 'aggressive-apparel')}
            hasValue={() => !!contentBackgroundColor}
            onDeselect={() =>
              setAttributes({ contentBackgroundColor: undefined })
            }
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: contentBackgroundColor,
                  label: __('Background Color', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ contentBackgroundColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ contentBackgroundColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Text Color', 'aggressive-apparel')}
            hasValue={() => !!contentTextColor}
            onDeselect={() => setAttributes({ contentTextColor: undefined })}
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: contentTextColor,
                  label: __('Text Color', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ contentTextColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ contentTextColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Content Padding', 'aggressive-apparel')}
            hasValue={() => !!contentPadding}
            onDeselect={() => setAttributes({ contentPadding: undefined })}
          >
            <BoxControl
              label={__('Padding', 'aggressive-apparel')}
              values={contentPadding}
              onChange={value => setAttributes({ contentPadding: value })}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Content Margin', 'aggressive-apparel')}
            hasValue={() => !!contentMargin}
            onDeselect={() => setAttributes({ contentMargin: undefined })}
          >
            <BoxControl
              label={__('Margin', 'aggressive-apparel')}
              values={contentMargin}
              onChange={value => setAttributes({ contentMargin: value })}
            />
          </ToolsPanelItem>
        </ToolsPanel>
      </InspectorControls>

      <li {...blockProps} role='none'>
        {/* Trigger */}
        <button
          className='aa-navigation-mega-menu__trigger'
          type='button'
          aria-expanded={isOpen}
          onClick={() => setIsOpen(!isOpen)}
        >
          <RichText
            tagName='span'
            className='aa-navigation-mega-menu__label'
            value={label}
            onChange={value => setAttributes({ label: value })}
            placeholder={__('Mega menu label…', 'aggressive-apparel')}
            allowedFormats={[]}
          />
          <span className='aa-navigation-mega-menu__icon'>
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
        </button>

        {/* Mega Menu Content */}
        <div
          className={`aa-navigation-mega-menu__content ${isOpen ? 'is-open' : ''}`}
          style={
            {
              ...contentStyles,
              '--mega-columns': columns,
            } as React.CSSProperties
          }
        >
          <div className='aa-navigation-mega-menu__inner'>
            <InnerBlocks
              allowedBlocks={ALLOWED_BLOCKS}
              template={TEMPLATE as any}
              templateLock={false}
            />
          </div>
        </div>
      </li>
    </>
  );
}
