/**
 * Tests for parallax layer parsing and per-frame transform math.
 *
 * @jest-environment jsdom
 */

import { applyParallaxDefaults } from '../config';
import { collectLayers } from '../layers';
import { applyLayerFrame } from '../transforms';
import type { ParallaxContext } from '../types';

const buildContext = (
  overrides: Partial<ParallaxContext> = {}
): ParallaxContext => applyParallaxDefaults(overrides);

const buildContainer = (
  layerAttributes: Record<string, string>
): { root: HTMLElement; layer: HTMLElement } => {
  const root = document.createElement('div');
  const layer = document.createElement('div');
  layer.setAttribute('data-parallax-enabled', 'true');
  Object.entries(layerAttributes).forEach(([key, value]) => {
    layer.setAttribute(key, value);
  });
  root.appendChild(layer);
  document.body.appendChild(root);
  return { root, layer };
};

afterEach(() => {
  document.body.innerHTML = '';
});

describe('collectLayers depth resolution', () => {
  it('reads the first-class data-parallax-depth attribute', () => {
    const { root } = buildContainer({ 'data-parallax-depth': '50' });
    const layers = collectLayers(root, buildContext());
    expect(layers[0].depth).toBe(0.5);
  });

  it('clamps out-of-range depth values', () => {
    const { root } = buildContainer({ 'data-parallax-depth': '250' });
    const layers = collectLayers(root, buildContext());
    expect(layers[0].depth).toBe(1);
  });

  it('maps the legacy effects.depthLevel scale onto signed depth', () => {
    // Legacy 0.5 = foreground → +0.5; legacy 1 = focal → 0.
    const { root } = buildContainer({
      'data-parallax-effects': JSON.stringify({ depthLevel: { value: 0.5 } }),
    });
    const layers = collectLayers(root, buildContext());
    expect(layers[0].depth).toBe(0.5);
  });

  it('prefers the first-class depth over the legacy effect', () => {
    const { root } = buildContainer({
      'data-parallax-depth': '-20',
      'data-parallax-effects': JSON.stringify({ depthLevel: { value: 0.5 } }),
    });
    const layers = collectLayers(root, buildContext());
    expect(layers[0].depth).toBeCloseTo(-0.2);
  });

  it('ignores malformed effects JSON instead of throwing', () => {
    const spy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    const { root } = buildContainer({ 'data-parallax-effects': '{oops' });
    expect(() => collectLayers(root, buildContext())).not.toThrow();
    expect(spy).toHaveBeenCalled();
    spy.mockRestore();
  });
});

describe('collectLayers static styles', () => {
  it('stacks nearer layers above farther ones automatically', () => {
    const { root, layer } = buildContainer({ 'data-parallax-depth': '100' });
    collectLayers(root, buildContext());
    expect(layer.style.zIndex).toBe('200');
  });

  it('honors a manual z-index override', () => {
    const { root, layer } = buildContainer({
      'data-parallax-depth': '100',
      'data-parallax-effects': JSON.stringify({ zIndex: { value: 7 } }),
    });
    collectLayers(root, buildContext());
    expect(layer.style.zIndex).toBe('7');
  });

  it('conveys near-depth via a gentle scale cue, not a Z offset', () => {
    // Depth must never become a literal translateZ (it offsets the click
    // region from the painted box). Flat mode leaves scale at 1; 3D mode
    // scales a near layer slightly larger.
    const flat = collectLayers(
      buildContainer({ 'data-parallax-depth': '100' }).root,
      buildContext()
    );
    expect(flat[0].depthScale).toBe(1);

    const threeD = collectLayers(
      buildContainer({ 'data-parallax-depth': '100' }).root,
      buildContext({ enableMouseInteraction: true, perspectiveDistance: 1000 })
    );
    expect(threeD[0].depthScale).toBeCloseTo(1.05);
  });

  it('scales a far layer slightly smaller in 3D mode', () => {
    const threeD = collectLayers(
      buildContainer({ 'data-parallax-depth': '-100' }).root,
      buildContext({ enableMouseInteraction: true, perspectiveDistance: 1000 })
    );
    expect(threeD[0].depthScale).toBeCloseTo(0.95);
  });
});

describe('applyLayerFrame', () => {
  it('rests at the natural position when centered in the zone', () => {
    const { root, layer } = buildContainer({ 'data-parallax-depth': '0' });
    const ctx = buildContext();
    const [cached] = collectLayers(root, ctx);

    applyLayerFrame(
      cached,
      { progress: 0.5, baseline: 0.5, pointerX: 0, pointerY: 0, is3D: false },
      ctx
    );
    expect(layer.style.translate).toBe('0.00px 0.00px');
  });

  it('moves foreground layers farther than background layers', () => {
    const ctx = buildContext({ intensity: 100 });

    const near = buildContainer({ 'data-parallax-depth': '100' });
    const [nearLayer] = collectLayers(near.root, ctx);
    applyLayerFrame(
      nearLayer,
      { progress: 1, baseline: 0.5, pointerX: 0, pointerY: 0, is3D: false },
      ctx
    );

    const far = buildContainer({ 'data-parallax-depth': '-100' });
    const [farLayer] = collectLayers(far.root, ctx);
    applyLayerFrame(
      farLayer,
      { progress: 1, baseline: 0.5, pointerX: 0, pointerY: 0, is3D: false },
      ctx
    );

    // depth +1 doubles travel; depth -1 pins the layer.
    expect(near.layer.style.translate).toBe('0.00px 200.00px');
    expect(far.layer.style.translate).toBe('0.00px 0.00px');
  });

  it('shifts foreground layers against the pointer', () => {
    const ctx = buildContext({
      enableMouseInteraction: true,
      maxMouseTranslation: 20,
      mouseInfluenceMultiplier: 0.5,
    });
    const { root, layer } = buildContainer({ 'data-parallax-depth': '100' });
    const [cached] = collectLayers(root, ctx);

    applyLayerFrame(
      cached,
      { progress: 0.5, baseline: 0.5, pointerX: 0.5, pointerY: 0, is3D: true },
      ctx
    );
    const [x] = layer.style.translate.split(' ');
    expect(parseFloat(x)).toBeLessThan(0);
  });

  it('keeps parallax translation intact when a rotation effect runs', () => {
    // Regression: the old single-transform pipeline let the rotation
    // effect overwrite the parallax translate entirely.
    const ctx = buildContext({ intensity: 100 });
    const { root, layer } = buildContainer({
      'data-parallax-depth': '0',
      'data-parallax-effects': JSON.stringify({
        rotation: {
          enabled: true,
          startRotation: 0,
          endRotation: 180,
          axis: 'z',
          speed: 1,
          mode: 'range',
          effectStart: 0,
          effectEnd: 1,
          effectMode: 'sustain',
        },
      }),
    });
    const [cached] = collectLayers(root, ctx);

    applyLayerFrame(
      cached,
      { progress: 1, baseline: 0.5, pointerX: 0, pointerY: 0, is3D: false },
      ctx
    );

    expect(layer.style.translate).toBe('0.00px 100.00px');
    expect(layer.style.rotate).toBe('180deg');
  });

  it('renders zero offset at the calibrated baseline (hero visible at load)', () => {
    // Regression: a hero already on screen at page load must look exactly
    // like the editor — no layer may be shifted (the button/paragraph
    // reorder bug). Baseline is calibrated to the load-time progress.
    const ctx = buildContext({ intensity: 100 });
    const { root, layer } = buildContainer({ 'data-parallax-depth': '60' });
    const [cached] = collectLayers(root, ctx);

    applyLayerFrame(
      cached,
      { progress: 0.8, baseline: 0.8, pointerX: 0, pointerY: 0, is3D: false },
      ctx
    );
    expect(layer.style.translate).toBe('0.00px 0.00px');

    // Scrolling away from the baseline starts the drift.
    applyLayerFrame(
      cached,
      { progress: 1, baseline: 0.8, pointerX: 0, pointerY: 0, is3D: false },
      ctx
    );
    const [, y] = layer.style.translate.split(' ');
    expect(parseFloat(y)).toBeGreaterThan(0);
  });

  it('skips redundant translate writes between identical frames', () => {
    const ctx = buildContext();
    const { root, layer } = buildContainer({ 'data-parallax-depth': '0' });
    const [cached] = collectLayers(root, ctx);
    const frame = {
      progress: 0.5,
      baseline: 0.5,
      pointerX: 0,
      pointerY: 0,
      is3D: false,
    };

    applyLayerFrame(cached, frame, ctx);
    layer.style.translate = 'sentinel';
    applyLayerFrame(cached, frame, ctx);
    expect(layer.style.translate).toBe('sentinel');
  });
});
