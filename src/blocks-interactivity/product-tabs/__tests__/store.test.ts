/**
 * Unit tests for the product-tabs store's deterministic logic: the tab/scrollspy
 * state getters, roving-tabindex keyboard navigation, and section selection.
 *
 * `@wordpress/interactivity` is virtual-mocked so the store config is captured
 * directly and getContext/getElement are controllable. The accordion collapse
 * animation and scroll-pinning need real layout + scrolling and are covered by
 * the Playwright e2e (`tests/e2e/product-tabs.spec.ts`) instead.
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

// jsdom implements neither of these; the store touches them.
beforeAll(() => {
  if (!window.matchMedia) {
    window.matchMedia = ((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: () => {},
      removeListener: () => {},
      addEventListener: () => {},
      removeEventListener: () => {},
      dispatchEvent: () => false,
    })) as any;
  }
  if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = function scrollIntoView(): void {};
  }
});

import { productTabsStore, isPanelOpen } from '../view';

const { state, actions } = productTabsStore as any;

afterEach(() => {
  document.body.innerHTML = '';
  mockContext = {};
  mockElement.ref = null;
  state.activeTab = 0;
  state.activeSection = '';
});

describe('isPanelOpen (accordion state)', () => {
  it('prefers the in-flight intent, falling back to the open attribute', () => {
    const details = document.createElement('details');

    // No intent yet → reflects the native attribute.
    expect(isPanelOpen(details)).toBe(false);
    details.setAttribute('open', '');
    expect(isPanelOpen(details)).toBe(true);

    // Mid-collapse: `open` lingers but the intent is closed.
    details.dataset.aaTarget = 'closed';
    expect(isPanelOpen(details)).toBe(false);

    // Mid-open: attribute set immediately, intent agrees.
    details.dataset.aaTarget = 'open';
    expect(isPanelOpen(details)).toBe(true);
  });
});

describe('tab state getters', () => {
  it('mark only the context tab as active/visible', () => {
    state.activeTab = 2;

    mockContext = { tabIndex: 2 };
    expect(state.isActiveTab).toBe(true);
    expect(state.isPanelVisible).toBe(true);
    expect(state.tabTabindex).toBe('0');
    expect(state.ariaSelected).toBe('true');

    mockContext = { tabIndex: 0 };
    expect(state.isActiveTab).toBe(false);
    expect(state.isPanelVisible).toBe(false);
    expect(state.tabTabindex).toBe('-1');
    expect(state.ariaSelected).toBe('false');
  });
});

describe('scrollspy state getters', () => {
  it('track the active section by id', () => {
    state.activeSection = 'pi-reviews';

    mockContext = { sectionId: 'pi-reviews' };
    expect(state.isActiveNav).toBe(true);
    expect(state.ariaCurrent).toBe('true');

    mockContext = { sectionId: 'pi-description' };
    expect(state.isActiveNav).toBe(false);
    expect(state.ariaCurrent).toBe('false');
  });
});

describe('selectTab', () => {
  it('sets the active tab from context and focuses the trigger', () => {
    const ref = document.createElement('button');
    document.body.appendChild(ref);
    const focus = jest.spyOn(ref, 'focus');
    mockElement.ref = ref;
    mockContext = { tabIndex: 3 };

    actions.selectTab();

    expect(state.activeTab).toBe(3);
    expect(focus).toHaveBeenCalled();
  });
});

describe('handleTabKeydown (roving tabindex)', () => {
  function buildTablist(count: number): HTMLElement[] {
    const nav = document.createElement('div');
    nav.setAttribute('role', 'tablist');
    const tabs: HTMLElement[] = [];
    for (let i = 0; i < count; i++) {
      const tab = document.createElement('button');
      tab.setAttribute('role', 'tab');
      nav.appendChild(tab);
      tabs.push(tab);
    }
    document.body.appendChild(nav);
    return tabs;
  }

  function press(key: string, target: HTMLElement): void {
    actions.handleTabKeydown({
      key,
      target,
      preventDefault: jest.fn(),
    } as any);
  }

  it('moves to the next/previous tab and wraps at the ends', () => {
    const tabs = buildTablist(3);

    state.activeTab = 0;
    press('ArrowRight', tabs[0]);
    expect(state.activeTab).toBe(1);

    state.activeTab = 2;
    press('ArrowRight', tabs[2]);
    expect(state.activeTab).toBe(0); // wraps forward

    state.activeTab = 0;
    press('ArrowLeft', tabs[0]);
    expect(state.activeTab).toBe(2); // wraps backward

    // Vertical arrows are aliases for horizontal.
    state.activeTab = 0;
    press('ArrowDown', tabs[0]);
    expect(state.activeTab).toBe(1);
    press('ArrowUp', tabs[1]);
    expect(state.activeTab).toBe(0);
  });

  it('jumps to first/last with Home/End and moves focus', () => {
    const tabs = buildTablist(4);
    const focusLast = jest.spyOn(tabs[3], 'focus');

    state.activeTab = 1;
    press('End', tabs[1]);
    expect(state.activeTab).toBe(3);
    expect(focusLast).toHaveBeenCalled();

    press('Home', tabs[3]);
    expect(state.activeTab).toBe(0);
  });

  it('ignores non-navigation keys and events outside a tablist', () => {
    const tabs = buildTablist(3);
    state.activeTab = 1;
    press('Enter', tabs[1]);
    expect(state.activeTab).toBe(1);

    const orphan = document.createElement('button');
    document.body.appendChild(orphan);
    press('ArrowRight', orphan);
    expect(state.activeTab).toBe(1);
  });
});

describe('scrollToSection', () => {
  it('sets the active section and scrolls its target into view', () => {
    const section = document.createElement('section');
    section.id = 'pi-additional_information';
    document.body.appendChild(section);
    const scrollIntoView = jest.spyOn(section, 'scrollIntoView');

    mockContext = { sectionId: 'pi-additional_information' };
    const preventDefault = jest.fn();
    actions.scrollToSection({ preventDefault } as any);

    expect(preventDefault).toHaveBeenCalled();
    expect(state.activeSection).toBe('pi-additional_information');
    expect(scrollIntoView).toHaveBeenCalled();
  });

  it('is a no-op when the target section is missing', () => {
    state.activeSection = 'pi-existing';
    mockContext = { sectionId: 'pi-missing' };

    actions.scrollToSection({ preventDefault: jest.fn() } as any);

    expect(state.activeSection).toBe('pi-existing');
  });
});
