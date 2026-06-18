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
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties } from 'react';

interface HScrollAttributes {
  itemWidth: string;
  speed: number;
  showProgress: boolean;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<HScrollAttributes>) {
  const { itemWidth, speed, showProgress } = attributes;
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
