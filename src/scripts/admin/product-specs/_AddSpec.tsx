/**
 * Product Specs settings — "Add spec" dialog and attribute suggestions.
 *
 * Specs start from a preset (one click, everything prefilled), from an
 * attribute the store already uses (linked, with steps and boundaries
 * guessed from real values), or from scratch.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ItemType, SpecItem, Suggestion } from './_types';
import { presets } from './_presets';

type Props = {
  existing: SpecItem[];
  disabled: boolean;
  onAdd: (item: Partial<SpecItem> & Pick<SpecItem, 'type'>) => void;
};

export function AddSpecButton({ existing, disabled, onAdd }: Props) {
  const [open, setOpen] = useState(false);
  const keys = new Set(existing.map(item => item.key));
  const all = presets();
  const groups = [...new Set(all.map(preset => preset.group))];

  const add = (item: Partial<SpecItem> & Pick<SpecItem, 'type'>) => {
    onAdd(item);
    setOpen(false);
  };

  const blank = (type: ItemType) => () => add({ type });

  return (
    <>
      <Button
        variant='primary'
        disabled={disabled}
        onClick={() => setOpen(true)}
      >
        {__('Add spec', 'aggressive-apparel')}
      </Button>
      {open && (
        <Modal
          title={__('Add a spec', 'aggressive-apparel')}
          onRequestClose={() => setOpen(false)}
          className='aa-ps-modal'
          size='large'
        >
          {groups.map(group => (
            <section key={group} className='aa-ps-presets'>
              <h2 className='aa-ps-presets__group'>{group}</h2>
              <div className='aa-ps-presets__grid'>
                {all
                  .filter(preset => preset.group === group)
                  .map(preset => {
                    const added = keys.has(preset.item.key);
                    return (
                      <button
                        type='button'
                        key={preset.item.key}
                        className='aa-ps-preset'
                        disabled={added}
                        onClick={() => add(preset.item)}
                      >
                        <span className='aa-ps-preset__name'>
                          {preset.item.name}
                        </span>
                        <span className='aa-ps-preset__summary'>
                          {added
                            ? __('Already added', 'aggressive-apparel')
                            : preset.summary}
                        </span>
                      </button>
                    );
                  })}
              </div>
            </section>
          ))}
          <section className='aa-ps-presets'>
            <h2 className='aa-ps-presets__group'>
              {__('Start from scratch', 'aggressive-apparel')}
            </h2>
            <div className='aa-ps-presets__grid'>
              <button
                type='button'
                className='aa-ps-preset'
                onClick={blank('pick')}
              >
                <span className='aa-ps-preset__name'>
                  {__('Scale', 'aggressive-apparel')}
                </span>
                <span className='aa-ps-preset__summary'>
                  {__(
                    'Pick a step per product, or set a default',
                    'aggressive-apparel'
                  )}
                </span>
              </button>
              <button
                type='button'
                className='aa-ps-preset'
                onClick={blank('number')}
              >
                <span className='aa-ps-preset__name'>
                  {__('Scale from a number', 'aggressive-apparel')}
                </span>
                <span className='aa-ps-preset__summary'>
                  {__(
                    'Enter a number; the step is worked out',
                    'aggressive-apparel'
                  )}
                </span>
              </button>
              <button
                type='button'
                className='aa-ps-preset'
                onClick={blank('text')}
              >
                <span className='aa-ps-preset__name'>
                  {__('Text detail', 'aggressive-apparel')}
                </span>
                <span className='aa-ps-preset__summary'>
                  {__('A plain value, like a material', 'aggressive-apparel')}
                </span>
              </button>
            </div>
          </section>
        </Modal>
      )}
    </>
  );
}

type SuggestionsProps = {
  suggestions: Suggestion[];
  onUse: (suggestion: Suggestion) => void;
  onDismiss: (suggestion: Suggestion) => void;
};

function typeLabel(type: ItemType): string {
  if (type === 'number') {
    return __('scale from a number', 'aggressive-apparel');
  }
  return type === 'pick'
    ? __('scale', 'aggressive-apparel')
    : __('text detail', 'aggressive-apparel');
}

export function Suggestions({
  suggestions,
  onUse,
  onDismiss,
}: SuggestionsProps) {
  if (suggestions.length === 0) {
    return null;
  }

  return (
    <div className='aa-ps-suggest'>
      <h2 className='aa-ps-suggest__title'>
        {__('Your products already have these', 'aggressive-apparel')}
      </h2>
      <p className='aa-ps-help'>
        {__(
          'Turn an attribute into a spec and those products show it right away, with no retyping.',
          'aggressive-apparel'
        )}
      </p>
      <ul className='aa-ps-suggest__list'>
        {suggestions.map(suggestion => (
          <li key={suggestion.name} className='aa-ps-suggest__row'>
            <span className='aa-ps-suggest__text'>
              <strong>{suggestion.name}</strong>
              <span className='aa-ps-help'>
                {sprintf(
                  /* translators: 1: number of products, 2: spec type, 3: example values. */
                  _n(
                    'On %1$d product · as a %2$s · e.g. %3$s',
                    'On %1$d products · as a %2$s · e.g. %3$s',
                    suggestion.products,
                    'aggressive-apparel'
                  ),
                  suggestion.products,
                  typeLabel(suggestion.type),
                  suggestion.values.slice(0, 3).join(', ')
                )}
              </span>
            </span>
            <Button
              variant='secondary'
              size='compact'
              onClick={() => onUse(suggestion)}
            >
              {__('Use as spec', 'aggressive-apparel')}
            </Button>
            <Button
              variant='tertiary'
              size='compact'
              onClick={() => onDismiss(suggestion)}
            >
              {__('Not now', 'aggressive-apparel')}
            </Button>
          </li>
        ))}
      </ul>
    </div>
  );
}
