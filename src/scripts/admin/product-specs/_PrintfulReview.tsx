/**
 * Product Specs settings — review a Printful product before it becomes a
 * size guide.
 *
 * The draft (Printful_Import::draft) arrives with its size chart converted
 * (garment measurements, or "measure yourself"), how-to-measure notes, the
 * measuring diagram, and each description bullet mapped to a spec: sure,
 * a best guess, unsure, or skipped. The store fixes anything, then creates
 * an ordinary guide; its mapping choices are remembered for next time.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import {
  Button,
  Notice,
  SelectControl,
  TextControl,
  TextareaControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type {
  DraftItem,
  GuideDetail,
  ImportDraft,
  ImportFact,
  Registry,
  SpecItem,
} from './_types';
import { newItem, specKey, toStored } from './_utils';
import { combine, nameFrom, valueFor } from './_import';
import ChartPreview from './_ChartPreview';
import ValuePicker from './_ValuePicker';

type Props = {
  guidesPath: string;
  draft: ImportDraft;
  /** Saved registry items. */
  items: SpecItem[];
  onBack: () => void;
  onCreated: (id: number, registry?: Registry) => void;
};

/** Where one fact goes: a spec key, 'skip', or '__new' for a new spec. */
type Mapping = { target: string; value: string; name: string };

const NEW = '__new';

const STATUS: Record<ImportFact['status'], string> = {
  sure: __('Matched', 'aggressive-apparel'),
  guess: __('Best guess', 'aggressive-apparel'),
  unsure: __('Needs you', 'aggressive-apparel'),
  skipped: __('Skipped', 'aggressive-apparel'),
};

export default function PrintfulReview({
  guidesPath,
  draft,
  items,
  onBack,
  onCreated,
}: Props) {
  const kinds = (['garment', 'body'] as const).filter(k => draft.charts[k]);
  const [kind, setKind] = useState<'garment' | 'body'>(kinds[0] ?? 'garment');
  const [title, setTitle] = useState(draft.title);
  const [notes, setNotes] = useState(draft.notes[kinds[0] ?? 'garment']);
  const [mappings, setMappings] = useState<Mapping[]>(() =>
    draft.facts.map(fact => ({
      target: fact.target,
      value: fact.value,
      name: nameFrom(fact.text),
    }))
  );
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  // Facts map to store-wide specs (a new guide owns nothing yet) or to a
  // new spec on this guide.
  const specs = items.filter(item => item.enabled && item.guides.length === 0);
  const byKey = new Map(specs.map(item => [item.key, item]));

  const update = (index: number, patch: Partial<Mapping>) =>
    setMappings(
      mappings.map((mapping, i) => {
        if (i !== index) {
          return mapping;
        }
        const next = { ...mapping, ...patch };
        if (patch.target !== undefined) {
          const item = byKey.get(patch.target);
          next.value = item ? valueFor(item, draft.facts[i].text) : '';
        }
        return next;
      })
    );

  const switchKind = (next: 'garment' | 'body') => {
    setKind(next);
    setNotes(draft.notes[next]);
  };

  // What creating would do.
  const values: Record<string, string> = {};
  for (const item of specs) {
    const picked = mappings
      .filter(mapping => mapping.target === item.key)
      .map(mapping => mapping.value);
    if (picked.length > 0) {
      values[item.key] = combine(item, picked);
    }
  }
  const fresh: { item: DraftItem; index: number }[] = [];
  mappings.forEach((mapping, index) => {
    if (mapping.target === NEW && mapping.name.trim() !== '') {
      const taken = [...items, ...fresh.map(entry => entry.item)];
      const item = newItem({ type: 'text', name: mapping.name.trim() }, taken);
      item.key = item.key || specKey(item.name, taken);
      fresh.push({ item, index });
    }
  });
  const unresolved = mappings.filter(mapping => mapping.target === '').length;
  const chart = draft.charts[kind];

  const create = async () => {
    setSaving(true);
    setError('');
    const specValues = { ...values };
    for (const { item, index } of fresh) {
      specValues[item.key] = valueFor(item, draft.facts[index].text);
    }
    try {
      const result = (await apiFetch({
        path: guidesPath,
        method: 'POST',
        data: {
          title,
          ...(chart && { chart }),
          notes,
          specs: specValues,
          specItems: fresh.map(({ item }) => toStored(item)),
          importSource: draft.source,
          diagramUrl: draft.diagrams[kind],
          // Every decision, so the next import of a similar product asks less.
          importRules: draft.facts.map((fact, index) => ({
            key: fact.key,
            target:
              mappings[index].target === NEW
                ? (fresh.find(entry => entry.index === index)?.item.key ?? '')
                : mappings[index].target || 'skip',
          })),
        },
      })) as GuideDetail & { registry?: Registry };
      onCreated(result.id, result.registry);
    } catch (failure) {
      const { message, data } = failure as {
        message?: string;
        data?: { errors?: string[] };
      };
      setError(
        [
          message ??
            __('The guide could not be created.', 'aggressive-apparel'),
          ...(data?.errors ?? []),
        ].join(' ')
      );
      setSaving(false);
    }
  };

  return (
    <div className='aa-ps-import-review'>
      <div className='aa-ps-guide__top'>
        <Button variant='link' onClick={onBack}>
          {__('← Back to search', 'aggressive-apparel')}
        </Button>
        <span className='aa-ps-help'>
          {sprintf(
            /* translators: %d: Printful product ID. */
            __('From Printful product #%d', 'aggressive-apparel'),
            draft.source.id
          )}
        </span>
      </div>

      {draft.guide > 0 && (
        <Notice status='warning' isDismissible={false}>
          {__(
            'You already have a size guide from this product. Creating another makes a second copy.',
            'aggressive-apparel'
          )}
        </Notice>
      )}

      <section className='aa-ps-guide__section'>
        <div className='aa-ps-import-review__head'>
          {draft.image && <img src={draft.image} alt='' />}
          <TextControl
            label={__('Guide name', 'aggressive-apparel')}
            value={title}
            onChange={setTitle}
            help={__(
              'Named after the blank, so it is easy to pick on a product.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
          />
        </div>
      </section>

      <section className='aa-ps-guide__section'>
        <div className='aa-ps-guide__section-head'>
          <h2>{__('Size chart', 'aggressive-apparel')}</h2>
          {kinds.length > 1 && (
            <div
              className='aa-ps-segments aa-ps-import-review__kinds'
              role='radiogroup'
              aria-label={__('Chart to import', 'aggressive-apparel')}
              style={{ gridTemplateColumns: 'repeat(2, minmax(0, 1fr))' }}
            >
              {kinds.map(k => (
                <button
                  key={k}
                  type='button'
                  role='radio'
                  aria-checked={kind === k}
                  className={cx(
                    'aa-ps-segments__step',
                    kind === k && 'is-active'
                  )}
                  onClick={() => switchKind(k)}
                >
                  {k === 'garment'
                    ? __('Garment measurements', 'aggressive-apparel')
                    : __('Body measurements', 'aggressive-apparel')}
                </button>
              ))}
            </div>
          )}
        </div>
        {chart ? (
          <div className='aa-ps-guide__chart'>
            <div className='aa-ps-guide__preview'>
              <ChartPreview chart={chart} />
            </div>
            {draft.diagrams[kind] && (
              <img
                className='aa-ps-import-review__diagram'
                src={draft.diagrams[kind]}
                alt={__('Measuring diagram', 'aggressive-apparel')}
              />
            )}
          </div>
        ) : (
          <p className='aa-ps-help'>
            {__(
              'Printful has no size chart for this product. Its specs still import, and you can add a chart afterwards.',
              'aggressive-apparel'
            )}
          </p>
        )}
        <TextareaControl
          label={__('Notes', 'aggressive-apparel')}
          value={notes}
          rows={5}
          onChange={setNotes}
          help={__(
            'Printful’s how-to-measure text. Shown under the chart; edit freely.',
            'aggressive-apparel'
          )}
        />
      </section>

      <section className='aa-ps-guide__section'>
        <h2>{__('Specs', 'aggressive-apparel')}</h2>
        <p className='aa-ps-help'>
          {__(
            'Each line of the product description, placed under a spec where it was clear. Change any of them, make a new spec for this guide, or leave it out. Your choices are remembered for the next import.',
            'aggressive-apparel'
          )}
        </p>
        <ol className='aa-ps-facts'>
          {draft.facts.map((fact, index) => {
            const mapping = mappings[index];
            const item = byKey.get(mapping.target);
            return (
              <li
                key={index}
                className={cx(
                  'aa-ps-fact',
                  `is-${mapping.target === '' ? 'unsure' : fact.status}`
                )}
              >
                <div className='aa-ps-fact__source'>
                  <span className='aa-ps-fact__status'>
                    {mapping.target === ''
                      ? STATUS.unsure
                      : STATUS[fact.status]}
                  </span>
                  <span className='aa-ps-fact__text'>{fact.text}</span>
                </div>
                <SelectControl
                  label={sprintf(
                    /* translators: %s: description line. */
                    __('Spec for “%s”', 'aggressive-apparel'),
                    fact.text
                  )}
                  hideLabelFromVision
                  value={mapping.target}
                  options={[
                    ...(mapping.target === ''
                      ? [
                          {
                            value: '',
                            label: __('Choose…', 'aggressive-apparel'),
                          },
                        ]
                      : []),
                    ...specs.map(spec => ({
                      value: spec.key,
                      label: spec.name,
                    })),
                    {
                      value: NEW,
                      label: __(
                        'New spec on this guide…',
                        'aggressive-apparel'
                      ),
                    },
                    {
                      value: 'skip',
                      label: __('Leave out', 'aggressive-apparel'),
                    },
                  ]}
                  onChange={target => update(index, { target })}
                  __next40pxDefaultSize
                  __nextHasNoMarginBottom
                />
                <div className='aa-ps-fact__value'>
                  {item && (
                    <ValuePicker
                      item={item}
                      value={mapping.value}
                      label={item.name}
                      onChange={value => update(index, { value })}
                    />
                  )}
                  {mapping.target === NEW && (
                    <TextControl
                      label={__('New spec name', 'aggressive-apparel')}
                      hideLabelFromVision
                      placeholder={__('Spec name', 'aggressive-apparel')}
                      value={mapping.name}
                      onChange={name => update(index, { name })}
                      __next40pxDefaultSize
                    />
                  )}
                </div>
              </li>
            );
          })}
        </ol>
      </section>

      {error && (
        <Notice status='error' isDismissible={false}>
          {error}
        </Notice>
      )}

      <div className='aa-ps-save aa-ps-guide__footer'>
        <Button
          variant='primary'
          disabled={saving || title.trim() === ''}
          isBusy={saving}
          onClick={() => void create()}
        >
          {__('Create size guide', 'aggressive-apparel')}
        </Button>
        <span className='aa-ps-help'>
          {[
            sprintf(
              /* translators: %d: number of specs filled in. */
              _n(
                '%d spec filled in',
                '%d specs filled in',
                Object.keys(values).length,
                'aggressive-apparel'
              ),
              Object.keys(values).length
            ),
            fresh.length > 0 &&
              sprintf(
                /* translators: %d: number of new specs. */
                _n(
                  '%d new spec',
                  '%d new specs',
                  fresh.length,
                  'aggressive-apparel'
                ),
                fresh.length
              ),
            unresolved > 0 &&
              sprintf(
                /* translators: %d: lines left unchosen (left out). */
                _n(
                  '%d line not placed (left out)',
                  '%d lines not placed (left out)',
                  unresolved,
                  'aggressive-apparel'
                ),
                unresolved
              ),
          ]
            .filter(Boolean)
            .join(' · ')}
        </span>
      </div>
    </div>
  );
}
