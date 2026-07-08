# Block Icon Design List

Each listed block has a local `icon.svg` source asset and a matching `icon.tsx` editor icon wrapper in the same block folder, kept in sync.

**Icon system (Rev A).** The icons are a native, WordPress-style **monochrome stroke** set — they read like core block icons in the inserter and track `currentColor` (no per-family colour). Rules:

- 24×24 grid, **20px live area** (~2px padding), single **1.5px stroke**, `fill="none"`.
- `stroke-linejoin="miter"` (crisp, on-brand corners) + `stroke-linecap="round"`.
- **One brand motif — "the cut":** a single 45° chamfer on the top-right corner of the dominant shape (framed glyphs), or a sharp **blade terminal** where the shape is round (search handle, arrows, price-tag point). Exactly one cut per icon.
- Small solid accents only (`fill="currentColor" stroke="none"`): blade arrowheads, razor stars, selected/badge dots, heart badges.
- Recurring shared marks: cut-cornered panel frame (containers), sharp diamond node, sharp-pointed heart (wishlist family), razor 4-point star (brand spark) / 5-point star (rating). Down-chevron = expand-in-place, right blade = drill/link/next.

| Block                                             | Family       | Icon direction                                                               |
| ------------------------------------------------- | ------------ | ---------------------------------------------------------------------------- |
| `src/blocks/product-rating`                       | Commerce     | Native-style rating star with a short review score row.                      |
| `src/blocks/aggressive-apparel-logo`              | Utility      | Native-style framed A mark for the brand logo block.                         |
| `src/blocks/copyright`                            | Utility      | Shielded copyright mark with native editor proportions.                      |
| `src/blocks/icon`                                 | Utility      | Reusable icon tile with a simple spark glyph.                                |
| `src/blocks/dark-mode-toggle`                     | Utility      | Native-style switch with a moon state.                                       |
| `src/blocks-interactivity/navigation`             | Navigation   | Native-style navigation rows branching to a menu node.                       |
| `src/blocks-interactivity/nav-link`               | Navigation   | Two navigation endpoints connected by a native arrow.                        |
| `src/blocks-interactivity/navigation-trigger`     | Navigation   | Menu trigger opening a side drawer.                                          |
| `src/blocks-interactivity/navigation-panel`       | Navigation   | Side navigation drawer with simple rows.                                     |
| `src/blocks-interactivity/nav-panel-header`       | Navigation   | Navigation panel header with close affordance.                               |
| `src/blocks-interactivity/nav-panel-footer`       | Navigation   | Navigation panel footer with compact actions.                                |
| `src/blocks-interactivity/nav-submenu-dropdown`   | Navigation   | Top nav item with a dropdown panel.                                          |
| `src/blocks-interactivity/nav-submenu-accordion`  | Navigation   | Stacked native accordion rows.                                               |
| `src/blocks-interactivity/nav-submenu-drilldown`  | Navigation   | Nested menu row drilling into another panel.                                 |
| `src/blocks-interactivity/nav-submenu-mega`       | Navigation   | Native-style mega menu grid.                                                 |
| `src/blocks-interactivity/wishlist`               | Commerce     | Native saved list with a heart marker.                                       |
| `src/blocks-interactivity/wishlist-button`        | Commerce     | Native pill button with saved heart action.                                  |
| `src/blocks-interactivity/wishlist-item-image`    | Commerce     | Product image frame with heart marker.                                       |
| `src/blocks-interactivity/wishlist-item-name`     | Commerce     | Product label with heart status.                                             |
| `src/blocks-interactivity/wishlist-item-price`    | Commerce     | Native price tag with value lines.                                           |
| `src/blocks-interactivity/wishlist-item-actions`  | Commerce     | Item action panel with a check mark.                                         |
| `src/blocks-interactivity/free-shipping-bar`      | Commerce     | Native truck with shipping progress.                                         |
| `src/blocks-interactivity/free-shipping-message`  | Commerce     | Message bubble with shipping cue.                                            |
| `src/blocks-interactivity/product-color-swatches` | Commerce     | Native grouped product swatches.                                             |
| `src/blocks-interactivity/product-tabs`           | Commerce     | Native tabbed product information panel.                                     |
| `src/blocks-interactivity/filter-toggle`          | Commerce     | Native filter funnel with active toggle.                                     |
| `src/blocks-interactivity/filter-active-bar`      | Commerce     | Native active filter chips row.                                              |
| `src/blocks-interactivity/hero-carousel`          | Motion Media | Native hero media slide with pagination.                                     |
| `src/blocks-interactivity/horizontal-scroll`      | Motion Media | Native horizontal card rail.                                                 |
| `src/blocks-interactivity/parallax`               | Motion Media | Native offset layers for depth movement.                                     |
| `src/blocks-interactivity/animate-on-scroll`      | Motion Media | Native viewport with an entering block.                                      |
| `src/blocks-interactivity/ticker`                 | Motion Media | Native moving content strip.                                                 |
| `src/blocks-interactivity/card-flip`              | Motion Media | Native card with a flip arrow.                                               |
| `src/blocks-interactivity/lookbook`               | Motion Media | Native editorial spread.                                                     |
| `src/blocks-interactivity/split-story`            | Motion Media | Native split editorial layout.                                               |
| `src/blocks-interactivity/recently-viewed`        | Motion Media | Native eye with a history cue.                                               |
| `src/blocks-interactivity/countdown-timer`        | Utility      | Native timer with deadline hand.                                             |
| `src/blocks-interactivity/modal`                  | Utility      | Native modal dialog over page frame.                                         |
| `src/blocks-interactivity/search`                 | Utility      | Native search lens with result lines.                                        |
| `src/blocks-interactivity/grid-list-toggle`       | Utility      | Native grid/list layout toggle.                                              |
| `src/blocks-interactivity/navigation` variations  | Navigation   | Simple nav rows, dropdown nav rows, and ecommerce cart-nav variation glyphs. |
