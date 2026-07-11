/**
 * EffectPresets - Quick-apply preset configurations for parallax effects
 *
 * Thin wrapper over the shared PresetPicker (editor-shared) so parallax
 * and animate-on-scroll present identical preset UI.
 */

import { __ } from '@wordpress/i18n';
import {
  findActivePresetKey,
  PresetPicker,
  type PresetTile,
} from '../../editor-shared';
import type { ElementParallaxSettings } from '../types';

export interface PresetConfig {
  name: string;
  settings: {
    enabled: boolean;
    speed: number;
    direction: string;
    delay: number;
    easing: string;
    effects: Record<string, unknown>;
  };
}

/** Single source of truth: tile presentation + the settings it applies. */
const PRESET_TILES: Array<PresetTile<PresetConfig>> = [
  {
    key: 'backdrop',
    name: __('Backdrop', 'aggressive-apparel'),
    description: __(
      'Slow drift behind the focal plane — for section background layers',
      'aggressive-apparel'
    ),
    value: {
      name: 'Backdrop',
      settings: {
        enabled: true,
        speed: 0.3,
        direction: 'down',
        delay: 0,
        easing: 'linear',
        effects: {
          depthLevel: { value: 0.5 },
        },
      },
    },
  },
  {
    key: 'subtle',
    name: __('Subtle', 'aggressive-apparel'),
    description: __(
      'Gentle drift with a hint of depth — safe everywhere',
      'aggressive-apparel'
    ),
    value: {
      name: 'Subtle',
      settings: {
        enabled: true,
        speed: 0.5,
        direction: 'down',
        delay: 0,
        easing: 'easeOut',
        effects: {
          depthLevel: { value: 1.2 },
        },
      },
    },
  },
  {
    key: 'float',
    name: __('Float', 'aggressive-apparel'),
    description: __(
      'Airy movement that fades in as it scrolls',
      'aggressive-apparel'
    ),
    value: {
      name: 'Float',
      settings: {
        enabled: true,
        speed: 0.8,
        direction: 'down',
        delay: 0,
        easing: 'easeInOut',
        effects: {
          depthLevel: { value: 1.5 },
          scrollOpacity: {
            enabled: true,
            startOpacity: 0.8,
            endOpacity: 1,
            fadeRange: 0.3,
          },
        },
      },
    },
  },
  {
    key: 'dramatic',
    name: __('Dramatic', 'aggressive-apparel'),
    description: __(
      'Fast, deep motion with zoom and fade — for hero moments',
      'aggressive-apparel'
    ),
    value: {
      name: 'Dramatic',
      settings: {
        enabled: true,
        speed: 1.5,
        direction: 'down',
        delay: 0,
        easing: 'easeOut',
        effects: {
          depthLevel: { value: 2.0 },
          zoom: {
            enabled: true,
            type: 'in',
            intensity: 0.15,
          },
          scrollOpacity: {
            enabled: true,
            startOpacity: 0,
            endOpacity: 1,
            fadeRange: 0.5,
          },
        },
      },
    },
  },
];

interface EffectPresetsProps {
  /** Current layer settings, used to highlight the matching preset. */
  settings?: ElementParallaxSettings;
  onApplyPreset: (_preset: PresetConfig) => void;
  onReset: () => void;
}

export const EffectPresets = ({
  settings,
  onApplyPreset,
  onReset,
}: EffectPresetsProps) => {
  const activeKey = settings
    ? findActivePresetKey(
        PRESET_TILES.map(tile => ({
          key: tile.key,
          value: tile.value.settings,
        })),
        settings
      )
    : null;

  return (
    <PresetPicker
      presets={PRESET_TILES}
      activeKey={activeKey}
      onApply={tile => onApplyPreset(tile.value)}
      onReset={onReset}
    />
  );
};
