/**
 * Product Specs settings — which size guides a spec is used on.
 *
 * One choice rather than two switches: every guide, some guides, or nowhere
 * (kept for later). The rules live in _placement.
 *
 * @package Aggressive_Apparel
 */

import { Button, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { GuideSummary, SpecItem } from './_types';
import { usedOnLabel, type UsedOn } from './_placement';

type Placement = Pick<SpecItem, 'enabled' | 'guides'>;

const CHOICES: UsedOn[] = ['every', 'some', 'nowhere'];

type Props = {
  item: Placement;
  guides: GuideSummary[];
  /** The choice shown, which can be Some guides before any guide is picked. */
  choice: UsedOn;
  onChoose: (value: UsedOn) => void;
  onGuides: (guides: number[]) => void;
};

export default function UsedOnPicker({
  item,
  guides,
  choice,
  onChoose,
  onGuides,
}: Props) {
  const help = {
    every: __(
      'Shown with every size guide. A guide can still hide it from its own products.',
      'aggressive-apparel'
    ),
    some: __('Shown only with the size guides you tick.', 'aggressive-apparel'),
    nowhere: __(
      'Shown nowhere for now. Its settings and values are kept, so it’s ready to use again.',
      'aggressive-apparel'
    ),
  }[choice];

  return (
    <div className='aa-ps-used-on'>
      <div
        className='aa-ps-used-on__choices'
        role='group'
        aria-label={__('Used on', 'aggressive-apparel')}
      >
        {CHOICES.map(value => (
          <Button
            key={value}
            variant={choice === value ? 'primary' : 'secondary'}
            aria-pressed={choice === value}
            onClick={() => onChoose(value)}
            __next40pxDefaultSize
          >
            {usedOnLabel(value)}
          </Button>
        ))}
      </div>
      <p className='aa-ps-help'>{help}</p>
      {choice === 'some' && (
        <div className='aa-ps-categories'>
          {guides.length === 0 && (
            <p className='aa-ps-help'>
              {__('No size guides yet.', 'aggressive-apparel')}
            </p>
          )}
          {guides.map(guide => (
            <CheckboxControl
              key={guide.id}
              label={guide.title}
              checked={item.guides.includes(guide.id)}
              onChange={checked =>
                onGuides(
                  checked
                    ? [...item.guides, guide.id]
                    : item.guides.filter(id => id !== guide.id)
                )
              }
            />
          ))}
        </div>
      )}
      {choice === 'some' && item.guides.length === 0 && guides.length > 0 && (
        <p className='aa-ps-help aa-ps-help--notice'>
          {__(
            'Tick at least one guide. Saved with none ticked, it’s used on every guide.',
            'aggressive-apparel'
          )}
        </p>
      )}
    </div>
  );
}
