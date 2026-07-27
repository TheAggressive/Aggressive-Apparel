# Aggressive Apparel Design System

Single source of truth for UI tokens, naming, and usage across the theme.

## Color System

### Adaptive colors (light-dark)

Defined in `theme.json` → `settings.custom.adaptiveColors`. Injected at runtime as `light-dark(light, dark)` palette entries by `Adaptive_Colors`.

| Slug                   | Editor name                     | Role                                    | Light                     | Dark                      |
| ---------------------- | ------------------------------- | --------------------------------------- | ------------------------- | ------------------------- |
| `surface`              | Surface (Adaptive)              | Default page/section background         | oklch(87% 0 0)            | oklch(20.5% 0 0)          |
| `surface-elevated`     | Surface Elevated (Adaptive)     | Higher-contrast surface                 | oklch(1 0 0)              | oklch(0 0 0)              |
| `foreground`           | Foreground (Adaptive)           | Primary text                            | oklch(0 0 0)              | oklch(1 0 0)              |
| `foreground-muted`     | Foreground Muted (Adaptive)     | Secondary/muted text                    | oklch(55.6% 0 0)          | oklch(87% 0 0)            |
| `accent`               | Accent (Adaptive)               | Brand interactive (links, hover, focus) | oklch(44.4% 0.177 26.899) | oklch(57.7% 0.245 27.325) |
| `accent-on-foreground` | Accent on Foreground (Adaptive) | Brand accent on inverted foreground     | oklch(68% 0.22 27.325)    | oklch(44.4% 0.177 26.899) |
| `border`               | Border (Adaptive)               | Borders and dividers                    | oklch(70.8% 0 0)          | oklch(43.9% 0 0)          |

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

The theme exposes Space Grotesk for body and interface text and Bebas Neue for headings. Font files are installed and activated through the WordPress Font Library, so `theme.json` declares the families without owning `fontFace` sources.

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

| Block                            | Variations                                                                                                     |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `core/button`                    | ghost, cta-ghost, text, small, cta, cta-small, outline-on-dark, cta-outline-on-dark, cta-small-outline-on-dark |
| `core/group`                     | frosted, surface-card, bordered, frosted-dark                                                                  |
| `core/heading`                   | display, overflow, text-mask                                                                                   |
| `core/image`                     | editorial                                                                                                      |
| `core/cover`                     | cinematic                                                                                                      |
| `core/paragraph`                 | badge, badge-muted, eyebrow, caption, meta, legal, price                                                       |
| `core/separator`                 | brand-stripe, subtle                                                                                           |
| `woocommerce/product-collection` | commerce-grid                                                                                                  |
| `woocommerce/product-template`   | commerce-cards                                                                                                 |
| `woocommerce/product-image`      | product-frame                                                                                                  |
| `woocommerce/product-price`      | commerce-price                                                                                                 |

### Reusable section styles

Stored as style variations in `styles/*.json` and available for Group, Columns, Media & Text, and Cover blocks:

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
7. Use WooCommerce block styles for product grids: Commerce Grid, Commerce Cards, Product Frame, and Commerce Price.
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

Button paint, typography, radius, padding, adaptive role tokens (`settings.custom.button.*`, including the `primary`, `secondary`, `icon`, `choice`, `danger`, `success`, and `ghost` roles), and the shared 44px control floor (`--aa-control-min`) live in `theme.json`. `components/buttons.css` owns interaction mechanics WordPress cannot express reliably—minimum targets, focus rings, motion, disabled/loading behavior—the CTA, icon, choice-pill, and stepper contracts, and the composable modifier axes (tone, density, elevation, radius) that only repaint or resize on top of that shared machinery. Feature CSS may set the primitive’s documented local custom properties for a contextual surface, but must not recreate hover, focus, pressed, disabled, radius, or target behavior. A modifier never drops a primary control below `--aa-control-min`: `--compact` tightens padding and type, never height. Product-card color choices use their dedicated WCAG-safe `--aa-card-swatch-min` (32px) and `--aa-card-swatch-dense-min` (28px) tokens so their literal colors read as one grouped selector. The filter drawer's secondary choice chips (size / fit / category) use `.aa-choice-pill--dense` (`--aa-control-min-dense`, 32px) so the dense list reads as one grouped control; the filter toggle, color swatches, sliders, and Apply/Clear buttons, plus forms, PDP variations, and purchase controls, retain the 44px floor.

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
| Product collection grid                         | registered `woocommerce/product-collection` Commerce Grid style                     |
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
6. Primary buttons, icon controls, form controls, PDP choices, steppers, and the filter toggle/swatches/Apply-Clear are at least `44px × 44px`. Secondary dense choices use explicit WCAG-safe targets: catalog-card color swatches (32px/28px) and the filter drawer's size/fit/category chips (`.aa-choice-pill--dense`, 32px).
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
| `components/field.css`         | `.aggressive-apparel-field`, `__input`, `__error`, `--checkbox`                               | Form inputs and validation                           |
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

| Check                                     | Command                                                 |
| ----------------------------------------- | ------------------------------------------------------- |
| Stylelint (theme, block, and editor CSS)  | `pnpm lint:css`                                         |
| Hex ban in feature CSS and patterns       | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| Editor UI chrome literals                 | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| Registered `is-style-*` usage in patterns | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| Woo product collections use Commerce Grid | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| High-risk raw CTA sizing in patterns      | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| BEM class names                           | `bin/check-design-system-css.sh` (runs with `lint:css`) |

Hex colors are allowed only in `src/styles/base/tokens.css`, `src/styles/admin/`, and content values like color-picker defaults. Editor control surfaces, borders, radii, help text, and notices should use `src/utils/editor-style-tokens.ts`.

## Migration Status

- [x] Week 1: Token foundation, slug rename, block styles, tokens.css
- [x] Week 2: Overlay + panel primitives, use-overlay.ts, all modal/drawer migrations
- [x] Week 3: All WooCommerce CSS → semantic `--aa-*` tokens (no hardcoded hex or dark-mode blocks)
- [x] Week 4: field/button/layout primitives, Stylelint + BEM CI checks
