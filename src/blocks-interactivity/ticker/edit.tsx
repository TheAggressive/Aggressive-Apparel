/**
 * Ticker Block Editor Component
 *
 * @package Aggressive_Apparel
 */

import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { TickerAttributes } from './types';

type EditProps = BlockEditProps<TickerAttributes>;

const INNER_BLOCKS_TEMPLATE: Array<[string, Record<string, unknown>]> = [
  [
    'core/paragraph',
    {
      placeholder: __('Add ticker content…', 'aggressive-apparel'),
    },
  ],
];

export default function Edit({ attributes, setAttributes }: EditProps) {
  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-ticker--editor',
  });

  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'ticker-editor__content',
    },
    {
      orientation: 'horizontal' as const,
      template: INNER_BLOCKS_TEMPLATE,
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Marquee Settings', 'aggressive-apparel')}
          initialOpen={true}
        >
          <RangeControl
            label={__('Speed (seconds)', 'aggressive-apparel')}
            value={attributes.speed}
            onChange={speed => setAttributes({ speed })}
            min={5}
            max={120}
            step={1}
            help={__(
              'Duration in seconds for one full scroll loop. Lower = faster.',
              'aggressive-apparel'
            )}
          />

          <SelectControl
            label={__('Direction', 'aggressive-apparel')}
            value={attributes.direction}
            options={[
              {
                label: __('Left', 'aggressive-apparel'),
                value: 'left',
              },
              {
                label: __('Right', 'aggressive-apparel'),
                value: 'right',
              },
            ]}
            onChange={direction => setAttributes({ direction })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <RangeControl
            label={__('Gap (px)', 'aggressive-apparel')}
            value={attributes.gap}
            onChange={gap => setAttributes({ gap })}
            min={0}
            max={200}
            step={4}
            help={__(
              'Space between items and between the original and duplicate tracks.',
              'aggressive-apparel'
            )}
          />
        </PanelBody>

        <PanelBody
          title={__('Behavior', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Pause on Hover', 'aggressive-apparel')}
            checked={attributes.pauseOnHover}
            onChange={pauseOnHover => setAttributes({ pauseOnHover })}
            help={__(
              'Pause the scrolling animation when the user hovers over the ticker.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
        </PanelBody>

        <PanelBody
          title={__('Edge Fade', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Fade Edges', 'aggressive-apparel')}
            checked={attributes.fadeEdges}
            onChange={fadeEdges => setAttributes({ fadeEdges })}
            help={__(
              'Apply a gradient fade to the left and right edges.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />

          {attributes.fadeEdges && (
            <RangeControl
              label={__('Fade Width (px)', 'aggressive-apparel')}
              value={attributes.fadeWidth}
              onChange={fadeWidth => setAttributes({ fadeWidth })}
              min={16}
              max={200}
              step={4}
            />
          )}
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div {...innerBlocksProps} />
      </div>
    </>
  );
}
