/**
 * Nav Link Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  InspectorControls,
  LinkControl,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  Popover,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { NavLinkAttributes } from './types';

export default function Edit({
  attributes,
  setAttributes,
  isSelected,
}: BlockEditProps<NavLinkAttributes>) {
  const { label, url, opensInNewTab, description, isCurrent } = attributes;
  const [isLinkOpen, setIsLinkOpen] = useState(false);

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-nav-link${isCurrent ? ' is-current' : ''}`,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Link Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('URL', 'aggressive-apparel')}
            value={url}
            onChange={value => setAttributes({ url: value })}
          />
          <ToggleControl
            label={__('Open in new tab', 'aggressive-apparel')}
            checked={opensInNewTab}
            onChange={value => setAttributes({ opensInNewTab: value })}
          />
          <TextControl
            label={__('Description', 'aggressive-apparel')}
            help={__(
              'Optional description shown below the link label.',
              'aggressive-apparel'
            )}
            value={description}
            onChange={value => setAttributes({ description: value })}
          />
          <ToggleControl
            label={__('Mark as current page', 'aggressive-apparel')}
            help={__(
              'Adds aria-current="page" for accessibility.',
              'aggressive-apparel'
            )}
            checked={isCurrent}
            onChange={value => setAttributes({ isCurrent: value })}
          />
        </PanelBody>
      </InspectorControls>
      <li {...blockProps} role='none'>
        <a
          className='wp-block-aggressive-apparel-nav-link__link'
          href={url || '#'}
          onClick={e => {
            e.preventDefault();
            setIsLinkOpen(true);
          }}
          role='menuitem'
          aria-current={isCurrent ? 'page' : undefined}
        >
          <RichText
            tagName='span'
            className='wp-block-aggressive-apparel-nav-link__label'
            value={label}
            onChange={value => setAttributes({ label: value })}
            placeholder={__('Add label…', 'aggressive-apparel')}
            allowedFormats={[]}
          />
          {description && (
            <span className='wp-block-aggressive-apparel-nav-link__description'>
              {description}
            </span>
          )}
        </a>
        {isSelected && isLinkOpen && (
          <Popover
            position='bottom center'
            onClose={() => setIsLinkOpen(false)}
            anchor={undefined}
            focusOnMount='firstElement'
          >
            <LinkControl
              value={{ url, opensInNewTab }}
              onChange={linkData => {
                if (linkData && typeof linkData === 'object') {
                  setAttributes({
                    url: linkData.url ?? '',
                    opensInNewTab: linkData.opensInNewTab ?? false,
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
      </li>
    </>
  );
}
