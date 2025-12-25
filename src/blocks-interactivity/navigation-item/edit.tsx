/**
 * Navigation Item Block - Edit Component
 *
 * @package Aggressive Apparel
 */

import {
  InspectorControls,
  // @ts-ignore - Experimental API that may not be exported in current version
  __experimentalLinkControl as LinkControl, // eslint-disable-line @wordpress/no-unsafe-wp-apis
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  Button,
  Popover,
  TextControl,
  ToggleControl,
  __experimentalToolsPanel as ToolsPanel, // eslint-disable-line @wordpress/no-unsafe-wp-apis
  __experimentalToolsPanelItem as ToolsPanelItem, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon } from '@wordpress/icons';

import type { NavigationItemAttributes } from '../navigation/types';

/**
 * Navigation item edit component
 */
export default function Edit({
  attributes,
  setAttributes,
  isSelected,
}: BlockEditProps<NavigationItemAttributes>) {
  const { label, url, opensInNewTab, rel, title, description, isCurrentPage } =
    attributes;

  const [isLinkOpen, setIsLinkOpen] = useState(false);

  const blockProps = useBlockProps({
    className: 'aa-navigation-item',
  });

  return (
    <>
      <InspectorControls>
        <ToolsPanel
          label={__('Link Settings', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              url: '',
              opensInNewTab: false,
              rel: undefined,
              title: undefined,
              description: undefined,
              isCurrentPage: false,
            });
          }}
        >
          <ToolsPanelItem
            label={__('URL', 'aggressive-apparel')}
            hasValue={() => !!url}
            onDeselect={() => setAttributes({ url: '' })}
            isShownByDefault
          >
            <TextControl
              label={__('URL', 'aggressive-apparel')}
              value={url}
              onChange={value => setAttributes({ url: value })}
              placeholder='https://'
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Open in new tab', 'aggressive-apparel')}
            hasValue={() => opensInNewTab}
            onDeselect={() => setAttributes({ opensInNewTab: false })}
            isShownByDefault
          >
            <ToggleControl
              label={__('Open in new tab', 'aggressive-apparel')}
              checked={opensInNewTab}
              onChange={value => setAttributes({ opensInNewTab: value })}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Rel Attribute', 'aggressive-apparel')}
            hasValue={() => !!rel}
            onDeselect={() => setAttributes({ rel: undefined })}
          >
            <TextControl
              label={__('Link rel', 'aggressive-apparel')}
              value={rel || ''}
              onChange={value => setAttributes({ rel: value })}
              help={__('e.g., nofollow, noopener', 'aggressive-apparel')}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Title Attribute', 'aggressive-apparel')}
            hasValue={() => !!title}
            onDeselect={() => setAttributes({ title: undefined })}
          >
            <TextControl
              label={__('Title attribute', 'aggressive-apparel')}
              value={title || ''}
              onChange={value => setAttributes({ title: value })}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Description', 'aggressive-apparel')}
            hasValue={() => !!description}
            onDeselect={() => setAttributes({ description: undefined })}
          >
            <TextControl
              label={__('Description', 'aggressive-apparel')}
              value={description || ''}
              onChange={value => setAttributes({ description: value })}
              help={__('For mega menu layouts', 'aggressive-apparel')}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Active Status', 'aggressive-apparel')}
            hasValue={() => isCurrentPage}
            onDeselect={() => setAttributes({ isCurrentPage: false })}
          >
            <ToggleControl
              label={__('Mark as current page', 'aggressive-apparel')}
              checked={isCurrentPage}
              onChange={value => setAttributes({ isCurrentPage: value })}
              help={__('Applies active styles.', 'aggressive-apparel')}
            />
          </ToolsPanelItem>
        </ToolsPanel>
      </InspectorControls>

      <li {...blockProps} role='none'>
        <div className='aa-navigation-item__content'>
          <RichText
            tagName='span'
            className='aa-navigation-item__label'
            value={label}
            onChange={value => setAttributes({ label: value })}
            placeholder={__('Add label…', 'aggressive-apparel')}
            allowedFormats={[]}
          />

          {/* Link Button */}
          <Button
            icon={linkIcon}
            className='aa-navigation-item__link-button'
            onClick={() => setIsLinkOpen(true)}
            aria-label={__('Edit link', 'aggressive-apparel')}
            isPressed={!!url}
          />

          {/* Link Popover */}
          {isLinkOpen && (
            <Popover
              position='bottom center'
              onClose={() => setIsLinkOpen(false)}
              anchor={undefined}
              focusOnMount='firstElement'
            >
              <LinkControl
                value={{ url, opensInNewTab }}
                onChange={({
                  url: newUrl,
                  opensInNewTab: newTab,
                }: {
                  url?: string;
                  opensInNewTab?: boolean;
                }) => {
                  setAttributes({
                    url: newUrl || '',
                    opensInNewTab: newTab || false,
                  });
                }}
                onRemove={() => {
                  setAttributes({ url: '' });
                  setIsLinkOpen(false);
                }}
              />
            </Popover>
          )}
        </div>

        {/* URL indicator */}
        {url && !isSelected && (
          <span className='aa-navigation-item__url-indicator'>{url}</span>
        )}
      </li>
    </>
  );
}
