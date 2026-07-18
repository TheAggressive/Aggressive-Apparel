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

| Block                            | Variations                                               |
| -------------------------------- | -------------------------------------------------------- |
| `core/button`                    | ghost, text, small, cta, cta-small, outline-on-dark      |
| `core/group`                     | frosted, surface-card, bordered, frosted-dark            |
| `core/heading`                   | display, overflow, text-mask                             |
| `core/image`                     | editorial                                                |
| `core/cover`                     | cinematic                                                |
| `core/paragraph`                 | badge, badge-muted, eyebrow, caption, meta, legal, price |
| `core/separator`                 | brand-stripe, subtle                                     |
| `woocommerce/product-collection` | commerce-grid                                            |
| `woocommerce/product-template`   | commerce-cards                                           |
| `woocommerce/product-image`      | product-frame                                            |
| `woocommerce/product-price`      | commerce-price                                           |

### Reusable section styles

Stored as style variations in `styles/*.json` and available for Group, Columns, Media & Text, and Cover blocks:

| Style                   | Purpose                                                                  |
| ----------------------- | ------------------------------------------------------------------------ |
| Section: Surface        | Adaptive neutral section surface                                         |
| Section: Brand Accent   | Adaptive accent section with appropriate foreground and button treatment |
| Section: Editorial Dark | Dark editorial section with light foreground and button treatment        |

## Usage Rules

1. **No raw hex** in feature CSS — use `var(--wp--preset--color--*)` or `var(--aa-color-*)`.
2. **Custom PHP buttons** — add class `wp-element-button` to inherit global button styles.
3. **Adaptive colors in blocks** — use palette slugs in block attributes; use the **Adaptive Color** panel for per-block light/dark overrides. The panel uses Light/Dark tabs plus the native WordPress color/gradient picker, and discovers each block’s color supports (text, link, heading, background, border, …) plus allowlisted custom attributes (e.g. overlay).
4. **Old slugs removed** — do not use `light-dark-white-black`, `light-dark-black-white`, `surface-alt`, `on-surface`, `on-surface-muted`.

## Pattern Authoring Rules

1. Use `core/navigation` for menu-like link rows.
2. Use registered button styles for CTA sizing and tone; do not repeat button padding/typography inline.
3. Use `foreground-muted` for secondary or legal copy.
4. Use font presets instead of raw `font-size` values unless a pattern genuinely needs a one-off display treatment.
5. Use Badge / Badge Muted for pills, labels, payment methods, and small metadata chips.
6. Use `surface-card` or `bordered` for framed content instead of rebuilding card chrome inline.
7. Use WooCommerce block styles for product grids: Commerce Grid, Commerce Cards, Product Frame, and Commerce Price.
8. Use the Design System Preview pattern after token or primitive changes to visually check the system.

## UI Consistency Contract

All user-facing UI should be built from the same primitives, tokens, and state rules. Features can change content and layout, but the interaction language should stay consistent across blocks, patterns, WooCommerce surfaces, and custom PHP output.

### Source of truth

| Concern                              | Source                                 | Use in code                                                                                                |
| ------------------------------------ | -------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Brand, surface, text, border, accent | `theme.json` palette + adaptive colors | `--wp--preset--color--*` or `--aa-color-*`                                                                 |
| Spacing scale                        | `theme.json` spacing presets           | `--wp--preset--spacing--*`                                                                                 |
| Typography scale                     | `theme.json` font presets              | `--wp--preset--font-size--*`                                                                               |
| Motion                               | `settings.custom.motion`               | `--aa-duration-*`, `--aa-ease-default`                                                                     |
| Overlays                             | `settings.custom.overlay`              | `--aa-overlay-*`                                                                                           |
| Stacking                             | `settings.custom.zIndex`               | `--aa-z-*`                                                                                                 |
| Component sizing                     | `settings.custom.size`                 | `--aa-button-height-*`, `--aa-input-height`, `--aa-panel-width-*`                                          |
| Radius                               | `settings.custom.radius`               | `--aa-radius-*`                                                                                            |
| Elevation                            | `settings.custom.shadow`               | `--aa-shadow-sm`, `--aa-shadow-md`, `--aa-shadow-lg`, `--aa-shadow-panel`                                  |
| Typography roles                     | `settings.custom.typeRole`             | `--aa-type-eyebrow-*`, `--aa-type-caption-*`, `--aa-type-meta-*`, `--aa-type-legal-*`, `--aa-type-price-*` |
| Status colors                        | Static palette tokens                  | `--aa-color-success`, `--aa-color-warning`, `--aa-color-error`, `--aa-color-info`                          |
| Commerce states                      | Derived aliases in `tokens.css`        | `--aa-commerce-sale-*`, `--aa-commerce-new-*`, `--aa-commerce-low-stock-*`                                 |
| Block editor control chrome          | `src/utils/editor-style-tokens.ts`     | `EDITOR_HELP_TEXT_STYLE`, `EDITOR_FIELDSET_STYLE`, `EDITOR_INFO_NOTICE_STYLE`                              |

### Primitive mapping

| UI element                                      | Required primitive                                                                   |
| ----------------------------------------------- | ------------------------------------------------------------------------------------ |
| Primary CTA, submit, add-to-cart, custom action | `wp-element-button` or `.aggressive-apparel-button--primary`                         |
| Secondary action                                | `.aggressive-apparel-button--outline` or registered `core/button` ghost style        |
| Link-like action                                | registered `core/button` text style                                                  |
| Hero or merchandising CTA                       | registered `core/button` CTA / CTA Small styles                                      |
| CTA over dark media                             | registered `core/button` Outline on Dark style, composed with CTA sizing when needed |
| Badges, labels, payment pills                   | registered `core/paragraph` Badge / Badge Muted styles                               |
| Eyebrow, caption, meta, legal, price copy       | registered `core/paragraph` type-role styles                                         |
| Commerce state chip                             | Badge style plus `aa-commerce-state-*` class                                         |
| Product collection grid                         | registered `woocommerce/product-collection` Commerce Grid style                      |
| Product card list item                          | registered `woocommerce/product-template` Commerce Cards style                       |
| Product media frame                             | registered `woocommerce/product-image` Product Frame style                           |
| Product price text                              | registered `woocommerce/product-price` Commerce Price style                          |
| Inputs, selects, textareas                      | `.aggressive-apparel-field__input` inside `.aggressive-apparel-field`                |
| Checkbox/radio groups                           | `.aggressive-apparel-field--checkbox` or a feature wrapper that reuses field tokens  |
| Validation, success, async feedback             | `.aggressive-apparel-message--success` / `--error`                                   |
| Modal, drawer, quick view, filters, size guide  | `.aggressive-apparel-overlay` + `.aggressive-apparel-panel`                          |
| Stacked vertical controls                       | `.aggressive-apparel-stack`                                                          |
| Inline button/control groups                    | `.aggressive-apparel-cluster`                                                        |
| Email/signup/search rows                        | `.aggressive-apparel-inline-form`                                                    |
| Cards and framed content                        | registered `core/group` styles: `surface-card` or `bordered`                         |

### State rules

Every interactive element should expose the same state language:

1. Hover uses opacity, foreground/surface inversion, or `--aa-color-hover-*`.
2. Focus uses a visible ring from `--aa-focus-ring` or the same two-layer outline pattern.
3. Disabled controls use reduced opacity and `cursor: not-allowed`.
4. Loading controls use `.is-loading` when possible.
5. Errors use `--aa-color-error`; success uses `--aa-color-success`.
6. Motion uses `--aa-duration-*` and turns off under `prefers-reduced-motion: reduce`.

### Configuration rule

When a UI decision should be user-configurable, expose it as a token or block setting first. Do not solve configurability by adding feature-specific CSS values. Feature CSS should compose tokens; `theme.json` and block controls should own configuration.

## Composable Primitives

| File                           | Classes                                                                                       | Purpose                                              |
| ------------------------------ | --------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| `components/overlay.css`       | `.aggressive-apparel-overlay`, `__backdrop`                                                   | Modal shell + backdrop blur                          |
| `components/panel.css`         | `.aggressive-apparel-panel`, `--md`, `--lg`, `--xl`                                           | Dialog container sizes                               |
| `components/field.css`         | `.aggressive-apparel-field`, `__input`, `__error`, `--checkbox`                               | Form inputs and validation                           |
| `components/buttons.css`       | `.aggressive-apparel-button`, `--primary`, `--accent`, `--outline`                            | Custom PHP buttons                                   |
| `components/layout.css`        | `.aggressive-apparel-stack`, `.aggressive-apparel-cluster`, `.aggressive-apparel-inline-form` | Flex layout recipes                                  |
| `base/content-elements.css`    | Semantic HTML inside `.wp-block-post-content`                                                 | Raw/classic content fallback                         |
| `woocommerce/blocks.css`       | `wp-block-woocommerce-*`, `wc-block-*`                                                        | Native WooCommerce block skin                        |
| `utils/editor-style-tokens.ts` | `EDITOR_*` style objects                                                                      | Shared React inline styles for block editor controls |
| `interactivity/use-overlay.ts` | `prepareOverlayOpen`, `closeOverlay`                                                          | Shared open/close JS behavior                        |

## Preview Pattern

Use `patterns/design-system-preview.php` as the living QA surface. It displays CTA styles, badges, commerce states, type roles, card styles, and WooCommerce product collection styling in one place.

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
