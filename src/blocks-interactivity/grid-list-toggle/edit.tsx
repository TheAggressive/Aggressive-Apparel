/**
 * Grid / List Toggle Block — Editor Preview
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, Notice } from '@wordpress/components';

import './editor.css';

interface GridListToggleAttributes {
  showLabels: boolean;
}

interface EditProps {
  attributes: GridListToggleAttributes;
  setAttributes: (attrs: Partial<GridListToggleAttributes>) => void;
}

const GridIcon = (): JSX.Element => (
  <svg
    xmlns='http://www.w3.org/2000/svg'
    viewBox='0 0 24 24'
    width='18'
    height='18'
    fill='currentColor'
    aria-hidden='true'
  >
    <path d='M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z' />
  </svg>
);

const ListIcon = (): JSX.Element => (
  <svg
    xmlns='http://www.w3.org/2000/svg'
    viewBox='0 0 24 24'
    width='18'
    height='18'
    fill='currentColor'
    aria-hidden='true'
  >
    <path d='M3 4h18v2H3V4zm0 7h18v2H3v-2zm0 7h18v2H3v-2z' />
  </svg>
);

export default function Edit({
  attributes,
  setAttributes,
}: EditProps): JSX.Element {
  const { showLabels } = attributes;

  const blockProps = useBlockProps({
    className: 'aa-grid-list-toggle',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Toggle Settings', 'aggressive-apparel')}>
          <ToggleControl
            label={__('Show Labels', 'aggressive-apparel')}
            checked={showLabels}
            onChange={value => setAttributes({ showLabels: value })}
            help={__(
              'Show "Grid" and "List" text labels beside the icons.',
              'aggressive-apparel'
            )}
          />
        </PanelBody>
        <PanelBody
          title={__('How this works', 'aggressive-apparel')}
          initialOpen={false}
        >
          <Notice status='info' isDismissible={false}>
            {__(
              "Place this block anywhere on a shop or category archive template. When clicked, it applies a list-view layout to the product collection on the same page. The visitor's preference is saved in their browser.",
              'aggressive-apparel'
            )}
          </Notice>
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <button
          type='button'
          className='aa-grid-list-toggle__btn aa-grid-list-toggle__btn--grid is-active'
          onClick={e => e.preventDefault()}
          aria-label={__('Grid view', 'aggressive-apparel')}
          aria-pressed={true}
        >
          <GridIcon />
          {showLabels && (
            <span className='aa-grid-list-toggle__label'>
              {__('Grid', 'aggressive-apparel')}
            </span>
          )}
        </button>
        <button
          type='button'
          className='aa-grid-list-toggle__btn aa-grid-list-toggle__btn--list'
          onClick={e => e.preventDefault()}
          aria-label={__('List view', 'aggressive-apparel')}
          aria-pressed={false}
        >
          <ListIcon />
          {showLabels && (
            <span className='aa-grid-list-toggle__label'>
              {__('List', 'aggressive-apparel')}
            </span>
          )}
        </button>
      </div>
    </>
  );
}
