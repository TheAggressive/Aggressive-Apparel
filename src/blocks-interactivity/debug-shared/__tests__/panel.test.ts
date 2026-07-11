/**
 * Tests for the shared scroll-debug floating panel.
 *
 * @jest-environment jsdom
 */

import { createDebugPanel, type PanelHandle } from '../panel';

const buildPanel = (storageKey = 'aa-dbg:test:1'): PanelHandle =>
  createDebugPanel({
    title: 'Test Debug',
    storageKey,
    sections: [
      {
        id: 'basic',
        label: 'Live state',
        rows: [
          { id: 'state', label: 'State', kind: 'badge' },
          {
            id: 'visibility',
            label: 'Visibility',
            kind: 'meter',
            markers: [0.3],
          },
          { id: 'direction', label: 'Direction', kind: 'text' },
        ],
      },
      {
        id: 'advanced',
        label: 'Details',
        startCollapsed: true,
        rows: [{ id: 'size', label: 'Size', kind: 'text' }],
      },
    ],
  });

afterEach(() => {
  document.body.innerHTML = '';
  window.localStorage.clear();
  jest.restoreAllMocks();
});

describe('createDebugPanel', () => {
  it('renders sections, honors startCollapsed, and toggles them', () => {
    buildPanel();

    const panel = document.querySelector<HTMLElement>('.aa-dbg-panel');
    expect(panel).not.toBeNull();
    expect(panel?.getAttribute('aria-label')).toBe('Test Debug');

    const bodies = document.querySelectorAll<HTMLElement>(
      '.aa-dbg-section__body'
    );
    expect(bodies[0].hidden).toBe(false);
    expect(bodies[1].hidden).toBe(true);

    const headers = document.querySelectorAll<HTMLButtonElement>(
      '.aa-dbg-section__header'
    );
    headers[1].click();
    expect(bodies[1].hidden).toBe(false);
    expect(headers[1].getAttribute('aria-expanded')).toBe('true');
  });

  it('updates text, badges (single modifier), meters, and warnings', () => {
    const handle = buildPanel();

    handle.setText('direction', '↓ Down');
    handle.setBadge('state', 'Active', 'active');
    handle.setBadge('state', 'Waiting', 'waiting');
    handle.setMeter('visibility', 1.7, '100.0%');
    handle.setWarning('Too tall');

    const values = Array.from(
      document.querySelectorAll('.aa-dbg-row__value')
    ).map(node => node.textContent);
    expect(values).toContain('↓ Down');

    const badge = document.querySelector('.aa-dbg-badge');
    expect(badge?.textContent).toBe('Waiting');
    expect(badge?.classList.contains('aa-dbg-badge--waiting')).toBe(true);
    expect(badge?.classList.contains('aa-dbg-badge--active')).toBe(false);

    const fill = document.querySelector<HTMLElement>('.aa-dbg-meter__fill');
    expect(fill?.style.width).toBe('100%');

    const warning = document.querySelector<HTMLElement>(
      '.aa-dbg-panel__warning'
    );
    expect(warning?.hidden).toBe(false);
    expect(warning?.textContent).toContain('Too tall');

    handle.setWarning(null);
    expect(warning?.hidden).toBe(true);
  });

  it('collapses via the header button and persists the state', () => {
    const handle = buildPanel();

    const collapse = document.querySelector<HTMLButtonElement>(
      '.aa-dbg-panel__collapse'
    );
    collapse?.click();

    const body = document.querySelector<HTMLElement>('.aa-dbg-panel__body');
    expect(body?.hidden).toBe(true);
    expect(collapse?.getAttribute('aria-expanded')).toBe('false');

    const persisted = JSON.parse(
      window.localStorage.getItem('aa-dbg:test:1') ?? '{}'
    );
    expect(persisted.collapsed).toBe(true);
    handle.destroy();

    // A new panel with the same key restores collapsed.
    buildPanel();
    expect(
      document.querySelector<HTMLElement>('.aa-dbg-panel__body')?.hidden
    ).toBe(true);
  });

  it('clamps restored positions so a panel can never be off-screen', () => {
    window.localStorage.setItem(
      'aa-dbg:test:1',
      JSON.stringify({ left: 99999, top: -500 })
    );

    buildPanel();

    const panel = document.querySelector<HTMLElement>('.aa-dbg-panel');
    const left = parseFloat(panel?.style.left ?? '0');
    const top = parseFloat(panel?.style.top ?? '0');
    expect(left).toBeLessThanOrEqual(window.innerWidth);
    expect(left).toBeGreaterThanOrEqual(0);
    expect(top).toBeGreaterThanOrEqual(0);
  });

  it('survives a throwing localStorage (private browsing)', () => {
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied');
    });
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied');
    });

    expect(() => {
      const handle = buildPanel();
      document
        .querySelector<HTMLButtonElement>('.aa-dbg-panel__collapse')
        ?.click();
      handle.destroy();
    }).not.toThrow();
  });

  it('removes the element and its document-level listeners on destroy', () => {
    const handle = buildPanel();
    const removeSpy = jest.spyOn(document, 'removeEventListener');

    handle.destroy();

    expect(document.querySelector('.aa-dbg-panel')).toBeNull();
    // AbortController-driven cleanup detaches the drag listeners.
    expect(() =>
      document.dispatchEvent(new MouseEvent('pointermove'))
    ).not.toThrow();
    removeSpy.mockRestore();
  });

  it('cascades default positions for multiple panels', () => {
    const first = buildPanel('aa-dbg:test:first');
    const second = buildPanel('aa-dbg:test:second');

    const panels = document.querySelectorAll<HTMLElement>('.aa-dbg-panel');
    expect(panels[0].style.right).not.toBe(panels[1].style.right);

    first.destroy();
    second.destroy();
  });
});
