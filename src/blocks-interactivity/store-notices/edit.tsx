/**
 * Store Notices (Toasts) Block — Editor Component.
 *
 * The live toast stack is fixed-positioned and populated from the WooCommerce
 * session at render time, so the editor shows a static in-flow preview of the
 * toast styling plus the configuration controls.
 *
 * @package Aggressive_Apparel
 * @since 1.173.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
  PanelBody,
  SelectControl,
  ToggleControl,
  TextControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

interface StoreNoticesAttributes {
  position: string;
  maxVisible: number;
  successDuration: number;
  noticeDuration: number;
  errorDuration: number;
  captureBlockNotices: boolean;
  badgePosition: string;
}

const BADGE_POSITION_OPTIONS = [
  { label: __('Bottom right', 'aggressive-apparel'), value: 'bottom-right' },
  { label: __('Top right', 'aggressive-apparel'), value: 'top-right' },
];

const POSITION_OPTIONS = [
  { label: __('Top right', 'aggressive-apparel'), value: 'top-right' },
  { label: __('Top center', 'aggressive-apparel'), value: 'top-center' },
  { label: __('Top left', 'aggressive-apparel'), value: 'top-left' },
  { label: __('Bottom right', 'aggressive-apparel'), value: 'bottom-right' },
  { label: __('Bottom center', 'aggressive-apparel'), value: 'bottom-center' },
  { label: __('Bottom left', 'aggressive-apparel'), value: 'bottom-left' },
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<StoreNoticesAttributes>) {
  const {
    position,
    maxVisible,
    successDuration,
    noticeDuration,
    errorDuration,
    captureBlockNotices,
    badgePosition,
  } = attributes;

  const blockProps = useBlockProps({ className: 'aa-notices-editor-preview' });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Layout', 'aggressive-apparel')}>
          <SelectControl
            label={__('Position', 'aggressive-apparel')}
            value={position}
            options={POSITION_OPTIONS}
            onChange={(value: string) => setAttributes({ position: value })}
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Maximum visible toasts', 'aggressive-apparel')}
            type='number'
            min={1}
            max={10}
            value={String(maxVisible)}
            onChange={(value: string) =>
              setAttributes({
                maxVisible: Math.max(1, parseInt(value, 10) || 4),
              })
            }
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('Checkmark badge position', 'aggressive-apparel')}
            help={__(
              'Where the success checkmark sits on the add-to-cart product image.',
              'aggressive-apparel'
            )}
            value={badgePosition}
            options={BADGE_POSITION_OPTIONS}
            onChange={(value: string) =>
              setAttributes({ badgePosition: value })
            }
            __nextHasNoMarginBottom
          />
        </PanelBody>
        <PanelBody title={__('Auto-dismiss', 'aggressive-apparel')}>
          <p style={{ marginTop: 0, fontSize: '12px', opacity: 0.75 }}>
            {__(
              'Time in milliseconds before a toast auto-dismisses. Set to 0 to keep it until manually closed.',
              'aggressive-apparel'
            )}
          </p>
          <TextControl
            label={__('Success duration (ms)', 'aggressive-apparel')}
            type='number'
            min={0}
            step={500}
            value={String(successDuration)}
            onChange={(value: string) =>
              setAttributes({
                successDuration: Math.max(0, parseInt(value, 10) || 0),
              })
            }
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Info / notice duration (ms)', 'aggressive-apparel')}
            type='number'
            min={0}
            step={500}
            value={String(noticeDuration)}
            onChange={(value: string) =>
              setAttributes({
                noticeDuration: Math.max(0, parseInt(value, 10) || 0),
              })
            }
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Error duration (ms, 0 = sticky)', 'aggressive-apparel')}
            type='number'
            min={0}
            step={500}
            value={String(errorDuration)}
            onChange={(value: string) =>
              setAttributes({
                errorDuration: Math.max(0, parseInt(value, 10) || 0),
              })
            }
            __nextHasNoMarginBottom
          />
        </PanelBody>
        <PanelBody title={__('Coverage', 'aggressive-apparel')}>
          <ToggleControl
            label={__('Capture cart & checkout notices', 'aggressive-apparel')}
            help={__(
              'Also mirror WooCommerce block cart/checkout and classic notices into these toasts, hiding the originals.',
              'aggressive-apparel'
            )}
            checked={captureBlockNotices}
            onChange={(value: boolean) =>
              setAttributes({ captureBlockNotices: value })
            }
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div className={`aa-notices aa-notices--badge-${badgePosition}`}>
          <div className='aa-notices__toast aa-notices__toast--success'>
            <span className='aa-notices__media' aria-hidden='true'>
              <span className='aa-notices__thumb' />
              <span className='aa-notices__badge' />
            </span>
            <p className='aa-notices__message'>
              {__('Product added to your cart.', 'aggressive-apparel')}
            </p>
            <button
              type='button'
              className='aa-notices__close'
              aria-hidden='true'
              tabIndex={-1}
            >
              &times;
            </button>
          </div>
          <div className='aa-notices__toast aa-notices__toast--error'>
            <span className='aa-notices__icon' aria-hidden='true' />
            <p className='aa-notices__message'>
              {__('That coupon code is invalid.', 'aggressive-apparel')}
            </p>
            <button
              type='button'
              className='aa-notices__close'
              aria-hidden='true'
              tabIndex={-1}
            >
              &times;
            </button>
          </div>
        </div>
        <p className='aa-notices-editor-preview__hint'>
          {__(
            'Store notices appear as toasts on the front end. This is a static preview.',
            'aggressive-apparel'
          )}
        </p>
      </div>
    </>
  );
}
