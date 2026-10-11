/**
 * Product Specs block — editor preview.
 *
 * Server-rendered on the front end from the store's spec items
 * (Products → Size Guides → Specs). Templates have no product to resolve, so the editor
 * shows each scale at its middle step with the same markup and classes.
 *
 * @package Aggressive_Apparel
 */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  CheckboxControl,
  Notice,
  PanelBody,
  SelectControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { cx, getProductSpecsData } from '../../utils/product-specs-data';

export type ProductSpecsAttributes = {
  layout: 'full' | 'compact';
  scales: string[];
  showLabels: boolean;
  showNudge: boolean;
  showDetails: boolean;
  showMeasurements: boolean;
  showGuideLink: boolean;
};

function Track({ steps, step }: { steps: number; step: number }) {
  return (
    <div
      className='aa-product-specs__track'
      style={{ gridTemplateColumns: `repeat(${steps}, 1fr)` }}
      aria-hidden='true'
    >
      {Array.from({ length: steps }).map((_, i) => (
        <span
          key={i}
          className={cx('aa-product-specs__segment', i === step && 'is-active')}
        />
      ))}
    </div>
  );
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<ProductSpecsAttributes>) {
  const {
    layout,
    scales,
    showLabels,
    showNudge,
    showDetails,
    showMeasurements,
    showGuideLink,
  } = attributes;
  const data = getProductSpecsData();
  const scaleItems = data.items.filter(item => item.type !== 'text');
  const detailItems = data.items.filter(item => item.type !== 'pick');
  const shown = scaleItems.filter(
    item => scales.length === 0 || scales.includes(item.key)
  );
  const isCompact = layout === 'compact';
  const blockProps = useBlockProps({
    className: `aa-product-specs is-layout-${layout}`,
  });

  const toggleScale = (key: string, checked: boolean) => {
    const current =
      scales.length === 0 ? scaleItems.map(item => item.key) : scales;
    const next = checked
      ? [...current, key]
      : current.filter(scale => scale !== key);
    const ordered = scaleItems
      .map(item => item.key)
      .filter(itemKey => next.includes(itemKey));
    // Every scale ticked is stored as "all", so new scales show automatically.
    setAttributes({
      scales: ordered.length === scaleItems.length ? [] : ordered,
    });
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Display', 'aggressive-apparel')}>
          <SelectControl
            label={__('Layout', 'aggressive-apparel')}
            value={layout}
            options={[
              { label: __('Full scales', 'aggressive-apparel'), value: 'full' },
              {
                label: __('Compact cards', 'aggressive-apparel'),
                value: 'compact',
              },
            ]}
            onChange={value =>
              // Advice belongs next to the size selector (compact); a full
              // specs section usually sits further down the page.
              setAttributes({
                layout: value === 'compact' ? 'compact' : 'full',
                showNudge: value === 'compact',
              })
            }
            help={__(
              'Compact fits next to the size selector. Full suits a specs section.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
          />
          {!isCompact && (
            <ToggleControl
              label={__('Show scale end labels', 'aggressive-apparel')}
              checked={showLabels}
              onChange={value => setAttributes({ showLabels: value })}
            />
          )}
          <ToggleControl
            label={__('Show advice', 'aggressive-apparel')}
            help={__(
              'The advice line set for the current step, such as “True to size.”',
              'aggressive-apparel'
            )}
            checked={showNudge}
            onChange={value => setAttributes({ showNudge: value })}
          />
          {!isCompact && (
            <ToggleControl
              label={__('Show details list', 'aggressive-apparel')}
              help={__(
                'Text details and numbers. They also appear in the Additional information tab.',
                'aggressive-apparel'
              )}
              checked={showDetails}
              onChange={value => setAttributes({ showDetails: value })}
            />
          )}
        </PanelBody>
        <PanelBody title={__('Size guide', 'aggressive-apparel')}>
          <ToggleControl
            label={__(
              'Show the picked size’s measurements',
              'aggressive-apparel'
            )}
            help={__(
              'Like “Size M: Chest 42 in, Length 29 in”, from the product’s size chart, once a size is picked.',
              'aggressive-apparel'
            )}
            checked={showMeasurements}
            onChange={value => setAttributes({ showMeasurements: value })}
          />
          <ToggleControl
            label={__('Show a “Size guide” link', 'aggressive-apparel')}
            help={__(
              'Opens the size guide. Shown when the page has a Size Guide block.',
              'aggressive-apparel'
            )}
            checked={showGuideLink}
            onChange={value => setAttributes({ showGuideLink: value })}
          />
        </PanelBody>
        <PanelBody title={__('Scales', 'aggressive-apparel')}>
          {scaleItems.length === 0 && (
            <p>{__('No scales are defined yet.', 'aggressive-apparel')}</p>
          )}
          {scaleItems.map(item => (
            <CheckboxControl
              key={item.key}
              label={item.name}
              checked={scales.length === 0 || scales.includes(item.key)}
              onChange={checked => toggleScale(item.key, checked)}
            />
          ))}
          <Notice status='info' isDismissible={false}>
            {__(
              'Each product only shows scales that apply to its category and have a value. This preview uses sample values.',
              'aggressive-apparel'
            )}{' '}
            {data.manageUrl && (
              <a href={data.manageUrl} target='_blank' rel='noreferrer'>
                {__('Manage specs', 'aggressive-apparel')}
              </a>
            )}
          </Notice>
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {shown.length === 0 && (
          <p>
            {__(
              'Product Specs: no scales to preview. Define them on Products → Size Guides → Specs.',
              'aggressive-apparel'
            )}
          </p>
        )}
        {shown.length > 0 && (
          <div
            className={
              isCompact ? 'aa-product-specs__cards' : 'aa-product-specs__scales'
            }
          >
            {shown.map(item => {
              const step = Math.floor((item.steps.length - 1) / 2);
              if (isCompact) {
                return (
                  <div className='aa-product-specs__card' key={item.key}>
                    <div
                      className={cx(
                        'aa-product-specs__line',
                        item.centered && 'is-centered'
                      )}
                    >
                      <div className='aa-product-specs__head'>
                        <span className='aa-product-specs__title'>
                          {item.name}
                        </span>
                        <span className='aa-product-specs__value'>
                          {item.steps[step]}
                        </span>
                      </div>
                      <Track steps={item.steps.length} step={step} />
                    </div>
                    {showNudge && (
                      <p className='aa-product-specs__nudge'>
                        {__(
                          'Advice for the current step shows here.',
                          'aggressive-apparel'
                        )}
                      </p>
                    )}
                  </div>
                );
              }
              return (
                <div
                  className={cx(
                    'aa-product-specs__scale',
                    item.centered && 'is-centered'
                  )}
                  key={item.key}
                >
                  <div className='aa-product-specs__head'>
                    <span className='aa-product-specs__title'>{item.name}</span>
                    <span className='aa-product-specs__value'>
                      {item.steps[step]}
                    </span>
                  </div>
                  <Track steps={item.steps.length} step={step} />
                  {showLabels && (
                    <div className='aa-product-specs__labels'>
                      <span>{item.start}</span>
                      <span>{item.mid}</span>
                      <span>{item.end}</span>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
        {!isCompact && showNudge && shown.length > 0 && (
          <p className='aa-product-specs__nudge'>
            {__(
              'Advice for the current step shows here.',
              'aggressive-apparel'
            )}
          </p>
        )}
        {shown.length > 0 && (showMeasurements || showGuideLink) && (
          <div className='aa-product-specs__footer'>
            {showMeasurements && (
              <p className='aa-product-specs__fit'>
                {__('Size M: Chest 42 in, Length 29 in', 'aggressive-apparel')}
              </p>
            )}
            {showGuideLink && (
              <span className='aa-product-specs__guide-link'>
                {__('Size guide', 'aggressive-apparel')}
              </span>
            )}
          </div>
        )}
        {!isCompact && showDetails && detailItems.length > 0 && (
          <dl className='aa-product-specs__details'>
            {detailItems.map(item => (
              <div className='aa-product-specs__detail' key={item.key}>
                <dt>{item.name}</dt>
                <dd>{__('Value for this product', 'aggressive-apparel')}</dd>
              </div>
            ))}
          </dl>
        )}
      </div>
    </>
  );
}
