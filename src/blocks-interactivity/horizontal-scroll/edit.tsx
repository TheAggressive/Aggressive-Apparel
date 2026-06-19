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

interface HScrollAttributes {
  itemWidth: string;
  speed: number;
  showProgress: boolean;
  activation: HScrollActivation;
  desktopBehavior: HScrollDesktopBehavior;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<HScrollAttributes>) {
  const { itemWidth, speed, showProgress, activation, desktopBehavior } =
    attributes;
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
        ['core/group', { style: { dimensions: { minHeight: '60vh' } } }],
        ['core/group', { style: { dimensions: { minHeight: '60vh' } } }],
        ['core/group', { style: { dimensions: { minHeight: '60vh' } } }],
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
              'Pinned hijacks vertical scroll to move slides; Inline is a normal swipe/scroll carousel. Touch always uses the inline carousel.',
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
            </>
          )}
          <ToggleControl
            label={__('Show Progress Bar', 'aggressive-apparel')}
            checked={showProgress}
            onChange={(val: boolean) => setAttributes({ showProgress: val })}
          />
        </PanelBody>
      </InspectorControls>
      <section {...blockProps}>
        <div className='aa-hscroll__viewport'>
          <div {...innerBlocksProps} />
        </div>
      </section>
    </>
  );
}
