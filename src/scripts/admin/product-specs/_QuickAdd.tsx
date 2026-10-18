/**
 * Product Specs settings — add a spec to a size guide in one row.
 *
 * Name, kind, and value; a scale also takes its steps and a number its unit.
 * Typing a name suggests the store's existing WooCommerce attributes (say,
 * Collection): picking one links the spec to it straight away, its values
 * ready to choose. Text specs can hold several values and link them to their
 * archive pages. Everything else (advice, labels, where it shows) has
 * sensible defaults and is one click away under "More options". Saving the
 * guide links or creates the spec's attribute.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import {
  Button,
  CheckboxControl,
  SelectControl,
  TextControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ItemType, SpecItem } from './_types';
import { blankItem } from './_utils';
import ValuePicker from './_ValuePicker';

/** An existing attribute, as the attribute route describes it. */
type Attribute = {
  taxonomy: string;
  label: string;
  archives: boolean;
  terms: { name: string; count: number }[];
};

type Props = {
  /** Attributes no spec uses yet (taxonomy => label), to link. */
  taxonomies: Record<string, string>;
  /** REST path of the attribute route. */
  attributePath: string;
  /** Add the spec; `more` opens its full editor. */
  onAdd: (
    partial: Partial<SpecItem> & Pick<SpecItem, 'type'>,
    value: string,
    more: boolean
  ) => void;
  onCancel: () => void;
};

export default function QuickAdd({
  taxonomies,
  attributePath,
  onAdd,
  onCancel,
}: Props) {
  const [name, setName] = useState('');
  const [type, setType] = useState<ItemType>('text');
  const [steps, setSteps] = useState(
    [
      __('Low', 'aggressive-apparel'),
      __('Medium', 'aggressive-apparel'),
      __('High', 'aggressive-apparel'),
    ].join(', ')
  );
  const [unit, setUnit] = useState('');
  const [value, setValue] = useState('');
  const [multiple, setMultiple] = useState(false);
  const [archives, setArchives] = useState(false);
  const [linked, setLinked] = useState<Attribute | null>(null);
  const [loading, setLoading] = useState('');

  const stepList = steps
    .split(',')
    .map(step => step.trim())
    .filter(Boolean);
  // A throwaway item so the value field matches the kind being added.
  const draft: SpecItem = {
    ...blankItem(type, stepList),
    name,
    unit,
    multiple: type === 'text' && multiple,
    terms: linked?.terms ?? [],
  };

  // Existing attributes named like what's typed, best matches first.
  const query = name.trim().toLowerCase();
  const matches =
    linked || query === ''
      ? []
      : Object.entries(taxonomies)
          .filter(([, label]) => label.toLowerCase().includes(query))
          .sort(
            ([, a], [, b]) =>
              Number(!a.toLowerCase().startsWith(query)) -
              Number(!b.toLowerCase().startsWith(query))
          )
          .slice(0, 4);

  const link = async (taxonomy: string) => {
    setLoading(taxonomy);
    try {
      const attribute = (await apiFetch({
        path: `${attributePath}?taxonomy=${encodeURIComponent(taxonomy)}`,
      })) as Attribute;
      setLinked(attribute);
      setName(attribute.label);
      setType('text');
      setValue('');
      // An attribute with archives is usually a several-values one, like
      // Collection; both stay switchable.
      setMultiple(true);
      setArchives(attribute.archives);
    } finally {
      setLoading('');
    }
  };

  const submit = (more: boolean) =>
    onAdd(
      {
        type,
        name: name.trim(),
        ...(linked && { taxonomy: linked.taxonomy, terms: linked.terms }),
        ...(type === 'text' && { multiple, archives }),
        ...(type !== 'text' && { steps: stepList }),
        ...(type === 'number' && { unit, thresholds: draft.thresholds }),
      },
      value,
      more
    );

  return (
    <div
      className='aa-ps-quick-add'
      role='group'
      aria-label={__('New spec', 'aggressive-apparel')}
    >
      <div className='aa-ps-quick-add__fields'>
        <TextControl
          label={__('Name', 'aggressive-apparel')}
          value={name}
          placeholder={__('Closure', 'aggressive-apparel')}
          onChange={next => {
            setName(next);
            if (linked && next !== linked.label) {
              setLinked(null);
            }
          }}
          __next40pxDefaultSize
        />
        {!linked && (
          <SelectControl
            label={__('Kind', 'aggressive-apparel')}
            value={type}
            options={[
              { value: 'text', label: __('Text', 'aggressive-apparel') },
              { value: 'pick', label: __('Scale', 'aggressive-apparel') },
              {
                value: 'number',
                label: __('Number on a scale', 'aggressive-apparel'),
              },
            ]}
            onChange={next => {
              setType(next as ItemType);
              setValue('');
            }}
            __next40pxDefaultSize
          />
        )}
        {type !== 'text' && (
          <TextControl
            label={__('Steps', 'aggressive-apparel')}
            value={steps}
            onChange={next => {
              setSteps(next);
              setValue('');
            }}
            help={__('Comma-separated, low to high.', 'aggressive-apparel')}
            __next40pxDefaultSize
          />
        )}
        {type === 'number' && (
          <TextControl
            label={__('Unit', 'aggressive-apparel')}
            value={unit}
            placeholder='g/m²'
            onChange={setUnit}
            __next40pxDefaultSize
          />
        )}
      </div>

      {matches.length > 0 && (
        <div className='aa-ps-quick-add__matches'>
          <span className='aa-ps-help'>
            {__('Use an existing attribute:', 'aggressive-apparel')}
          </span>
          {matches.map(([taxonomy, label]) => (
            <Button
              key={taxonomy}
              variant='secondary'
              size='compact'
              isBusy={loading === taxonomy}
              disabled={loading !== ''}
              onClick={() => void link(taxonomy)}
            >
              {label}
            </Button>
          ))}
        </div>
      )}

      {linked && (
        <p className='aa-ps-help aa-ps-quick-add__linked'>
          {sprintf(
            /* translators: 1: attribute name, 2: number of values. */
            _n(
              'Uses your “%1$s” attribute and its %2$d value.',
              'Uses your “%1$s” attribute and its %2$d values.',
              linked.terms.length,
              'aggressive-apparel'
            ),
            linked.label,
            linked.terms.length
          )}{' '}
          <Button
            variant='link'
            onClick={() => {
              setLinked(null);
              setValue('');
            }}
          >
            {__('Make a new one instead', 'aggressive-apparel')}
          </Button>
        </p>
      )}

      {type === 'text' && (
        <div className='aa-ps-quick-add__options'>
          <CheckboxControl
            label={__('Several values', 'aggressive-apparel')}
            checked={multiple}
            onChange={next => {
              setMultiple(next);
              setValue('');
            }}
          />
          <CheckboxControl
            label={__('Link values to archive pages', 'aggressive-apparel')}
            checked={archives}
            onChange={setArchives}
          />
        </div>
      )}

      {name.trim() !== '' && (type === 'text' || stepList.length > 1) && (
        <div className='aa-ps-quick-add__value'>
          <span className='aa-ps-default__label' aria-hidden='true'>
            {multiple && type === 'text'
              ? __('Values on this guide', 'aggressive-apparel')
              : __('Value on this guide', 'aggressive-apparel')}
          </span>
          <ValuePicker
            item={draft}
            value={value}
            label={__('Value on this guide', 'aggressive-apparel')}
            onChange={setValue}
          />
        </div>
      )}

      <div className='aa-ps-quick-add__actions'>
        <Button
          variant='primary'
          disabled={name.trim() === ''}
          onClick={() => submit(false)}
        >
          {__('Add', 'aggressive-apparel')}
        </Button>
        <Button
          variant='secondary'
          disabled={name.trim() === ''}
          onClick={() => submit(true)}
        >
          {__('Add with more options', 'aggressive-apparel')}
        </Button>
        <Button variant='tertiary' onClick={onCancel}>
          {__('Cancel', 'aggressive-apparel')}
        </Button>
      </div>
    </div>
  );
}
