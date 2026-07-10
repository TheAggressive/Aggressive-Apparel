/**
 * Load the shared icon library list for block editor controls.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchIconList, type IconListItem } from './icon-library';

export interface UseIconListResult {
  icons: IconListItem[];
  isLoading: boolean;
  error: string;
}

/**
 * Fetch the icon library once (module-cached) and expose loading/error state.
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

        if (!cancelled) {
          setIcons(list);
        }
      } catch {
        if (!cancelled) {
          setError(__('Could not load icon library.', 'aggressive-apparel'));
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    };

    void loadIcons();

    return () => {
      cancelled = true;
    };
  }, []);

  return { icons, isLoading, error };
}
