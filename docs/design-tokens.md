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

`src/styles/components/forms.css` is the single token-driven form layer: native
`input`/`select`/`textarea` baseline (layered + `:where()`), plus unlayered
WooCommerce (block + classic), select2, file input, autofill, and `:user-invalid`
coverage. Buttons are intentionally NOT styled here (editor-controlled).

## Style Variations (FSE)

Theme style variations live in `/styles/*.json` (e.g. `styles/section-surface.json`). Block
style variations are defined in theme.json `styles.blocks.*.variations` (e.g. the
`core/group` "Card") or via `register_block_style` in `Core/Theme_Support`.

## Spacing Scale

47 spacing presets from `0.5` (0.125rem) to `96` (24rem), including fluid variants with `clamp()` for responsive spacing.

## Typography

System font stack with 20+ fluid font sizes using `clamp()` for responsive scaling.
