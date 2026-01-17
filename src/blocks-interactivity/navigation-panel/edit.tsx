/**
 * Navigation Panel Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  InnerBlocks,
  InspectorControls,
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
import type {
  AnimationStyle,
  NavigationPanelAttributes,
  PanelPosition,
} from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-menu',
  'aggressive-apparel/nav-link',
  'aggressive-apparel/nav-submenu',
  'core/search',
  'core/social-links',
  'core/buttons',
  'core/heading',
  'core/paragraph',
  'core/separator',
  'core/spacer',
  'core/group',
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<NavigationPanelAttributes>) {
  const { position, animationStyle, width, showOverlay, showCloseButton } =
    attributes;

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-navigation-panel wp-block-aggressive-apparel-navigation-panel--${position} wp-block-aggressive-apparel-navigation-panel--${animationStyle}`,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Panel Settings', 'aggressive-apparel')}>
          <SelectControl
            label={__('Position', 'aggressive-apparel')}
            value={position}
            options={[
              { label: __('Right', 'aggressive-apparel'), value: 'right' },
              { label: __('Left', 'aggressive-apparel'), value: 'left' },
            ]}
            onChange={value =>
              setAttributes({ position: value as PanelPosition })
            }
          />
          <SelectControl
            label={__('Animation Style', 'aggressive-apparel')}
            value={animationStyle}
            options={[
              {
                label: __('Slide', 'aggressive-apparel'),
                value: 'slide',
              },
              {
                label: __('Push (moves page content)', 'aggressive-apparel'),
                value: 'push',
              },
              {
                label: __('Reveal (page slides away)', 'aggressive-apparel'),
                value: 'reveal',
              },
              {
                label: __('Fade', 'aggressive-apparel'),
                value: 'fade',
              },
            ]}
            onChange={value =>
              setAttributes({ animationStyle: value as AnimationStyle })
            }
            help={__(
              'Choose how the panel animates when opening.',
              'aggressive-apparel'
            )}
          />
          <TextControl
            label={__('Panel Width', 'aggressive-apparel')}
            help={__(
              'CSS width value. Use min() for responsive widths.',
              'aggressive-apparel'
            )}
            value={width}
            onChange={value => setAttributes({ width: value })}
          />
          <ToggleControl
            label={__('Show Overlay', 'aggressive-apparel')}
            help={__(
              'Dark overlay behind the panel when open.',
              'aggressive-apparel'
            )}
            checked={showOverlay}
            onChange={value => setAttributes({ showOverlay: value })}
          />
          <ToggleControl
            label={__('Show Close Button', 'aggressive-apparel')}
            checked={showCloseButton}
            onChange={value => setAttributes({ showCloseButton: value })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        {showOverlay && (
          <div
            className='wp-block-aggressive-apparel-navigation-panel__overlay'
            aria-hidden='true'
            style={{ opacity: 0.3 }}
          />
        )}
        <div className='wp-block-aggressive-apparel-navigation-panel__content'>
          <div className='wp-block-aggressive-apparel-navigation-panel__header'>
            {showCloseButton && (
              <button
                className='wp-block-aggressive-apparel-navigation-panel__close'
                type='button'
                aria-label={__('Close menu', 'aggressive-apparel')}
                onClick={e => e.preventDefault()}
              >
                <svg
                  aria-hidden='true'
                  xmlns='http://www.w3.org/2000/svg'
                  width='24'
                  height='24'
                  viewBox='0 0 24 24'
                  fill='none'
                  stroke='currentColor'
                  strokeWidth='2'
                  strokeLinecap='round'
                  strokeLinejoin='round'
                >
                  <line x1='18' y1='6' x2='6' y2='18' />
                  <line x1='6' y1='6' x2='18' y2='18' />
                </svg>
              </button>
            )}
          </div>
          <div className='wp-block-aggressive-apparel-navigation-panel__body'>
            <InnerBlocks allowedBlocks={ALLOWED_BLOCKS} />
          </div>
        </div>
      </div>
    </>
  );
}
