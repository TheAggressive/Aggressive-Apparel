/**
 * Product Specs settings — a scale's steps.
 *
 * Each step is a row: its name, and for a scale from a number, the range it
 * covers ("under 145 g/m²", "from 145 to 170", "220 g/m² and up") with the
 * one boundary that starts it. A number scale also shows its bands as a bar,
 * sized by their real ranges, so the scale reads at a glance. A boundary
 * that isn't above the one before is flagged in place.
 *
 * Steps are the spec attribute's terms: removing a step takes its term with
 * it, so the others keep theirs (Product_Specs_Taxonomy::sync_steps).
 *
 * @package Aggressive_Apparel
 */

import { Button, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { DraftItem } from './_types';
import { addStep, boundary, removeStep } from './_steps';

type Props = {
  item: DraftItem;
  limits: { minSteps: number; maxSteps: number };
  onChange: (patch: Partial<DraftItem>) => void;
};

/** A boundary as stored, so two different boundaries never look alike. */
function show(value: number): string {
  return Number.isFinite(value) ? String(value) : '–';
}

/** The bands of a number scale as a bar, each sized by its range. */
function Bands({ item }: { item: DraftItem }) {
  const t = item.thresholds;
  if (t.length === 0) {
    return null;
  }
  const span = t.length > 1 ? (t[t.length - 1] - t[0]) / (t.length - 1) : 20;
  const low = t[0] - span;
  const high = t[t.length - 1] + span;
  // A band with a missing or out-of-order boundary still gets a sliver.
  const width = (from: number, to: number) => {
    const share = ((to - from) / (high - low || 1)) * 100;
    return Number.isFinite(share) ? Math.max(6, share) : 6;
  };

  return (
    <div className='aa-ps-bands' aria-hidden='true'>
      <div className='aa-ps-bands__bar'>
        {item.steps.map((step, i) => (
          <span
            key={i}
            className='aa-ps-bands__band'
            style={{
              flexGrow: width(
                i === 0 ? low : t[i - 1],
                i === t.length ? high : t[i]
              ),
            }}
          >
            <span className='aa-ps-bands__name'>{step || i + 1}</span>
            {i > 0 && (
              <span className='aa-ps-bands__edge'>{show(t[i - 1])}</span>
            )}
          </span>
        ))}
      </div>
    </div>
  );
}

export default function StepsEditor({ item, limits, onChange }: Props) {
  const isNumber = item.type === 'number';
  const unit = item.unit;
  const last = item.steps.length - 1;
  const t = item.thresholds;

  // "145 g/m²", or just "145" for a scale with no unit.
  const amount = (value: number) =>
    unit ? `${show(value)} ${unit}` : show(value);

  const setStep = (i: number, value: string) => {
    const steps = [...item.steps];
    steps[i] = value;
    onChange({ steps });
  };

  const setThreshold = (i: number, value: string) => {
    const thresholds = [...t];
    thresholds[i] = boundary(value);
    onChange({ thresholds });
  };

  /** Why step i's boundary is wrong ('' when fine). */
  const problem = (i: number): string => {
    const value = t[i - 1];
    if (value === undefined || Number.isNaN(value)) {
      return __('Enter a number.', 'aggressive-apparel');
    }
    if (i > 1 && value <= t[i - 2]) {
      return sprintf(
        /* translators: %s: previous boundary, with its unit. */
        __('Must be higher than %s.', 'aggressive-apparel'),
        amount(t[i - 2])
      );
    }
    return '';
  };

  return (
    <div className='aa-ps-step-editor'>
      {isNumber && <Bands item={item} />}
      <ol className='aa-ps-step-list'>
        {item.steps.map((step, i) => {
          const error = isNumber && i > 0 ? problem(i) : '';
          return (
            <li key={i} className={cx('aa-ps-step', error && 'has-error')}>
              <span className='aa-ps-step__index' aria-hidden='true'>
                {i + 1}
              </span>
              <TextControl
                label={sprintf(
                  /* translators: %d: step number. */
                  __('Step %d name', 'aggressive-apparel'),
                  i + 1
                )}
                hideLabelFromVision
                value={step}
                placeholder={__('Step name', 'aggressive-apparel')}
                onChange={value => setStep(i, value)}
                __next40pxDefaultSize
              />
              {isNumber && (
                <div className='aa-ps-step__range'>
                  {i === 0 ? (
                    <span className='aa-ps-step__fixed'>
                      {sprintf(
                        /* translators: %s: first boundary, with its unit. */
                        __('under %s', 'aggressive-apparel'),
                        amount(t[0] ?? 0)
                      )}
                    </span>
                  ) : (
                    <>
                      <span className='aa-ps-step__word'>
                        {__('from', 'aggressive-apparel')}
                      </span>
                      <span className='aa-ps-number'>
                        <input
                          className='aa-ps-number__input'
                          type='number'
                          step='any'
                          aria-label={
                            unit
                              ? sprintf(
                                  /* translators: 1: step name, 2: unit. */
                                  __(
                                    '%1$s starts at (%2$s)',
                                    'aggressive-apparel'
                                  ),
                                  step || String(i + 1),
                                  unit
                                )
                              : sprintf(
                                  /* translators: %s: step name. */
                                  __('%s starts at', 'aggressive-apparel'),
                                  step || String(i + 1)
                                )
                          }
                          aria-invalid={error ? true : undefined}
                          value={Number.isNaN(t[i - 1]) ? '' : t[i - 1]}
                          onChange={event =>
                            setThreshold(i - 1, event.target.value)
                          }
                        />
                        {unit && (
                          <span
                            className='aa-ps-number__unit'
                            aria-hidden='true'
                          >
                            {unit}
                          </span>
                        )}
                      </span>
                      <span className='aa-ps-step__word'>
                        {i === last
                          ? __('and up', 'aggressive-apparel')
                          : sprintf(
                              /* translators: %s: next boundary. */
                              __('to %s', 'aggressive-apparel'),
                              show(t[i])
                            )}
                      </span>
                    </>
                  )}
                </div>
              )}
              <Button
                className='aa-ps-step__remove'
                icon='trash'
                label={sprintf(
                  /* translators: %s: step name. */
                  __('Remove %s', 'aggressive-apparel'),
                  step || String(i + 1)
                )}
                showTooltip
                disabled={item.steps.length <= limits.minSteps}
                onClick={() => onChange(removeStep(item, i))}
                size='compact'
              />
              {error && (
                <p className='aa-ps-step__error' role='alert'>
                  {error}
                </p>
              )}
            </li>
          );
        })}
      </ol>
      <Button
        variant='secondary'
        icon='plus'
        disabled={item.steps.length >= limits.maxSteps}
        onClick={() => onChange(addStep(item))}
      >
        {__('Add step', 'aggressive-apparel')}
      </Button>
    </div>
  );
}
