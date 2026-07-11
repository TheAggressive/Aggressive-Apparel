/**
 * Tests for the parallax frame-path performance work: split geometry
 * (measure once, compute per frame) stays byte-identical to the legacy
 * single-call API, and redundant style writes are skipped.
 *
 * @jest-environment jsdom
 */

import type { CachedLayer } from '../layers';
import { applyLayerFrame, type FrameInput } from '../transforms';
import type { ParallaxContext } from '../types';
import {
  calculateProgressWithinBoundary,
  measureProgressGeometry,
  progressFromGeometry,
} from '../utils';

const BOUNDARY = { top: '20%', right: '0%', bottom: '-10%', left: '0%' };

/** Element with synthetic layout metrics (jsdom has no layout engine). */
const makeMeasuredElement = (
  offsetTop: number,
  height: number
): HTMLElement => {
  const el = document.createElement('div');
  Object.defineProperty(el, 'offsetTop', { value: offsetTop });
  Object.defineProperty(el, 'offsetParent', { value: null });
  Object.defineProperty(el, 'offsetHeight', { value: height });
  return el;
};

const setScrollY = (y: number): void => {
  Object.defineProperty(window, 'scrollY', { value: y, configurable: true });
};

describe('progress geometry split', () => {
  it('measure + compute matches the legacy single-call API exactly', () => {
    const el = makeMeasuredElement(2400, 500);
    const geom = measureProgressGeometry(el, BOUNDARY);

    for (const scrollY of [0, 800, 1600, 2400, 3200, 9999]) {
      setScrollY(scrollY);
      const legacy = calculateProgressWithinBoundary(el, BOUNDARY, 0.3);
      const split = progressFromGeometry(
        geom,
        scrollY,
        window.innerHeight,
        0.3
      );
      expect(split.progress).toBe(legacy.progress);
      expect(split.isWithinBoundary).toBe(legacy.isWithinBoundary);
    }
  });

  it('parses boundary values once into pixels', () => {
    const el = makeMeasuredElement(1000, 400);
    const geom = measureProgressGeometry(el, BOUNDARY);
    // jsdom viewport is 1024×768: 20% of 768 = 153.6, -10% = -76.8.
    expect(geom.boundaryTopPx).toBeCloseTo(153.6);
    expect(geom.boundaryBottomPx).toBeCloseTo(-76.8);
    expect(geom.docTop).toBe(1000);
    expect(geom.elementHeight).toBe(400);
  });

  it('progress runs 0→1 through the zone', () => {
    const el = makeMeasuredElement(2000, 400);
    const geom = measureProgressGeometry(el, {
      top: '0%',
      right: '0%',
      bottom: '0%',
      left: '0%',
    });
    const early = progressFromGeometry(geom, 0, 768, 0);
    const late = progressFromGeometry(geom, 5000, 768, 0);
    expect(early.progress).toBe(0);
    expect(late.progress).toBe(1);
  });
});

describe('applyLayerFrame write dedupe', () => {
  const ctx = { intensity: 60 } as ParallaxContext;

  const makeLayer = (
    effects: CachedLayer['effects'] = {}
  ): CachedLayer & { element: HTMLElement } => ({
    element: document.createElement('div'),
    depth: 0.4,
    speed: 1.2,
    direction: 'down',
    ease: (v: number) => v,
    effects,
    depthScale: 1,
    hasFrameEffects: Boolean(effects.scrollOpacity?.enabled),
    needsRect: false,
    rect: null,
    lastTranslate: '',
    lastScale: '',
    lastEffectStyles: {},
    baselineEasedFor: NaN,
    baselineEased: 0,
  });

  const frame: FrameInput = {
    progress: 0.7,
    baseline: 0.5,
    pointerX: 0,
    pointerY: 0,
    is3D: false,
  };

  it('skips translate/scale writes when values are unchanged', () => {
    const layer = makeLayer();
    applyLayerFrame(layer, frame, ctx);
    expect(layer.element.style.translate).not.toBe('');

    // External mutation survives an identical frame → write was skipped.
    layer.element.style.translate = '999px 999px';
    layer.element.style.scale = '9';
    applyLayerFrame(layer, { ...frame }, ctx);
    expect(layer.element.style.translate).toBe('999px 999px');
    expect(layer.element.style.scale).toBe('9');

    // A real change writes again.
    applyLayerFrame(layer, { ...frame, progress: 0.9 }, ctx);
    expect(layer.element.style.translate).not.toBe('999px 999px');
  });

  it('dedupes effect style writes per property', () => {
    const layer = makeLayer({
      scrollOpacity: {
        enabled: true,
        startOpacity: 0,
        endOpacity: 1,
        fadeRange: 'full',
      },
    });
    applyLayerFrame(layer, frame, ctx);
    const written = layer.element.style.opacity;
    expect(written).not.toBe('');

    layer.element.style.opacity = '0.123';
    applyLayerFrame(layer, { ...frame }, ctx);
    expect(layer.element.style.opacity).toBe('0.123');
  });

  it('memoizes ease(baseline) per layer', () => {
    let calls = 0;
    const layer = makeLayer();
    layer.ease = (v: number) => {
      calls++;
      return v;
    };
    applyLayerFrame(layer, frame, ctx);
    applyLayerFrame(layer, { ...frame, progress: 0.71 }, ctx);
    applyLayerFrame(layer, { ...frame, progress: 0.72 }, ctx);
    // 1 baseline call + 3 progress calls (not 6).
    expect(calls).toBe(4);
  });
});
