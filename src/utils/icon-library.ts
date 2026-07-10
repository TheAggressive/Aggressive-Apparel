/**
 * Shared icon library fetches for the block editor.
 *
 * Dedupes the full-list REST call across icon pickers and seeds a per-slug
 * SVG cache so canvas previews skip a second round-trip when the list is warm.
 *
 * @package Aggressive_Apparel
 */

import apiFetch from '@wordpress/api-fetch';

export interface IconListItem {
  slug: string;
  svg: string;
}

interface IconListResponse {
  icons: IconListItem[];
}

interface IconPreviewResponse {
  slug: string;
  svg: string;
}

let cachedList: IconListItem[] | null = null;
let listPromise: Promise<IconListItem[]> | null = null;

const svgCache = new Map<string, string>();
const svgPromises = new Map<string, Promise<string>>();

/**
 * Seed the per-slug SVG map from a list payload.
 */
function seedSvgCache(icons: IconListItem[]): void {
  icons.forEach(({ slug, svg }) => {
    if (slug && svg && !svgCache.has(slug)) {
      svgCache.set(slug, svg);
    }
  });
}

/**
 * Load the full icon library once per editor session.
 */
export function fetchIconList(): Promise<IconListItem[]> {
  if (cachedList) {
    return Promise.resolve(cachedList);
  }

  if (!listPromise) {
    listPromise = apiFetch<IconListResponse>({
      path: '/aggressive-apparel/v1/icons',
    })
      .then(response => {
        const icons = response.icons ?? [];
        cachedList = icons;
        seedSvgCache(icons);
        return icons;
      })
      .catch(error => {
        // Allow a later mount to retry after a failed request.
        listPromise = null;
        throw error;
      });
  }

  return listPromise;
}

/**
 * Load SVG markup for one slug, reusing list/preview caches when available.
 */
export async function fetchIconSvg(slug: string): Promise<string> {
  if (!slug) {
    return '';
  }

  const cached = svgCache.get(slug);
  if (undefined !== cached) {
    return cached;
  }

  // Wait for an in-flight full-list fetch so the canvas can reuse its SVGs.
  if (listPromise) {
    try {
      await listPromise;
      const afterList = svgCache.get(slug);
      if (undefined !== afterList) {
        return afterList;
      }
    } catch {
      // Fall through to the per-slug endpoint.
    }
  }

  const inflight = svgPromises.get(slug);
  if (inflight) {
    return inflight;
  }

  const request = apiFetch<IconPreviewResponse>({
    path: `/aggressive-apparel/v1/icons/${encodeURIComponent(slug)}`,
  })
    .then(response => {
      const svg = response.svg ?? '';
      svgCache.set(slug, svg);
      return svg;
    })
    .catch(() => {
      svgPromises.delete(slug);
      return '';
    })
    .finally(() => {
      svgPromises.delete(slug);
    });

  svgPromises.set(slug, request);
  return request;
}
