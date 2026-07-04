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
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties } from 'react';

type HScrollActivation = 'top' | 'center' | 'bottom';
type HScrollDesktopBehavior = 'pinned' | 'inline';
type HScrollSnapBehavior = 'off' | 'proximity' | 'paged';
type HScrollSnapStrength = 'soft' | 'medium' | 'strong' | 'aggressive';
type SwipeHintStyle = 'off' | 'cue' | 'label' | 'badge';

interface HScrollAttributes {
  itemWidth: string;
  speed: number;
  showProgress: boolean;
  activation: HScrollActivation;
  desktopBehavior: HScrollDesktopBehavior;
  snapBehavior: HScrollSnapBehavior;
  snapStrength: HScrollSnapStrength;
  pagedCommitPercent: number;
  swipeHintStyle: SwipeHintStyle;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<HScrollAttributes>) {
  const {
    itemWidth,
    speed,
    showProgress,
    activation,
    desktopBehavior,
    snapBehavior = 'paged',
    snapStrength = 'medium',
    pagedCommitPercent = 5,
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
                label={__('Snap Behavior', 'aggressive-apparel')}
                help={__(
                  'Proximity gently aligns a nearby slide after scrolling stops. Paged commits to one adjacent slide after movement begins.',
                  'aggressive-apparel'
                )}
                value={snapBehavior}
                options={[
                  {
                    label: __('Off', 'aggressive-apparel'),
                    value: 'off',
                  },
                  {
                    label: __('Proximity', 'aggressive-apparel'),
                    value: 'proximity',
                  },
                  {
                    label: __('Paged', 'aggressive-apparel'),
                    value: 'paged',
                  },
                ]}
                onChange={(val: string | undefined) =>
                  setAttributes({
                    snapBehavior: (val as HScrollSnapBehavior) ?? 'paged',
                  })
                }
              />
              {snapBehavior === 'paged' && (
                <RangeControl
                  label={__('Paging Sensitivity', 'aggressive-apparel')}
                  help={__(
                    'How far you scroll toward the next slide before paging commits, as a percentage of the gap. Lower feels snappier.',
                    'aggressive-apparel'
                  )}
                  value={pagedCommitPercent}
                  onChange={(val: number | undefined) =>
                    setAttributes({ pagedCommitPercent: val ?? 5 })
                  }
                  min={5}
                  max={50}
                  step={5}
                />
              )}
              {snapBehavior === 'proximity' && (
                <SelectControl
                  label={__('Snap Strength', 'aggressive-apparel')}
                  help={__(
                    'Controls how close scrolling must stop before proximity alignment engages.',
                    'aggressive-apparel'
                  )}
                  value={snapStrength}
                  options={[
                    {
                      label: __('Soft', 'aggressive-apparel'),
                      value: 'soft',
                    },
                    {
                      label: __('Medium', 'aggressive-apparel'),
                      value: 'medium',
                    },
                    {
                      label: __('Strong', 'aggressive-apparel'),
                      value: 'strong',
                    },
                    {
                      label: __('Aggressive', 'aggressive-apparel'),
                      value: 'aggressive',
                    },
                  ]}
                  onChange={(val: string | undefined) =>
                    setAttributes({
                      snapStrength: (val as HScrollSnapStrength) ?? 'medium',
                    })
                  }
                />
              )}
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
