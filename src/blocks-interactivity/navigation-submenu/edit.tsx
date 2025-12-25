/**
 * Navigation Submenu Block - Edit Component
 *
 * @package Aggressive Apparel
 */
import {
  // @ts-ignore - Experimental API that may not be exported in current version
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown, // eslint-disable-line @wordpress/no-unsafe-wp-apis
  InnerBlocks,
  InspectorControls,
  LinkControl,
  RichText,
  useBlockProps,
  // @ts-ignore - Experimental API that may not be exported in current version
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  BoxControl,
  Button,
  PanelBody,
  Popover,
  SelectControl,
  TextControl,
  __experimentalToolsPanel as ToolsPanel, // eslint-disable-line @wordpress/no-unsafe-wp-apis
  __experimentalToolsPanelItem as ToolsPanelItem, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon } from '@wordpress/icons';

import type { NavigationSubmenuAttributes } from '../navigation/types';

/**
 * Allowed inner blocks for submenu
 */
const ALLOWED_BLOCKS = [
  'aggressive-apparel/navigation-item',
  'aggressive-apparel/navigation-submenu',
];

/**
 * Navigation submenu edit component
 */
export default function Edit({
  attributes,
  setAttributes,
  clientId,
  isSelected,
}: BlockEditProps<NavigationSubmenuAttributes>) {
  const {
    label,
    url,
    alignment,
    width,
    listBackgroundColor,
    listTextColor,
    listPadding,
    listMargin,
  } = attributes;

  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  const [isLinkOpen, setIsLinkOpen] = useState(false);
  const [isSubmenuOpen, setIsSubmenuOpen] = useState(false);
  const submenuRef = useRef<HTMLDivElement>(null);

  // Check if this block or any of its children are selected
  const isBlockOrChildSelected = useSelect(
    select => {
      const { hasSelectedInnerBlock } = select('core/block-editor') as any;
      return isSelected || hasSelectedInnerBlock(clientId, true);
    },
    [clientId, isSelected]
  );

  // Keep submenu open when block or children are selected
  useEffect(() => {
    if (isBlockOrChildSelected) {
      setIsSubmenuOpen(true);
    }
  }, [isBlockOrChildSelected]);

  const blockProps = useBlockProps({
    className: `aa-navigation-submenu aa-navigation-submenu--align-${alignment}`,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Submenu Settings', 'aggressive-apparel')}
          initialOpen={true}
        >
          <TextControl
            label={__('Parent URL (optional)', 'aggressive-apparel')}
            value={url || ''}
            onChange={value => setAttributes({ url: value })}
            help={__(
              'Optional URL for the parent item. If set, the link is clickable.',
              'aggressive-apparel'
            )}
          />

          <SelectControl
            label={__('Dropdown Alignment', 'aggressive-apparel')}
            value={alignment}
            options={[
              { label: __('Left', 'aggressive-apparel'), value: 'left' },
              { label: __('Center', 'aggressive-apparel'), value: 'center' },
              { label: __('Right', 'aggressive-apparel'), value: 'right' },
            ]}
            onChange={value =>
              setAttributes({ alignment: value as typeof alignment })
            }
          />

          <SelectControl
            label={__('Dropdown Width', 'aggressive-apparel')}
            value={width as 'auto' | 'full' | '200px' | '250px' | '300px'}
            options={[
              { label: __('Auto', 'aggressive-apparel'), value: 'auto' },
              { label: __('Full Width', 'aggressive-apparel'), value: 'full' },
              { label: __('200px', 'aggressive-apparel'), value: '200px' },
              { label: __('250px', 'aggressive-apparel'), value: '250px' },
              { label: __('300px', 'aggressive-apparel'), value: '300px' },
            ]}
            onChange={value => setAttributes({ width: value })}
          />
        </PanelBody>

        <ToolsPanel
          label={__('List Styles', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              listBackgroundColor: undefined,
              listTextColor: undefined,
              listPadding: undefined,
              listMargin: undefined,
            });
          }}
        >
          <ToolsPanelItem
            label={__('List Background', 'aggressive-apparel')}
            hasValue={() => !!listBackgroundColor}
            onDeselect={() => setAttributes({ listBackgroundColor: undefined })}
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: listBackgroundColor,
                  label: __('List Background', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ listBackgroundColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ listBackgroundColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('List Text Color', 'aggressive-apparel')}
            hasValue={() => !!listTextColor}
            onDeselect={() => setAttributes({ listTextColor: undefined })}
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: listTextColor,
                  label: __('List Text Color', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ listTextColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ listTextColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('List Padding', 'aggressive-apparel')}
            hasValue={() => !!listPadding}
            onDeselect={() => setAttributes({ listPadding: undefined })}
          >
            <BoxControl
              label={__('List Padding', 'aggressive-apparel')}
              values={listPadding}
              onChange={(value: any) => setAttributes({ listPadding: value })}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('List Margin', 'aggressive-apparel')}
            hasValue={() => !!listMargin}
            onDeselect={() => setAttributes({ listMargin: undefined })}
          >
            <BoxControl
              label={__('List Margin', 'aggressive-apparel')}
              values={listMargin}
              onChange={(value: any) => setAttributes({ listMargin: value })}
            />
          </ToolsPanelItem>
        </ToolsPanel>
      </InspectorControls>

      <li {...blockProps} role='none'>
        <div className='aa-navigation-submenu__trigger-wrapper'>
          {/* Trigger Button/Link */}
          <button
            className='aa-navigation-submenu__trigger'
            type='button'
            aria-expanded={isSubmenuOpen}
            onClick={() => setIsSubmenuOpen(!isSubmenuOpen)}
          >
            <RichText
              tagName='span'
              className='aa-navigation-submenu__label'
              value={label}
              onChange={value => setAttributes({ label: value })}
              placeholder={__('Submenu label…', 'aggressive-apparel')}
              allowedFormats={[]}
            />
            <span className='aa-navigation-submenu__icon'>
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

          {/* Optional Link Button */}
          <Button
            icon={linkIcon}
            className='aa-navigation-submenu__link-button'
            onClick={() => setIsLinkOpen(true)}
            aria-label={__('Edit parent link', 'aggressive-apparel')}
            isPressed={!!url}
          />

          {/* Link Popover */}
          {isLinkOpen && (
            <Popover
              position='bottom center'
              onClose={() => setIsLinkOpen(false)}
              focusOnMount='firstElement'
            >
              <LinkControl
                value={{ url: url || '' }}
                onChange={value => {
                  setAttributes({ url: value?.url || '' });
                }}
                onRemove={() => {
                  setAttributes({ url: '' });
                  setIsLinkOpen(false);
                }}
              />
            </Popover>
          )}
        </div>

        {/* Submenu Content */}
        <div
          ref={submenuRef}
          className={`aa-navigation-submenu__content ${
            isSubmenuOpen ? 'is-open' : ''
          }`}
          style={{ width: width === 'full' ? '100%' : width }}
        >
          <ul
            className='aa-navigation-submenu__items'
            role='menu'
            style={{
              backgroundColor: listBackgroundColor,
              color: listTextColor,
              paddingTop: listPadding?.top,
              paddingRight: listPadding?.right,
              paddingBottom: listPadding?.bottom,
              paddingLeft: listPadding?.left,
              marginTop: listMargin?.top,
              marginRight: listMargin?.right,
              marginBottom: listMargin?.bottom,
              marginLeft: listMargin?.left,
            }}
          >
            <InnerBlocks
              allowedBlocks={ALLOWED_BLOCKS}
              orientation='vertical'
              templateLock={false}
              renderAppender={InnerBlocks.ButtonBlockAppender}
            />
          </ul>
        </div>
      </li>
    </>
  );
}
