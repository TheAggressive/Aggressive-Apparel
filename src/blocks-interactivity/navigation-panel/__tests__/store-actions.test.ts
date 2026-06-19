/**
 * Tests for the navigation-panel store's drill-stack + submenu actions.
 *
 * `@wordpress/interactivity` is mocked so `store()` returns the raw config
 * (exposing actions/callbacks/state) and `getContext()`/`getElement()` are
 * controllable. The DOM-touching steps inside the actions (findPanel, inert,
 * focus) no-op without a panel in the document, leaving the state transitions
 * under test in isolation.
 *
 * @jest-environment jsdom
 */

// Reassigned per test; referenced in the (hoisted) jest.mock factory.
let mockContext: Record<string, unknown> = {};
const mockElement = { ref: null as HTMLElement | null };

jest.mock(
  '@wordpress/interactivity',
  () => ({
    store: (_ns: string, config: unknown) => config,
    getContext: () => mockContext,
    getElement: () => mockElement,
    withSyncEvent: (fn: unknown) => fn,
  }),
  // The real module is a WP runtime external (not installed), so mock it virtually.
  { virtual: true }
);

import panelStore from '../store';

const actions = (panelStore as any).actions;
const callbacks = (panelStore as any).callbacks;
const state = (panelStore as any).state;

let slugCounter = 0;

// A fresh panel slug per test (isolates the module-global _panels state). Also
// drops in the matching announcer element so announce() doesn't warn about a
// missing live region.
function freshSlug(): string {
  const slug = `panel-${slugCounter++}`;
  const announcer = document.createElement('div');
  announcer.id = `${slug}-announcer`;
  announcer.setAttribute('aria-live', 'polite');
  document.body.appendChild(announcer);
  return slug;
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('drill stack actions', () => {
  it('drillInto pushes submenus and drillBack pops them', () => {
    const panelSlug = freshSlug();

    mockContext = { panelSlug, submenuId: 'shop' };
    actions.drillInto();
    expect(state.drillStack).toEqual(['shop']);

    mockContext = { panelSlug, submenuId: 'men' };
    actions.drillInto();
    expect(state.drillStack).toEqual(['shop', 'men']);

    mockContext = { panelSlug };
    actions.drillBack();
    expect(state.drillStack).toEqual(['shop']);

    actions.drillBack();
    expect(state.drillStack).toEqual([]);
  });

  it('drillBack is a no-op on an empty stack', () => {
    const panelSlug = freshSlug();
    mockContext = { panelSlug };
    expect(() => actions.drillBack()).not.toThrow();
    expect(state.drillStack).toEqual([]);
  });

  it('closeAllSubmenus clears the stack and active submenu', () => {
    const panelSlug = freshSlug();
    mockContext = { panelSlug, submenuId: 'a' };
    actions.drillInto();
    mockContext = { panelSlug, submenuId: 'b' };
    actions.toggleSubmenu();

    mockContext = { panelSlug };
    actions.closeAllSubmenus();
    expect(state.drillStack).toEqual([]);
    expect(state.activeSubmenuId).toBeNull();
  });

  it('dispatches aa-nav-panel-state-change so the portaled blocks can react', () => {
    const panelSlug = freshSlug();
    const spy = jest.spyOn(window, 'dispatchEvent');
    mockContext = { panelSlug, submenuId: 'shop' };
    actions.drillInto();

    const evt = spy.mock.calls
      .map(c => c[0])
      .find(e => e.type === 'aa-nav-panel-state-change') as
      | CustomEvent
      | undefined;
    expect(evt).toBeDefined();
    expect(evt?.detail).toEqual({ panelSlug });
    spy.mockRestore();
  });
});

describe('mega/accordion submenu toggle', () => {
  it('toggleSubmenu sets then clears the active submenu', () => {
    const panelSlug = freshSlug();
    mockContext = { panelSlug, submenuId: 'mega1', menuType: 'accordion' };

    actions.toggleSubmenu();
    expect(state.activeSubmenuId).toBe('mega1');

    // Toggling the same id closes it.
    actions.toggleSubmenu();
    expect(state.activeSubmenuId).toBeNull();
  });

  it('isSubmenuOpen reflects the active submenu', () => {
    const panelSlug = freshSlug();
    mockContext = { panelSlug, submenuId: 'x', menuType: 'accordion' };
    expect(callbacks.isSubmenuOpen()).toBe(false);
    actions.toggleSubmenu();
    expect(callbacks.isSubmenuOpen()).toBe(true);
  });
});

describe('hasDrillHistory callback', () => {
  it('is false at the root and true once drilled in', () => {
    const panelSlug = freshSlug();
    mockContext = { panelSlug };
    expect(callbacks.hasDrillHistory()).toBe(false);

    mockContext = { panelSlug, submenuId: 'shop' };
    actions.drillInto();
    mockContext = { panelSlug };
    expect(callbacks.hasDrillHistory()).toBe(true);
  });
});
