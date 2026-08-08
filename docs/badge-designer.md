# Badge Designer

The Products → Badges taxonomy editor is a **React badge studio** (canvas + style/shape library + inspector). Automatic badges (Sale, New, Low stock, Bestseller) share the same visual controls; Sale label text comes from **Store Copy**.

## Architecture

- **`Badge_Style_Schema`** — defaults, sanitization, CSS custom-property emission (admin + storefront). Authoritative compiler.
- **`Badge_Shapes`** — curated SVG silhouettes (mask or stroked frame) plus custom SVG.
- **`Badge_Studio_Rest`** — `POST /aggressive-apparel/v1/badge-studio/compile` for server-side preview HTML (`manage_categories`).
- **React app** — `src/scripts/admin/badge-studio/` mounts on add/edit; syncs state into hidden `badge_*` fields for classic taxonomy save.
- **Term meta** — additive fields with safe backfills (`badge_border_gap` defaults to `0` so legacy layered badges stay flush).

## Looks (presets)

Apply-only chips in the studio library: Solid, Outline, Layered, Pill, Minimal, Glass, Soft shadow, Gradient blaze, Ticket stub, Ribbon corner, Stamp, Neon outline. They dump field values; nothing is stored as a “preset id”.

## Shape

Library: rectangle, pill, ticket, shield, hex, burst, ribbon fold, tag, stamp, custom SVG.

- **Mask fill** — clips the badge background to the path. CSS borders are suppressed (clipped by the mask); the Border inspector section hides while mask mode is active.
- **SVG frame** — stroked path around content (`badge_frame_color` → border color → `currentColor`).
- **Pill** — radius-driven rectangle geometry (not an SVG silhouette); it does not get `--shaped`.

Custom shapes: paste SVG containing a `path` (and optional `viewBox`). Path `d` and `viewBox` are allowlisted on resolve; markup is sanitized on save.

Studio live preview mirrors PHP via `badge-studio/_compile.ts` + `badge-preview-shapes.ts` (mask data-URI + frame SVG).

## Border gap

Layered mode supports **Gap (px)** between the outer CSS border and the inner inset ring. Gap `0` matches the pre-designer flush inset. Transparent / low-alpha fills cannot paint a gap spacer — gap collapses to a flush inner ring.

## Placement

3×3 pad (corners + edge centers), priority, offset X/Y (−40…40), rotation (−45…45°).

## Fill & effects

- Solid or linear gradient (angle + two stops).
- Outer drop shadow (blur / spread / color).
- Optional glass blur (`backdrop-filter`; disabled under `prefers-reduced-motion`).

## Type & icon

Font size presets or custom px; weight, case, tracking, line height. Icon source (none / emoji / library / SVG) plus position: before text, after text, or icon only.

## Storefront CSS

`src/styles/woocommerce/product-badges.css` consumes `--badge-*` variables emitted by the schema. The same stylesheet loads on the badge taxonomy screens so list previews and the studio match the catalog.
