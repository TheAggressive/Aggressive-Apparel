/**
 * Search Block — Editor Component.
 *
 * Renders a static preview of the trigger button. The full-screen modal is a
 * front-end-only concern (rendered in wp_footer by the PHP controller).
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
  PanelBody,
  ToggleControl,
  RangeControl,
  TextControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

interface SearchAttributes {
  label: string;
  showLabel: boolean;
  iconSize: number;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<SearchAttributes>) {
  const { label, showLabel, iconSize } = attributes;
  const blockProps = useBlockProps({ className: 'aa-search-trigger' });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Accessible label', 'aggressive-apparel')}
            help={__(
              'Used for the aria-label and optional visible text.',
              'aggressive-apparel'
            )}
            value={label}
            onChange={(value: string) => setAttributes({ label: value })}
          />
          <ToggleControl
            label={__('Show visible label', 'aggressive-apparel')}
            checked={showLabel}
            onChange={(value: boolean) => setAttributes({ showLabel: value })}
          />
          <RangeControl
            label={__('Icon size', 'aggressive-apparel')}
            value={iconSize}
            onChange={(value?: number) =>
              setAttributes({ iconSize: value ?? 24 })
            }
            min={16}
            max={48}
          />
        </PanelBody>
      </InspectorControls>

      <button type='button' {...blockProps} aria-label={label}>
        <svg
          xmlns='http://www.w3.org/2000/svg'
          width={iconSize}
          height={iconSize}
          viewBox='0 0 24 24'
          fill='currentColor'
          aria-hidden='true'
          focusable='false'
        >
          <path d='M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3.4 1.1 1.1 3.4-3.4c.9.6 1.9.9 3 .9 3 0 5.5-2.5 5.5-5.5S16.5 6 13.5 6zm0 9c-2 0-3.5-1.5-3.5-3.5S11.5 8 13.5 8 17 9.5 17 11.5 15.5 15 13.5 15z' />
        </svg>
        {showLabel && <span className='aa-search-trigger__label'>{label}</span>}
      </button>
    </>
  );
}
