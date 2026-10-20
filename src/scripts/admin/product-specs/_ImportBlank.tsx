/**
 * Product Specs settings — review one blank before it becomes a size guide.
 *
 * Its name, size chart (garment or body measurements, the measuring diagram
 * if wanted, and how-to-measure notes), where the guide applies (categories, store
 * default), and each description line's spec (_ImportFact).
 *
 * @package Aggressive_Apparel
 */

import {
  Notice,
  TextControl,
  TextareaControl,
  ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type {
  Category,
  ImportAttribute,
  ImportBlankState,
  ImportDraft,
  ImportMapping,
  SpecItem,
} from './_types';
import ChartPreview from './_ChartPreview';
import ImportFact from './_ImportFact';

type Props = {
  draft: ImportDraft;
  state: ImportBlankState;
  specs: SpecItem[];
  attributes: Record<string, string>;
  /** New attributes by description-line key, shared by every blank. */
  created: Record<string, ImportAttribute>;
  categories: Category[];
  onState: (patch: Partial<ImportBlankState>) => void;
  onMapping: (index: number, patch: Partial<ImportMapping>) => void;
  onAttribute: (index: number, patch: Partial<ImportAttribute>) => void;
};

export default function ImportBlank({
  draft,
  state,
  specs,
  attributes,
  created,
  categories,
  onState,
  onMapping,
  onAttribute,
}: Props) {
  const kinds = (['garment', 'body'] as const).filter(
    kind => draft.charts[kind]
  );
  const chart = draft.charts[state.kind];
  const diagram = draft.diagrams[state.kind];
  // An attribute another line already turns into a spec isn't offered again.
  const unclaimed = (factKey: string) =>
    Object.fromEntries(
      Object.entries(attributes).filter(
        ([taxonomy]) =>
          !Object.entries(created).some(
            ([key, other]) => key !== factKey && other.taxonomy === taxonomy
          )
      )
    );

  const toggleCategory = (id: number) =>
    onState({
      categories: state.categories.includes(id)
        ? state.categories.filter(other => other !== id)
        : [...state.categories, id],
    });

  return (
    <div className='aa-ps-review__blank'>
      <header className='aa-ps-review__head'>
        {draft.image && <img src={draft.image} alt='' />}
        <div className='aa-ps-review__name'>
          <TextControl
            label={__('Guide name', 'aggressive-apparel')}
            value={state.title}
            onChange={title => onState({ title })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <p className='aa-ps-review__source'>
            {sprintf(
              /* translators: %s: Printful product name. */
              __('From Printful: %s', 'aggressive-apparel'),
              draft.source.title
            )}
          </p>
        </div>
      </header>

      {draft.guide > 0 && (
        <Notice status='warning' isDismissible={false}>
          {__(
            'You already imported this blank. Creating it again makes a second guide.',
            'aggressive-apparel'
          )}
        </Notice>
      )}

      <section
        className='aa-ps-review__part'
        aria-labelledby='aa-ps-review-chart'
      >
        <div className='aa-ps-review__part-head'>
          <h3 id='aa-ps-review-chart'>
            {__('Size chart', 'aggressive-apparel')}
          </h3>
          {kinds.length > 1 && (
            <div
              className='aa-ps-pills'
              role='radiogroup'
              aria-label={__('Measurements', 'aggressive-apparel')}
            >
              {kinds.map(kind => (
                <button
                  key={kind}
                  type='button'
                  role='radio'
                  aria-checked={state.kind === kind}
                  className={cx(
                    'aa-ps-pill',
                    state.kind === kind && 'is-active'
                  )}
                  onClick={() => onState({ kind })}
                >
                  {kind === 'garment'
                    ? __('Garment', 'aggressive-apparel')
                    : __('Body', 'aggressive-apparel')}
                </button>
              ))}
            </div>
          )}
        </div>
        {chart ? (
          <div className='aa-ps-review__chart'>
            <ChartPreview chart={chart} />
            {diagram && (
              <div
                className={cx(
                  'aa-ps-review__diagram',
                  !state.diagram && 'is-off'
                )}
              >
                <img
                  src={diagram}
                  alt={__('Measuring diagram', 'aggressive-apparel')}
                />
                <ToggleControl
                  label={__('Use this diagram', 'aggressive-apparel')}
                  help={
                    state.diagram
                      ? __(
                          'Shown with the chart on product pages.',
                          'aggressive-apparel'
                        )
                      : __(
                          'Left out. You can add your own in the guide later.',
                          'aggressive-apparel'
                        )
                  }
                  checked={state.diagram}
                  onChange={on => onState({ diagram: on })}
                  __nextHasNoMarginBottom
                />
              </div>
            )}
          </div>
        ) : (
          <p className='aa-ps-help'>
            {__(
              'Printful has no size chart for this blank. The guide shows its specs only, with no Size Guide button, until you add a chart.',
              'aggressive-apparel'
            )}
          </p>
        )}
        <details className='aa-ps-review__notes'>
          <summary>
            {__('How to measure (shown under the chart)', 'aggressive-apparel')}
          </summary>
          <TextareaControl
            label={__('Notes', 'aggressive-apparel')}
            hideLabelFromVision
            value={state.notes[state.kind]}
            rows={6}
            onChange={notes =>
              onState({ notes: { ...state.notes, [state.kind]: notes } })
            }
            __nextHasNoMarginBottom
          />
        </details>
      </section>

      <section
        className='aa-ps-review__part'
        aria-labelledby='aa-ps-review-where'
      >
        <h3 id='aa-ps-review-where'>
          {__('Where it applies', 'aggressive-apparel')}
        </h3>
        <p className='aa-ps-help'>
          {__(
            'Products in these categories use this guide unless they pick another.',
            'aggressive-apparel'
          )}
        </p>
        <div
          className='aa-ps-chips'
          role='group'
          aria-label={__('Categories', 'aggressive-apparel')}
        >
          {categories.map(category => (
            <button
              key={category.id}
              type='button'
              aria-pressed={state.categories.includes(category.id)}
              className={cx(
                'aa-ps-chip',
                state.categories.includes(category.id) && 'is-on'
              )}
              onClick={() => toggleCategory(category.id)}
            >
              {category.name}
            </button>
          ))}
        </div>
        <ToggleControl
          label={__('Store-wide default', 'aggressive-apparel')}
          help={__(
            'For products with no guide of their own or from a category.',
            'aggressive-apparel'
          )}
          checked={state.isDefault}
          onChange={isDefault => onState({ isDefault })}
          __nextHasNoMarginBottom
        />
      </section>

      <section
        className='aa-ps-review__part'
        aria-labelledby='aa-ps-review-specs'
      >
        <h3 id='aa-ps-review-specs'>
          {__('Specs from the description', 'aggressive-apparel')}
        </h3>
        <p className='aa-ps-help'>
          {__(
            'Each line goes to a spec where it was clear. Decide the rest; your choices apply to the other blanks here and to future imports.',
            'aggressive-apparel'
          )}
        </p>
        <ol className='aa-ps-lines'>
          {draft.facts.map((fact, index) => (
            <ImportFact
              key={index}
              fact={fact}
              mapping={state.mappings[index]}
              attribute={created[fact.key]}
              specs={specs}
              attributes={unclaimed(fact.key)}
              onMapping={patch => onMapping(index, patch)}
              onAttribute={patch => onAttribute(index, patch)}
            />
          ))}
        </ol>
      </section>
    </div>
  );
}
