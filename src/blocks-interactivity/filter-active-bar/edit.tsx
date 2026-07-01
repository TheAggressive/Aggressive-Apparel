/**
 * Active Filter Bar — Editor Preview
 *
 * Renders a non-interactive preview of the active-filter bar (sample pills +
 * Clear All) so the user can see what they're placing. The real, store-driven
 * bar is emitted by render.php on the front end.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Notice } from '@wordpress/components';

export default function Edit(): JSX.Element {
  const blockProps = useBlockProps({
    className: 'aa-filter-active-bar',
  });

  const samplePills = [
    __('T-Shirts', 'aggressive-apparel'),
    __('Black', 'aggressive-apparel'),
    __('Mens', 'aggressive-apparel'),
  ];

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Where this appears', 'aggressive-apparel')}
          initialOpen={true}
        >
          <Notice status='info' isDismissible={false}>
            {__(
              'Desktop-only pills on shop, category, and tag archives (1024px+): up to three removable pills, a "+N" chip with tooltip for the rest, and underlined Clear All. Tablet (768–1023px) shows Clear All only. Hidden on mobile — use the filter drawer there instead.',
              'aggressive-apparel'
            )}
          </Notice>
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div className='aa-filter-active-bar__pills'>
          {samplePills.map(label => (
            <button
              key={label}
              type='button'
              className='aa-filter-active-bar__pill'
              onClick={e => e.preventDefault()}
            >
              {label}
              <span className='aa-filter-active-bar__pill-x' aria-hidden='true'>
                ×
              </span>
            </button>
          ))}
          <span
            className='aa-filter-active-bar__overflow'
            tabIndex={0}
            role='note'
            aria-label={__(
              'Additional filters: Large, In Stock Only',
              'aggressive-apparel'
            )}
            data-tooltip={__('Large, In Stock Only', 'aggressive-apparel')}
          >
            +2
          </span>
        </div>
        <button
          type='button'
          className='aa-filter-active-bar__clear-all'
          onClick={e => e.preventDefault()}
        >
          {__('Clear All', 'aggressive-apparel')}
        </button>
      </div>
    </>
  );
}
