---
name: verify
description: Build, launch, and drive this theme's blocks in the running wp-env WordPress site to verify changes end-to-end.
---

# Verifying theme changes at runtime

## Environment

- wp-env serves the site at `http://localhost:9910` (tests: 9920). Check
  with `curl -s -o /dev/null -w "%{http_code}" http://localhost:9910/`;
  start with `pnpm env:start` if down.
- Admin login: `admin` / `password` at `/wp-login.php`.
- WP-CLI: `pnpm cli <wp args>` (runs `wp-env run cli wp`). The theme dir
  is mounted at `/var/www/html/wp-content/themes/aggressive-apparel`
  inside the container — write a block-markup file into the theme dir and
  `pnpm cli post create <container-path> --post_type=page --post_status=publish --porcelain`
  to make test pages. Delete the file afterwards.

## Browser (WSL2 — no system Chromium, no sudo)

Playwright browsers are cached in `~/.cache/ms-playwright/` but the
system lacks their shared libs (libnss3, libnspr4, libasound). Fix
without sudo — download and extract the debs, then LD_LIBRARY_PATH:

```bash
cd <scratchpad> && mkdir -p libs && cd libs
apt-get download libnspr4 libasound2t64        # libnss3 may 404 on security mirror:
curl -sO http://archive.ubuntu.com/ubuntu/pool/main/n/nss/<latest libnss3 amd64 deb>
for d in *.deb; do dpkg-deb -x "$d" extracted; done
cd .. && npm init -y && npm i playwright-core@1.55
LD_LIBRARY_PATH=$PWD/libs/extracted/usr/lib/x86_64-linux-gnu node <script>.js
# executablePath: ~/.cache/ms-playwright/chromium-1194/chrome-linux/chrome
```

Do NOT try Windows Chrome over CDP — WSL is in NAT mode; Windows
loopback is unreachable from WSL.

## Driving gotchas

- After changing a `render.php`, sync it: the build copies
  `src/**/render.php` → `build/**` only during `pnpm build:interactivity`.
- The block editor never reaches `networkidle` (heartbeat) — wait for
  `iframe[name="editor-canvas"]` instead.
- Clicking a container block in the canvas selects its inner block.
  Select wrappers via the data API:
  `wp.data.dispatch('core/block-editor').selectBlock(clientId)` after
  `waitForFunction` on `getBlocks().length > 0`.
- Front-end debug tooling (parallax / animate-on-scroll Debug Mode) only
  renders for users with `edit_posts` — log in first; logged-out fetches
  are the negative test.
- Sidebar (editor chrome) styles: `--wp-components-color-*` variables do
  not resolve in every WP version's sidebar — computed styles collapsing
  to `0px none` means a var() had no fallback.

## Performance verification (scroll/animation work)

Bench with the cached-Chromium setup above plus CDP:

```js
const cdp = await page.context().newCDPSession(page);
await cdp.send('Emulation.setCPUThrottlingRate', { rate: 6 }); // expose main-thread cost
await cdp.send('Performance.enable'); // getMetrics before/after → LayoutCount,
// RecalcStyleCount/Duration, ScriptDuration deltas across a driven scroll
```

- Drive scroll with a rAF loop calling `window.scrollTo` (triangle wave),
  count frames + >20ms/>34ms deltas + PerformanceObserver longtasks.
- "Same result" proof: dump `getComputedStyle().translate/scale/opacity`
  for all layers at fixed scroll offsets, diff old vs new build.
- Bisecting a recalc storm: neuter suspects via `addInitScript` overrides —
  scoped `textContent` setter no-op, `DOMTokenList.add/remove` filters,
  `--kill=<selector>` element removal after load. ~50ms recalcs on small
  DOMs = document-wide invalidation: check for body-level `:has()` first
  (banned; see bin/check-design-system-css.sh) — `textContent =` is a
  childList mutation that triggers it.
