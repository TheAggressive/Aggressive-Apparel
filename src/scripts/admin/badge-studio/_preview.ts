/**
 * Server-compiled badge preview.
 *
 * The studio has no style compiler of its own: `Badge_Style_Schema` (PHP) is
 * the only implementation, reached over the badge-studio REST route. That is
 * deliberate — a client mirror of the compiler drifts from the storefront
 * silently, which is exactly how frame widths and border modes diverged before.
 *
 * @package Aggressive_Apparel
 */

import apiFetch from '@wordpress/api-fetch';
import { useEffect, useRef, useState } from '@wordpress/element';
import type { CompiledBadge, StudioConfig } from './_types';

/** Keystroke-to-request delay. Long enough to coalesce typing, short enough to feel live. */
const COMPILE_DEBOUNCE_MS = 140;

/**
 * Compile a badge preview on the server.
 *
 * @param restUrl Compile route URL.
 * @param fields  Flat `badge_*` field map.
 * @param label   Badge label text.
 */
export async function compileBadge(
  restUrl: string,
  fields: Record<string, string>,
  label: string
): Promise<CompiledBadge> {
  return apiFetch<CompiledBadge>({
    url: restUrl,
    method: 'POST',
    data: { fields, label },
  });
}

/**
 * Keep a server-compiled preview in sync with studio state.
 *
 * Seeded from `config.compiled` so the first paint needs no request, then
 * recompiled (debounced) on every edit. A failed or superseded request leaves
 * the previous good compile in place rather than blanking the canvas.
 *
 * @param config Studio bootstrap config.
 * @param fields Current field map.
 * @param label  Current badge label.
 */
export function useCompiledBadge(
  config: StudioConfig,
  fields: Record<string, string>,
  label: string
): CompiledBadge {
  const [compiled, setCompiled] = useState<CompiledBadge>(config.compiled);
  // Monotonic request id — a slow response must never overwrite a newer one.
  const requestId = useRef(0);
  const seeded = useRef(true);

  useEffect(() => {
    // The seed already reflects the initial fields; skip the redundant round trip.
    if (seeded.current) {
      seeded.current = false;
      return;
    }

    const id = ++requestId.current;
    const timer = setTimeout(() => {
      compileBadge(config.restUrl, fields, label)
        .then(next => {
          if (id === requestId.current) {
            setCompiled(next);
          }
        })
        .catch(() => {
          // Keep the last good compile; the canvas stays readable and
          // the next edit retries.
        });
    }, COMPILE_DEBOUNCE_MS);

    return () => clearTimeout(timer);
  }, [config.restUrl, fields, label]);

  return compiled;
}
