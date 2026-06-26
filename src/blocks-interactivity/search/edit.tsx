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
          <path d='M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z' />
        </svg>
        {showLabel && <span className='aa-search-trigger__label'>{label}</span>}
      </button>
    </>
  );
}
