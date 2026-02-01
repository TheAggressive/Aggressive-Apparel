/**
 * Copyright Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  AlignmentControl,
  BlockControls,
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface CopyrightAttributes {
  ownerName: string;
  showStartYear: boolean;
  startYear: string;
  separator: string;
  prefix: string;
  suffix: string;
  textAlign: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<CopyrightAttributes>) {
  const {
    ownerName,
    showStartYear,
    startYear,
    separator,
    prefix,
    suffix,
    textAlign,
  } = attributes;
  const currentYear = new Date().getFullYear().toString();

  const yearDisplay =
    showStartYear && startYear !== currentYear
      ? `${startYear}${separator}${currentYear}`
      : currentYear;

  const copyrightText = `${prefix} ${yearDisplay} ${ownerName}`;

  const blockProps = useBlockProps({
    style: { textAlign: textAlign || undefined },
  });

  return (
    <>
      <BlockControls>
        <AlignmentControl
          value={textAlign}
          onChange={value => setAttributes({ textAlign: value ?? '' })}
        />
      </BlockControls>
      <InspectorControls>
        <PanelBody title={__('Copyright Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Owner Name', 'aggressive-apparel')}
            value={ownerName}
            onChange={value => setAttributes({ ownerName: value })}
          />
          <TextControl
            label={__('Prefix', 'aggressive-apparel')}
            help={__('Symbol or text before the year.', 'aggressive-apparel')}
            value={prefix}
            onChange={value => setAttributes({ prefix: value })}
          />
          <ToggleControl
            label={__('Show Start Year', 'aggressive-apparel')}
            help={__(
              'Display a year range (e.g. 2012–2026) instead of just the current year.',
              'aggressive-apparel'
            )}
            checked={showStartYear}
            onChange={value => setAttributes({ showStartYear: value })}
          />
          {showStartYear && (
            <>
              <TextControl
                label={__('Start Year', 'aggressive-apparel')}
                value={startYear}
                onChange={value => setAttributes({ startYear: value })}
              />
              <SelectControl
                label={__('Year Separator', 'aggressive-apparel')}
                value={separator}
                options={[
                  { label: '– (en dash)', value: '–' },
                  { label: '- (hyphen)', value: '-' },
                  { label: '/ (slash)', value: '/' },
                ]}
                onChange={value => setAttributes({ separator: value })}
              />
            </>
          )}
        </PanelBody>
      </InspectorControls>
      <p {...blockProps}>
        <span className='wp-block-aggressive-apparel-copyright__text'>
          {copyrightText}
        </span>
        <RichText
          tagName='span'
          className='wp-block-aggressive-apparel-copyright__suffix'
          value={suffix}
          onChange={value => setAttributes({ suffix: value })}
          placeholder={__('. All rights reserved.', 'aggressive-apparel')}
          allowedFormats={['core/bold', 'core/italic', 'core/link']}
        />
      </p>
    </>
  );
}
