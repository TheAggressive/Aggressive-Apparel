/**
 * Product Specs settings — review one blank before it becomes a size guide.
 *
 * Its name, size chart (garment or body measurements, the measuring diagram
 * and how-to-measure notes, each if wanted), where the guide applies (suggested
 * categories, store default), and each description line's spec (_ImportFact).
 * Says what keeps it out of the one-click batch, if anything (importStops).
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
  ImportStop,
  SpecItem,
} from './_types';
import ChartPreview from './_ChartPreview';
import ImportFact from './_ImportFact';
import WhereItApplies from './_WhereItApplies';

type Props = {
  draft: ImportDraft;
  state: ImportBlankState;
  specs: SpecItem[];
  attributes: Record<string, string>;
  /** New attributes by description-line key, shared by every blank. */
  created: Record<string, ImportAttribute>;
  categories: Category[];
  /** What keeps it out of the one-click batch. */
  stops: ImportStop[];
  /** Open the guide already in place. */
  onOpen: (guide: number) => void;
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
  stops,
  onOpen,
  onState,
  onMapping,
  onAttribute,
}: Props) {
  const kinds = (['garment', 'body'] as const).filter(
    kind => draft.charts[kind]
  );
  // Lines to decide and a missing name show where they are; these don't.
  const signOffs: string[] = [
    ...(stops.includes('confirm')
      ? [
          __(
            'Check the best guesses under “Specs from the description”.',
            'aggressive-apparel'
          ),
        ]
      : []),
    ...(stops.includes('where')
      ? [
          __(
            'Choose where it applies. Without a category, only products that pick this guide use it.',
            'aggressive-apparel'
          ),
        ]
      : []),
    ...(stops.includes('empty')
      ? [
          __(
            'It has no size chart or specs, so the guide wouldn’t show anything.',
            'aggressive-apparel'
          ),
        ]
      : []),
  ];
  const taken = state.taken.filter(item => !state.categories.includes(item.id));
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

  if (draft.guide > 0 && !state.secondGuide) {
    return (
      <div className='aa-ps-review__blank'>
        <header className='aa-ps-review__head'>
          {draft.image && <img src={draft.image} alt='' />}
          <div className='aa-ps-review__name'>
            <strong>{draft.title}</strong>
            <p className='aa-ps-review__source'>
              {sprintf(
                /* translators: %s: Printful product name. */
                __('From Printful: %s', 'aggressive-apparel'),
                draft.source.title
              )}
            </p>
          </div>
        </header>
        <Notice
          status='info'
          isDismissible={false}
          actions={[
            {
              label: __('Open your guide', 'aggressive-apparel'),
              onClick: () => onOpen(draft.guide),
            },
            {
              label: __('Make a second guide instead', 'aggressive-apparel'),
              onClick: () => onState({ secondGuide: true }),
            },
          ]}
        >
          {__(
            'You already have a guide for this blank, so it stays exactly as it is, with your edits. Nothing here will be imported.',
            'aggressive-apparel'
          )}
        </Notice>
      </div>
    );
  }

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
        <Notice
          status='warning'
          isDismissible={false}
          actions={[
            {
              label: __('Keep mine instead', 'aggressive-apparel'),
              onClick: () => onState({ secondGuide: false }),
            },
          ]}
        >
          {__(
            'This makes a second guide for the same blank. Your existing guide stays as it is.',
            'aggressive-apparel'
          )}
        </Notice>
      )}

      {signOffs.length > 0 && (
        <Notice
          status='info'
          isDismissible={false}
          className='aa-ps-review__stops'
        >
          <p>
            {__(
              'This one needs a look before it can go with the ready guides:',
              'aggressive-apparel'
            )}
          </p>
          <ul>
            {signOffs.map(line => (
              <li key={line}>{line}</li>
            ))}
          </ul>
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
                          'Hidden on product pages. Switch it back on in the guide any time.',
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
        {state.notes[state.kind].trim() !== '' && (
          <ToggleControl
            label={__('Use these notes', 'aggressive-apparel')}
            help={
              state.withNotes
                ? __(
                    'Shown under the chart on product pages.',
                    'aggressive-apparel'
                  )
                : __(
                    'Hidden on product pages. Switch them back on in the guide any time.',
                    'aggressive-apparel'
                  )
            }
            checked={state.withNotes}
            onChange={on => onState({ withNotes: on })}
            __nextHasNoMarginBottom
          />
        )}
        {(state.withNotes || state.notes[state.kind].trim() === '') && (
          <details className='aa-ps-review__notes'>
            <summary>
              {__(
                'How to measure (shown under the chart)',
                'aggressive-apparel'
              )}
            </summary>
            <TextareaControl
              label={__('Notes', 'aggressive-apparel')}
              hideLabelFromVision
              value={state.notes[state.kind]}
              rows={6}
              // Writing notes means wanting them, even on a chart whose
              // empty notes were left off.
              onChange={notes =>
                onState({
                  notes: { ...state.notes, [state.kind]: notes },
                  withNotes: true,
                })
              }
              __nextHasNoMarginBottom
            />
          </details>
        )}
      </section>

      <section
        className='aa-ps-review__part'
        aria-labelledby='aa-ps-review-where'
      >
        <h3 id='aa-ps-review-where'>
          {__('Where it applies', 'aggressive-apparel')}
        </h3>
        <WhereItApplies
          categories={categories}
          selected={state.categories}
          onCategories={selected => onState({ categories: selected })}
          isDefault={state.isDefault}
          onDefault={isDefault => onState({ isDefault })}
        >
          {draft.where.source !== '' && state.categories.length > 0 && (
            <p className='aa-ps-help'>
              {draft.where.source === 'remembered'
                ? __(
                    'Picked from where you put this kind of garment last time.',
                    'aggressive-apparel'
                  )
                : __(
                    'Picked from the garment’s name. Change it if it’s wrong, and your choice is used next time.',
                    'aggressive-apparel'
                  )}
            </p>
          )}
          {taken.map(item => (
            <p key={item.id} className='aa-ps-help'>
              {sprintf(
                /* translators: 1: category name, 2: size guide name. */
                __(
                  '%1$s stays with %2$s, so only products that pick this guide use it. Choose %1$s above to move it here instead.',
                  'aggressive-apparel'
                ),
                item.name,
                item.guide
              )}
            </p>
          ))}
        </WhereItApplies>
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
