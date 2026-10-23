/** Bound the entire response, including decoding a stalled response body. */
export const REQUEST_TIMEOUT_MS = 15_000;

export async function requestJson(
  url: string,
  init: RequestInit = {}
): Promise<{ response: Response; data: unknown }> {
  const controller = new AbortController();
  let timer: ReturnType<typeof setTimeout> | undefined;
  const timeout = new Promise<never>((_resolve, reject) => {
    timer = setTimeout(() => {
      controller.abort();
      reject(new Error('Wishlist request timed out'));
    }, REQUEST_TIMEOUT_MS);
  });
  try {
    return await Promise.race([
      (async () => {
        const response = await fetch(url, {
          ...init,
          mode: 'same-origin',
          signal: controller.signal,
        });
        const data: unknown =
          response.status === 204 ? null : await response.json();
        return { response, data };
      })(),
      timeout,
    ]);
  } finally {
    clearTimeout(timer);
  }
}
