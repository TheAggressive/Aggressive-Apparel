# Local performance testing

Lighthouse performance budgets run locally against the existing WordPress site.
They are intentionally separate from pre-commit hooks and GitHub Actions.

## Requirements

- Use Node.js 22.19 or newer. The project overrides Lighthouse CI's older
  bundled audit engine with Lighthouse 13 so it remains compatible with current
  Chromium releases.
- Install dependencies with `pnpm install`.
- Test while logged out.

The runner discovers Linux Chrome, Chromium, or an existing Playwright Chromium.
Set `CHROME_PATH=/absolute/path/to/chrome` when automatic discovery is not
suitable.

On WSL/Linux without Chrome system libraries, the runner installs bundled
libraries automatically on first use. Manual setup is still available with
`pnpm run perf:setup`.

Windows Chrome is intentionally not selected from WSL because its DevTools port
is not reachable through WSL localhost in every environment.

## Commands

### Automated (recommended)

These commands handle prerequisites for you:

- Chrome libraries on Linux/WSL (first run only, cached in
  `.cache/lighthouse-chrome-deps/`).
- `wp-env` startup when the local site is down (`LHCI_ENSURE_SITE=1`).

```sh
pnpm run perf:auto:quick    # shop audit, auto start wp-env if needed
pnpm run perf:auto:budget   # full budget run, auto start wp-env if needed
pnpm run perf:auto          # production build + full budget run
```

### Manual control

Use these when `wp-env` is already running and you want faster iteration:

- `pnpm run perf:setup` downloads Linux libraries for Playwright Chromium when
  sudo is unavailable (WSL-friendly).
- `pnpm run perf:quick` audits `/shop/` once and reports budget warnings.
- `pnpm run perf:report` audits the home, shop, and a discovered product once
  without enforcing budgets.
- `pnpm run perf:budget` audits those pages three times and enforces the median
  performance budgets.
- `pnpm run perf` creates a production build before running the enforced budget.
- `pnpm run preflight` runs QA, creates a production build, and then enforces the
  performance budget.

Set `LHCI_ENSURE_SITE=1` on any perf command to opt into automatic `wp-env`
startup without using the `perf:auto:*` aliases.

Reports are written to `.lighthouseci/reports/` and are excluded from Git.
Each run replaces reports for that mode (`quick`, `report`, or `budget`); older
runs in the same mode are removed automatically. Set `LHCI_KEEP_REPORTS=1` to
keep prior reports. Temporary Lighthouse CI files in `.lighthouseci/` are also
cleared on each run.

## Configuration

Use a different local origin with `LHCI_BASE_URL`, for example:

```sh
LHCI_BASE_URL=http://localhost:8888 pnpm run perf:quick
```

The full checks discover a published product through the WooCommerce Store API.
Override it when needed:

```sh
LHCI_PRODUCT_URL=http://localhost:9910/product/example/ pnpm run perf:budget
```

The initial enforced limits cover LCP, CLS, Total Blocking Time, transfer sizes,
and request counts. Performance score and server response time begin as warnings
because local execution naturally varies.
