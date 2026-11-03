/**
 * Product Specs settings — a guide's notes and measuring diagram.
 *
 * Both show under the size chart on product pages. Each has the same On/Off
 * switch as a spec (_OnOff), and the import's "Use these notes" / "Use this
 * diagram" save to it: switching one off hides it on product pages but keeps
 * it here (Size_Chart::show), so switching it back on brings it back.
 *
 * @package Aggressive_Apparel
 */

import type { ReactNode } from 'react';
import { Button, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { GuideDetail } from './_types';
import OnOff from './_OnOff';

type Attachment = {
  id: number;
  url: string;
  sizes?: { medium?: { url: string } };
};

type MediaFrame = {
  on: (event: string, callback: () => void) => void;
  open: () => void;
  state: () => {
    get: (key: string) => { first: () => { toJSON: () => unknown } };
  };
};

type Fields = Pick<
  GuideDetail,
  'notes' | 'diagram' | 'showNotes' | 'showDiagram'
>;

type Props = Fields & { onChange: (patch: Partial<Fields>) => void };

/** One part (notes or diagram): a heading, its switch, and what it holds. */
function Part({
  title,
  has,
  on,
  label,
  onSwitch,
  children,
}: {
  title: string;
  /** Whether there's anything to show; the switch only shows when there is. */
  has: boolean;
  on: boolean;
  label: string;
  onSwitch: (on: boolean) => void;
  children: ReactNode;
}) {
  const hidden = has && !on;
  return (
    <div className={cx('aa-ps-guide-part', hidden && 'is-off')}>
      <div className='aa-ps-guide-part__head'>
        <h3 className='aa-ps-guide-part__title'>{title}</h3>
        {has && (
          <OnOff
            checked={on}
            label={label}
            onText={__('Shown', 'aggressive-apparel')}
            offText={__('Hidden', 'aggressive-apparel')}
            onChange={onSwitch}
          />
        )}
      </div>
      {hidden && (
        <p className='aa-ps-help aa-ps-help--notice'>
          {__(
            'Hidden on product pages. It stays here, so switching it back on brings it back.',
            'aggressive-apparel'
          )}
        </p>
      )}
      <div className='aa-ps-guide-part__body'>{children}</div>
    </div>
  );
}

export default function GuideNotes({
  notes,
  diagram,
  showNotes,
  showDiagram,
  onChange,
}: Props) {
  const pickDiagram = () => {
    const media = (
      window as unknown as { wp?: { media?: (options: object) => MediaFrame } }
    ).wp?.media;
    if (!media) {
      return;
    }
    const frame = media({
      title: __('Measuring diagram', 'aggressive-apparel'),
      library: { type: 'image' },
      multiple: false,
      button: { text: __('Use this image', 'aggressive-apparel') },
    });
    frame.on('select', () => {
      const image = frame
        .state()
        .get('selection')
        .first()
        .toJSON() as Attachment;
      onChange({
        diagram: { id: image.id, url: image.sizes?.medium?.url ?? image.url },
      });
    });
    frame.open();
  };

  return (
    <section className='aa-ps-guide__section aa-ps-guide__notes'>
      <h2>{__('Notes and diagram', 'aggressive-apparel')}</h2>
      <p className='aa-ps-help'>
        {__(
          'Shown under the size chart on product pages. Switch either off to hide it without losing it.',
          'aggressive-apparel'
        )}
      </p>
      <div className='aa-ps-guide-parts'>
        <Part
          title={__('Notes', 'aggressive-apparel')}
          has={notes.trim() !== ''}
          on={showNotes}
          label={__('Show the notes on product pages', 'aggressive-apparel')}
          onSwitch={on => onChange({ showNotes: on })}
        >
          <TextareaControl
            label={__('Notes', 'aggressive-apparel')}
            hideLabelFromVision
            value={notes}
            rows={6}
            placeholder={__(
              'How to measure, or fit notes like “Model is 6’1” wearing M”.',
              'aggressive-apparel'
            )}
            onChange={text => onChange({ notes: text })}
            help={__('Links become clickable.', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          />
        </Part>
        <Part
          title={__('Measuring diagram', 'aggressive-apparel')}
          has={diagram !== null}
          on={showDiagram}
          label={__('Show the diagram on product pages', 'aggressive-apparel')}
          onSwitch={on => onChange({ showDiagram: on })}
        >
          <div className='aa-ps-diagram'>
            {diagram ? (
              <img src={diagram.url} alt='' />
            ) : (
              <span className='aa-ps-diagram__empty' aria-hidden='true' />
            )}
            <div className='aa-ps-diagram__actions'>
              <Button variant='secondary' onClick={pickDiagram}>
                {diagram
                  ? __('Replace', 'aggressive-apparel')
                  : __('Add a measuring diagram', 'aggressive-apparel')}
              </Button>
              {diagram && (
                <Button
                  variant='tertiary'
                  isDestructive
                  onClick={() => onChange({ diagram: null })}
                >
                  {__('Remove', 'aggressive-apparel')}
                </Button>
              )}
            </div>
          </div>
        </Part>
      </div>
    </section>
  );
}
