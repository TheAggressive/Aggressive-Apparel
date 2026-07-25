/**
 * Size Guide block — editor preview.
 *
 * The front end resolves the current product's assigned guide on the server.
 * The editor intentionally previews only the trigger so the modal cannot trap
 * focus or interfere with template editing.
 *
 * @package Aggressive_Apparel
 */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  Notice,
  PanelBody,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { IconEditorPreview } from '../../utils/icon-editor-preview';

interface SizeGuideAttributes {
  label: string;
  showIcon: boolean;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<SizeGuideAttributes>) {
  const { label, showIcon } = attributes;
  const effectiveLabel = label.trim() || __('Size Guide', 'aggressive-apparel');
  const blockProps = useBlockProps({
    className: 'aggressive-apparel-size-guide__trigger',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Size Guide settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Button label', 'aggressive-apparel')}
            value={label}
            onChange={value => setAttributes({ label: value })}
            help={__(
              'An empty label falls back to “Size Guide.”',
              'aggressive-apparel'
            )}
          />
          <ToggleControl
            label={__('Show measuring-tape icon', 'aggressive-apparel')}
            checked={showIcon}
            onChange={value => setAttributes({ showIcon: value })}
          />
        </PanelBody>

        <PanelBody
          title={__('Product context', 'aggressive-apparel')}
          initialOpen={false}
        >
          <Notice status='info' isDismissible={false}>
            {__(
              'The front end renders the guide assigned to the current product, its category, or the global fallback. It renders nothing when the Size Guide feature is disabled or no guide is assigned.',
              'aggressive-apparel'
            )}
          </Notice>
        </PanelBody>
      </InspectorControls>

      <button
        {...blockProps}
        type='button'
        onClick={event => event.preventDefault()}
        aria-haspopup='dialog'
      >
        {showIcon && (
          <IconEditorPreview
            slug='measuring-tape'
            size={22}
            className='aggressive-apparel-size-guide__trigger-icon'
          />
        )}
        {effectiveLabel}
      </button>
    </>
  );
}
