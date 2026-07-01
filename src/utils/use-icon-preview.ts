/**
 * Fetch icon SVG markup for the block editor canvas.
 *
 * Fetches once per slug and scales via CSS on the wrapper — no refetch while
 * dragging a size slider.
 *
 * @package Aggressive_Apparel
 */

import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';

interface IconPreviewResponse {
  slug: string;
  svg: string;
}

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
        const response = await apiFetch<IconPreviewResponse>({
          path: `/aggressive-apparel/v1/icons/${encodeURIComponent(slug)}`,
        });

        if (!cancelled) {
          setSvg(response.svg ?? '');
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
