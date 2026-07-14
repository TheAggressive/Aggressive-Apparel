/**
 * Horizontal Scroll Block — Editor Component.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  useInnerBlocksProps,
  InspectorControls,
} from '@wordpress/block-editor';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties } from 'react';

type HScrollActivation = 'top' | 'center' | 'bottom';
type HScrollDesktopBehavior = 'pinned' | 'inline';
type HScrollSnapBehavior = 'off' | 'proximity' | 'paged';
type SwipeHintStyle = 'off' | 'cue' | 'label' | 'badge';

interface HScrollAttributes {
  ariaLabel: string;
  itemWidth: string;
  speed: number;
  showProgress: boolean;
  activation: HScrollActivation;
  desktopBehavior: HScrollDesktopBehavior;
  snapBehavior: HScrollSnapBehavior;
  swipeHintStyle: SwipeHintStyle;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<HScrollAttributes>) {
  const {
    ariaLabel = '',
    itemWidth,
    speed,
    showProgress,
    activation,
    desktopBehavior,
    snapBehavior = 'off',
    swipeHintStyle = 'cue',
  } = attributes;
  const previewStyle = {
    '--aa-hscroll-item-width': itemWidth,
  } as CSSProperties;

  const blockProps = useBlockProps({
    className: 'aa-hscroll is-horizontal',
    style: previewStyle,
  });
  const innerBlocksProps = useInnerBlocksProps(
    { className: 'aa-hscroll__track' },
    {
      orientation: 'horizontal',
      template: [
        ['core/group', {}],
        ['core/group', {}],
        ['core/group', {}],
      ],
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Horizontal Scroll', 'aggressive-apparel')}>
          <TextControl
            label={__('Accessibility Label', 'aggressive-apparel')}
            help={__(
              'Describes this gallery to screen readers. Set a unique label when a page has more than one horizontal-scroll section.',
              'aggressive-apparel'
            )}
            value={ariaLabel}
            onChange={(val: string) => setAttributes({ ariaLabel: val })}
          />
          <UnitControl
            label={__('Item Width', 'aggressive-apparel')}
            help={__('Width of each slide.', 'aggressive-apparel')}
            value={itemWidth}
            onChange={(val: string | undefined) =>
              setAttributes({ itemWidth: val ?? '60vw' })
            }
            units={[
              { value: 'vw', label: 'vw', default: 60 },
              { value: 'px', label: 'px', default: 800 },
              { value: '%', label: '%', default: 60 },
            ]}
          />
          <SelectControl
            label={__('Desktop Behavior', 'aggressive-apparel')}
            help={__(
              'Pinned maps natural vertical scrolling to horizontal movement. Inline is a normal swipe/scroll carousel. Touch always uses the inline carousel.',
              'aggressive-apparel'
            )}
            value={desktopBehavior}
            options={[
              {
                label: __('Pinned (scroll-driven)', 'aggressive-apparel'),
                value: 'pinned',
              },
              {
                label: __('Inline carousel', 'aggressive-apparel'),
                value: 'inline',
              },
            ]}
            onChange={(val: string | undefined) =>
              setAttributes({
                desktopBehavior: (val as HScrollDesktopBehavior) ?? 'pinned',
              })
            }
          />
          {desktopBehavior === 'pinned' && (
            <>
              <RangeControl
                label={__('Scroll Speed', 'aggressive-apparel')}
                help={__(
                  'Multiplier for how much vertical scroll is consumed.',
                  'aggressive-apparel'
                )}
                value={speed}
                onChange={(val: number | undefined) =>
                  setAttributes({ speed: val ?? speed })
                }
                min={0.5}
                max={3}
                step={0.25}
              />
              <SelectControl
                label={__('Activate When Block Reaches', 'aggressive-apparel')}
                help={__(
                  'Where the section pins before horizontal scroll begins.',
                  'aggressive-apparel'
                )}
                value={activation}
                options={[
                  {
                    label: __('Top of screen', 'aggressive-apparel'),
                    value: 'top',
                  },
                  {
                    label: __('Center of screen', 'aggressive-apparel'),
                    value: 'center',
                  },
                  {
                    label: __('Bottom of screen', 'aggressive-apparel'),
                    value: 'bottom',
                  },
                ]}
                onChange={(val: string | undefined) =>
                  setAttributes({
                    activation: (val as HScrollActivation) ?? 'top',
                  })
                }
              />
              <SelectControl
                label={__('Scroll Behavior', 'aggressive-apparel')}
                help={__(
                  'Smooth scrubs the gallery horizontally in lock-step with scrolling. Stepped advances one slide per scroll, gliding it into place while further input is held.',
                  'aggressive-apparel'
                )}
                value={snapBehavior === 'paged' ? 'paged' : 'off'}
                options={[
                  {
                    label: __('Smooth (continuous)', 'aggressive-apparel'),
                    value: 'off',
                  },
                  {
                    label: __(
                      'Stepped (one slide per scroll)',
                      'aggressive-apparel'
                    ),
                    value: 'paged',
                  },
                ]}
                onChange={(val: string | undefined) =>
                  setAttributes({
                    snapBehavior: (val as HScrollSnapBehavior) ?? 'off',
                  })
                }
              />
            </>
          )}
          <ToggleControl
            label={__('Show Progress Bar', 'aggressive-apparel')}
            checked={showProgress}
            onChange={(val: boolean) => setAttributes({ showProgress: val })}
          />
          <SelectControl
            label={__('Mobile Swipe Hint', 'aggressive-apparel')}
            help={__(
              'Animation cue uses a bare chevron so it does not look like a button. Badge adds a circular background.',
              'aggressive-apparel'
            )}
            value={swipeHintStyle}
            options={[
              { label: __('Off', 'aggressive-apparel'), value: 'off' },
              {
                label: __('Animation cue', 'aggressive-apparel'),
                value: 'cue',
              },
              {
                label: __('Cue with "Swipe" label', 'aggressive-apparel'),
                value: 'label',
              },
              {
                label: __('Badge (circle)', 'aggressive-apparel'),
                value: 'badge',
              },
            ]}
            onChange={(val: string) =>
              setAttributes({ swipeHintStyle: val as SwipeHintStyle })
            }
          />
        </PanelBody>
      </InspectorControls>
      <section {...blockProps}>
        <div className='aa-hscroll__range'>
          <div className='aa-hscroll__viewport'>
            <div {...innerBlocksProps} />
            {swipeHintStyle !== 'off' && (
              <div
                className={`aa-hscroll__swipe-hint aa-hscroll__swipe-hint--${swipeHintStyle}`}
                aria-hidden='true'
              >
                {swipeHintStyle === 'label' && (
                  <span className='aa-hscroll__swipe-hint-label'>
                    {__('Swipe', 'aggressive-apparel')}
                  </span>
                )}
                <span className='aa-hscroll__swipe-hint-icon'>
                  <span className='aa-hscroll__swipe-hint-chevron'>
                    <svg
                      width='36'
                      height='36'
                      viewBox='0 0 24 24'
                      fill='currentColor'
                      aria-hidden='true'
                    >
                      <path d='M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z' />
                    </svg>
                  </span>
                  <span className='aa-hscroll__swipe-hint-chevron aa-hscroll__swipe-hint-chevron--trail'>
                    <svg
                      width='36'
                      height='36'
                      viewBox='0 0 24 24'
                      fill='currentColor'
                      aria-hidden='true'
                    >
                      <path d='M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z' />
                    </svg>
                  </span>
                </span>
              </div>
            )}
          </div>
        </div>
      </section>
    </>
  );
}
