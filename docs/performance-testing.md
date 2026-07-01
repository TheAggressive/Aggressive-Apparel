# Local performance testing

Lighthouse performance budgets run locally against the existing WordPress site.
They are intentionally separate from pre-commit hooks and GitHub Actions.

## Requirements

- Install dependencies with `pnpm install`.
- Serve the site at `http://localhost:9910` and test while logged out.
- Build production assets before making release decisions.

The runner discovers Linux Chrome, Chromium, or an existing Playwright Chromium.
Set `CHROME_PATH=/absolute/path/to/chrome` when automatic discovery is not
suitable. WSL users can install the libraries required by an existing Playwright
browser with:

```sh
pnpm exec playwright install-deps chromium
```

Windows Chrome is intentionally not selected from WSL because its DevTools port
is not reachable through WSL localhost in every environment.

## Commands

- `pnpm run perf:quick` audits `/shop/` once and reports budget warnings.
- `pnpm run perf:report` audits the home, shop, and a discovered product once
  without enforcing budgets.
- `pnpm run perf:budget` audits those pages three times and enforces the median
  performance budgets.
- `pnpm run perf` creates a production build before running the enforced budget.
- `pnpm run preflight` runs QA, creates a production build, and then enforces the
  performance budget.

Reports are written to `.lighthouseci/reports/` and are excluded from Git.

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
