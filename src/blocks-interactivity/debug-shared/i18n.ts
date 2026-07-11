/**
 * Localizable strings for the shared scroll-debug tooling.
 *
 * The debug UI runs inside code-split view-module chunks where
 * `@wordpress/i18n` is not available as a script module, so translation
 * happens in PHP: `aggressive_apparel_enqueue_block_debug_assets()`
 * prints a `#aa-dbg-i18n` JSON blob (once, footer, debug-gated) whose
 * keys MUST mirror `DEFAULT_STRINGS` below. Missing/absent blob falls
 * back to English — the tool always works.
 *
 * Templates use `{name}` placeholders resolved by `fmt()`.
 *
 * @package Aggressive Apparel
 */

export interface DebugStrings {
  titleParallax: string;
  titleAos: string;
  panelCollapse: string;
  panelExpand: string;
  sectionLive: string;
  sectionDetails: string;
  legend: string;
  rowState: string;
  rowVisibility: string;
  rowProgress: string;
  rowDirection: string;
  rowThreshold: string;
  rowFramerate: string;
  rowSize: string;
  rowBoundary: string;
  rowObserver: string;
  phaseWaiting: string;
  phaseApproaching: string;
  phaseActive: string;
  engineLabel: string;
  engineActive: string;
  engineIdle: string;
  animationLabel: string;
  animationShown: string;
  animationHidden: string;
  reverseLabel: string;
  yes: string;
  no: string;
  directionDown: string;
  directionUp: string;
  measuring: string;
  thresholdEntry: string;
  thresholdEntryExit: string;
  boundaryConfigured: string;
  boundaryEffective: string;
  boundaryExtends: string;
  lineEntryBottom: string;
  lineEntryTop: string;
  lineExit: string;
  legendBoundary: string;
  legendEffective: string;
  legendElement: string;
  legendEntry: string;
  legendEntryTop: string;
  legendExit: string;
  legendZone: string;
  warnUnreachable: string;
}

/** English defaults; PHP overrides keys via the #aa-dbg-i18n blob. */
export const DEFAULT_STRINGS: DebugStrings = {
  titleParallax: 'Parallax Debug',
  titleAos: 'Animate On Scroll Debug',
  panelCollapse: 'Collapse debug panel',
  panelExpand: 'Expand debug panel',
  sectionLive: 'Live state',
  sectionDetails: 'Details',
  legend: 'Legend',
  rowState: 'State',
  rowVisibility: 'Visibility',
  rowProgress: 'Progress',
  rowDirection: 'Scroll direction',
  rowThreshold: 'Threshold',
  rowFramerate: 'Frame rate',
  rowSize: 'Element size',
  rowBoundary: 'Boundary',
  rowObserver: 'Observer',
  phaseWaiting: 'Waiting',
  phaseApproaching: 'Approaching',
  phaseActive: 'Active',
  engineLabel: 'Engine',
  engineActive: 'Active',
  engineIdle: 'Idle',
  animationLabel: 'Animation',
  animationShown: 'Shown',
  animationHidden: 'Hidden',
  reverseLabel: 'Reverse on scroll back',
  yes: 'Yes',
  no: 'No',
  directionDown: '↓ Down',
  directionUp: '↑ Up',
  measuring: '— measuring…',
  thresholdEntry: '{pct}% entry',
  thresholdEntryExit: '{entry}% entry · {exit}% exit',
  boundaryConfigured: 'Detection boundary',
  boundaryEffective: 'Observer boundary (incl. engine buffer)',
  boundaryExtends: '· extends beyond viewport',
  lineEntryBottom: 'Entry (bottom) {pct}%',
  lineEntryTop: 'Entry (top) {pct}%',
  lineExit: 'Exit ≤ {pct}%',
  legendBoundary:
    'Detection boundary — area the observer watches (viewport ± your margins)',
  legendEffective:
    'Observer boundary — detection boundary plus the engine’s pre-activation buffer',
  legendElement:
    'This block’s element — outlined even while its content is hidden',
  legendEntry: 'Entry line — triggers at {pct}% visible when scrolling down',
  legendEntryTop:
    'Entry line for scrolling up (same {pct}%, measured from the bottom)',
  legendExit: 'Exit line — reverses once visibility falls below {pct}%',
  legendZone:
    'Entry zone — tinted band the boundary edge must reach to trigger',
  warnUnreachable:
    'Entry threshold {pct}% is unreachable: the element ({elem}px) is taller than the detection area ({root}px). Max visibility ≈ {max}%.',
};

let cached: DebugStrings | null = null;

/** Resolve strings: PHP-provided translations over English defaults. */
export const getStrings = (): DebugStrings => {
  if (cached) {
    return cached;
  }
  let overrides: Partial<DebugStrings> = {};
  try {
    const node = document.getElementById('aa-dbg-i18n');
    if (node?.textContent) {
      const parsed: unknown = JSON.parse(node.textContent);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        overrides = parsed as Partial<DebugStrings>;
      }
    }
  } catch {
    // Malformed blob → English defaults.
  }
  cached = { ...DEFAULT_STRINGS, ...overrides };
  return cached;
};

/** Replace {name} placeholders in a string template. */
export const fmt = (
  template: string,
  values: Record<string, string | number>
): string =>
  template.replace(/\{(\w+)\}/g, (match, key: string) =>
    key in values ? String(values[key]) : match
  );

/** Test-only: clear the memoized strings. */
export const resetStringsForTests = (): void => {
  cached = null;
};
