# Navigation, overlays & block debug tooling

The two navigation subsystems and their cross-boundary gotchas, the shared
modal/overlay contract, and the parallax / animate-on-scroll debug tooling.

---

## Navigation System

Navigation is split into **two independent block subsystems**, each with its own
root block and Interactivity store. `nav-link` is the shared leaf used by both.

### Desktop subsystem — `aggressive-apparel/navigation`

Horizontal menu bar. Store: `aggressive-apparel/navigation` ([navigation/store.ts](../src/blocks-interactivity/navigation/store.ts)).

| Block                  | Role                                                                                                              |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `navigation`           | Root container. Provides submenu theming context (`navigationId`, `submenuBackgroundColor`, border/radius, etc.). |
| `navigation-trigger`   | Hamburger button. Lives in the desktop nav but drives the **mobile panel** store (opens the drawer).              |
| `nav-submenu-dropdown` | Click/hover dropdown (`ancestor: navigation`).                                                                    |
| `nav-submenu-mega`     | Full-width mega menu with arbitrary inner blocks (`ancestor: navigation`).                                        |

### Mobile subsystem — `aggressive-apparel/navigation-panel`

Slide-in drawer, **portaled to `wp_footer`** so `position: fixed` escapes ancestor
stacking/transform contexts. Store: `aggressive-apparel/navigation-panel`
([navigation-panel/store.ts](../src/blocks-interactivity/navigation-panel/store.ts)).
Mutable state lives in `state._panels[panelSlug]`, shared between the trigger and
the portaled panel.

| Block                                   | Role                                                                                          |
| --------------------------------------- | --------------------------------------------------------------------------------------------- |
| `navigation-panel`                      | Root drawer. Provides `navigationId` (= panelSlug) + panel hover color context.               |
| `nav-panel-header` / `nav-panel-footer` | Optional drawer chrome (`parent: navigation-panel`).                                          |
| `nav-submenu-accordion`                 | Expand-in-place submenu (`ancestor: navigation-panel`).                                       |
| `nav-submenu-drilldown`                 | Slide-over submenu, supports nesting + overlay/push animation (`ancestor: navigation-panel`). |

### Shared leaf

- `nav-link` — single link. `parent` of every container above. Consumes `navigationId` context.

### Shared code

- `nav-shared/dom.ts` + `nav-shared/keys.ts` hold the helpers/constants that are
  byte-identical across both subsystems (`logError`/`logWarning`,
  `safeQuerySelector*`, `safeGetElementById`, `prefersReducedMotion`, `KEYS`,
  `ARROW_KEYS`, `FOCUSABLE_SELECTOR`, `TRANSITION_DURATION_MS`). Each subsystem's
  `utils.ts`/`constants.ts` re-exports them, so internal `from './utils'` /
  `from './constants'` imports are unchanged. Subsystem-specific things
  (`SELECTORS`, `announce`, `focusMenuItem`, state classes, timing, ID helpers)
  intentionally stay per-subsystem because they differ.

### Cross-subsystem gotchas

- Because the panel is portaled, **`data-wp-bind` / `data-wp-class` directives don't
  react across the portal boundary**. Drilldown open-state class and the trigger's
  `aria-expanded` are toggled imperatively in `callbacks.onSubmenuStateChange`.
- `focus()` on an element inside an off-screen sliding panel cancels the slide —
  always pass `{ preventScroll: true }` for in-panel focus moves.
- The blocks have **no view modules**. Each subsystem's store is shipped once as a
  shared script module (`@aggressive-apparel/navigation-store` /
  `-panel-store`, built from `src/interactivity/`) and enqueued directly in
  `class-navigation-functions.php`; the store self-registers via `store()` before
  hydration. `supports.interactivity` loads the runtime; `render.php` emits the
  directives. Don't add per-block `viewScriptModule`s back — `wp_enqueue_script_module`
  alone doesn't add a bare specifier to the import map (only declared deps of
  enqueued modules get mapped), so a view module importing the store would fail.

**Key Attributes:**

- `breakpoint`: Mobile breakpoint (default: 1024px)
- `openOn`: "hover" or "click" for desktop submenus
- `position`: "left" or "right" for the mobile panel
- drilldown `animationStyle`: "overlay" or "push" (iOS-style parallax)

## Modal & Overlay Pattern

All full-screen modals and overlays **must** follow this consistent pattern:

### CSS

1. **Animated backdrop blur**: Base state `backdrop-filter: blur(0)`, transitions to `blur(4px)` on open. Both `background-color` and `backdrop-filter` are in the `transition` list so they animate in and out smoothly.
2. **`@starting-style`** for entry animation: Wrapped in `@supports selector(@starting-style)`. Defines the initial state (opacity 0, transform, blur 0) so the browser has a "before" state to transition from.
3. **`prefers-reduced-motion: reduce`**: Disables all transitions, animations, and `backdrop-filter`.
4. **`[hidden]` override**: `display: none` to ensure the hidden attribute works with flex/grid containers.

### JavaScript

1. **`lockScroll()` on open** (from `@aggressive-apparel/scroll-lock`): Called immediately when opening.
2. **`unlockScroll()` deferred to `transitionend`** on close: Listen for `transitionend` with `propertyName === 'opacity'` on the modal/panel element. Include a safety `setTimeout` fallback (~50ms after expected duration) for reduced motion or edge cases. Use a `done` flag to prevent double execution.
3. **`hidden` attribute managed manually**: Remove `hidden` + force reflow (`void el.offsetHeight`) before setting open state. Set `hidden = true` inside the same `finish()` callback as `unlockScroll()`.

### Current Implementations

| Component         | CSS                     | JS              | Backdrop Opacity |
| ----------------- | ----------------------- | --------------- | ---------------- |
| Quick View        | `quick-view.css`        | `quick-view.js` | 50%              |
| Size Guide        | `size-guide.css`        | `size-guide.js` | 80%              |
| Bottom Nav Search | `mobile-bottom-nav.css` | `bottom-nav.js` | 50%              |

## Block Debug Tooling (parallax / animate-on-scroll)

Both blocks share one debug implementation in `src/blocks-interactivity/debug-shared/`
(controller, panel, overlays, probe, perf monitor, i18n); the per-block files
(`parallax/debug/controller.ts`, `animate-on-scroll/debug.ts`) are thin adapters.
Inspector preset UI is likewise shared via `src/blocks-interactivity/editor-shared/`.

- **Visitors get zero debug bytes.** `debugMode` is gated in each `render.php`
  by `aggressive_apparel_can_view_block_debug()` (`edit_posts`, filterable) —
  gating the context prevents the code-split debug chunk from loading, and the
  debug CSS ships standalone (`src/styles/components/debug-overlays.css` stub →
  enqueued only when debug renders via `aggressive_apparel_enqueue_block_debug_assets()`,
  which also prints the translated `#aa-dbg-i18n` strings blob — keys mirror
  `debug-shared/i18n.ts`).
- **Cross-bundle rule:** shared dirs compile into EACH block's bundle, so
  module-level state cannot coordinate block types. Coordinate through the DOM
  (dataset counter on `<html>`, data-attribute refcounts, DOM element counts).
- **Activation buffer:** parallax expands its observer boundary by the
  `activationBuffer` attribute (% of viewport height, default 20, 0 disables)
  so the frame engine warms up before layers become visible — that's why the
  debug view shows both a "Detection boundary" and an "Observer boundary" for
  parallax but a single box for animate-on-scroll (which needs no warm-up).
- **Effective threshold:** production observers use `getEffectiveThreshold()`
  (`debug-shared/utils`, pure — safe to import from view code): elements taller
  than the root box auto-cap the trigger at 90% of the reachable ratio. The
  debug UI displays the same effective value (parallax stashes it on
  `ctx.effectiveThreshold`).
- The debug probe runs its own dense-threshold IntersectionObserver with the
  production rootMargin — never widen production observer thresholds for
  debugging.
