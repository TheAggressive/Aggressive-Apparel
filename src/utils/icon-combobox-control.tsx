/**
 * Icon library combobox for block editor controls.
 *
 * @package Aggressive_Apparel
 */

import apiFetch from '@wordpress/api-fetch';
import { ComboboxControl, Notice, Spinner } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

interface IconListItem {
  slug: string;
  svg: string;
}

interface IconListResponse {
  icons: IconListItem[];
}

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
  const [iconList, setIconList] = useState<IconListItem[]>([]);
  const [iconsLoading, setIconsLoading] = useState(true);
  const [iconsError, setIconsError] = useState('');

  useEffect(() => {
    let cancelled = false;

    const loadIcons = async () => {
      setIconsLoading(true);
      setIconsError('');

      try {
        const response = await apiFetch<IconListResponse>({
          path: '/aggressive-apparel/v1/icons',
        });

        if (cancelled) {
          return;
        }

        setIconList(response.icons ?? []);
      } catch {
        if (!cancelled) {
          setIconsError(
            __('Could not load icon library.', 'aggressive-apparel')
          );
        }
      } finally {
        if (!cancelled) {
          setIconsLoading(false);
        }
      }
    };

    void loadIcons();

    return () => {
      cancelled = true;
    };
  }, []);

  const iconOptions = useMemo(() => {
    const options = iconList.map(({ slug }) => ({
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
  }, [allowNone, iconList]);

  const iconThumbnails = useMemo(() => {
    const thumbnails = new Map<string, string>();

    iconList.forEach(({ slug, svg }) => {
      thumbnails.set(slug, svg);
    });

    return thumbnails;
  }, [iconList]);

  if (iconsError) {
    return (
      <Notice status='error' isDismissible={false}>
        {iconsError}
      </Notice>
    );
  }

  if (iconsLoading) {
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
