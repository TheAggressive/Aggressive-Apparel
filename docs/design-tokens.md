# theme.json tokens & the override contract

How theme.json, the `--aa-*` token layer, and the three editor surfaces relate.
For the full design system (colour ramps, primitives, pattern rules) see
[`design-system.md`](design-system.md).

---

## Layout

- **Content Width:** 1200px
- **Wide Width:** 1600px

## Color Palette

The editor swatch **name matches the slug** (so `var(--aa-color-foreground)` ↔
"Foreground"), and adaptive (light/dark) colors carry an **"(Adaptive)"** suffix
so it's clear they shift with the color scheme.

| Editor name                     | Slug (= CSS)           | Value                                   |
| ------------------------------- | ---------------------- | --------------------------------------- |
| Primary                         | `primary`              | Legacy alias to adaptive Accent         |
| Red                             | `red`                  | Legacy alias to adaptive Accent         |
| White                           | `white`                | `#ffffff`                               |
| Black                           | `black`                | `#000000`                               |
| Transparent                     | `transparent`          | `transparent` (explicit "no fill")      |
| Surface (Adaptive)              | `surface`              | Adaptive page/section background        |
| Surface Elevated (Adaptive)     | `surface-elevated`     | Adaptive higher-contrast surface        |
| Surface Muted (Adaptive)        | `surface-muted`        | Soft panel between elevated and surface |
| Surface Sunken (Adaptive)       | `surface-sunken`       | Recessed wells / input fills            |
| Foreground (Adaptive)           | `foreground`           | Adaptive primary text                   |
| Foreground Muted (Adaptive)     | `foreground-muted`     | Adaptive secondary text                 |
| Accent (Adaptive)               | `accent`               | Adaptive brand interactive color        |
| Accent on Foreground (Adaptive) | `accent-on-foreground` | Inverse accent for foreground surfaces  |
| Border (Adaptive)               | `border`               | Adaptive borders/dividers               |
| Border Muted (Adaptive)         | `border-muted`         | Quieter hairlines than `border`         |
| Success                         | `success`              | `oklch(52.7% 0.137 150.1)` (status)     |
| Warning                         | `warning`              | `oklch(55.3% 0.174 38.4)` (status)      |
| Error                           | `error`                | `oklch(57.7% 0.215 27.3)` (status)      |
| Info                            | `info`                 | `oklch(48.8% 0.217 264.4)` (status)     |
| Neutral                         | `neutral`              | `oklch(55.1% 0.023 264.4)` (status)     |

Adaptive colors are injected as `light-dark()` palette entries by
`Core/Adaptive_Colors` from `settings.custom.adaptiveColors` (edit their `light`/
`dark` values there). Status colors are plain palette swatches.

## Source of Truth & Token Layer

**theme.json `settings` is the single source of truth.** `src/styles/base/tokens.css`
(`--aa-*`) is a thin **consumer/alias + derivation + `@property`** layer on top of
the WordPress-generated `--wp--preset--*` / `--wp--custom--*` variables — not the
source. Don't move values out of theme.json into tokens.css.

Slot rule: values an editor should be able to pick live in **UI-backed preset
slots** (`color.palette`, `shadow.presets`, `spacing.spacingSizes`,
`typography.fontSizes`); CSS-only internals (radius, motion, z-index) stay in
`settings.custom`. Status colors → palette; shadows → `shadow.presets`; the
matching `--aa-*` tokens repoint at those presets.

**Three editor surfaces:** front end; the editor **canvas iframe** (gets theme.json
presets + tokens via `add_theme_support('editor-styles')` + `add_editor_style('build/styles/base/tokens.css')` in `Core/Theme_Support`); and the editor **chrome**
(sidebar/popovers — no presets, so `var(--aa-*)` can't resolve there — use the JS
map in `src/utils/editor-style-tokens.ts`).

**Override principle:** the design system must NOT style what the block editor
controls (buttons, element/block styling), and our CSS must stay overridable by
the editor. Style only what the editor can't reach (raw inputs, WC-encapsulated
fields, select2, autofill); keep it low priority (`@layer` + `:where()`); never use
`!important` or unlayered/high-specificity to beat editor/theme.json output. WC
overrides may be unlayered only to beat WooCommerce's own unlayered CSS, and set
only structural props (use `color: inherit` so editor colors win).

## Forms

One field design everywhere — the WooCommerce checkout look: translucent
adaptive fill, `radius-lg`, a reserved 3px left bar (accent on focus, error on
`:user-invalid` / `aria-invalid`), 16px values, and uppercase tracked labels
above the control.

- **Source:** `settings.custom.field` in theme.json (font, size, padding, radius,
  bar width, fill strengths, label type) → `--aa-field-*` aliases and derived
  paint (`--aa-field-bg`, `-bg-hover`, `-bg-solid`, `-placeholder`,
  `-bar-focus|invalid|valid`) in `tokens.css`. Every consumer below reads these;
  none restates the values. Labels and placeholders use foreground-muted nudged
  10% toward foreground: labels can sit on the fill (checkout's floating
  labels), where plain foreground-muted is ~4.0:1.
- **Status text:** `--aa-color-error-text` / `--aa-color-success-text` mix 30%
  of the adaptive foreground into the static status swatches, which alone sit
  near 3.3–3.7:1. Use them for validation messages and thin indicators (≥ 4.9:1
  on every surface, both schemes); keep the swatches for fills and badges.
- **`src/styles/components/forms.css`** (frontend, enqueued after main; also an
  editor style, so server-rendered previews match): the native baseline for
  text-like inputs, `textarea`, checkbox/radio/range, file, color, raw `input`
  submit/reset/button, labels, fieldset/legend, output, autofill. Plus unlayered
  bridges for WooCommerce classic forms, select2 and the core Comments Form
  block. `<button>` elements are NOT styled (editor-controlled).
- **`components/forms-fluent-forms.css`** — the Fluent Forms "Inherit Theme
  Style" adapter (`.fluentform.ff-inherit-theme-style`): maps `--fluentform-*`,
  status messages, Choices.js multiselects (choice-pill chips, theme chevron,
  picker-style list) and the flatpickr date field + calendar (appended to
  `<body>`, so those rules are global and prefixed with `body` to beat
  flatpickr's equal-specificity CSS). Enqueued and added as an editor style
  only when `FLUENTFORM` is defined.
- **Icons:** `--aa-icon-chevron|check|close|calendar` (defined in forms.css) are
  black SVG masks — paint them with `background-color` so they take any token
  colour.
- **Button fallback tokens:** raw `input` submits and `.aggressive-apparel-button`
  variants read `--aa-button-*` (`settings.custom.button` typography/padding).
  `styles.elements.button` keeps literal values so Global Styles can edit them;
  `TestStyles` fails if the two drift.
- **`<select>` paint** is `styles.elements.select` in theme.json (unlayered), so
  its states in forms.css are unlayered too. Where the browser supports
  `appearance: base-select`, the baseline also themes the open list
  (`::picker(select)`: elevated surface, rows with the field's accent bar on
  hover/keyboard focus, an accent `::checkmark`, a turning `::picker-icon`).
  The end padding is `--aa-select-padding-end` (read by theme.json), which
  shrinks there because the chevron moves into the content box. Other browsers
  keep the native list. WooCommerce selects keep WC's own `appearance: none`.
- **Checkboxes / radios** are drawn (`appearance: none`): field fill, a muted
  frame (≥ 3:1), accent fill plus the variation pills' check, a dash for
  `:indeterminate`, and an accent dot for radios. The mark is a background
  image animated through `background-size` with `--aa-ease-spring`, so it works
  whatever `display` a plugin forces. `forced-colors` restores the native
  widgets.
- **`.aggressive-apparel-field`** (`components/field.css`, in main.css) is the
  class-based wrapper for the same field.
- **Checkout/cart** (`woocommerce/checkout.css`) consumes the same tokens and
  keeps only what WooCommerce's DOM needs: floating labels and the `!important`
  that beats WC's field selectors.

**Layer order matters.** Tailwind's `base` layer (Preflight) resets input
padding, border, radius and background, and every `aa-*` layer is ordered
below it. The baseline therefore lives in its own `aa-forms` layer, which
`main.css` pins directly after `base` (`… aa-utilities, theme, base, aa-forms`).
It beats Preflight but loses to Tailwind utilities and all unlayered CSS.

**Boundaries** live in ONE wrapper selector at the top of the baseline (every
rule nests inside it): WooCommerce Blocks (`[class*='wc-block']` itself or any
ancestor), WordPress components and block placeholders in the editor canvas,
Fluent Forms presets other than Inherit Theme Style, and the flatpickr
calendar's own controls. Text controls also
skip quantity steppers (`.qty`, `.quantity *`, `.aa-sticky-cart__qty-input`,
`.aggressive-apparel-quick-view__qty-input`) and the search modal input. Choice
labels (wrapping or followed by a checkbox/radio) stay sentence case.
flatpickr inputs are exempt from the read-only look: their `readonly` only keeps
the mobile keyboard closed.

Locked by `tests/e2e/native-form-baseline.spec.ts` (native + Fluent Forms
computed result), `tests/e2e/checkout-field-contract.spec.ts` (checkout fields
read the tokens, labels still float, focus/error bar), and `TestStyles`.

## Style Variations (FSE)

Theme style variations live in `/styles/*.json` (e.g. `styles/section-surface.json`). Block
style variations are defined in theme.json `styles.blocks.*.variations` (e.g. the
`core/group` "Card") or via `register_block_style` in `Core/Theme_Support`.

## Spacing Scale

47 spacing presets from `0.5` (0.125rem) to `96` (24rem), including fluid variants with `clamp()` for responsive spacing.

## Typography

System font stack with 20+ fluid font sizes using `clamp()` for responsive scaling.
