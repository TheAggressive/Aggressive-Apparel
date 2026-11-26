# Theme audit & Design System V2 — November 19, 2026

Branch `feat/design-system-v2-audit`. This pass audited the theme, fixed
verified defects, extended the design system, added an editorial pattern
library and a 12-section homepage. It builds on the October 29–30 audit
([`theme-quality-audit.md`](theme-quality-audit.md)), whose security,
accessibility and dependency findings were not re-litigated here.

## Baseline (before any change)

| Gate | Result |
| --- | --- |
| `pnpm build` | pass |
| `pnpm lint:all` | pass (3 file-length warnings > 800 lines, pre-existing) |
| `pnpm test:js` | 727 / 727 |
| `pnpm test:php` | 1022 tests, 4548 assertions, pass |
| `pnpm phpstan` (level 8) | no errors |
| Horizontal overflow, 6 pages × 5 widths × 2 schemes | none |

Passing gates did not mean the theme was defect-free: the findings below were
all present while every gate was green.

## Findings and resolutions

Severity follows the brief: Critical (security, data, serious commerce
failure), High (broken workflow, significant a11y, major regression), Medium
(responsive, inconsistency, maintainability), Low (cleanup).

| Sev. | Finding (evidence) | Resolution |
| --- | --- | --- |
| High | **12 theme patterns contained 25 invalid blocks** — inserting them showed "unexpected or invalid content". Covers with a background color lacked the `has-background` class the current save emits and carried wrong overlay dim classes; Image borders sat on `<figure>`; `product-duet` saved a margin Product Collection doesn't serialize. Found by the new editor-validity test. | Markup repaired in `about-page`, `animated-category-mosaic`, `category-landing-composition`, `community-spotlight`, `gift-guide-grid`, `hero-carousel-product-launch`, `homepage-drop-composition`, `homepage-seasonal-shop`, `product-duet`, `shop-the-look`, `studio-story-scroll`, `testimonial-large-quote`. New `tests/e2e/pattern-validity.spec.ts` parses every registered theme pattern in the real editor. |
| Medium | **Responsive product grids rendered one column fewer than configured** (`src/styles/woocommerce/blocks.css`). WooCommerce's track formula assumes a 1.25em gap; the theme's 2rem gap made "Columns: 5" archives show 4 and "Columns: 4" patterns show 3. | Track minimum re-derived from the real gap; archive, search, related-products and cart templates set to the 4 they displayed, so those pages are unchanged (verified at 375/1024/1440/1920). CSS contract test. |
| Medium | **Add-to-cart pill wrapped onto two lines in two-up mobile cards** — the container query compacted the button only for 200–320px cards, not the ~150–190px cards a phone grid produces. | Compact every slot under 320px; tighter inline padding under 200px. Contract test (fails on the old CSS). |
| Medium | **Token text contrast failed AA in three pairings**: light `foreground-muted` / `accent` on `surface-sunken` (3.8 / 4.48), dark `accent` on `surface-muted` (4.30). No live text use of those pairs was found, so this was latent. | Warm light neutral ladder (surface 87% grey → 95% paper) and dark `surface-muted` 26% → 24%. `token-contracts.test.ts` asserts every text role on every surface in both schemes. |
| Medium | **Undefined preset references**: footer `textColor: "bone"` (no such color — uncoloured footer children would inherit the light-mode black on the black footer); `home-storefront` used the undeclared Pirata One family (resolved only because it happened to be installed in the local Font Library). | Footer uses `white` like its 13 explicit children; Pirata One removed. A contract test now fails on any preset slug in templates, parts, patterns or section styles that doesn't resolve. |
| Medium | **Inconsistent page gutters**: `<main>` padding was 16px (shop, product, page), ~32px (cart, account) or ~36px (checkout, search). | One fluid `gutter` preset (16px → 48px) on every template and the footer. |
| Medium | **Documentation drift**: the design-system color table listed stale values and omitted four adaptive tokens; `design-tokens.md` omitted two palette colors; the lint's hand-kept block-style allowlist rejected the registered `brand-stripe`. | Tables rewritten (roles only — values live in `theme.json`); tests assert the docs list every color, type role, layout preset and aspect ratio; the lint now reads `register_block_style()` and `styles/*.json`. |
| Medium | **No semantic spacing, display type or image-ratio system** — sections used ad-hoc numeric spacing; every heading was condensed Bebas caps with H1 topping out at 48px; no brand aspect ratios. | Design System V2 (below). |
| Low | Stale comment in `_account-layout.css` claimed `foreground-muted` fails AA. | Corrected. |

### Content issues on the live homepage (not code; not changed)

The homepage that renders locally (and, by the same mechanism, in production)
is a Site Editor customisation stored in the database (`wp_template` "home"),
not `templates/home.html`. Its problems are content, so they were left alone:
category and accessories tiles are empty black boxes (no images set), the
"Featured Tees" grid shows a hoodie and sunglasses, and sections are
compressed. The new homepage addresses these, but is **not live** until that
customisation is reset (see "Adopting" below).

## Design System V2

All values live in `theme.json`; `tokens.css` aliases them. Usage rules:
[`design-system.md`](design-system.md).

- **Color.** Warm paper light neutrals (`surface`, `surface-muted`,
  `surface-sunken`, borders); AA enforced for every text/surface pair in both
  schemes. Editorial headings take their section's text color, so red is the
  deliberate accent (eyebrows, CTAs) rather than every heading.
- **Layout rhythm.** `gutter` plus `section-compact` (40→64px),
  `section-standard` (64→128px), `section-editorial` (80→160px),
  `section-immersive` (96→208px); section style variations adopt them.
- **Typography.** New roles `display` (Campaign Display), `headline`
  (Headline (Sans) — Space Grotesk, sentence case) and `lead`; H1/H2 one
  step larger. Existing roles unchanged.
- **Images.** `campaign` (21:9) and `editorial-portrait` (4:5) ratios;
  documented rules for focal points, text-safe overlays, loading priority, alt
  text and placeholders. Product cards keep the existing square media slot.
- **Components.** Editorial Grid product-collection style (frameless, the
  Shop archive's card); stretched-link category tiles.
- **Preview.** The Design System Preview pattern shows the new roles, a
  rhythm ruler and an Editorial Grid.

## Patterns and homepage

Twelve "Editorial Sections" patterns plus the `editorial-homepage`
composition, rendered by `templates/home.html`. Store data is resolved at
render time (`WooCommerce\Editorial_Catalog`), never hardcoded; Best Sellers
ranks by real sales only, falling back to Featured products labelled "Staff
picks", or nothing. Details: [`design-system.md`](design-system.md#editorial-pattern-library).

## Verification

Screenshots (home, shop, category, product, cart, checkout) were captured
before and after at 375, 768, 1024, 1440 and 1920px in light and dark mode;
no page has horizontal overflow at any width. The new homepage was previewed
by temporarily setting the local database `home` customisation to draft,
then restored.

New automated coverage: `token-contracts.test.ts` (contrast, preset
resolution, doc drift), CSS contracts (editorial roles, card buttons, grid
columns), `pattern-validity.spec.ts`, `class-test-editorial-catalog.php`
(term resolution, URL fallback, attribute vs category filtering, sales
detection, all three Best Sellers branches, grid markup round trip), and two
design-system lint tests. Final gate results are in the pull request.

## Outstanding

### Needs a decision or production access

1. **Adopting the homepage.** Production's customised `home` template keeps
   overriding the theme file. Reset it in Site Editor → Templates (or insert
   the Editorial Homepage pattern) once photography is in place.
2. **Approved photography.** Hero, artwork, women's, Defiance Division, drop
   and lookbook slots are named placeholders (`… (replace image)`). Category
   tiles read category images from WooCommerce; set them per category.
3. **Production slugs are assumed, not verified.** Tiles look for
   `t-shirts|tshirts|tees`, `hoodies`, `outerwear|jackets`, `accessories`;
   sections for `womens` (Collection attribute), `defiance-division` and
   `dead-signal` (attribute, category or tag; the drop is filterable via
   `aggressive_apparel_featured_drop_slugs`). Missing terms degrade safely
   (tile omitted, grid omitted, CTA → Shop), but should be checked.
4. **Behaviour change from the grid fix on database templates.** Production's
   customised `single-product` (and `home`) templates set Columns: 5, which
   used to render 4. They will now render 5 (e.g. five related products). Set
   them to 4 in the Site Editor to keep the old look.
5. **Copy review.** Section copy is restrained and avoids claims about
   materials, process or stock, but is brand voice and should be approved.

### Not done in this pass

- Lighthouse / Core Web Vitals were not re-measured (`pnpm perf:auto`); the
  homepage adds no scripts, and its images are placeholders, so LCP must be
  measured once real photography is in.
- Manual screen-reader and keyboard walkthroughs were not performed; coverage
  is the existing axe/keyboard e2e suites plus the checks above.
- No new security review: this pass added no endpoints, forms, or
  user-input handling. See the October audit for the open tooling advisories.
- Existing patterns still force product images to 3:4 inside Product Image
  blocks; they were left as-is pending a decision on catalog image ratios.
- `src/styles/components/block-styles.css` is unlayered, contrary to the
  override principle's preference for `@layer` + `:where()`; changing it
  shifts cascade order site-wide and needs its own visual pass.
- `product-duet` lost a 1.5rem top margin its Product Collection never
  serialised (it was the cause of the invalid block).
