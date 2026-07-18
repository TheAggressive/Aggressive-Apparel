/**
 * Load the shared icon library list for block editor controls.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
  fetchIconList,
  getCachedIconSvg,
  prefetchIconThumbnails,
  subscribeIconThumbnails,
  type IconListItem,
} from './icon-library';

export interface UseIconListResult {
  icons: IconListItem[];
  isLoading: boolean;
  error: string;
}

/**
 * Merge cached thumbnail SVGs onto the slug list.
 */
function withCachedSvgs(list: IconListItem[]): IconListItem[] {
  return list.map(item => {
    const svg = getCachedIconSvg(item.slug);

    if (undefined === svg) {
      return item;
    }

    return {
      ...item,
      svg,
    };
  });
}

/**
 * Fetch icon slugs immediately, then fill thumbnails as they arrive.
 */
export function useIconList(): UseIconListResult {
  const [icons, setIcons] = useState<IconListItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    const loadIcons = async () => {
      setIsLoading(true);
      setError('');

      try {
        const list = await fetchIconList();

        if (cancelled) {
          return;
        }

        setIcons(withCachedSvgs(list));
        setIsLoading(false);

        // Prefetch is owned by the list hook (not fetchIconList) so canvas-only
        // preview mounts do not pull the bulk thumbnail payload.
        void prefetchIconThumbnails().catch(() => {
          // Labels remain available even if thumbnails fail.
        });
      } catch {
        if (!cancelled) {
          setError(__('Could not load icon library.', 'aggressive-apparel'));
          setIsLoading(false);
        }
      }
    };

    void loadIcons();

    const unsubscribe = subscribeIconThumbnails(() => {
      if (cancelled) {
        return;
      }

      setIcons(current => {
        if (!current.length) {
          return current;
        }

        return withCachedSvgs(current);
      });
    });

    return () => {
      cancelled = true;
      unsubscribe();
    };
  }, []);

  return { icons, isLoading, error };
}
