/**
 * Fetch icon SVG markup for the block editor canvas.
 *
 * Uses the shared icon library cache. Thumbnail prefetch can seed the map,
 * but canvas previews use the per-slug route and do not wait on the full
 * library. Size is applied via CSS on the wrapper — no refetch while
 * dragging a size slider.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import { fetchIconSvg } from './icon-library';

export interface UseIconPreviewResult {
  svg: string;
  isLoading: boolean;
}

/**
 * Load sanitized SVG markup for an icon slug from the theme REST API.
 */
export function useIconPreview(slug: string): UseIconPreviewResult {
  const [svg, setSvg] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (!slug) {
      setSvg('');
      setIsLoading(false);
      return;
    }

    let cancelled = false;

    const loadPreview = async () => {
      setIsLoading(true);

      try {
        const markup = await fetchIconSvg(slug);

        if (!cancelled) {
          setSvg(markup);
        }
      } catch {
        if (!cancelled) {
          setSvg('');
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    };

    void loadPreview();

    return () => {
      cancelled = true;
    };
  }, [slug]);

  return { svg, isLoading };
}
