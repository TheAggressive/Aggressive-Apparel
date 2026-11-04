/**
 * Product Specs admin — one spec's row, its edit panel, and its wording.
 *
 * Shared by a size guide's specs (_GuideSpecs) and a product's Size & specs
 * tab (_ProductPanel), so both read and work the same: the spec's name with
 * short notes, its value, an Edit button and a Shown/Hidden switch, and
 * under it, when open, a panel that rewords it for just this guide or
 * product.
 *
 * @package Aggressive_Apparel
 */

import type { ReactNode } from 'react';
import { Button, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { SpecItem } from './_types';
import OnOff from './_OnOff';
import { setWording, type Wording } from './_wording';

type RowProps = {
  /** The spec's name as shown here. */
  title: string;
  /** Short notes under the name ("Reworded for this guide"). */
  notes: string[];
  /** The value control. */
  children: ReactNode;
  shown: boolean;
  /** Shown, but with nothing to show. */
  empty: boolean;
  /** What switching it does, for screen readers. */
  switchLabel: string;
  onShow: (shown: boolean) => void;
  editing: boolean;
  onEdit: () => void;
  /** The edit panel, while editing. */
  panel?: ReactNode;
};

export function SpecRow({
  title,
  notes,
  children,
  shown,
  empty,
  switchLabel,
  onShow,
  editing,
  onEdit,
  panel,
}: RowProps) {
  return (
    <div className={cx(editing && 'is-editing')}>
      <div
        className={cx(
          'aa-ps-spec-row',
          !shown && 'is-off',
          shown && empty && 'is-empty'
        )}
      >
        <div className='aa-ps-spec-row__name'>
          <span className='aa-ps-spec-row__title'>
            {title || __('Untitled spec', 'aggressive-apparel')}
          </span>
          {notes.map(note => (
            <span key={note} className='aa-ps-spec-row__note'>
              {note}
            </span>
          ))}
        </div>
        <div className='aa-ps-spec-row__value'>{children}</div>
        <div className='aa-ps-spec-row__actions'>
          <Button
            icon='edit'
            size='compact'
            label={sprintf(
              /* translators: %s: spec name. */
              __('Edit %s', 'aggressive-apparel'),
              title || __('spec', 'aggressive-apparel')
            )}
            showTooltip
            isPressed={editing}
            aria-expanded={editing}
            onClick={onEdit}
          />
          <OnOff
            checked={shown}
            label={switchLabel}
            onText={__('Shown here', 'aggressive-apparel')}
            offText={__('Hidden here', 'aggressive-apparel')}
            onChange={onShow}
          />
        </div>
      </div>
      {editing && panel}
    </div>
  );
}

type PanelProps = {
  /** The panel's heading ("Edit Fit"). */
  title: string;
  /** Beside the heading (a switch). */
  aside?: ReactNode;
  /** What editing here does. */
  help: ReactNode;
  children: ReactNode;
  /** Buttons at the foot; Done is always there. */
  actions?: ReactNode;
  onDone: () => void;
};

export function EditPanel({
  title,
  aside,
  help,
  children,
  actions,
  onDone,
}: PanelProps) {
  return (
    <div className='aa-ps-edit-panel' role='region' aria-label={title}>
      <div className='aa-ps-edit-panel__head'>
        <h3 className='aa-ps-edit-panel__title'>{title}</h3>
        {aside}
      </div>
      <p className='aa-ps-help'>{help}</p>
      {children}
      <div className='aa-ps-edit-panel__foot'>
        {actions}
        <Button variant='secondary' onClick={onDone}>
          {__('Done', 'aggressive-apparel')}
        </Button>
      </div>
    </div>
  );
}

type WordingProps = {
  /** The spec as worded before this guide or product rewords it. */
  item: SpecItem;
  wording: Wording;
  onChange: (wording: Wording) => void;
};

/** Reword a spec's name and steps; blank fields keep the wording shown greyed. */
export function WordingFields({ item, wording, onChange }: WordingProps) {
  const own = wording[item.key] ?? {};
  const set = (field: string, text: string) =>
    onChange(setWording(wording, item.key, field, text));

  return (
    <div className='aa-ps-guide-wording'>
      <TextControl
        className='aa-ps-guide-wording__name'
        label={__('Name', 'aggressive-apparel')}
        value={own.title ?? ''}
        placeholder={item.name}
        onChange={text => set('title', text)}
        __next40pxDefaultSize
        __nextHasNoMarginBottom
      />
      {item.type !== 'text' &&
        item.steps.map((step, i) => (
          <TextControl
            key={i}
            label={sprintf(
              /* translators: %d: step number. */
              __('Step %d', 'aggressive-apparel'),
              i + 1
            )}
            value={own[`step${i}`] ?? ''}
            placeholder={step}
            onChange={text => set(`step${i}`, text)}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        ))}
    </div>
  );
}
