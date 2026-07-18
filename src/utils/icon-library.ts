/**
 * Shared icon library fetches for the block editor.
 *
 * Slugs load first (tiny). Brand thumbnails come from a build-time catalog
 * served by REST with ETag revalidation. Canvas previews use /icons/{slug}
 * and never wait on the bulk thumbnail payload.
 *
 * @package Aggressive_Apparel
 */

import apiFetch from '@wordpress/api-fetch';

export interface IconListItem {
  slug: string;
  /** Present after thumbnail prefetch. */
  svg?: string;
}

interface IconListResponse {
  icons: IconListItem[];
  catalogHash?: string;
}

interface IconThumbnailsResponse {
  thumbnails: Record<string, string>;
  catalogHash?: string;
}

interface IconPreviewResponse {
  slug: string;
  svg: string;
}

let cachedList: IconListItem[] | null = null;
let listPromise: Promise<IconListItem[]> | null = null;
let listEtag: string | null = null;

let thumbnailsPromise: Promise<void> | null = null;
let thumbnailsLoaded = false;
let thumbnailsEtag: string | null = null;

const svgCache = new Map<string, string>();
const svgPromises = new Map<string, Promise<string>>();
const thumbnailListeners = new Set<() => void>();

/**
 * Notify subscribers when the SVG cache gains new thumbnails.
 */
function notifyThumbnailListeners(): void {
  thumbnailListeners.forEach(listener => {
    listener();
  });
}

/**
 * Subscribe to thumbnail cache updates (combobox progressive fill).
 */
export function subscribeIconThumbnails(listener: () => void): () => void {
  thumbnailListeners.add(listener);

  return () => {
    thumbnailListeners.delete(listener);
  };
}

/**
 * Seed the per-slug SVG map from a thumbnail map.
 */
function seedSvgCacheFromMap(thumbnails: Record<string, string>): void {
  let seeded = false;

  Object.entries(thumbnails).forEach(([slug, svg]) => {
    if (slug && svg && !svgCache.has(slug)) {
      svgCache.set(slug, svg);
      seeded = true;
    }
  });

  if (seeded) {
    notifyThumbnailListeners();
  }
}

/**
 * Fetch JSON with ETag support (handles 304 without a response body).
 */
async function fetchJsonWithEtag<T>(
  path: string,
  etag: string | null
): Promise<{ data: T | null; etag: string | null; notModified: boolean }> {
  const headers: Record<string, string> = {};

  if (etag) {
    headers['If-None-Match'] = etag;
  }

  const response = await apiFetch<Response, false>({
    path,
    parse: false,
    headers,
  });

  const nextEtag = response.headers.get('ETag');

  if (response.status === 304) {
    return {
      data: null,
      etag: nextEtag ?? etag,
      notModified: true,
    };
  }

  if (!response.ok) {
    throw new Error(`Icon library request failed (${response.status}).`);
  }

  const data = (await response.json()) as T;

  return {
    data,
    etag: nextEtag,
    notModified: false,
  };
}

/**
 * Load icon slugs once per editor session (no SVG markup).
 */
export function fetchIconList(): Promise<IconListItem[]> {
  if (cachedList) {
    return Promise.resolve(cachedList);
  }

  if (!listPromise) {
    listPromise = fetchJsonWithEtag<IconListResponse>(
      '/aggressive-apparel/v1/icons',
      listEtag
    )
      .then(({ data, etag, notModified }) => {
        if (etag) {
          listEtag = etag;
        }

        if (notModified && cachedList) {
          return cachedList;
        }

        const icons = data?.icons ?? [];
        cachedList = icons;
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
 * Prefetch picker thumbnails once per editor session.
 *
 * Uses the build-time brand catalog on the server. Oversized icons are
 * omitted and loaded on demand via fetchIconSvg().
 */
export function prefetchIconThumbnails(): Promise<void> {
  if (thumbnailsLoaded) {
    return Promise.resolve();
  }

  if (!thumbnailsPromise) {
    thumbnailsPromise = fetchJsonWithEtag<IconThumbnailsResponse>(
      '/aggressive-apparel/v1/icons/thumbnails',
      thumbnailsEtag
    )
      .then(({ data, etag, notModified }) => {
        if (etag) {
          thumbnailsEtag = etag;
        }

        if (!notModified && data?.thumbnails) {
          seedSvgCacheFromMap(data.thumbnails);
        }

        thumbnailsLoaded = true;
      })
      .catch(error => {
        // Allow a later mount to retry after a failed request.
        thumbnailsPromise = null;
        throw error;
      });
  }

  return thumbnailsPromise;
}

/**
 * Read a cached thumbnail SVG without triggering a network request.
 */
export function getCachedIconSvg(slug: string): string | undefined {
  return svgCache.get(slug);
}

/**
 * Load SVG markup for one slug without waiting on the bulk thumbnail prefetch.
 */
export async function fetchIconSvg(slug: string): Promise<string> {
  if (!slug) {
    return '';
  }

  const cached = svgCache.get(slug);
  if (undefined !== cached) {
    return cached;
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
      notifyThumbnailListeners();
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
