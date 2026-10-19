/**
 * Product Specs settings — one description line in an import.
 *
 * Shows the line, how sure the import is about it, and where it goes: one of
 * the store's specs, one of its WooCommerce attributes that isn't a spec yet,
 * a new attribute (set up right here: name, kind, which guides, archive
 * links), or left out. The value it will save is always visible and
 * editable, so nothing surprises after import.
 *
 * @package Aggressive_Apparel
 */

import {
  CheckboxControl,
  SelectControl,
  TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type {
  ImportAttribute,
  ImportFact as Fact,
  ImportMapping,
  SpecItem,
} from './_types';
import { attributeItem, valueFor } from './_import';
import ValuePicker from './_ValuePicker';

type Props = {
  fact: Fact;
  mapping: ImportMapping;
  /** The new attribute this line's key uses, when it goes to one. */
  attribute?: ImportAttribute;
  /** The store's store-wide specs. */
  specs: SpecItem[];
  /** WooCommerce attributes that aren't specs yet (taxonomy => label). */
  attributes: Record<string, string>;
  onMapping: (mapping: Partial<ImportMapping>) => void;
  /** Start or change this line's new attribute. */
  onAttribute: (attribute: Partial<ImportAttribute>) => void;
};

const STATUS: Record<Fact['status'], string> = {
  sure: __('Matched', 'aggressive-apparel'),
  guess: __('Best guess', 'aggressive-apparel'),
  unsure: __('Needs you', 'aggressive-apparel'),
  skipped: __('Left out', 'aggressive-apparel'),
};

export default function ImportFact({
  fact,
  mapping,
  attribute,
  specs,
  attributes,
  onMapping,
  onAttribute,
}: Props) {
  const spec = specs.find(item => item.key === mapping.target);
  const target = mapping.target;
  const pending = target === '';
  const status: Fact['status'] = pending
    ? 'unsure'
    : target === 'skip'
      ? 'skipped'
      : mapping.touched
        ? 'sure'
        : fact.status;
  const choice =
    target === 'new' && attribute?.taxonomy
      ? `attr:${attribute.taxonomy}`
      : target;
  const draft = target === 'new' && attribute ? attributeItem(attribute) : null;

  const choose = (value: string) => {
    if (value.startsWith('attr:')) {
      const taxonomy = value.slice(5);
      onAttribute({
        taxonomy,
        name: attributes[taxonomy] ?? taxonomy,
        kind: 'multiple',
        scope: 'store',
      });
      return;
    }
    if (value === 'new') {
      onAttribute({ taxonomy: '' });
      return;
    }
    const item = specs.find(other => other.key === value);
    onMapping({
      target: value,
      value: item ? valueFor(item, fact.text) : '',
    });
  };

  return (
    <li className={cx('aa-ps-line', `is-${status}`)}>
      <div className='aa-ps-line__main'>
        <div className='aa-ps-line__source'>
          <span className='aa-ps-line__status'>{STATUS[status]}</span>
          <p className='aa-ps-line__text'>{fact.text}</p>
        </div>

        <SelectControl
          label={sprintf(
            /* translators: %s: description line. */
            __('Where “%s” goes', 'aggressive-apparel'),
            fact.text
          )}
          hideLabelFromVision
          value={choice}
          onChange={choose}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        >
          {pending && (
            <option value=''>
              {__('Choose where it goes', 'aggressive-apparel')}
            </option>
          )}
          <optgroup label={__('Your specs', 'aggressive-apparel')}>
            {specs.map(item => (
              <option key={item.key} value={item.key}>
                {item.name}
              </option>
            ))}
          </optgroup>
          {Object.keys(attributes).length > 0 && (
            <optgroup
              label={__('Your WooCommerce attributes', 'aggressive-apparel')}
            >
              {Object.entries(attributes).map(([taxonomy, label]) => (
                <option key={taxonomy} value={`attr:${taxonomy}`}>
                  {label}
                </option>
              ))}
            </optgroup>
          )}
          <optgroup label={__('Create', 'aggressive-apparel')}>
            <option value='new'>
              {__('New attribute', 'aggressive-apparel')}
            </option>
          </optgroup>
          <option value='skip'>{__('Leave out', 'aggressive-apparel')}</option>
        </SelectControl>

        <div className='aa-ps-line__value'>
          {spec && (
            <ValuePicker
              item={spec}
              value={mapping.value}
              label={sprintf(
                /* translators: %s: spec name. */
                __('%s value', 'aggressive-apparel'),
                spec.name
              )}
              onChange={value => onMapping({ value })}
            />
          )}
          {draft && (
            <ValuePicker
              item={draft}
              value={mapping.value}
              label={sprintf(
                /* translators: %s: attribute name. */
                __('%s value', 'aggressive-apparel'),
                draft.name || __('New attribute', 'aggressive-apparel')
              )}
              onChange={value => onMapping({ value })}
            />
          )}
          {target === 'skip' && (
            <span className='aa-ps-line__note'>
              {__('Not added to the guide', 'aggressive-apparel')}
            </span>
          )}
        </div>
      </div>

      {target === 'new' && attribute && (
        <fieldset className='aa-ps-line__attribute'>
          <legend>
            {attribute.taxonomy
              ? sprintf(
                  /* translators: %s: attribute name. */
                  __('Use your “%s” attribute as a spec', 'aggressive-apparel'),
                  attributes[attribute.taxonomy] ?? attribute.name
                )
              : __('New attribute', 'aggressive-apparel')}
          </legend>
          <TextControl
            label={__('Name', 'aggressive-apparel')}
            value={attribute.name}
            onChange={name => onAttribute({ name })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('Kind', 'aggressive-apparel')}
            value={attribute.kind}
            options={[
              { value: 'text', label: __('Text', 'aggressive-apparel') },
              {
                value: 'multiple',
                label: __('Several values', 'aggressive-apparel'),
              },
              { value: 'pick', label: __('Scale', 'aggressive-apparel') },
              {
                value: 'number',
                label: __('Number on a scale', 'aggressive-apparel'),
              },
            ]}
            onChange={kind =>
              onAttribute({ kind: kind as ImportAttribute['kind'] })
            }
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          {attribute.kind === 'pick' && (
            <TextControl
              label={__('Steps', 'aggressive-apparel')}
              help={__('Comma-separated, low to high.', 'aggressive-apparel')}
              value={attribute.steps}
              onChange={steps => onAttribute({ steps })}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          )}
          {attribute.kind === 'number' && (
            <TextControl
              label={__('Unit', 'aggressive-apparel')}
              placeholder='g/m²'
              value={attribute.unit}
              onChange={unit => onAttribute({ unit })}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          )}
          <SelectControl
            label={__('Shows on', 'aggressive-apparel')}
            value={attribute.scope}
            options={[
              {
                value: 'guide',
                label: __('This guide only', 'aggressive-apparel'),
              },
              {
                value: 'store',
                label: __('Every guide', 'aggressive-apparel'),
              },
            ]}
            onChange={scope =>
              onAttribute({ scope: scope as ImportAttribute['scope'] })
            }
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          {(attribute.kind === 'text' || attribute.kind === 'multiple') && (
            <CheckboxControl
              label={__(
                'Link values to their archive pages',
                'aggressive-apparel'
              )}
              checked={attribute.archives}
              onChange={archives => onAttribute({ archives })}
            />
          )}
        </fieldset>
      )}
    </li>
  );
}
