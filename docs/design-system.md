# Aggressive Apparel Design System

Single source of truth for UI tokens, naming, and usage across the theme.

## Color System

### Adaptive colors (light-dark)

Defined in `theme.json` → `settings.custom.adaptiveColors`. Injected at runtime as `light-dark(light, dark)` palette entries by `Adaptive_Colors`.

Values live only in `theme.json` (the token contract test reads them from
there); this table records each token's **role**.

| Slug                   | Role                                                                                         |
| ---------------------- | -------------------------------------------------------------------------------------------- |
| `surface`              | Default page/section background. Light mode is a warm paper neutral, dark mode a soft black. |
| `surface-elevated`     | Highest-contrast surface (white / black): cards, product media wells, overlays.              |
| `surface-muted`        | Soft raised panel between `surface` and `surface-elevated` (account cards, empty states).    |
| `surface-sunken`       | Recessed wells: image placeholders, input fills.                                             |
| `foreground`           | Primary text.                                                                                |
| `foreground-muted`     | Secondary text, captions, meta.                                                              |
| `accent`               | Brand interactive text: links, hover, focus.                                                 |
| `accent-fill`          | Brand red as a **fill** (buttons, badges); brighter than `accent` in dark mode.              |
| `accent-on-foreground` | Brand accent on an inverted (foreground-coloured) fill.                                      |
| `accent-on-raised`     | Accent text on raised dark surfaces (striped rows) — only where `accent` fails 4.5:1.        |
| `border`               | Borders and dividers.                                                                        |
| `border-muted`         | Quieter hairlines than `border`.                                                             |

Every text role (`foreground`, `foreground-muted`, `accent`) clears WCAG AA
(4.5:1) on every surface in **both** schemes, and light surfaces keep the order
sunken → surface → muted → elevated. `src/styles/__tests__/token-contracts.test.ts`
enforces both, plus that this table and `design-tokens.md` list every token.

**CSS variable:** `var(--wp--preset--color--{slug})`  
**Alias:** `var(--aa-color-{slug})` in `src/styles/base/tokens.css`

### Legacy and absolute colors

| Slug      | Editor name | Role                            |
| --------- | ----------- | ------------------------------- |
| `primary` | Primary     | Legacy alias to adaptive Accent |
| `red`     | Red         | Legacy alias to adaptive Accent |
| `white`   | White       | Absolute white                  |
| `black`   | Black       | Absolute black                  |

Use **adaptive** tokens for surfaces, text, and brand UI. The legacy `primary` and `red` slugs remain only so existing saved content resolves to adaptive Accent.

### Gradients

| Slug                  | Name                                      |
| --------------------- | ----------------------------------------- |
| `brand-fade`          | Brand Fade (accent → inverse accent)      |
| `brand-sweep`         | Brand Sweep                               |
| `brand-radial`        | Brand Radial                              |
| `surface-fade`        | Surface Fade (surface → surface-elevated) |
| `surface-vignette`    | Surface Vignette                          |
| `dark-overlay`        | Dark Overlay                              |
| `dark-overlay-strong` | Dark Overlay Strong                       |
| `light-overlay`       | Light Overlay                             |
| `editorial-dark`      | Editorial Dark                            |

## Typography

Space Grotesk carries body and interface text; Bebas Neue carries headings and
campaign display. Font files are installed and activated through the WordPress
Font Library, so `theme.json` declares the families without owning `fontFace`
sources. Only declared families may be referenced from theme markup — the
token contract test fails on any other `fontFamily` slug.

Bebas Neue has no lowercase and renders smaller than its nominal size, so a
page built only from Bebas headings reads as one shouted voice. Mix the roles:
Bebas for campaign moments and section labels, the sans **Headline** for
editorial statements, **Lead** for intro copy.

### Type roles

| Role           | How to apply                                             | Use                                                            |
| -------------- | -------------------------------------------------------- | -------------------------------------------------------------- |
| `display`      | Heading style **Campaign Display** (`is-style-campaign`) | Hero and campaign headlines. At most one per section.          |
| `headline`     | Heading style **Headline (Sans)** (`is-style-headline`)  | Editorial statements, story sections, sentence-case headlines. |
| h1–h6          | Default heading (theme.json `styles.elements`)           | Document structure; Bebas, fluid sizes.                        |
| `lead`         | Paragraph style **Lead** (`is-style-lead`)               | One intro paragraph under a section heading.                   |
| `eyebrow`      | Paragraph style **Eyebrow**                              | Short kicker above a heading (accent, caps, tracked).          |
| `caption`      | Paragraph style **Caption**                              | Image and figure captions.                                     |
| `meta`         | Paragraph style **Meta**                                 | Dates, counts, small uppercase metadata.                       |
| `legal`        | Paragraph style **Legal**                                | Fine print.                                                    |
| `price`        | Paragraph style **Price** / Commerce Price               | Prices outside product blocks.                                 |
| `productTitle` | Product card titles (`--aa-type-product-title-*`)        | Product names in grids.                                        |

Sizes come from `settings.custom.typeRole` (aliased as `--aa-type-<role>-*`);
`display`, `headline` and `lead` use the fluid `display`, `headline` and `lead`
font-size presets, which editors can also pick directly.

## Layout rhythm

One gutter and four section densities, all fluid spacing presets
(`var:preset|spacing|<slug>`, aliased `--aa-gutter` / `--aa-section-*`):

| Preset              | 375px → 1440px+ | Use                                                                  |
| ------------------- | --------------- | -------------------------------------------------------------------- |
| `gutter`            | 16px → 48px     | Horizontal page padding on every template's `<main>` and the footer. |
| `section-compact`   | 40px → 64px     | Utility bands: trust rows, marquee, newsletter, surface cards.       |
| `section-standard`  | 64px → 128px    | Default merchandising section (product rows, category grids).        |
| `section-editorial` | 80px → 160px    | Story-led sections: original-work feature, collections, brand.       |
| `section-immersive` | 96px → 208px    | Rare campaign moments; never two in a row.                           |

Rules:

1. A section's vertical padding uses one of the `section-*` presets — never a
   raw number. Pick by the section's **importance**, not to fill space.
2. Stack sections with `blockGap: 0` on the page wrapper and let each section
   own its padding, so adjacent sections with different surfaces meet cleanly.
3. Content stays inside `contentSize` (1200px); merchandising grids and
   editorial media may go `wide` (1600px); campaign media goes `full`.
4. Section style variations (below) already apply these presets.

## Image system

| Ratio                  | Slug / source                      | Use                                                     |
| ---------------------- | ---------------------------------- | ------------------------------------------------------- |
| 21:9 Campaign          | `campaign` (`settings.dimensions`) | Wide campaign images on desktop. Covers use min-height. |
| 4:5 Editorial Portrait | `editorial-portrait`               | Editorial and lookbook portraits, detail close-ups.     |
| 4:5, 2:3, 1:1, 16:9    | WordPress defaults                 | Category tiles (4:5), tall looks (2:3), detail (1:1).   |
| Product images         | 1:1 slot, contain                  | Every card; product images are designed square.         |

Rules:

1. **Product photography is shown whole, in a square.** The product images
   are designed square, so every product card's media slot is 1:1 and shows
   the photo with `contain`, never a crop that could cut a sleeve or a
   print. Category tiles are 4:5: the photo is a whole square at the top
   and the name sits on the black band under it, never over the artwork.
   The New Arrivals lead card is 1:1.11 so it lines up with the 2 × 2
   beside it. The product gallery frame
   follows the main photo. Patterns do not override the card slot.
2. Hero and campaign images are Covers with a **min-height in `svh`/`vh`**
   (60–90) rather than a fixed ratio, and a **focal point** set on the subject.
   Avoid thin banner strips: no campaign Cover under 60svh on desktop.
3. **Text-safe areas:** place Cover content to one side (`contentPosition`)
   and pair it with the `dark-overlay` / `dark-overlay-strong` gradient at
   30–60% so copy clears 4.5:1 regardless of the photo.
4. **Loading:** WordPress marks the first large content image
   `fetchpriority="high"` and lazy-loads the rest; don't add `loading` or
   priority attributes by hand.
5. **Alt text:** describe the garment (and its artwork, where it has one) in product and editorial
   images; decorative Cover backgrounds keep empty alt.
6. **Placeholders:** where approved photography isn't available, patterns ship
   an image-less Cover named `… (replace image)` in List View. Never substitute
   generated or stock imagery for retail product photos.

## Editorial pattern library

Twelve section patterns (category **Editorial Sections**) and one composition,
**Editorial Homepage** (`editorial-homepage`), which `templates/home.html`
renders. Each section is its own pattern, so editors can reorder, remove or
insert any section alone.

| #   | Pattern                       | Rhythm / surface             | Live data                                                                                                                                                                                                                               |
| --- | ----------------------------- | ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 01  | `editorial-campaign-hero`     | studio cover (light in both) | The slogan at poster scale beside the campaign photo set in the Site Editor (4:5, its focal point placing the crop), named with the cover product's live price; else the cover product's own photo whole; nothing chosen → slogan alone |
| 02  | `editorial-new-arrivals`      | compact                      | One rail of up to eight, newest first after the cover's garment; nothing after it → omitted                                                                                                                                             |
| 03  | `editorial-category-showcase` | compact                      | Shop by line: one ruled row per stocked line, best-stocked first, name at display scale, piece count, three of its products                                                                                                             |
| 04  | `editorial-artwork-spotlight` | white (light in both)        | "Aggressive is a compliment." + paragraph beside up to three on-figure photos with a Shown line; no photos → typographic                                                                                                                |
| 05  | `editorial-best-sellers`      | compact                      | Three large numbered cards: real sales → Best sellers; else Featured; else omitted                                                                                                                                                      |
| 06  | `editorial-featured-drop`     | black spread / band          | Drop photo + name + row of two, or title row over a stock-sized grid; no stocked term → omitted                                                                                                                                         |
| 07  | `editorial-womens-collection` | white spread                 | Copy + row of two beside a full-height on-figure photo, light in both schemes; no stocked term → omitted                                                                                                                                |
| 08  | `editorial-hoodies-feature`   | compact                      | Lineup on one studio band: up to four side by side (short ranges centred), five or more a rail                                                                                                                                          |
| 09  | `editorial-defiance-division` | black spread / band          | As the drop band (`Editorial_Collection_Band`); no stocked term → omitted                                                                                                                                                               |
| 10  | `editorial-accessories`       | compact                      | With an on-figure accessory photo: photo + two products + a tile counting the rest; else up to four, or a rail                                                                                                                          |
| 11  | `editorial-featured-looks`    | studio (light in both)       | Swipe gallery of three to five square on-figure photos, each linked to and named for its product; fewer → omitted                                                                                                                       |
| 12  | `editorial-brand-story`       | compact                      | One row: "Without the edges sanded off. / Graphic pieces, plain essentials and accessories from an independent label." left, Shop all (+ Our story) right                                                                               |

**What the merchant controls** (`WooCommerce\Editorial_Controls`, no
settings screen of its own: each control lives with its content):

- **Editorial photos (Media Library).** Each image has _Homepage use_: Not
  reviewed (default), Approved or Excluded, and a _Homepage priority_
  (higher first). Only **approved** photos fill editorial slots (the name,
  Women's, accessories, the lookbook, collection bands); nothing publishes
  itself. The list view filters by homepage use and has bulk _Approve for
  homepage_ / _Exclude from homepage_. A photo is always shown with, and
  linked to, the product whose main or gallery image it is. The field's help
  says whether an image looks like a photograph or a cut-out (corner pixels,
  cached as `_aa_photo_kind`); approval is the merchant's call.
- **Cover (Site Editor).** The campaign photo is the _Homepage campaign
  image_ template part (Appearance → Editor → Patterns → Template parts),
  a core Cover block: Replace picks the photo from the Media Library, the
  focal point places the crop (one 4:5 frame at every width, shown as such
  in the part's editor), and alt text describes it. Edit it there rather than
  from the Home template, whose patterns the editor would save as static
  copies. In the hero it loads first: eager, high priority, `sizes` scaled
  by how much wider the photo is than its frame (`Catalog_Image_Loading`).
  _Homepage cover_ on one product (product edit screen, General tab) names
  the garment under the photo with its live price, so tick the garment the
  photo shows. Without a campaign photo that product's own image is the
  optional fallback, shown whole; with neither, the slogan alone. The cover
  never picks a product itself. New Arrivals starts after the cover's
  product only when it is the newest.
- **Shop by line.** _Show in Shop by line_ on a product category; marked
  categories appear in WooCommerce's category order (drag to reorder in
  Products → Categories). Until any are marked, the standard lines (T-shirts,
  Hoodies, Outerwear, Swimwear, Leggings, Accessories) in that fixed order.
  Piece counts are live; an empty line is left out.

Rules the library follows (new sections should too):

1. **No hardcoded store data.** Term IDs and archive URLs differ between
   environments, so patterns resolve them at render time through
   `WooCommerce\Editorial_Catalog` (term by slug → real archive URL, or the
   Shop page). A section whose CTA names a collection ("Shop women's",
   "Shop the drop") renders only when the store has that term **with
   products** (`Editorial_Catalog::stocked_term()`); otherwise it renders
   nothing, rather than a CTA to the whole catalog.
2. **No invented merchandising.** Best Sellers ranks by recorded sales only;
   without sales it shows the store's Featured products, labelled
   "Featured" (never "staff picks", which claims who chose them).
   Copy makes no claims about materials, process or stock.
3. **One card.** `WooCommerce\Editorial_Product_Grid` emits every grid with
   the Editorial Grid card the Shop archive uses (media well, title, price,
   swatches). Editors can retune each query in the block inspector.
4. **Real photography or a different layout, never an empty frame.**
   The hero shows only the campaign photo the merchant set (above), never
   product imagery it picked itself. Other photo slots take the store's own
   approved product photography through `WooCommerce\Editorial_Imagery`, as
   plain core/image blocks linked to the product. Category tiles
   (`term_image()`) take the category's own image, else its newest product
   photo in any orientation. Each photo appears once per page, claimed in
   render order: tiles skip what the hero showed; a collection slot (women's) may reuse a photo because it
   must show that collection; the lookbook tops up with shown photos rather
   than disappear. A slot that can't be filled changes the layout (the hero
   becomes type only, the lookbook is omitted below three portraits). No homepage section ships an image-less
   placeholder Cover any more; the collection bands (drop, Defiance
   Division) use the collection's own portrait photo or drop the photo.
5. **Grids declare their gap.** Core sizes a responsive grid's tracks
   (Column count + Minimum width) from the block's own gap and assumes 0.5em
   when none is set; against the theme's 48px gap that drops a column. Set
   Block spacing on every responsive Grid group.
6. **One red accent per section.** The eyebrow carries the brand red;
   headings take the section's text color (Headline and Campaign Display
   inherit it; plain headings set Foreground).
7. **Motion never hides merchandise.** Products, category tiles and
   photographs are visible at rest, never behind a scroll reveal: Animate on
   Scroll's defaults (30% visible, inside the top three quarters of the
   viewport) left a visitor looking at a New Arrivals heading over ~360px of
   nothing, and full-page captures showed whole sections blank. Reveals are
   for short copy only, at one house setting: `slide` up 24px over 0.7s,
   `threshold` 0.1, `detectionBoundary` bottom 0%, children staggered 0.08s.
   The hero never animates. The block renders content visible without
   JavaScript and holds still under reduced motion. Shop by line is the one
   index that moves: each line's name (the reveal wraps the heading alone)
   rises in with its rule drawing across beside it, and on hover or focus
   the other names step back while the active line's thumbnails lift. The
   thumbnails themselves are never inside the reveal.
8. **Sections size to their stock.** Grids count what they can actually
   show (`Editorial_Catalog::visible_count()`: catalog-visible, in stock),
   not the term's raw count. Two staff picks are two large cards, not a
   third of an empty row; one hoodie is a half-and-half spread, two sit
   beside the headline, five or more become a rail;
   a one-product women's collection is carried by its photo. Below 1024px
   editorial grids of three or more columns are two-up. The campaign hero
   (`aa-campaign-hero`) sizes the slogan to its column (container units)
   beside the photo, and to the full width when there is no photo.
9. **Two heading roles.** A heading that names a product set (New arrivals,
   Featured, Women's, Hoodies, Accessories, How it's worn) is display type:
   the plain heading set to Foreground, or Campaign Display where the
   section is a feature; a heading that makes a statement ("Pick your
   issue.", "Aggressive is a compliment.") is Headline (Sans).
10. **Spreads alternate; grids don't repeat.** Photo sections are
    edge-to-edge spreads (`aa-bleed-photo` column: the photo runs the full
    row height and crops to cover): hero photo right, drop band photo left,
    Women's photo right, so on phones photo and copy alternate instead of two
    photos stacking. Beside a photo, products show as one row of two, never
    a 2 × 2 that would stretch the photo. Product sections take distinct
    shapes: lead + 2 × 2 (New Arrivals), intro + cards (Featured), headline
    - cards sized to the count (Hoodies), rail (Accessories), title row +
      grid (collection bands without a photo). Copy beside a full-bleed photo lines
      up with the wide column through
      `max(gutter, (100% - wide-size) / 2)` padding.
11. **Shopping follows the scheme; editorial moments are fixed colors.**
    Shopping sections (`aa-shop-section`: categories, New Arrivals,
    Featured, Accessories) share the page `surface` with compact padding,
    so the products fill the section. Editorial and feature sections take
    a surface that holds in both schemes: black (hero, the drop and
    Defiance bands, the lookbook), deep brand red (The name, `aa-statement`
    keeps the light scheme's oxblood), white (the Women's spread) and stone
    (Hoodies: `surface-sunken` with `aa-scheme-light`). White-background
    blocks and `aa-scheme-light` are light in both schemes and black ones
    dark (`adaptive-block-colors.css`), so text, buttons and cards inside
    resolve the right values. Dark mode therefore keeps a light chapter
    mid-page instead of black after charcoal. Two same-surface sections
    that meet (`aa-shop-section` or the closing `aa-page-section`) are
    divided by a hairline at the wide column's width. Listing-card names
    are Large (18px), the New Arrivals lead XX-Large; eyebrow-to-heading
    spacing is 12px in every section header.
12. The slogan appears once, in the hero. Copy follows
    [`brand-voice.md`](brand-voice.md). The two brand statements (The
    name and the closing row) share one Headline scale, a step under the
    slogan and above the section headings; the red band is compact so it
    paces the page without outweighing the products beside it.
13. **One action role per position.** A link beside a section heading
    (All products, Shop new arrivals, Shop accessories) is the Text button
    style; a feature's copy column (Featured, Women's, Hoodies) carries a
    Ghost button, Outline on Dark on the black bands. The filled CTA opens
    and closes the page (hero, closing row) and appears nowhere between.

**Adopting the homepage on an existing site.** A Blog Home (`home`) template
customised in the Site Editor is stored in the database and overrides
`templates/home.html`, so deploying the theme does not change a customised
homepage. To adopt the new one, reset that template in Site Editor →
Templates, or insert the Editorial Homepage pattern into it. WordPress caches
the theme's pattern list per theme version; a release bumps the version, and
locally `wp eval 'wp_get_theme()->delete_pattern_cache();'` refreshes it.

## Custom Tokens

Defined in `theme.json` → `settings.custom`. Exposed as `--wp--custom--*`.

| Group             | CSS variable example                                | Purpose                           |
| ----------------- | --------------------------------------------------- | --------------------------------- |
| `motion.duration` | `--wp--custom--motion--duration--normal`            | Animation timing                  |
| `motion.ease`     | `--wp--custom--motion--ease`                        | Easing curve                      |
| `overlay`         | `--wp--custom--overlay--blur`                       | Modal backdrop blur               |
| `zIndex`          | `--wp--custom--z-index--modal`                      | Stacking order                    |
| `size`            | `--wp--custom--size--button-height--md`             | Component sizing                  |
| `radius`          | `--wp--custom--radius--control`                     | Shared control/card/panel corners |
| `shadow`          | `--wp--custom--shadow--panel`                       | Shared elevation                  |
| `button`          | `--wp--custom--button--primary--hover-background`   | Adaptive button roles and states  |
| `focusRing`       | `--wp--custom--focus-ring`                          | Adaptive keyboard focus treatment |
| `fontWeights`     | `--wp--custom--font-weights--space-grotesk--medium` | Available family weights          |
| `typeRole`        | `--wp--custom--type-role--eyebrow--font-size`       | Semantic typography roles         |

Aliases in `src/styles/base/tokens.css` use the `--aa-*` prefix. Status colors come from static palette entries; commerce-state aliases are derived in the alias layer.

## Where to Change Things

| Change                            | Edit                                                                                                    |
| --------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Brand colors, adaptive pairs      | `theme.json` → `settings.custom.adaptiveColors`                                                         |
| Legacy brand aliases, white/black | `theme.json` → `settings.color.palette`                                                                 |
| Global button/link/heading        | `theme.json` → `styles.elements` or Site Editor → Styles → Elements                                     |
| WooCommerce block defaults        | `theme.json` → `styles.blocks.woocommerce/*`                                                            |
| Spacing/typography scale          | `theme.json` → `settings.spacing` / `settings.typography`                                               |
| Motion, z-index, overlay          | `theme.json` → `settings.custom`                                                                        |
| Radius, shadows, component sizing | `theme.json` → `settings.custom`                                                                        |
| Type roles and family weights     | `theme.json` → `settings.custom`                                                                        |
| Status colors                     | `theme.json` → `settings.color.palette`                                                                 |
| Derived commerce states           | `src/styles/base/tokens.css`                                                                            |
| Raw semantic post content         | `src/styles/base/content-elements.css`; keep configurable block defaults in `theme.json` first          |
| Editor-side control chrome        | `src/utils/editor-style-tokens.ts`                                                                      |
| Block style recipes               | `src/styles/components/block-styles.css`, `styles/*.json`, and `Theme_Support::register_block_styles()` |
| Feature-specific layout           | That feature's CSS only — use tokens, no raw hex                                                        |

## Block Style Variations

Registered in `includes/Core/class-theme-support.php`:

| Block                            | Variations                                                                                                                   |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `core/button`                    | ghost, cta-ghost, text, small, cta, cta-small, outline-on-dark, cta-outline-on-dark, cta-small-outline-on-dark, text-on-dark |
| `core/group`                     | frosted, surface-card, bordered, frosted-dark                                                                                |
| `core/heading`                   | display, campaign, headline, overflow, text-mask                                                                             |
| `core/image`                     | editorial                                                                                                                    |
| `core/cover`                     | cinematic                                                                                                                    |
| `core/paragraph`                 | badge, badge-muted, eyebrow, caption, meta, lead, legal, price                                                               |
| `core/separator`                 | brand-stripe, subtle                                                                                                         |
| `woocommerce/product-collection` | commerce-grid, editorial-grid                                                                                                |
| `woocommerce/product-template`   | commerce-cards                                                                                                               |
| `woocommerce/product-image`      | product-frame                                                                                                                |
| `woocommerce/product-price`      | commerce-price                                                                                                               |

### Reusable section styles

Stored as style variations in `styles/*.json` and available for Group, Columns, Media & Text, and Cover blocks. Each applies the `gutter` horizontally and a section rhythm vertically (Surface: `section-compact`; Brand Accent and Editorial Dark: `section-standard`):

| Style                   | Purpose                                                                  |
| ----------------------- | ------------------------------------------------------------------------ |
| Section: Surface        | Adaptive neutral section surface                                         |
| Section: Brand Accent   | Adaptive accent section with appropriate foreground and button treatment |
| Section: Editorial Dark | Dark editorial section with light foreground and button treatment        |

## Usage Rules

1. **No raw hex** in feature CSS — use `var(--wp--preset--color--*)` or `var(--aa-color-*)`.
2. **Custom PHP buttons** — a shaped `.aggressive-apparel-button--primary` / `--outline` / `--danger` / `--success` / `--ghost` renders fully on its own (paint, radius, padding, brand type). Adding `wp-element-button` is optional and lets theme.json own the paint/typography instead; when present it takes precedence over the standalone fallback.
3. **Adaptive colors in blocks** — use palette slugs in block attributes; use the **Adaptive Color** panel for per-block light/dark overrides. The panel uses Light/Dark tabs plus the native WordPress color/gradient picker, and discovers each block’s color supports (text, link, heading, background, border, …) plus allowlisted custom attributes (e.g. overlay).
4. **Old slugs removed** — do not use `light-dark-white-black`, `light-dark-black-white`, `surface-alt`, `on-surface`, `on-surface-muted`.

## Pattern Authoring Rules

1. Use `core/navigation` for menu-like link rows.
2. Use one registered button style for CTA sizing and tone; do not stack `is-style-*` classes or repeat button padding/typography inline.
3. Use `foreground-muted` for secondary or legal copy.
4. Use font presets instead of raw `font-size` values unless a pattern genuinely needs a one-off display treatment.
5. Use Badge / Badge Muted for pills, labels, payment methods, and small metadata chips.
6. Use `surface-card` or `bordered` for framed content instead of rebuilding card chrome inline.
7. Use WooCommerce block styles for product grids: Commerce Grid (framed cards for utility contexts such as cart upsells) or Editorial Grid (frameless cards matching the Shop archive, for storytelling sections), plus Commerce Cards, Product Frame, and Commerce Price.
8. Use the Design System Preview pattern after token or primitive changes to visually check the system.

## UI Consistency Contract

All user-facing UI should be built from the same primitives, tokens, and state rules. Features can change content and layout, but the interaction language should stay consistent across blocks, patterns, WooCommerce surfaces, and custom PHP output.

### Source of truth

| Concern                              | Source                                 | Use in code                                                                                                                                                                                                                                                             |
| ------------------------------------ | -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Brand, surface, text, border, accent | `theme.json` palette + adaptive colors | `--wp--preset--color--*` or `--aa-color-*`                                                                                                                                                                                                                              |
| Spacing scale                        | `theme.json` spacing presets           | `--wp--preset--spacing--*`                                                                                                                                                                                                                                              |
| Typography scale                     | `theme.json` font presets              | `--wp--preset--font-size--*`                                                                                                                                                                                                                                            |
| Motion                               | `settings.custom.motion`               | `--aa-duration-*`, `--aa-ease-default`                                                                                                                                                                                                                                  |
| Overlays                             | `settings.custom.overlay`              | `--aa-overlay-*`                                                                                                                                                                                                                                                        |
| Stacking                             | `settings.custom.zIndex`               | `--aa-z-*`                                                                                                                                                                                                                                                              |
| Component sizing                     | `settings.custom.size`                 | `--aa-control-min` (44px standard floor), `--aa-control-min-dense` (32px narrow-card choice floor), `--aa-card-swatch-min` (32px), `--aa-card-swatch-dense-min` (28px), `--aa-button-height-md/-lg`, `--aa-icon-button-size`, `--aa-input-height`, `--aa-panel-width-*` |
| Radius                               | `settings.custom.radius`               | `--aa-radius-*`                                                                                                                                                                                                                                                         |
| Elevation                            | `settings.custom.shadow`               | `--aa-shadow-sm`, `--aa-shadow-md`, `--aa-shadow-lg`, `--aa-shadow-panel`                                                                                                                                                                                               |
| Typography roles                     | `settings.custom.typeRole`             | `--aa-type-eyebrow-*`, `--aa-type-caption-*`, `--aa-type-meta-*`, `--aa-type-legal-*`, `--aa-type-price-*`                                                                                                                                                              |
| Status colors                        | Static palette tokens                  | `--aa-color-success`, `--aa-color-warning`, `--aa-color-error`, `--aa-color-info`                                                                                                                                                                                       |
| Commerce states                      | Derived aliases in `tokens.css`        | `--aa-commerce-sale-*`, `--aa-commerce-new-*`, `--aa-commerce-low-stock-*`                                                                                                                                                                                              |
| Block editor control chrome          | `src/utils/editor-style-tokens.ts`     | `EDITOR_HELP_TEXT_STYLE`, `EDITOR_FIELDSET_STYLE`, `EDITOR_INFO_NOTICE_STYLE`                                                                                                                                                                                           |

Button paint, typography, radius, padding, adaptive role tokens (`settings.custom.button.*`, including the `primary`, `secondary`, `icon`, `choice`, `danger`, `success`, and `ghost` roles), and the shared 44px control floor (`--aa-control-min`) live in `theme.json`. `components/buttons.css` owns interaction mechanics WordPress cannot express reliably—minimum targets, focus rings, motion, disabled/loading behavior—the CTA, icon, choice-pill, and stepper contracts, and the composable modifier axes (tone, density, elevation, radius) that only repaint or resize on top of that shared machinery. Feature CSS may set the primitive’s documented local custom properties for a contextual surface, but must not recreate hover, focus, pressed, disabled, radius, or target behavior. A modifier never drops a primary control below `--aa-control-min`: `--compact` tightens padding and type, never height. Product-card color choices use their dedicated WCAG-safe `--aa-card-swatch-min` (32px) and `--aa-card-swatch-dense-min` (28px) tokens so their literal colors read as one grouped selector. The filter drawer's secondary choice chips (size / collection / category) use `.aa-choice-pill--dense` (`--aa-control-min-dense`, 32px) so the dense list reads as one grouped control; the filter toggle, color swatches, sliders, and Apply/Clear buttons, plus forms, PDP variations, and purchase controls, retain the 44px floor.

### Brand expression

Primary and secondary actions use the full pill radius as a fixed Aggressive Apparel signature. Their character comes from a heavy uppercase label, a decisive 2px edge, high-contrast adaptive paint, and a restrained one-pixel lift followed by a compact press. Keep the silhouette mature and clean: do not replace the pill with sharp corners, exaggerated shadows, gradients, or decorative motion. Tertiary actions remain text-like so they cannot compete with a purchase action.

A one-glyph utility action is the circular expression of the same pill system. Its default background remains transparent through hover; intent is usually shown by changing only the glyph to the adaptive accent. Product-card Preview and Wishlist actions are the deliberate exception: their neutral media-chip surface and icon color remain stable while the glyph moves from muted to full opacity on hover or focus; an active Wishlist heart still uses the accent to communicate selection. Destructive utility icons shift to the adaptive error color without adding a fill. Selectable choices are outlined pills that fill with adaptive foreground on hover and remain filled when selected. Product-color swatches may retain their literal color center, but their target and focus behavior follow the same contract.

### Conversion hierarchy

Use visual weight to make the next low-friction purchase step unmistakable:

1. **Primary** — one per decision area. Use for the single highest-intent conversion action: Buy Now on a product surface, View Cart in a success panel, the final form submit, or checkout continuation.
2. **Secondary outline** — use for the supporting action beside the primary: Add to Cart when Buy Now is present, Continue Shopping, filter clearing, and alternative routes. It fills on hover so it feels responsive without competing at rest.
3. **Tertiary text** — use for reversible, low-priority navigation such as View Full Product or dismissal; keep it muted so it sits below the purchase CTAs.

Do not place two primary buttons beside each other. In quick view and the sticky cart, **Buy Now is primary and Add to Cart is secondary** (direct-purchase intent gets the highest visual weight); success panels make View Cart primary and Continue Shopping secondary.

### Primitive mapping

| UI element                                      | Required primitive                                                                  |
| ----------------------------------------------- | ----------------------------------------------------------------------------------- |
| Primary CTA, submit, add-to-cart, custom action | `wp-element-button` or `.aggressive-apparel-button--primary`                        |
| Secondary action                                | `.aggressive-apparel-button--outline` or registered `core/button` ghost style       |
| Link-like action                                | registered `core/button` text style                                                 |
| Hero or merchandising CTA                       | registered `core/button` CTA / CTA Small styles                                     |
| CTA over dark media                             | `outline-on-dark`, `cta-outline-on-dark`, or `cta-small-outline-on-dark`            |
| Text link on a black section                    | `text-on-dark` (white, underlined; the accent red is too dark on black)             |
| Destructive confirm CTA (delete, remove)        | `.aggressive-apparel-button--danger`                                                |
| Positive confirm CTA (saved, applied)           | `.aggressive-apparel-button--success`                                               |
| Low-emphasis action between CTA and text link   | `.aggressive-apparel-button--ghost`                                                 |
| Quick View / Sticky Cart shaped CTA             | `.aggressive-apparel-button--sm` (14px type, 44px minimum target)                   |
| Dense toolbar / inline CTA (keeps 44px target)  | `.aggressive-apparel-button--compact`                                               |
| Oversized hero CTA                              | `.aggressive-apparel-button--lg` (add `--elevated` to float over media)             |
| Pill or hard-square CTA shape                   | `.aggressive-apparel-button--pill` / `--square`                                     |
| Icon-only or compact utility action             | `.aa-icon-button`; add `--only` when no visible label is present                    |
| Destructive utility action                      | `.aa-icon-button.aa-icon-button--danger`                                            |
| Variation, filter, or compact tab choice        | `.aa-choice-pill`                                                                   |
| Quantity plus/minus inside a segmented control  | `.aa-stepper-button`                                                                |
| Badges, labels, payment pills                   | registered `core/paragraph` Badge / Badge Muted styles                              |
| Eyebrow, caption, meta, legal, price copy       | registered `core/paragraph` type-role styles                                        |
| Commerce state chip                             | Badge style plus `aa-commerce-state-*` class                                        |
| Product collection grid                         | registered `woocommerce/product-collection` Commerce Grid or Editorial Grid style   |
| Product card list item                          | registered `woocommerce/product-template` Commerce Cards style                      |
| Product media frame                             | registered `woocommerce/product-image` Product Frame style                          |
| Product price text                              | registered `woocommerce/product-price` Commerce Price style                         |
| Inputs, selects, textareas                      | `.aggressive-apparel-field__input` inside `.aggressive-apparel-field`               |
| Checkbox/radio groups                           | `.aggressive-apparel-field--checkbox` or a feature wrapper that reuses field tokens |
| Validation, success, async feedback             | `.aggressive-apparel-message--success` / `--error`                                  |
| Modal, drawer, quick view, filters, size guide  | `.aggressive-apparel-overlay` + `.aggressive-apparel-panel`                         |
| Stacked vertical controls                       | `.aggressive-apparel-stack`                                                         |
| Inline button/control groups                    | `.aggressive-apparel-cluster`                                                       |
| Email/signup/search rows                        | `.aggressive-apparel-inline-form`                                                   |
| Cards and framed content                        | registered `core/group` styles: `surface-card` or `bordered`                        |

### State rules

Every interactive element should expose the same state language:

1. Primary hover inverts to adaptive foreground with the adaptive accent-on-foreground label.
2. Secondary hover fills with its border color; tertiary hover changes text color without gaining a background.
3. Focus preserves the variant’s paint and adds the visible two-layer `--aa-focus-ring`.
4. Disabled controls are inert, do not react on hover, and use reduced opacity plus `cursor: not-allowed`.
5. Selected variation controls expose `aria-pressed="true"`; unavailable options remain inert and must not animate on hover.
6. Primary buttons, icon controls, form controls, PDP choices, steppers, and the filter toggle/swatches/Apply-Clear are at least `44px × 44px`. Secondary dense choices use explicit WCAG-safe targets: catalog-card color swatches (32px/28px) and the filter drawer's size/collection/category chips (`.aa-choice-pill--dense`, 32px).
7. Loading controls use `.is-loading` and `aria-busy="true"` when possible.
8. Errors use `--aa-color-error`; success uses `--aa-color-success`.
9. Motion uses `--aa-duration-*` and turns off under `prefers-reduced-motion: reduce`.

### Configuration rule

When a UI decision should be user-configurable, expose it as a token or block setting first. Do not solve configurability by adding feature-specific CSS values. Feature CSS should compose tokens; `theme.json` and block controls should own configuration.

## Composable Primitives

| File                           | Classes                                                                                       | Purpose                                              |
| ------------------------------ | --------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| `components/overlay.css`       | `.aggressive-apparel-overlay`, `__backdrop`                                                   | Modal shell + backdrop blur                          |
| `components/panel.css`         | `.aggressive-apparel-panel`, `--md`, `--lg`, `--xl`                                           | Dialog container sizes                               |
| `components/field.css`         | `.aggressive-apparel-field`, `__input`, `__error`, `--checkbox`                               | Class-based shared field (`--aa-field-*` tokens)     |
| `components/buttons.css`       | `.aggressive-apparel-button`, `.aa-icon-button`, `.aa-choice-pill`, `.aa-stepper-button`      | Shared CTA, utility, choice, and stepper contracts   |
| `components/layout.css`        | `.aggressive-apparel-stack`, `.aggressive-apparel-cluster`, `.aggressive-apparel-inline-form` | Flex layout recipes                                  |
| `base/content-elements.css`    | Semantic HTML inside `.wp-block-post-content`                                                 | Raw/classic content fallback                         |
| `woocommerce/blocks.css`       | `wp-block-woocommerce-*`, `wc-block-*`                                                        | Native WooCommerce block skin                        |
| `utils/editor-style-tokens.ts` | `EDITOR_*` style objects                                                                      | Shared React inline styles for block editor controls |
| `interactivity/use-overlay.ts` | `prepareOverlayOpen`, `closeOverlay`                                                          | Shared open/close JS behavior                        |

## Preview Pattern

Use `patterns/design-system-preview.php` as the living QA surface. It displays primary, secondary, tertiary, tone (danger/success/ghost), density (compact/large), elevated, shape (pill/square), disabled, loading, icon, destructive, choice, unavailable, and on-dark button treatments alongside badges, commerce states, type roles, cards, and WooCommerce product collection styling.

Compose in PHP/HTML:

```html
<form
	class="my-feature__form aggressive-apparel-stack aggressive-apparel-stack--md"
>
	<input class="aggressive-apparel-field__input" type="email" />
	<button
		class="aggressive-apparel-button aggressive-apparel-button--primary aggressive-apparel-button--full"
	>
		Submit
	</button>
</form>

<div class="aggressive-apparel-overlay my-feature">
	<div class="aggressive-apparel-overlay__backdrop"></div>
	<div
		class="aggressive-apparel-panel aggressive-apparel-panel--lg my-feature__panel"
	></div>
</div>
```

Reference migrations: exit-intent, back-in-stock, load-more.

## Lint Enforcement

| Check                                                                                                    | Command                                                 |
| -------------------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| Stylelint (theme, block, and editor CSS)                                                                 | `pnpm lint:css`                                         |
| Hex ban in feature CSS and patterns                                                                      | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| Editor UI chrome literals                                                                                | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| Registered `is-style-*` usage in patterns (names read from `register_block_style()` and `styles/*.json`) | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| Woo product collections use Commerce Grid or Editorial Grid                                              | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| High-risk raw CTA sizing in patterns                                                                     | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| BEM class names                                                                                          | `bin/check-design-system-css.sh` (runs with `lint:css`) |

Hex colors are allowed only in `src/styles/base/tokens.css`, `src/styles/admin/`, and content values like color-picker defaults. Editor control surfaces, borders, radii, help text, and notices should use `src/utils/editor-style-tokens.ts`.

## Migration Status

- [x] Week 1: Token foundation, slug rename, block styles, tokens.css
- [x] Week 2: Overlay + panel primitives, use-overlay.ts, all modal/drawer migrations
- [x] Week 3: All WooCommerce CSS → semantic `--aa-*` tokens (no hardcoded hex or dark-mode blocks)
- [x] Week 4: field/button/layout primitives, Stylelint + BEM CI checks
