# Block Placement Guide

Agency-facing rules for where Aggressive Apparel blocks belong in FSE templates, which Store Enhancements they depend on, and what happens if they are misplaced.

WooCommerce-dependent blocks declare `supports.requiresPlugins: ["woocommerce"]` and are hidden from the inserter when WooCommerce is inactive.

---

## Quick reference

| Block | Best place | Feature flag | Renders empty / no-ops when |
| ----- | ---------- | ------------ | --------------------------- |
| `filter-toggle` | Shop / category / tag archive header | `product_filters` | Flag off, or not a filterable archive |
| `filter-active-bar` | Below archive header / above product grid | `product_filters` | Flag off, or not a filterable archive |
| `grid-list-toggle` | Archive toolbar near sorting | — | Anywhere (client-only layout preference) |
| `product-color-swatches` | Inside product card / product template | — | No product context or no color attribute |
| `product-rating` | Product card or single product | — | No product / no rating data |
| `product-tabs` | Single product template | — | Not `is_product()` |
| `wishlist-button` | Product card or single product | `wishlist` | Flag off |
| `wishlist` (+ item children) | Dedicated wishlist page | `wishlist` | Flag off |
| `recently-viewed` | Single product, cart, or home | — | Client list empty (shell still renders) |
| `lookbook` | Landing / collection pages | — | No `mediaUrl` |
| `countdown-timer` | Sale / drop landing pages | — | Invalid / missing end date |
| `free-shipping-bar` / `free-shipping-message` | Header, cart, checkout, sticky regions | — | No free-shipping threshold |
| `search` | Header / mobile nav | — | Never gated (global UX) |
| `copyright` | Footer template part | — | Never gated (legal identity from Settings → General) |
| `card-flip` | Marketing sections | — | — |
| Nav family | Header / footer parts | — | Parent/ancestor constraints in editor |

---

## Product Filters (critical)

**Enable first:** Appearance → Store Enhancements → **Product Filters**.

Filter UI is **not** auto-injected into templates. You must place the blocks (or use the pattern).

### Where to place

| Block | Recommended location |
| ----- | -------------------- |
| `aggressive-apparel/filter-toggle` | Archive header, next to title / sorting |
| `aggressive-apparel/filter-active-bar` | Directly under the header, above `woocommerce/product-collection` |
| `aggressive-apparel/grid-list-toggle` | Same header toolbar as `filter-toggle` (included in `shop-archive-header`) |

**Templates:** `archive-product`, `taxonomy-product_cat`, and any custom product-tag archive template.

**Starter pattern:** `aggressive-apparel/shop-archive-header` (`patterns/shop-archive-header.php`) includes filter toggle, grid/list toggle, and active filter bar.

### Behavior notes

- Markup only outputs on **shop**, **product category**, and **product tag** archives (`Product_Filters::is_filterable_archive()`).
- On those archives, the shared store (`aggressive-apparel/product-filters`) loads CSS/JS (~40 KB). Non-archive pages do not get the bundle.
- Layout (`drawer` / `sidebar` / `horizontal`) is controlled in Store Enhancements, not on the blocks.
- `filter-toggle` `mobileOnly`: `auto` hides the button on desktop when layout is not `drawer` (sidebar/horizontal already expose filters).
- Each block ships its own small `style.css`; the drawer/sidebar chrome CSS lives in `product-filters.css`.

### Do not

- Place filter blocks only on the homepage expecting them to filter a remote shop query — they require a filterable archive request.
- Expect the default `templates/archive-product.html` to include them — it does not; add the pattern or blocks yourself.
- Gate or lazy-load **search** the same way — search is intentionally global.

### Opt-in for custom routes

```php
add_filter( 'aggressive_apparel_product_filters_needs_assets', '__return_true' );
```

Use only when you have a custom filterable catalog surface that is not a standard WC archive.

---

## Wishlist

**Enable first:** Store Enhancements → **Wishlist**.

| Block | Placement |
| ----- | --------- |
| `wishlist-button` | Product cards (`woocommerce/product-template`) and/or single product template |
| `wishlist` | A page dedicated to the saved list |
| `wishlist-item-*` | **Only** as children of `wishlist` (editor-enforced `parent`) |

### Button placement mode

Store Enhancements → Wishlist button placement:

- **`auto`** — theme can inject a heart on single product; block still works in cards.
- **`block`** — automatic single-product heart is suppressed; place `wishlist-button` yourself.

Assign the wishlist page under the Wishlist settings so empty-state / nav links resolve correctly.

Card template for the page block should include, in order:

1. `wishlist-item-image`
2. `wishlist-item-name`
3. `wishlist-item-price`
4. `wishlist-item-actions`

---

## Catalog & product cards

| Block | Placement | Notes |
| ----- | --------- | ----- |
| `product-color-swatches` | Inside `woocommerce/product-template` (archive cards) or single product | Prefers `data-aa-product-image` / `data-aa-product-link` from `Product_Card_Contract` |
| `product-rating` | Same card / single contexts | Uses post context `postId`; brand-mark rating UI |
| `grid-list-toggle` | Archive toolbar | Persists layout preference in `localStorage`; pair with product-collection |

Keep swatches near the product image so image/link swapping has a clear card root.

**Starter card template** (`product-collection-card-starter` and most product-row patterns): image → color swatches → title + wishlist → rating → price → button.

---

## Single product

| Block | Placement | Notes |
| ----- | --------- | ----- |
| `product-tabs` | `single-product` template | Returns empty when not `is_product()` |
| `recently-viewed` | Below related products or after tabs | Records current product; excludes it from the list |
| `wishlist-button` | Near title / add to cart | See Wishlist placement mode above |

Size Guide, Sticky Add to Cart, Quick View, etc. are **enhancements**, not blocks — toggle them in Store Enhancements.

---

## Cart, checkout, shipping

| Block | Placement | Notes |
| ----- | --------- | ----- |
| `free-shipping-bar` | Header, cart drawer, cart page | Needs a free-shipping threshold (WC zone or `aggressive_apparel_free_shipping_threshold`) |
| `free-shipping-message` | Same surfaces, lighter copy | Same threshold source |
| `recently-viewed` | Empty cart / cart upsell areas | Optional recovery content |

---

## Marketing & content

| Block | Placement | Notes |
| ----- | --------- | ----- |
| `lookbook` | Landing / collection pages | Requires media + optional hotspots with product IDs |
| `countdown-timer` | Product cards, sale rows, promo banners, landing pages, editorial sections, drop heroes | Use a placement variation such as Product Card, Card Stack, Segment Chips, Promo Banner, Sale Strip, Urgency Pill, Boxed Launch, Outline Grid, Editorial Stack, Magazine Feature, or a Hero preset; needs a valid end datetime or product sale end date |
| `card-flip` | Feature grids, lookbooks | Hover = CSS only; click = keyboard-accessible IA |
| `hero-carousel` | Home / campaign heroes | Cover slides as inner blocks |
| `parallax` / `animate-on-scroll` | Any long-scroll page | Respect `prefers-reduced-motion` |
| `modal` | Site-wide promos | Supports exit-intent / scroll-depth triggers |
| `ticker` / `horizontal-scroll` | Announcement / product rails | |
| `split-story` | Editorial splits | Static layout block (`src/blocks/`) |
| `search` | Header / mobile bottom nav | Global; assets load when the block is present |

### Countdown timer theming

The countdown block supports native WordPress color, gradient, spacing, typography, and border controls. Its visual variations are layout presets, not fixed brand skins.

Theme-level overrides can target these variable groups on `.aggressive-apparel-countdown` or on a specific modifier such as `.aggressive-apparel-countdown--hero-panel`:

| Group | Variables |
| ----- | --------- |
| Typography | `--aa-countdown-label-font-size`, `--aa-countdown-label-font-weight`, `--aa-countdown-value-font-size`, `--aa-countdown-value-font-weight`, `--aa-countdown-unit-font-size`, `--aa-countdown-unit-font-weight` |
| Color | `--aa-countdown-label-color`, `--aa-countdown-value-color`, `--aa-countdown-unit-color`, `--aa-countdown-border-color`, `--aa-countdown-segment-color`, `--aa-countdown-segment-bg` |
| Shape | `--aa-countdown-border-width`, `--aa-countdown-border-style`, `--aa-countdown-radius`, `--aa-countdown-pill-radius` |
| Rhythm | `--aa-countdown-gap`, `--aa-countdown-segment-padding`, `--aa-countdown-surface-padding` |
| Scale | `--aa-countdown-hero-value-font-size`, `--aa-countdown-feature-value-font-size` |

---

## Navigation

Two independent subsystems — do not nest a panel block inside the desktop `navigation` root expecting shared state.

**Desktop** (`aggressive-apparel/navigation` store):

- Root: `navigation`
- Children: `nav-link`, `nav-submenu-dropdown`, `nav-submenu-mega`
- `navigation-trigger` lives in the desktop bar but opens the **panel** store

**Mobile panel** (`aggressive-apparel/navigation-panel` store, portaled to `wp_footer`):

- Root: `navigation-panel`
- Optional chrome: `nav-panel-header`, `nav-panel-footer`
- Children: `nav-link`, `nav-submenu-accordion`, `nav-submenu-drilldown`

`nav-link` is the shared leaf (`parent` of every container above).

---

## Copyright (footer)

Place `aggressive-apparel/copyright` in the **footer template part** (already in `parts/footer.html`). Prefer the Site Editor over hardcoding a year in a paragraph.

| Setting | Where |
| ------- | ----- |
| Terms of Service page | Settings → **Terms** |
| Legal / organization name | Settings → **Terms** |
| Privacy Policy page | Settings → **Privacy** (WordPress core; publish the page so visitors can open it) |
| Owner source / LLC / Schema / links / separator | Block inspector |

**Starter pattern:** `aggressive-apparel/footer-columns` uses the copyright block with legal links.

### Behavior notes

- Owner source `legal_name` reads the option on Settings → Terms; empty falls back to Site Title.
- `showLegalLinks` appends Privacy / Terms when those pages are configured.
- `showSchema` emits Organization + WebSite `copyrightHolder` / `copyrightYear` JSON-LD once per page.
- Agency overrides: `aggressive_apparel_copyright_parts`, `aggressive_apparel_copyright_html`, `aggressive_apparel_copyright_schema`, `aggressive_apparel_copyright_legal_links`.

### Do not

- Hardcode `© 2026 …` in footer patterns — the year will go stale.
- Nest another `contentinfo` landmark solely for this line; keep it inside the existing footer.

---

## Editor checklist (agencies)

1. Turn on the matching Store Enhancement before expecting commerce UI.
2. For filters: insert `shop-archive-header` (or both filter blocks) on **all** product archive templates you use.
3. For wishlist: create a page with the `wishlist` block; set button placement to `block` if you want full template control.
4. Prefer product-card blocks inside `woocommerce/product-template` so they inherit product context.
5. After template edits, view a real shop archive and a plain blog post — filters should load only on the archive.

---

## Related

- Store Enhancements overview: [`README.md`](../README.md#store-enhancements)
- Design tokens: [`docs/design-system.md`](design-system.md)
- Developer architecture (nav, filters, cards): [`CLAUDE.md`](../CLAUDE.md)
