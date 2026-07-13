/**
 * Wishlist Button — Editor Preview
 *
 * Renders a non-interactive preview of the heart button so the
 * user can see what they're placing. The real button is emitted
 * by render.php on the front end with full Interactivity API
 * directives bound to the current product context.
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

import { HeartIcon } from '../../utils/heart-icon';
import './editor.css';

interface WishlistButtonAttributes {
  label: string;
  showLabel: boolean;
  showIcon: boolean;
  iconOnly: boolean;
  size: 'default' | 'large';
  alignment: 'left' | 'center' | 'right';
}

interface EditProps {
  attributes: WishlistButtonAttributes;
  setAttributes: (attrs: Partial<WishlistButtonAttributes>) => void;
}

export default function Edit({
  attributes,
  setAttributes,
}: EditProps): JSX.Element {
  const { label, showLabel, showIcon, iconOnly, size, alignment } = attributes;

  const effectiveShowIcon = iconOnly ? true : showIcon;
  const effectiveShowLabel = iconOnly ? false : showLabel;

  const buttonClasses = [
    'aggressive-apparel-wishlist__toggle',
    iconOnly ? 'aggressive-apparel-wishlist__toggle--icon-only' : '',
    'large' === size ? 'aggressive-apparel-wishlist__toggle--large' : '',
  ]
    .filter(Boolean)
    .join(' ');

  const blockProps = useBlockProps({
    className: 'aggressive-apparel-wishlist-block',
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
        <PanelBody title={__('Wishlist Button Settings', 'aggressive-apparel')}>
          <ToggleControl
            label={__('Icon Only', 'aggressive-apparel')}
            checked={iconOnly}
            onChange={value => setAttributes({ iconOnly: value })}
            help={__(
              'Render a label-less heart button. The label text is still used as the accessible name.',
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
                : __('Text shown beside the heart.', 'aggressive-apparel')
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
            label={__('Size', 'aggressive-apparel')}
            value={size}
            onChange={value =>
              setAttributes({
                size: (value as 'default' | 'large') || 'default',
              })
            }
            options={[
              {
                label: __('Default', 'aggressive-apparel'),
                value: 'default',
              },
              {
                label: __(
                  'Large (use on single product pages)',
                  'aggressive-apparel'
                ),
                value: 'large',
              },
            ]}
          />
        </PanelBody>

        <PanelBody
          title={__('Where this appears', 'aggressive-apparel')}
          initialOpen={false}
        >
          <Notice status='info' isDismissible={false}>
            {__(
              'This button binds to the current product context. Place it inside a Single Product template, or inside a Query Loop / Product Collection block. On pages without a product context it renders nothing. The Wishlist feature must be enabled in Theme Settings.',
              'aggressive-apparel'
            )}
          </Notice>
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <button
          type='button'
          className={buttonClasses}
          onClick={e => e.preventDefault()}
          aria-pressed='false'
        >
          {effectiveShowIcon && (
            <HeartIcon className='aggressive-apparel-wishlist__icon' />
          )}
          {effectiveShowLabel && label.trim() !== '' && (
            <span className='aggressive-apparel-wishlist__label'>{label}</span>
          )}
          {!effectiveShowLabel && label.trim() !== '' && (
            <span className='screen-reader-text'>{label}</span>
          )}
        </button>
      </div>
    </>
  );
}
