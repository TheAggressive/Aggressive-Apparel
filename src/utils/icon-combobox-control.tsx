/**
 * Icon library combobox for block editor controls.
 *
 * @package Aggressive_Apparel
 */

import { ComboboxControl, Notice, Spinner } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useIconList } from './use-icon-list';

export interface IconComboboxControlProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  allowNone?: boolean;
  help?: string;
}

export function IconComboboxControl({
  label,
  value,
  onChange,
  allowNone = false,
  help,
}: IconComboboxControlProps) {
  const { icons, isLoading, error } = useIconList();

  const iconOptions = useMemo(() => {
    const options = icons.map(({ slug }) => ({
      label: slug,
      value: slug,
    }));

    if (allowNone) {
      return [
        { label: __('None', 'aggressive-apparel'), value: '' },
        ...options,
      ];
    }

    return options;
  }, [allowNone, icons]);

  const iconThumbnails = useMemo(() => {
    const thumbnails = new Map<string, string>();

    icons.forEach(({ slug, svg }) => {
      thumbnails.set(slug, svg);
    });

    return thumbnails;
  }, [icons]);

  if (error) {
    return (
      <Notice status='error' isDismissible={false}>
        {error}
      </Notice>
    );
  }

  if (isLoading) {
    return <Spinner />;
  }

  return (
    <ComboboxControl
      __next40pxDefaultSize
      __nextHasNoMarginBottom
      label={label}
      value={value}
      options={iconOptions}
      onChange={nextValue => onChange(nextValue ?? '')}
      help={help}
      __experimentalRenderItem={({ item }) => {
        const thumbnail = iconThumbnails.get(String(item.value ?? ''));

        return (
          <span className='aggressive-apparel-icon-option'>
            {thumbnail ? (
              <span
                className='aggressive-apparel-icon-option__icon'
                aria-hidden='true'
                dangerouslySetInnerHTML={{ __html: thumbnail }}
              />
            ) : null}
            <span className='aggressive-apparel-icon-option__label'>
              {item.label}
            </span>
          </span>
        );
      }}
    />
  );
}
