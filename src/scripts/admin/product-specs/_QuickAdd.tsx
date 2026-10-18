/**
 * Product Specs settings — add a spec to a size guide in one row.
 *
 * Name, kind, and value; a scale also takes its steps and a number its unit.
 * Everything else (advice, labels, attribute names, where it shows) has
 * sensible defaults and is one click away under "More options". Saving the
 * guide creates the spec's WooCommerce attribute.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ItemType, SpecItem } from './_types';
import { blankItem } from './_utils';
import ValuePicker from './_ValuePicker';

type Props = {
  /** Add the spec; `more` opens its full editor. */
  onAdd: (
    partial: Partial<SpecItem> & Pick<SpecItem, 'type'>,
    value: string,
    more: boolean
  ) => void;
  onCancel: () => void;
};

export default function QuickAdd({ onAdd, onCancel }: Props) {
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

  const stepList = steps
    .split(',')
    .map(step => step.trim())
    .filter(Boolean);
  // A throwaway item so the value field matches the kind being added.
  const draft: SpecItem = {
    ...blankItem(type, stepList),
    name,
    unit,
  };

  const submit = (more: boolean) =>
    onAdd(
      {
        type,
        name: name.trim(),
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
          onChange={setName}
          __next40pxDefaultSize
        />
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
      {name.trim() !== '' && (type === 'text' || stepList.length > 1) && (
        <div className='aa-ps-quick-add__value'>
          <span className='aa-ps-default__label' aria-hidden='true'>
            {__('Value on this guide', 'aggressive-apparel')}
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
