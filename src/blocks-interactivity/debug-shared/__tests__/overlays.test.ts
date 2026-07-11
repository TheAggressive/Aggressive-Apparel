/**
 * Tests for the shared scroll-debug overlays.
 *
 * @jest-environment jsdom
 */

import {
  acquireBoundaryOverlay,
  createElementOverlay,
  releaseBoundaryOverlays,
} from '../overlays';
import type { DebugBoundary } from '../types';

const BOUNDARY_A: DebugBoundary = {
  top: '-10%',
  right: '0%',
  bottom: '-10%',
  left: '0%',
};
const BOUNDARY_B: DebugBoundary = {
  top: '200px',
  right: '0%',
  bottom: '0%',
  left: '0%',
};

afterEach(() => {
  document.body.innerHTML = '';
  releaseBoundaryOverlays('block-1');
  releaseBoundaryOverlays('block-2');
});

describe('boundary overlay registry', () => {
  it('shares one element between instances with identical geometry', () => {
    acquireBoundaryOverlay('block-1', 'configured', BOUNDARY_A, 'Detection');
    acquireBoundaryOverlay('block-2', 'configured', BOUNDARY_A, 'Detection');

    expect(document.querySelectorAll('.aa-dbg-boundary')).toHaveLength(1);

    releaseBoundaryOverlays('block-1');
    expect(document.querySelectorAll('.aa-dbg-boundary')).toHaveLength(1);

    releaseBoundaryOverlays('block-2');
    expect(document.querySelectorAll('.aa-dbg-boundary')).toHaveLength(0);
  });

  it('creates separate overlays for different geometries', () => {
    acquireBoundaryOverlay('block-1', 'configured', BOUNDARY_A, 'Detection');
    acquireBoundaryOverlay('block-2', 'configured', BOUNDARY_B, 'Detection');

    expect(document.querySelectorAll('.aa-dbg-boundary')).toHaveLength(2);
  });

  it('inverts the boundary into the inset and flags off-viewport bounds', () => {
    acquireBoundaryOverlay('block-1', 'configured', BOUNDARY_B, 'Detection');

    const overlay = document.querySelector<HTMLElement>('.aa-dbg-boundary');
    expect(overlay?.style.inset).toBe('-200px 0% 0% 0%');
    expect(overlay?.querySelector('.aa-dbg-boundary__label')?.textContent).toBe(
      'Detection · extends beyond viewport'
    );
  });

  it('releases configured and effective overlays together', () => {
    acquireBoundaryOverlay('block-1', 'configured', BOUNDARY_A, 'Detection');
    acquireBoundaryOverlay('block-1', 'effective', BOUNDARY_B, 'Observer');
    expect(document.querySelectorAll('.aa-dbg-boundary')).toHaveLength(2);

    releaseBoundaryOverlays('block-1');
    expect(document.querySelectorAll('.aa-dbg-boundary')).toHaveLength(0);
  });
});

describe('element overlay', () => {
  const mockRect = (target: HTMLElement, height: number): void => {
    target.getBoundingClientRect = jest.fn(
      () =>
        ({
          top: 100,
          left: 50,
          width: 400,
          height,
          right: 450,
          bottom: 100 + height,
          x: 50,
          y: 100,
          toJSON: () => ({}),
        }) as DOMRect
    );
  };

  it('positions the entry line/zone from the threshold', () => {
    const target = document.createElement('div');
    document.body.appendChild(target);
    mockRect(target, 500);

    const overlay = createElementOverlay(target, { threshold: 0.3 });

    const entryLine = document.querySelector<HTMLElement>(
      '.aa-dbg-line--entry'
    );
    const zone = document.querySelector<HTMLElement>('.aa-dbg-zone');
    expect(entryLine?.style.top).toBe('150px');
    expect(zone?.style.top).toBe('150px');
    expect(zone?.style.height).toBe('350px');

    // Top-entry counterpart sits at (1 - threshold) × height.
    const topLine = document.querySelector<HTMLElement>(
      '.aa-dbg-line--entry-top'
    );
    expect(topLine?.style.top).toBe('350px');
    expect(topLine?.hidden).toBe(false);

    overlay.destroy();
    expect(document.querySelector('.aa-dbg-element')).toBeNull();
  });

  it('hides the top-entry line when it would overlap the entry line', () => {
    const target = document.createElement('div');
    document.body.appendChild(target);
    mockRect(target, 500);

    createElementOverlay(target, { threshold: 0.5 });

    expect(
      document.querySelector<HTMLElement>('.aa-dbg-line--entry-top')?.hidden
    ).toBe(true);
  });

  it('renders the instance tag only when a label is given', () => {
    const target = document.createElement('div');
    document.body.appendChild(target);
    mockRect(target, 500);

    createElementOverlay(target, { threshold: 0.3, label: 'parallax_42' });
    expect(document.querySelector('.aa-dbg-element__tag')?.textContent).toBe(
      'parallax_42'
    );

    document.body.innerHTML = '';
    const bare = document.createElement('div');
    document.body.appendChild(bare);
    mockRect(bare, 500);
    createElementOverlay(bare, { threshold: 0.3 });
    expect(document.querySelector('.aa-dbg-element__tag')).toBeNull();
  });

  it('applies the identity accent as a CSS custom property', () => {
    const target = document.createElement('div');
    document.body.appendChild(target);
    mockRect(target, 500);

    createElementOverlay(target, {
      threshold: 0.3,
      accent: 'oklch(72% 0.17 230deg)',
    });

    const container = document.querySelector<HTMLElement>('.aa-dbg-element');
    expect(container?.style.getPropertyValue('--aa-dbg-identity')).toBe(
      'oklch(72% 0.17 230deg)'
    );
  });

  it('renders an exit line only when an exit threshold is given', () => {
    const target = document.createElement('div');
    document.body.appendChild(target);
    mockRect(target, 500);

    createElementOverlay(target, { threshold: 0.4, exitThreshold: 0.2 });

    const exitLine = document.querySelector<HTMLElement>('.aa-dbg-line--exit');
    expect(exitLine?.style.top).toBe('100px');

    document.body.innerHTML = '';
    const target2 = document.createElement('div');
    document.body.appendChild(target2);
    mockRect(target2, 500);
    createElementOverlay(target2, { threshold: 0.4 });
    expect(document.querySelector('.aa-dbg-line--exit')).toBeNull();
  });

  it('swaps phase classes without stacking them', () => {
    const target = document.createElement('div');
    document.body.appendChild(target);
    mockRect(target, 500);

    const overlay = createElementOverlay(target, { threshold: 0.3 });
    const container = document.querySelector<HTMLElement>('.aa-dbg-element');

    expect(container?.classList.contains('is-waiting')).toBe(true);

    overlay.setPhase('active');
    expect(container?.classList.contains('is-active')).toBe(true);
    expect(container?.classList.contains('is-waiting')).toBe(false);

    overlay.setPhase('approaching');
    expect(container?.classList.contains('is-approaching')).toBe(true);
    expect(container?.classList.contains('is-active')).toBe(false);
  });
});
