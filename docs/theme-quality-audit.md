# Theme quality audit — October 29–30, 2026

This review covers SEO output, storefront accessibility, resource loading,
security boundaries, and maintainability in the theme repository. It includes
the initial frontend pass and the extended security/readiness review requested
afterward. Business features and settings are retained. Confirmed defects in
pricing isolation, keyboard access, enlarged-text layout, request validation,
and Recently Viewed rendering are corrected as described below.

**Readiness remains conditional.** Automated repository checks are not a
security certification, manual accessibility sign-off, or proof of production
Core Web Vitals. Remaining dependency risks and deployment checks are explicit.

## Implemented findings

| Priority | Finding | Change |
| --- | --- | --- |
| High | `helpers.ts` included CSS-hidden controls, negative-tab-index buttons, and controls disabled by a fieldset in its focus trap. Focusing one of these can leave Tab navigation stuck. | Filter candidates by sequential tab eligibility, disabled state, hidden/inert ancestors, rendered boxes, and computed visibility. Use the same candidates for initial overlay focus. |
| High | Social-proof links and the dismiss button were deliberately excluded from the accessibility tree and tab order, although pointer users could operate them. | Expose visible controls to keyboard and screen-reader users. Keep the existing CSS visibility behavior for hidden toasts and the existing pause-on-focus behavior. Do not introduce live announcements for recurring marketing messages. Replace the test and comments that enshrined the inaccessible behavior. |
| Medium | The search result live region was a sibling of the modal dialog. Assistive technologies can exclude content outside an `aria-modal` dialog. | Put the existing polite live region inside the dialog and make its announcements atomic. |
| Medium | Initial overlay focus was scheduled for the next animation frame without cancellation when the overlay closed. | Cancel pending focus when the focus controller is cleaned up; skip hidden or detached shells. |
| Medium | Empty audience/category combinations received `noindex` only through a Rank Math filter. | Apply the existing empty-result decision through `wp_robots` too. Preserve other directives and site-wide indexing restrictions; leave populated archives alone. |
| Medium | Social proof warmed every notification image at initialization, competing with primary page resources even for notifications never displayed. | Warm only the current notification initially and the next when a toast appears. Deduplicate warmed URLs and use low fetch priority. Timings and notification order stay the same. A 12-image regression fixture starts one preload instead of twelve. |
| Low | Product badge CSS repeated six identical declarations, including mask properties and backdrop filters. | Remove the redundant declarations. Retain the effective property values and reduced-motion override. |

Relevant implementation files:

- `src/interactivity/helpers.ts` and `src/interactivity/use-overlay.ts`
- `includes/Core/class-search-modal.php`
- `includes/WooCommerce/class-social-proof.php` and `src/interactivity/social-proof.ts`
- `includes/WooCommerce/class-audience-landing-pages.php`
- `src/styles/woocommerce/product-badges.css`

## Extended review: implemented findings

| Priority | Finding and evidence | Resolution |
| --- | --- | --- |
| High | Public search cached complete price payloads by query/currency. A regression test reproduced a member's filtered price being returned to a guest. | Remove the shared response cache and emit `Cache-Control: private, no-store`. Keep indexed candidate-ID caching; resolve prices and visibility in the current shopper context. Remove the REST handler's unused index dependency. |
| Medium | Frontend search omitted the REST nonce needed to retain signed-in WordPress permissions, and concatenating `?query=` broke query-style REST URLs. | Send a nonce on authenticated pages only, keep guest search nonce-free, and construct URLs with `URL.searchParams`. Tests cover signed-in/guest requests and plain-permalink routing. |
| High | Filter labels/slugs were escaped using a DOM text node, which does not escape quotes for attributes. An attacker-controlled quoted selection could create an event-handler attribute in the generated pill markup. | Use one pure attribute-safe escaping helper across filters, shared utilities, search, and tab editing. Browser coverage verifies quoted URL selections stay attribute data; unit coverage checks that no injected event attribute is created. |
| Medium | Any logged-in account bypassed public endpoint throttling, including self-service customer accounts. | Exempt site/store administrators, rather than every account. Add subscriber/customer limit regressions and retain staff access. |
| Medium | Public search accepted arbitrarily long queries, and scalar facet schemas with custom sanitizers did not enforce their declared types/lengths. | Bound search queries to 200 characters; validate scalar facet inputs before sanitization and query work. Malformed requests return 400. |
| Medium | Numeric HTML entities outside Unicode's valid range threw `RangeError` in the shared decoder. | Replace invalid references with the replacement character; preserve valid Unicode and single-level entity decoding. |
| Medium | Recently Viewed used an unsupported `data-wp-html` directive, leaving its grid unrendered. | Use an actual watch callback and DOM nodes. Validate API data, protocols, history IDs, and request origin. Bound/cancel requests, preserve query-style REST routing, use viewing order, and request thumbnail images with dimensions. Keep the optional section hidden on failures. |
| Medium | At 200% root text size on a 390px viewport, the fixed header and collection heading/CTA row overflowed horizontally. | Allow those rows to wrap when their contents no longer fit. Retain their normal-width arrangement. Add enlarged-text reflow and forced-colors keyboard tests. |
| High/Critical tooling | The production-only dependency audit omitted the build-tool tree. Its initial metadata reported 6 critical and 47 high findings. | Apply available patched tooling versions and record a two-line wp-env import adaptation for the secured Git client's named export. Add tests of wp-env's actual local clone/fetch/checkout path, ZIP packaging API, serializer API, and FTP download API. See remaining risks below. |

This pass also replaced tests/comments that asserted the unsupported Recently
Viewed directive or treated working links/buttons as inaccessible decoration.
These were concrete correctness and maintenance problems; code authorship or
whether a particular file was AI-generated cannot be established from style.

## Security boundary inventory

| Surface | Review and verification |
| --- | --- |
| All custom REST controllers | Inventory covers search, facets, icon lookup/previews, badge compilation, spec registry/attribute lookup, bulk product specs, size guide CRUD/duplicate/convert/apply, and Printful search/drafts. The test fixture explicitly registers optional controllers so feature defaults cannot hide routes. Anonymous, subscriber, and customer callers are rejected by every privileged permission callback. Actual REST dispatch also checks editorial/shopper denial and store-manager access. |
| Cookie authentication / CSRF | Browser requests to the privileged icon endpoint reject missing and invalid REST nonces and accept the authenticated administrator's valid nonce. Existing save handlers were checked for capability checks plus WordPress/WooCommerce nonce verification. |
| Public content and pricing | Existing tests exclude unpublished, password-protected, hidden, and coming-soon products from public search. New tests cover shopper-price isolation, cache headers, input bounds, and rate-limit roles. |
| SQL | Reviewed catalog constraints and search-index query construction: dynamic values use prepared placeholders, identifiers are validated/prepared, and result sets are bounded. Existing static analysis and query tests remain in place. |
| HTML / SVG / client storage | Reviewed generated-markup sinks, badge SVG allowlists, notices sanitization, tab content sanitizers, and wishlist/history boundaries. Added quoted-attribute, malformed entity, unsafe URL, and Recently Viewed rendering regressions. |
| Remote requests / uploads / updates | Reviewed safe HTTP calls, fixed API hosts, Printful diagram host restrictions and MIME validation, temporary-file cleanup, and GitHub release package host/path validation plus fail-closed SHA-256 verification. Existing updater/import tests retained. |
| Accounts / wishlist / stock alerts | Account presentation continues through WooCommerce authorization. Wishlist account writes continue through WooCommerce's authenticated API. Stock signup uses native WooCommerce services, consent checks, limits and scanner-safe email links; privacy export/retention behavior remains delegated as documented. |
| Supply chain / CI | Locked PHP audit reports no advisories. Full JavaScript audit includes development tools; available fixes are locked and compatibility-tested. A pattern scan of tracked files found no matching private-key/GitHub-token/AWS-access-key signatures; this is not a full historical secret scan. Existing pinned Actions, Dependabot, CodeQL configuration, release verification and build gates were reviewed. CodeQL was not executed locally in this pass. |

## Remaining findings and boundaries

### Three high tooling advisories remain

The final full JavaScript audit reports **0 critical, 3 high, 30 moderate, and
5 low** findings. The earlier production-only check remains clear, but it
cannot establish build-tool safety. `pnpm audit:dependencies` now makes the full
check explicit and exits nonzero while high findings remain. No advisories have
been ignored or suppressed.

- `extract-zip` 2.0.1: [symlink target traversal](https://github.com/advisories/GHSA-jmr9-qjv8-65gv) and [writes through a final-component symlink](https://github.com/advisories/GHSA-7pqw-9j4j-h8q3). The npm audit response suggested 2.0.2, but an installation attempt and direct registry inspection established that 2.0.2 is not published; GitHub lists no patched release. An unavailable override was removed. This remains in developer download tooling. Restrict archive sources to approved official URLs; replace or patch the extractor with dedicated adversarial archive tests before accepting arbitrary sources.
- Older Zod consumers: [oversized collection validation](https://github.com/advisories/GHSA-mrhx-c7xc-m3w5). React Hooks tooling was moved to the patched compatible v4 line. Chromium BiDi consumers still depend on the old API and access `error.errors`; replacing their root Zod with v4 would silently break error handling. Migrate those consumers with protocol-level tests when compatible upstream releases are available.

These identified paths belong to development tooling, not the packaged
storefront. That limits exposure but does not make the advisories resolved.
The moderate/low findings also remain maintenance work. A blanket
"secure" or "enterprise-ready" verdict would be unsupported.

### Archive SEO depends on the configured SEO plugin

The sampled Studio shop, audience, and paginated archive responses had no
canonical link or meta description. Product pages had the core singular
canonical. Internal search and cart pages had `noindex`, as expected.

The audience canonical, description, and sitemap integrations currently target
Rank Math. Confirm these outputs on the production installation with its actual
SEO plugin and settings. Adding another unconditional metadata emitter in the
theme risks duplicate or conflicting canonicals. The WooCommerce documentation
now states this dependency explicitly.

### Studio payment-plugin notices contaminate HTML and REST responses

The local PayPal integration prints a notice about checking Google Pay
availability before `wp_loaded`. Those notices occur before the page document
and before REST JSON. The REST body consequently fails JSON parsing. The desktop
axe findings from the sampled pages were link styling failures in these printed
notices, rather than storefront markup. Resolve the plugin initialization issue
and production error-display configuration separately; the theme should not
silence another component's diagnostics.

### Large settings and styling files remain maintenance hotspots

The existing file-length gate flags settings, icon management, product-tab
administration, and form styling among the files over 800 lines. They remain
below the hard limit. Splitting these by arbitrary line counts would add churn;
extract collaborators when changing a specific responsibility and retain the
public hooks and settings contracts.

### Deployment-scale behavior still needs measurement

The rate limiter uses atomic counters with a persistent object cache, but its
transient fallback is a read/modify/write operation and is not atomic across
concurrent requests. It is application-level abuse reduction, not a replacement
for infrastructure rate limiting. Verify the production cache/proxy setup.

Audience sitemap generation runs a newest-product lookup for each audience and
category combination. Rank Math caches generated output, but cold generation
still grows with the audience/category cross-product. Load-test cache misses
with the real catalog size before assigning a large-catalog performance budget.

## Review evidence

Read-only Studio scans cover home, shop, internal search, empty cart, an existing
product, an audience archive, a color-filtered catalog, and catalog page 2, at
1440px and 390px widths. Each sampled response had one title, one H1, and one main
landmark. The sampled homepage hero was eager/high-priority. Mobile axe scans
reported no WCAG A/AA violations; desktop findings came from plugin notices.

The source review also checked template landmarks and heading levels, crawlable
pagination, audience redirects and metadata hooks, font preloads, catalog and
hover image priorities, conditional asset loading, reduced-motion styles, and
shared overlay/navigation behavior. Existing protections in these areas were
retained. This is a targeted review of critical paths, not a claim that every
possible plugin combination or user-created block layout has been certified.

No production settings, stored content, or Studio database fixtures were changed.
Fixture-based browser tests use the repository's isolated native WordPress site.

## Validation

- Full `qa:fast` gate: lint, formatting, TypeScript, JS/tool tests, production
  dependency audit, build, Composer validation, PHPStan, and PHP unit tests.
- Targeted PHP integration tests for audience SEO and social-proof output.
- Browser regressions for accessibility, navigation, search, toast hit testing
  and keyboard access, and size charts.

Initial-pass results: 539 JavaScript tests across 64 suites, 693 PHP unit tests with 3,143
assertions, and 24 browser tests passed. The targeted PHP integration run passed
14 tests. All 16 read-only Studio page/viewport scans completed. Changed PHP
files also passed PHP_CodeSniffer, and `git diff --check` passed.

These checks establish correctness of the changes; they do not establish
production Core Web Vitals gains. Real LCP, INP, and CLS need production field
data. Studio runs Xdebug and additional plugins, so its timings are unsuitable as production performance
claims. Screen-reader announcements still need manual assistive-technology
verification; axe cannot establish that they are spoken correctly.

### Extended validation

The completed extended checks passed:

- `qa:fast`: lint, formatting, TypeScript, production dependency audit, build,
  Composer validation, PHPStan, **547 JavaScript tests across 66 suites**, **173
  tooling tests**, and **693 PHP unit tests / 3,144 assertions**.
- All PHP integration, security, accessibility and performance suites: **223
  tests / 853 assertions**. Combined with the unit suite: **916 PHP tests**.
- Complete browser gate against the stable build: **121 passed**, with no
  skipped or flaky cases. This includes real Store API history rendering,
  signed-in/guest REST nonce behavior, quoted filter selections, live security
  headers, axe scans, keyboard focus, enlarged-text reflow, forced colors,
  catalog navigation, cart/checkout presentation and wishlist failure paths.
- Changed PHP files pass PHP_CodeSniffer; `git diff --check` passes. The native
  runs use WordPress 7.0.2, WooCommerce 11.2.1 and host PHP 8.5.6. The repository's
  pinned CI PHP lanes remain authoritative for version compatibility.

Browser validation ran after the production build finished. The toast
hit-testing test uses reduced motion to avoid sampling a moving target, and
retry timing measurements use a monotonic clock. No assertions were weakened.

The full development dependency audit still intentionally reports the three
high findings above; its nonzero exit is not counted as a passing security gate.

### Deployment and manual acceptance still required

- Verify production SEO output with its real Rank Math configuration: one title,
  canonical and description; self-canonical paginated audience routes; empty
  combinations excluded; sitemap URLs fetchable and aligned with canonicals.
- Resolve the separate payment-plugin initialization notice and verify production
  REST JSON and error-display settings. The theme has not silenced diagnostics
  or changed another plugin's code.
- Run NVDA/Firefox and VoiceOver/Safari checks for search announcements, quick
  view, size guide, mini-cart, filter drawer, validation errors and focus return.
  Automated axe/keyboard/forced-colors checks do not establish spoken output or
  every platform's accessibility behavior.
- Measure deployed mobile/desktop field data. Target p75 LCP <=2.5s, INP <=200ms,
  and CLS <=0.1 across representative routes, following [Google's Web Vitals
  guidance](https://web.dev/articles/vitals). Local correctness tests and resource
  scheduling changes do not prove those production thresholds.
- Verify hosting protections and operational controls: origin/cache behavior,
  TLS, privileged account access, backups/restore, monitoring and plugin updates.
  These are not controlled or certified by the theme repository.

## Reference guidance

- [WAI-ARIA modal dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/): modal content and keyboard focus requirements.
- [WAI-ARIA discussion of modal live regions](https://github.com/w3c/aria/issues/1854): assistive-technology differences for announcements outside dialogs.
- [Google: optimize LCP](https://web.dev/articles/optimize-lcp): prioritize primary content and avoid competing unnecessary resource requests.
- [Google: noindex](https://developers.google.com/search/docs/crawling-indexing/block-indexing): supported indexing directives.
- [WordPress REST authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/): cookie authentication and REST nonces.
