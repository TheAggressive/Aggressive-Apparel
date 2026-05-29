/**
 * Editor helpers for patching parallax effect objects on blocks.
 *
 * @package Aggressive_Apparel
 */

import type { ParallaxEffects } from '../types';

/** Effect keys exposed in the block editor effects panel. */
export type ParallaxEffectName = keyof ParallaxEffects;

const PARALLAX_EFFECT_NAMES: readonly ParallaxEffectName[] = [
  'zoom',
  'depthLevel',
  'zIndex',
  'scrollOpacity',
  'magneticMouse',
  'blur',
  'colorTransition',
  'dynamicShadow',
  'rotation',
  'velocityBlur',
] as const;

/**
 * Whether a string is a known parallax effect key.
 */
export function isParallaxEffectName(
  value: string
): value is ParallaxEffectName {
  return (PARALLAX_EFFECT_NAMES as readonly string[]).includes(value);
}

/**
 * Default property values when enabling an effect in the editor.
 */
export const PARALLAX_EFFECT_DEFAULTS: {
  readonly [K in ParallaxEffectName]?: Partial<NonNullable<ParallaxEffects[K]>>;
} = {
  zoom: { type: 'in', intensity: 0.1 },
  depthLevel: { value: 1.5 },
  scrollOpacity: { startOpacity: 0, endOpacity: 1, fadeRange: 'full' },
  velocityBlur: { maxBlur: 10, sensitivity: 0.5, direction: 'vertical' },
  magneticMouse: { strength: 0.5, range: 200, mode: 'attract' },
  blur: { startBlur: 5, endBlur: 0, fadeRange: 'full' },
  colorTransition: {
    startColor: '#ffffff',
    endColor: '#000000',
    transitionType: 'background',
  },
  dynamicShadow: {
    startShadow: '0px 0px 0px rgba(0,0,0,0)',
    endShadow: '10px 10px 20px rgba(0,0,0,0.3)',
    shadowType: 'box-shadow',
  },
  rotation: {
    startRotation: 0,
    endRotation: 45,
    axis: 'z',
    speed: 1.0,
    mode: 'range',
  },
};

/**
 * Read default props for an effect, optionally adjusting depth from parallax speed.
 */
export function getParallaxEffectDefaults(
  effectName: ParallaxEffectName,
  parallaxEnabled: boolean
): Partial<NonNullable<ParallaxEffects[typeof effectName]>> {
  const base = PARALLAX_EFFECT_DEFAULTS[effectName] ?? {};
  if (effectName === 'depthLevel') {
    return { ...base, value: parallaxEnabled ? 1.5 : 1 };
  }
  return { ...base };
}

/**
 * Update one property on a named parallax effect.
 */
export function setParallaxEffectProperty(
  effects: ParallaxEffects,
  effectName: ParallaxEffectName,
  key: string,
  value: string | number | boolean | undefined
): ParallaxEffects {
  const current = effects[effectName];
  const slice: Record<string, unknown> =
    typeof current === 'object' && current !== null
      ? { ...(current as Record<string, unknown>) }
      : {};

  if (value === undefined) {
    delete slice[key];
  } else {
    slice[key] = value;
  }

  return {
    ...effects,
    [effectName]: slice as NonNullable<ParallaxEffects[typeof effectName]>,
  };
}

/**
 * Enable or disable an effect on the effects map.
 */
export function toggleParallaxEffect(
  effects: ParallaxEffects,
  effectName: ParallaxEffectName,
  enabled: boolean,
  parallaxEnabled: boolean
): ParallaxEffects {
  if (!enabled) {
    const next = { ...effects };
    delete next[effectName];
    return next;
  }

  return {
    ...effects,
    [effectName]: {
      enabled: true,
      ...getParallaxEffectDefaults(effectName, parallaxEnabled),
    },
  };
}
