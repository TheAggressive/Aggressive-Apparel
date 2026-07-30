/**
 * Tests for the desktop navigation store's submenu + mobile-detection logic.
 *
 * `@wordpress/interactivity` is virtual-mocked so the store config is exposed
 * directly and getContext/getElement are controllable. Indicator/DOM steps
 * no-op without the menubar in the document.
 *
 * @jest-environment jsdom
 */

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
  { virtual: true }
);

import navigationStore from '../store';

const actions = (navigationStore as any).actions;
const callbacks = (navigationStore as any).callbacks;
const state = (navigationStore as any).state;

let navCounter = 0;

// Fresh nav id per test (isolates module-global _navs) plus the matching
// announcer element so announce() doesn't warn about a missing live region.
function freshNav(): string {
  const navId = `nav-test-${navCounter++}`;
  const announcer = document.createElement('div');
  announcer.id = `navigation-announcer-${navId}`;
  announcer.setAttribute('aria-live', 'polite');
  document.body.appendChild(announcer);
  return navId;
}

beforeAll(() => {
  mockElement.ref = document.createElement('nav');
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('submenu open/close', () => {
  it('toggleSubmenu opens then closes the same submenu', () => {
    const navId = freshNav();

    mockContext = { navId, submenuId: 'shop' };
    actions.toggleSubmenu();
    expect(state.activeSubmenuId).toBe('shop');

    actions.toggleSubmenu();
    expect(state.activeSubmenuId).toBeNull();
  });

  it('toggleSubmenu switches directly between submenus', () => {
    const navId = freshNav();

    mockContext = { navId, submenuId: 'shop' };
    actions.toggleSubmenu();
    mockContext = { navId, submenuId: 'about' };
    actions.toggleSubmenu();
    expect(state.activeSubmenuId).toBe('about');
  });

  it('closeSubmenu and closeAllSubmenus clear the active submenu', () => {
    const navId = freshNav();

    mockContext = { navId, submenuId: 'shop' };
    actions.openSubmenu();
    expect(state.activeSubmenuId).toBe('shop');

    mockContext = { navId };
    actions.closeSubmenu();
    expect(state.activeSubmenuId).toBeNull();

    mockContext = { navId, submenuId: 'shop' };
    actions.openSubmenu();
    mockContext = { navId };
    actions.closeAllSubmenus();
    expect(state.activeSubmenuId).toBeNull();
  });

  it('isSubmenuOpen reflects the active submenu', () => {
    const navId = freshNav();

    mockContext = { navId, submenuId: 'shop' };
    expect(callbacks.isSubmenuOpen()).toBe(false);
    actions.toggleSubmenu();
    expect(callbacks.isSubmenuOpen()).toBe(true);
  });

  it('Escape closes the submenu and restores focus to its trigger', () => {
    jest.spyOn(window, 'requestAnimationFrame').mockImplementation(callback => {
      callback(0);
      return 0;
    });
    const navId = freshNav();
    const nav = document.createElement('nav');
    const trigger = document.createElement('button');
    const panelLink = document.createElement('a');

    nav.id = navId;
    trigger.setAttribute('aria-controls', 'shop');
    panelLink.href = '/shop/item/';
    nav.append(trigger, panelLink);
    document.body.appendChild(nav);

    mockContext = { navId, submenuId: 'shop' };
    actions.openSubmenu();
    panelLink.focus();

    const event = new KeyboardEvent('keydown', {
      key: 'Escape',
      cancelable: true,
    });
    callbacks.onEscape(event);

    expect(state.activeSubmenuId).toBeNull();
    expect(document.activeElement).toBe(trigger);
    expect(event.defaultPrevented).toBe(true);
  });
});

describe('mobile detection (init)', () => {
  it('sets isMobile from the breakpoint matchMedia query', () => {
    const navId = freshNav();
    const matchMedia = jest.fn().mockReturnValue({
      matches: true,
      addEventListener: jest.fn(),
    });
    window.matchMedia = matchMedia as unknown as typeof window.matchMedia;

    mockContext = { navId, breakpoint: 768, openOn: 'hover' };
    callbacks.init();

    expect(matchMedia).toHaveBeenCalledWith('(max-width: 767px)');
    expect(state.isMobile).toBe(true);
  });
});
