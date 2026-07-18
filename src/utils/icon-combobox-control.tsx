/**
 * Icon library combobox for block editor controls.
 *
 * @package Aggressive_Apparel
 */

import { ComboboxControl, Notice, Spinner } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
  fetchIconSvg,
  getCachedIconSvg,
  prefetchIconThumbnails,
  subscribeIconThumbnails,
} from './icon-library';
import { useIconList } from './use-icon-list';

export interface IconComboboxControlProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  allowNone?: boolean;
  help?: string;
}

/**
 * Thumbnail that waits for the bulk prefetch, then fetches oversized icons.
 */
function IconOptionThumbnail({ slug }: { slug: string }) {
  const [svg, setSvg] = useState(() => getCachedIconSvg(slug) ?? '');

  useEffect(() => {
    let cancelled = false;

    const cached = getCachedIconSvg(slug);
    if (cached) {
      setSvg(cached);
      return;
    }

    const unsubscribe = subscribeIconThumbnails(() => {
      const next = getCachedIconSvg(slug);
      if (next && !cancelled) {
        setSvg(next);
      }
    });

    void prefetchIconThumbnails()
      .then(async () => {
        if (cancelled) {
          return;
        }

        const afterBulk = getCachedIconSvg(slug);
        if (afterBulk) {
          setSvg(afterBulk);
          return;
        }

        // Omitted from bulk (oversized) — fetch just this slug.
        const markup = await fetchIconSvg(slug);
        if (!cancelled && markup) {
          setSvg(markup);
        }
      })
      .catch(() => {
        void fetchIconSvg(slug).then(markup => {
          if (!cancelled && markup) {
            setSvg(markup);
          }
        });
      });

    return () => {
      cancelled = true;
      unsubscribe();
    };
  }, [slug]);

  if (!svg) {
    return (
      <span
        className='aggressive-apparel-icon-option__icon'
        aria-hidden='true'
      />
    );
  }

  return (
    <span
      className='aggressive-apparel-icon-option__icon'
      aria-hidden='true'
      dangerouslySetInnerHTML={{ __html: svg }}
    />
  );
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
        const slug = String(item.value ?? '');

        return (
          <span className='aggressive-apparel-icon-option'>
            {slug ? <IconOptionThumbnail slug={slug} /> : null}
            <span className='aggressive-apparel-icon-option__label'>
              {item.label}
            </span>
          </span>
        );
      }}
    />
  );
}
