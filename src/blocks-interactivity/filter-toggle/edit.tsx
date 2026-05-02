/**
 * Product Filter Toggle — Editor Preview
 *
 * Renders a non-interactive preview of the filter trigger button so the
 * user can see what they're placing. The real button is emitted by
 * render.php on the front end.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  InspectorControls,
  BlockControls,
  AlignmentToolbar,
} from '@wordpress/block-editor';
import {
  PanelBody,
  TextControl,
  ToggleControl,
  SelectControl,
  Notice,
} from '@wordpress/components';

import './editor.css';

interface FilterToggleAttributes {
  label: string;
  showLabel: boolean;
  showIcon: boolean;
  iconOnly: boolean;
  mobileOnly: 'auto' | 'always' | 'never';
  alignment: 'left' | 'center' | 'right';
}

interface EditProps {
  attributes: FilterToggleAttributes;
  setAttributes: (attrs: Partial<FilterToggleAttributes>) => void;
}

const FilterIcon = (): JSX.Element => (
  <svg
    width='20'
    height='20'
    viewBox='0 0 24 24'
    fill='none'
    stroke='currentColor'
    strokeWidth='2'
    strokeLinecap='round'
    strokeLinejoin='round'
    aria-hidden='true'
  >
    <polygon points='22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3' />
  </svg>
);

export default function Edit({
  attributes,
  setAttributes,
}: EditProps): JSX.Element {
  const { label, showLabel, showIcon, iconOnly, mobileOnly, alignment } =
    attributes;

  const effectiveShowIcon = iconOnly ? true : showIcon;
  const effectiveShowLabel = iconOnly ? false : showLabel;

  const blockProps = useBlockProps({
    className: `aa-product-filters__trigger${
      iconOnly ? ' aa-product-filters__trigger--icon-only' : ''
    }`,
    style: {
      display: 'inline-flex',
      justifyContent:
        alignment === 'center'
          ? 'center'
          : alignment === 'right'
            ? 'flex-end'
            : 'flex-start',
    },
  });

  return (
    <>
      <BlockControls>
        <AlignmentToolbar
          value={alignment}
          onChange={value =>
            setAttributes({
              alignment: (value as 'left' | 'center' | 'right') || 'left',
            })
          }
        />
      </BlockControls>

      <InspectorControls>
        <PanelBody title={__('Trigger Settings', 'aggressive-apparel')}>
          <ToggleControl
            label={__('Icon Only', 'aggressive-apparel')}
            checked={iconOnly}
            onChange={value => setAttributes({ iconOnly: value })}
            help={__(
              'Render a square, label-less icon button. The label text is still used as the accessible name.',
              'aggressive-apparel'
            )}
          />
          <TextControl
            label={__('Button Label', 'aggressive-apparel')}
            value={label}
            onChange={value => setAttributes({ label: value })}
            help={
              iconOnly
                ? __(
                    'Used as the screen-reader label only.',
                    'aggressive-apparel'
                  )
                : __('Text shown beside the icon.', 'aggressive-apparel')
            }
          />
          {!iconOnly && (
            <>
              <ToggleControl
                label={__('Show Label', 'aggressive-apparel')}
                checked={showLabel}
                onChange={value => setAttributes({ showLabel: value })}
              />
              <ToggleControl
                label={__('Show Icon', 'aggressive-apparel')}
                checked={showIcon}
                onChange={value => setAttributes({ showIcon: value })}
              />
            </>
          )}
          <SelectControl
            label={__('Mobile-Only Visibility', 'aggressive-apparel')}
            value={mobileOnly}
            onChange={value =>
              setAttributes({
                mobileOnly: (value as 'auto' | 'always' | 'never') || 'auto',
              })
            }
            options={[
              {
                label: __(
                  'Auto (hide on desktop when sidebar/bar is visible)',
                  'aggressive-apparel'
                ),
                value: 'auto',
              },
              {
                label: __('Always mobile-only', 'aggressive-apparel'),
                value: 'always',
              },
              {
                label: __('Always visible', 'aggressive-apparel'),
                value: 'never',
              },
            ]}
            help={__(
              '"Auto" mirrors the legacy automatic placement: the button only shows on small screens when the desktop layout is "Sidebar" or "Horizontal Bar".',
              'aggressive-apparel'
            )}
          />
        </PanelBody>

        <PanelBody
          title={__('Where this appears', 'aggressive-apparel')}
          initialOpen={false}
        >
          <Notice status='info' isDismissible={false}>
            {__(
              'This button only renders on shop, product category, and product tag archives. On other pages it is hidden automatically. The Product Filters feature must be enabled in Theme Settings.',
              'aggressive-apparel'
            )}
          </Notice>
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <button
          type='button'
          className={`aa-product-filters__trigger${
            iconOnly ? 'aa-product-filters__trigger--icon-only' : ''
          }`}
          onClick={e => e.preventDefault()}
          aria-haspopup='dialog'
          aria-expanded={false}
          aria-controls='aa-product-filters-drawer'
        >
          {effectiveShowIcon && <FilterIcon />}
          {effectiveShowLabel && label.trim() !== '' && (
            <span className='aa-product-filters__trigger-label'>{label}</span>
          )}
          {!effectiveShowLabel && label.trim() !== '' && (
            <span className='screen-reader-text'>{label}</span>
          )}
        </button>
      </div>
    </>
  );
}
