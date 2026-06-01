# Aggressive Apparel Design System

Single source of truth for UI tokens, naming, and usage across the theme.

## Color System

### Adaptive colors (light-dark)

Defined in `theme.json` → `settings.custom.adaptiveColors`. Injected at runtime as `light-dark(light, dark)` palette entries by `Adaptive_Colors`.

| Slug | Editor name | Role | Light | Dark |
|------|-------------|------|-------|------|
| `surface` | Background | Default page/section background | oklch(87% 0 0) | oklch(20.5% 0 0) |
| `surface-elevated` | Background Elevated | Higher-contrast surface (pure black in dark) | oklch(87% 0 0) | oklch(0 0 0) |
| `foreground` | Text | Primary text | oklch(0 0 0) | oklch(1 0 0) |
| `foreground-muted` | Text Muted | Secondary/muted text | oklch(55.6% 0 0) | oklch(87% 0 0) |
| `accent` | Accent | Brand interactive (links, hover, focus) | oklch(44.4% 0.177 26.899) | oklch(57.7% 0.245 27.325) |
| `border` | Border | Borders and dividers | oklch(70.8% 0 0) | oklch(43.9% 0 0) |

**CSS variable:** `var(--wp--preset--color--{slug})`  
**Alias:** `var(--aa-color-{slug})` in `src/styles/base/tokens.css`

### Static brand colors

| Slug | Editor name | Role |
|------|-------------|------|
| `primary` | Brand | Alias to brand red — used by buttons/links in theme.json |
| `red` | Brand Red | Static brand red value |
| `white` | White | Absolute white |
| `black` | Black | Absolute black |

Use **adaptive** tokens for surfaces and text. Use **static** white/black/red only when you need absolute values (e.g. button text on red background).

### Gradients

| Slug | Name |
|------|------|
| `brand-fade` | Brand Fade (accent → red) |
| `surface-fade` | Surface Fade (surface → surface-elevated) |
| `dark-overlay` | Dark Overlay |
| `light-overlay` | Light Overlay |

## Custom Tokens

Defined in `theme.json` → `settings.custom`. Exposed as `--wp--custom--*`.

| Group | CSS variable example | Purpose |
|-------|---------------------|---------|
| `motion.duration` | `--wp--custom--motion--duration--normal` | Animation timing |
| `motion.ease` | `--wp--custom--motion--ease` | Easing curve |
| `overlay` | `--wp--custom--overlay--blur` | Modal backdrop blur |
| `zIndex` | `--wp--custom--z-index--modal` | Stacking order |
| `size` | `--wp--custom--size--button-height--md` | Component sizing |
| `radius` | `--wp--custom--radius--control` | Shared control/card/panel corners |
| `shadow` | `--wp--custom--shadow--panel` | Shared elevation |
| `statusColors` | `--wp--custom--status-colors--success` | Success, warning, error, info states |

Aliases in `src/styles/base/tokens.css` use `--aa-*` prefix.

## Where to Change Things

| Change | Edit |
|--------|------|
| Brand colors, adaptive pairs | `theme.json` → `settings.custom.adaptiveColors` |
| Static brand red/white/black | `theme.json` → `settings.color.palette` |
| Global button/link/heading | `theme.json` → `styles.elements` or Site Editor → Styles → Elements |
| Spacing/typography scale | `theme.json` → `settings.spacing` / `settings.typography` |
| Motion, z-index, overlay | `theme.json` → `settings.custom` |
| Radius, shadows, status colors, component sizing | `theme.json` → `settings.custom` |
| Editor-side control chrome | `src/utils/editor-style-tokens.ts` |
| Block style recipes | `src/styles/components/block-styles.css` + `Theme_Support::register_block_styles()` |
| Feature-specific layout | That feature's CSS only — use tokens, no raw hex |

## Block Style Variations

Registered in `includes/Core/class-theme-support.php`:

| Block | Variations |
|-------|------------|
| `core/button` | ghost, text, small, cta, cta-small, outline-on-dark |
| `core/group` | surface-card, bordered |
| `core/paragraph` | badge, badge-muted |
| `core/separator` | subtle |

## Usage Rules

1. **No raw hex** in feature CSS — use `var(--wp--preset--color--*)` or `var(--aa-color-*)`.
2. **Custom PHP buttons** — add class `wp-element-button` to inherit global button styles.
3. **Adaptive colors in blocks** — use palette slugs in block attributes; use Adaptive Colors panel for per-block light/dark overrides.
4. **Old slugs removed** — do not use `light-dark-white-black`, `light-dark-black-white`, `surface-alt`, `on-surface`, `on-surface-muted`.

## Pattern Authoring Rules

1. Use `core/navigation` for menu-like link rows.
2. Use registered button styles for CTA sizing and tone; do not repeat button padding/typography inline.
3. Use `foreground-muted` for secondary or legal copy.
4. Use font presets instead of raw `font-size` values unless a pattern genuinely needs a one-off display treatment.
5. Use Badge / Badge Muted for pills, labels, payment methods, and small metadata chips.
6. Use `surface-card` or `bordered` for framed content instead of rebuilding card chrome inline.

## UI Consistency Contract

All user-facing UI should be built from the same primitives, tokens, and state rules. Features can change content and layout, but the interaction language should stay consistent across blocks, patterns, WooCommerce surfaces, and custom PHP output.

### Source of truth

| Concern | Source | Use in code |
|---------|--------|-------------|
| Brand, surface, text, border, accent | `theme.json` palette + adaptive colors | `--wp--preset--color--*` or `--aa-color-*` |
| Spacing scale | `theme.json` spacing presets | `--wp--preset--spacing--*` |
| Typography scale | `theme.json` font presets | `--wp--preset--font-size--*` |
| Motion | `settings.custom.motion` | `--aa-duration-*`, `--aa-ease-default` |
| Overlays | `settings.custom.overlay` | `--aa-overlay-*` |
| Stacking | `settings.custom.zIndex` | `--aa-z-*` |
| Component sizing | `settings.custom.size` | `--aa-button-height-*`, `--aa-input-height`, `--aa-panel-width-*` |
| Radius | `settings.custom.radius` | `--aa-radius-*` |
| Elevation | `settings.custom.shadow` | `--aa-shadow-sm`, `--aa-shadow-md`, `--aa-shadow-lg`, `--aa-shadow-panel` |
| Status colors | `settings.custom.statusColors` | `--aa-color-success`, `--aa-color-warning`, `--aa-color-error`, `--aa-color-info` |
| Block editor control chrome | `src/utils/editor-style-tokens.ts` | `EDITOR_HELP_TEXT_STYLE`, `EDITOR_FIELDSET_STYLE`, `EDITOR_INFO_NOTICE_STYLE` |

### Primitive mapping

| UI element | Required primitive |
|------------|--------------------|
| Primary CTA, submit, add-to-cart, custom action | `wp-element-button` or `.aggressive-apparel-button--primary` |
| Secondary action | `.aggressive-apparel-button--outline` or registered `core/button` ghost style |
| Link-like action | registered `core/button` text style |
| Hero or merchandising CTA | registered `core/button` CTA / CTA Small styles |
| CTA over dark media | registered `core/button` Outline on Dark style, composed with CTA sizing when needed |
| Badges, labels, payment pills | registered `core/paragraph` Badge / Badge Muted styles |
| Inputs, selects, textareas | `.aggressive-apparel-field__input` inside `.aggressive-apparel-field` |
| Checkbox/radio groups | `.aggressive-apparel-field--checkbox` or a feature wrapper that reuses field tokens |
| Validation, success, async feedback | `.aggressive-apparel-message--success` / `--error` |
| Modal, drawer, quick view, filters, size guide | `.aggressive-apparel-overlay` + `.aggressive-apparel-panel` |
| Stacked vertical controls | `.aggressive-apparel-stack` |
| Inline button/control groups | `.aggressive-apparel-cluster` |
| Email/signup/search rows | `.aggressive-apparel-inline-form` |
| Cards and framed content | registered `core/group` styles: `surface-card` or `bordered` |

### State rules

Every interactive element should expose the same state language:

1. Hover uses opacity, foreground/surface inversion, or `--aa-color-hover-*`.
2. Focus uses a visible ring from `--aa-focus-ring` or the same two-layer outline pattern.
3. Disabled controls use reduced opacity and `cursor: not-allowed`.
4. Loading controls use `.is-loading` when possible.
5. Errors use `--aa-color-error`; success uses `--aa-color-success`.
6. Motion uses `--aa-duration-*` and turns off under `prefers-reduced-motion: reduce`.

### Configuration rule

When a UI decision should be user-configurable, expose it as a token or block setting first. Do not solve configurability by adding feature-specific CSS values. Feature CSS should compose tokens; `theme.json`, style variations, and block controls should own configuration.

## Composable Primitives

| File | Classes | Purpose |
|------|---------|---------|
| `components/overlay.css` | `.aggressive-apparel-overlay`, `__backdrop` | Modal shell + backdrop blur |
| `components/panel.css` | `.aggressive-apparel-panel`, `--md`, `--lg`, `--xl` | Dialog container sizes |
| `components/field.css` | `.aggressive-apparel-field`, `__input`, `__error`, `--checkbox` | Form inputs and validation |
| `components/buttons.css` | `.aggressive-apparel-button`, `--primary`, `--accent`, `--outline` | Custom PHP buttons |
| `components/layout.css` | `.aggressive-apparel-stack`, `.aggressive-apparel-cluster`, `.aggressive-apparel-inline-form` | Flex layout recipes |
| `utils/editor-style-tokens.ts` | `EDITOR_*` style objects | Shared React inline styles for block editor controls |
| `interactivity/use-overlay.ts` | `prepareOverlayOpen`, `closeOverlay` | Shared open/close JS behavior |

Compose in PHP/HTML:

```html
<form class="my-feature__form aggressive-apparel-stack aggressive-apparel-stack--md">
  <input class="aggressive-apparel-field__input" type="email" />
  <button class="aggressive-apparel-button aggressive-apparel-button--primary aggressive-apparel-button--full">Submit</button>
</form>

<div class="aggressive-apparel-overlay my-feature">
  <div class="aggressive-apparel-overlay__backdrop"></div>
  <div class="aggressive-apparel-panel aggressive-apparel-panel--lg my-feature__panel"></div>
</div>
```

Reference migrations: exit-intent, back-in-stock, load-more.

## Style Variations

Global style presets in `styles/` — selectable in Site Editor → Styles.

| Slug | Title | What it changes |
|------|-------|-----------------|
| `high-contrast` | High Contrast | Sharper adaptive color pairs, square buttons, bolder headings |
| `compact` | Compact | Tighter block gap, smaller body/button/nav typography |
| `minimal` | Minimal | Softer surfaces, sentence-case buttons/headings, underlined links |

Variations that define `settings.custom.adaptiveColors` override the base palette via `Adaptive_Colors` (merged theme.json data).

## Lint Enforcement

| Check | Command |
|-------|---------|
| Stylelint (theme, block, and editor CSS) | `pnpm lint:css` |
| Hex ban in feature CSS and patterns | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| Editor UI chrome literals | `bin/check-design-system-css.sh` (runs with `lint:css`) |
| BEM class names | `bin/check-design-system-css.sh` (runs with `lint:css`) |

Hex colors are allowed only in `src/styles/base/tokens.css`, `src/styles/admin/`, and content values like color-picker defaults. Editor control surfaces, borders, radii, help text, and notices should use `src/utils/editor-style-tokens.ts`.

## Migration Status

- [x] Week 1: Token foundation, slug rename, block styles, tokens.css
- [x] Week 2: Overlay + panel primitives, use-overlay.ts, all modal/drawer migrations
- [x] Week 3: All WooCommerce CSS → semantic `--aa-*` tokens (no hardcoded hex or dark-mode blocks)
- [x] Week 4: field/button/layout primitives, style variations, Stylelint + BEM CI checks
