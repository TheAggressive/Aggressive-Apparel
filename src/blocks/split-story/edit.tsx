/**
 * Split Story Block — Editor Component.
 *
 * Container for two fixed column blocks (media / content). The columns are
 * locked in place (`templateLock: 'all'`) so the layout can't be broken, while
 * each column's own content stays fully editable.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
  Notice,
  PanelBody,
  RangeControl,
  SelectControl,
  ToggleControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

import {
  getSplitStoryClassName,
  getSplitStoryStyle,
  type SplitStoryAttributes,
} from '../split-story-shared/props';

const TEMPLATE: [string, Record<string, unknown>?][] = [
  ['aggressive-apparel/split-story-media'],
  [
    'aggressive-apparel/split-story-content',
    { layout: { type: 'constrained' } },
  ],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<SplitStoryAttributes>) {
  const { mediaPosition, mediaWidth, mediaHeight, sticky, stickyTop, stackOrder } =
    attributes;

  const blockProps = useBlockProps({
    className: getSplitStoryClassName(attributes),
    style: getSplitStoryStyle(attributes),
  });

  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    template: TEMPLATE,
    templateLock: 'all',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Layout', 'aggressive-apparel')}>
          <SelectControl
            label={__('Media position', 'aggressive-apparel')}
            value={mediaPosition}
            options={[
              { label: __('Left', 'aggressive-apparel'), value: 'left' },
              { label: __('Right', 'aggressive-apparel'), value: 'right' },
            ]}
            onChange={val =>
              setAttributes({
                mediaPosition: val as SplitStoryAttributes['mediaPosition'],
              })
            }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <RangeControl
            label={__('Media column width (%)', 'aggressive-apparel')}
            value={mediaWidth}
            onChange={val =>
              setAttributes({ mediaWidth: val ?? 50 })
            }
            min={25}
            max={75}
            step={5}
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('Media height', 'aggressive-apparel')}
            help={
              mediaHeight === 'viewport'
                ? __(
                    'Media fills the viewport height and is cropped to cover.',
                    'aggressive-apparel'
                  )
                : __(
                    'Media sizes to its own content.',
                    'aggressive-apparel'
                  )
            }
            value={mediaHeight}
            options={[
              {
                label: __('Full viewport', 'aggressive-apparel'),
                value: 'viewport',
              },
              { label: __('Fit content', 'aggressive-apparel'), value: 'content' },
            ]}
            onChange={val =>
              setAttributes({
                mediaHeight: val as SplitStoryAttributes['mediaHeight'],
              })
            }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <SelectControl
            label={__('Stack order on mobile', 'aggressive-apparel')}
            value={stackOrder}
            options={[
              {
                label: __('Media first', 'aggressive-apparel'),
                value: 'media-first',
              },
              {
                label: __('Content first', 'aggressive-apparel'),
                value: 'content-first',
              },
            ]}
            onChange={val =>
              setAttributes({
                stackOrder: val as SplitStoryAttributes['stackOrder'],
              })
            }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        </PanelBody>
        <PanelBody title={__('Sticky media', 'aggressive-apparel')}>
          <ToggleControl
            label={__('Stick media while content scrolls', 'aggressive-apparel')}
            checked={sticky}
            onChange={val => setAttributes({ sticky: val })}
            __nextHasNoMarginBottom
          />
          {sticky && (
            <>
              <RangeControl
                label={__('Sticky offset (rem)', 'aggressive-apparel')}
                help={__(
                  'Top gap for a fixed site header.',
                  'aggressive-apparel'
                )}
                value={stickyTop}
                onChange={val => setAttributes({ stickyTop: val ?? 0 })}
                min={0}
                max={10}
                step={0.5}
                __nextHasNoMarginBottom
              />
              <Notice status='info' isDismissible={false}>
                {__(
                  'The media only appears to stick when the content column is taller than it — add enough content to create scroll room.',
                  'aggressive-apparel'
                )}
              </Notice>
            </>
          )}
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
