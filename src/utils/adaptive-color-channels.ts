/**
 * Adaptive color channels — discovery, storage, and CSS application.
 *
 * Enterprise model:
 * - Discover color surfaces from block `supports` + allowlisted custom attrs.
 * - Store pairs in one `adaptiveColors` map keyed by channel id.
 * - Migrate legacy `adaptiveBackground` / `adaptiveText` on read.
 * - Apply via CSS vars + marker classes (descendant-safe) or attribute sync.
 *
 * @package Aggressive_Apparel
 * @since 1.70.0
 */

import { __ } from '@wordpress/i18n';

import {
  composeLightDark,
  hasAdaptivePairValue,
  normalizeAdaptivePair,
  type AdaptiveColorPair,
} from './adaptive-color-value';

/**
 * Built-in + allowlisted channel ids.
 * New channels can be added without changing storage shape.
 */
export type AdaptiveChannelId =
  | 'text'
  | 'link'
  | 'linkHover'
  | 'heading'
  | 'caption'
  | 'button'
  | 'background'
  | 'border'
  | 'overlay';

export type AdaptiveColorsMap = Partial<
  Record<AdaptiveChannelId, AdaptiveColorPair>
>;

export type AdaptiveApplication =
  | {
      /** Apply to the block wrapper via CSS custom property + marker class. */
      type: 'css';
      cssVar: string;
      markerClass: string;
      /** Direct CSS property for static-save inline styles (wrapper-only channels). */
      cssProperty?: 'background' | 'color' | 'border-color';
      /** Whether the native picker may set CSS gradients on this channel. */
      allowGradient?: boolean;
    }
  | {
      /** Sync composed light-dark() into a native block attribute. */
      type: 'attribute';
      attribute: string;
    };

export interface AdaptiveChannelDefinition {
  id: AdaptiveChannelId;
  /** Stable sort for panel order (lower first). */
  order: number;
  /** Section title in the Adaptive Color panel. */
  label: string;
  /**
   * Optional inspector group key. Channels that share a group render as one
   * section (e.g. Link default + hover).
   */
  group?: string;
  /** Row label inside a `rows` group (defaults to “Color”). */
  rowLabel?: string;
  /**
   * Inspector layout for a group. `dual-tabs` matches core Styles → Color → Link
   * (one row, stacked indicators, Default/Hover tabs in the picker).
   */
  presentation?: 'rows' | 'dual-tabs';
  application: AdaptiveApplication;
  /**
   * Whether this channel is available for a registered block type.
   */
  isSupported: (blockName: string, settings: BlockTypeSettingsLike) => boolean;
  /**
   * Whether a conflicting core/native color is set for this channel.
   */
  hasCoreConflict: (attributes: BlockAttributesLike) => boolean;
  /**
   * Clear conflicting core/native color attrs when adaptive wins.
   */
  clearCoreConflicts: (
    attributes: BlockAttributesLike
  ) => Partial<BlockAttributesLike>;
}

export interface BlockTypeSettingsLike {
  name?: string;
  supports?: unknown;
  attributes?: Record<string, unknown>;
}

export interface BlockAttributesLike {
  adaptiveColors?: AdaptiveColorsMap;
  adaptiveBackground?: AdaptiveColorPair;
  adaptiveText?: AdaptiveColorPair;
  backgroundColor?: string;
  textColor?: string;
  linkColor?: string;
  borderColor?: string;
  overlayColor?: string;
  style?: {
    color?: {
      background?: string;
      text?: string;
      [key: string]: unknown;
    };
    border?: {
      color?: string;
      [key: string]: unknown;
    };
    elements?: {
      link?: {
        color?: { text?: string; [key: string]: unknown };
        /** Native WordPress link hover: style.elements.link[':hover'].color.text */
        ':hover'?: { color?: { text?: string; [key: string]: unknown } };
        [key: string]: unknown;
      };
      heading?: { color?: { text?: string; [key: string]: unknown } };
      button?: {
        color?: { text?: string; background?: string; [key: string]: unknown };
      };
      caption?: { color?: { text?: string; [key: string]: unknown } };
      [key: string]: unknown;
    };
    [key: string]: unknown;
  };
  [key: string]: unknown;
}

/**
 * Read a nested supports value (boolean, object, or undefined).
 */
export function getSupportsValue(supports: unknown, path: string[]): unknown {
  let current: unknown = supports;

  for (const key of path) {
    if (!current || typeof current !== 'object') {
      return undefined;
    }
    current = (current as Record<string, unknown>)[key];
  }

  return current;
}

/**
 * Whether a supports path is enabled.
 *
 * Objects count as enabled (WP often uses `{ … }` feature bags).
 */
export function isSupportsEnabled(supports: unknown, path: string[]): boolean {
  const value = getSupportsValue(supports, path);
  if (value === true) {
    return true;
  }
  if (value && typeof value === 'object') {
    return true;
  }
  return false;
}

function getColorSupportsObject(
  settings: BlockTypeSettingsLike
): Record<string, unknown> | true | false | undefined {
  const color = getSupportsValue(settings.supports, ['color']);
  if (color === true || color === false) {
    return color;
  }
  if (color && typeof color === 'object') {
    return color as Record<string, unknown>;
  }
  return undefined;
}

/**
 * WordPress defaults: when `color` support exists as an object, background/text
 * are on unless explicitly false. Opt-in channels (link, heading, …) require true.
 */
function supportsColorBackground(settings: BlockTypeSettingsLike): boolean {
  const color = getColorSupportsObject(settings);
  if (color === undefined || color === false) {
    return false;
  }
  if (color === true) {
    return true;
  }
  return color.background !== false;
}

function supportsColorText(settings: BlockTypeSettingsLike): boolean {
  const color = getColorSupportsObject(settings);
  if (color === undefined || color === false) {
    return false;
  }
  if (color === true) {
    return true;
  }
  return color.text !== false;
}

function supportsColorKey(
  settings: BlockTypeSettingsLike,
  key: 'link' | 'heading' | 'button' | 'caption'
): boolean {
  const color = getColorSupportsObject(settings);
  if (!color || color === true) {
    // Shorthand `color: true` does not enable link/heading/button/caption.
    return false;
  }
  const value = color[key];
  return value === true || (typeof value === 'object' && value !== null);
}

function supportsBorderColor(settings: BlockTypeSettingsLike): boolean {
  const border = getSupportsValue(settings.supports, ['border']);
  if (border === true) {
    return true;
  }
  if (border && typeof border === 'object') {
    // Same defaulting model as color.background: on unless explicitly false.
    return (border as Record<string, unknown>).color !== false;
  }
  return false;
}

function hasAttribute(
  settings: BlockTypeSettingsLike,
  attribute: string
): boolean {
  return Boolean(settings.attributes?.[attribute]);
}

function cloneStyle(
  attributes: BlockAttributesLike
): BlockAttributesLike['style'] | undefined {
  if (!attributes.style) {
    return undefined;
  }
  return {
    ...attributes.style,
    color: attributes.style.color ? { ...attributes.style.color } : undefined,
    border: attributes.style.border
      ? { ...attributes.style.border }
      : undefined,
    elements: attributes.style.elements
      ? { ...attributes.style.elements }
      : undefined,
  };
}

function clearStyleColorChannel(
  attributes: BlockAttributesLike,
  channel: 'background' | 'text'
): Partial<BlockAttributesLike> {
  const style = cloneStyle(attributes);
  if (style?.color) {
    delete style.color[channel];
    if (Object.keys(style.color).length === 0) {
      delete style.color;
    }
  }

  if (channel === 'background') {
    return { backgroundColor: undefined, style };
  }

  return { textColor: undefined, style };
}

function clearElementColor(
  attributes: BlockAttributesLike,
  element: 'link' | 'heading' | 'button' | 'caption'
): Partial<BlockAttributesLike> {
  const style = cloneStyle(attributes);
  const elements = style?.elements ? { ...style.elements } : undefined;

  if (elements?.[element]) {
    const nextElement = { ...(elements[element] as Record<string, unknown>) };
    if (nextElement.color && typeof nextElement.color === 'object') {
      const color = { ...(nextElement.color as Record<string, unknown>) };
      delete color.text;
      delete color.background;
      if (Object.keys(color).length === 0) {
        delete nextElement.color;
      } else {
        nextElement.color = color;
      }
    }
    if (Object.keys(nextElement).length === 0) {
      delete elements[element];
    } else {
      elements[element] = nextElement;
    }
  }

  if (style) {
    style.elements =
      elements && Object.keys(elements).length > 0 ? elements : undefined;
  }

  const patch: Partial<BlockAttributesLike> = { style };
  if (element === 'link') {
    patch.linkColor = undefined;
  }

  return patch;
}

/**
 * Clear native link hover color (`style.elements.link[':hover']`).
 */
function clearLinkHoverColor(
  attributes: BlockAttributesLike
): Partial<BlockAttributesLike> {
  const style = cloneStyle(attributes);
  const elements = style?.elements ? { ...style.elements } : undefined;
  const link = elements?.link;

  if (link && typeof link === 'object') {
    const nextLink = { ...link };
    delete nextLink[':hover'];
    if (Object.keys(nextLink).length === 0) {
      delete elements!.link;
    } else {
      elements!.link = nextLink;
    }
  }

  if (style) {
    style.elements =
      elements && Object.keys(elements).length > 0 ? elements : undefined;
  }

  return { style };
}

/**
 * Channel registry — ordered for the Adaptive Color panel.
 *
 * Extend here (or via `registerAdaptiveColorChannel`) to scale.
 */
export const ADAPTIVE_COLOR_CHANNELS: AdaptiveChannelDefinition[] = [
  {
    id: 'text',
    order: 10,
    label: __('Typography', 'aggressive-apparel'),
    application: {
      type: 'css',
      cssVar: '--aa-adaptive-color',
      markerClass: 'aa-has-adaptive-color',
      cssProperty: 'color',
    },
    isSupported: (_name, settings) => supportsColorText(settings),
    hasCoreConflict: attributes =>
      Boolean(attributes.textColor || attributes.style?.color?.text),
    clearCoreConflicts: attributes =>
      clearStyleColorChannel(attributes, 'text'),
  },
  {
    id: 'link',
    order: 20,
    label: __('Link', 'aggressive-apparel'),
    group: 'link',
    presentation: 'dual-tabs',
    application: {
      type: 'css',
      cssVar: '--aa-adaptive-link',
      markerClass: 'aa-has-adaptive-link',
    },
    isSupported: (_name, settings) => supportsColorKey(settings, 'link'),
    hasCoreConflict: attributes =>
      Boolean(
        attributes.linkColor || attributes.style?.elements?.link?.color?.text
      ),
    clearCoreConflicts: attributes => clearElementColor(attributes, 'link'),
  },
  {
    id: 'linkHover',
    order: 25,
    label: __('Link', 'aggressive-apparel'),
    group: 'link',
    presentation: 'dual-tabs',
    application: {
      type: 'css',
      cssVar: '--aa-adaptive-link-hover',
      // Same marker as `link` — one class drives default + hover rules.
      markerClass: 'aa-has-adaptive-link',
    },
    isSupported: (_name, settings) => supportsColorKey(settings, 'link'),
    hasCoreConflict: attributes =>
      Boolean(attributes.style?.elements?.link?.[':hover']?.color?.text),
    clearCoreConflicts: attributes => clearLinkHoverColor(attributes),
  },
  {
    id: 'heading',
    order: 30,
    label: __('Heading', 'aggressive-apparel'),
    application: {
      type: 'css',
      cssVar: '--aa-adaptive-heading',
      markerClass: 'aa-has-adaptive-heading',
    },
    isSupported: (_name, settings) => supportsColorKey(settings, 'heading'),
    hasCoreConflict: attributes =>
      Boolean(attributes.style?.elements?.heading?.color?.text),
    clearCoreConflicts: attributes => clearElementColor(attributes, 'heading'),
  },
  {
    id: 'caption',
    order: 40,
    label: __('Caption', 'aggressive-apparel'),
    application: {
      type: 'css',
      cssVar: '--aa-adaptive-caption',
      markerClass: 'aa-has-adaptive-caption',
    },
    isSupported: (_name, settings) => supportsColorKey(settings, 'caption'),
    hasCoreConflict: attributes =>
      Boolean(attributes.style?.elements?.caption?.color?.text),
    clearCoreConflicts: attributes => clearElementColor(attributes, 'caption'),
  },
  {
    id: 'button',
    order: 50,
    label: __('Button', 'aggressive-apparel'),
    application: {
      type: 'css',
      cssVar: '--aa-adaptive-button',
      markerClass: 'aa-has-adaptive-button',
    },
    isSupported: (_name, settings) => supportsColorKey(settings, 'button'),
    hasCoreConflict: attributes =>
      Boolean(
        attributes.style?.elements?.button?.color?.text ||
        attributes.style?.elements?.button?.color?.background
      ),
    clearCoreConflicts: attributes => clearElementColor(attributes, 'button'),
  },
  {
    id: 'background',
    order: 60,
    label: __('Background', 'aggressive-apparel'),
    application: {
      type: 'css',
      cssVar: '--aa-adaptive-bg',
      markerClass: 'aa-has-adaptive-bg',
      cssProperty: 'background',
      allowGradient: true,
    },
    isSupported: (_name, settings) => supportsColorBackground(settings),
    hasCoreConflict: attributes =>
      Boolean(
        attributes.backgroundColor || attributes.style?.color?.background
      ),
    clearCoreConflicts: attributes =>
      clearStyleColorChannel(attributes, 'background'),
  },
  {
    id: 'border',
    order: 70,
    label: __('Border', 'aggressive-apparel'),
    application: {
      type: 'css',
      cssVar: '--aa-adaptive-border',
      markerClass: 'aa-has-adaptive-border',
      cssProperty: 'border-color',
    },
    isSupported: (_name, settings) => supportsBorderColor(settings),
    hasCoreConflict: attributes =>
      Boolean(attributes.borderColor || attributes.style?.border?.color),
    clearCoreConflicts: attributes => {
      const style = cloneStyle(attributes);
      if (style?.border) {
        delete style.border.color;
        if (Object.keys(style.border).length === 0) {
          delete style.border;
        }
      }
      return { borderColor: undefined, style };
    },
  },
  {
    id: 'overlay',
    order: 80,
    label: __('Overlay', 'aggressive-apparel'),
    application: {
      type: 'attribute',
      attribute: 'overlayColor',
    },
    isSupported: (_name, settings) => hasAttribute(settings, 'overlayColor'),
    hasCoreConflict: attributes =>
      Boolean(
        attributes.overlayColor &&
        !String(attributes.overlayColor).startsWith('light-dark(') &&
        !hasAdaptivePairValue(attributes.adaptiveColors?.overlay)
      ),
    clearCoreConflicts: () => ({}),
  },
];

const channelById = new Map(
  ADAPTIVE_COLOR_CHANNELS.map(channel => [channel.id, channel])
);

/**
 * Register or replace a channel definition (for theme/plugin extensions).
 *
 * @param definition - Channel definition.
 */
export function registerAdaptiveColorChannel(
  definition: AdaptiveChannelDefinition
): void {
  const existingIndex = ADAPTIVE_COLOR_CHANNELS.findIndex(
    channel => channel.id === definition.id
  );
  if (existingIndex >= 0) {
    ADAPTIVE_COLOR_CHANNELS[existingIndex] = definition;
  } else {
    ADAPTIVE_COLOR_CHANNELS.push(definition);
  }
  channelById.set(definition.id, definition);
  ADAPTIVE_COLOR_CHANNELS.sort((a, b) => a.order - b.order);
}

/**
 * Get a channel definition by id.
 */
export function getAdaptiveColorChannel(
  id: AdaptiveChannelId | string
): AdaptiveChannelDefinition | undefined {
  return channelById.get(id as AdaptiveChannelId);
}

/**
 * Discover supported adaptive channels for a block type.
 */
export function discoverAdaptiveColorChannels(
  blockName: string,
  settings: BlockTypeSettingsLike
): AdaptiveChannelDefinition[] {
  return ADAPTIVE_COLOR_CHANNELS.filter(channel =>
    channel.isSupported(blockName, settings)
  );
}

/**
 * Whether the block type should receive adaptive color attributes / panel.
 */
export function blockSupportsAdaptiveColors(
  blockName: string,
  settings: BlockTypeSettingsLike
): boolean {
  return discoverAdaptiveColorChannels(blockName, settings).length > 0;
}

/**
 * Merge legacy attrs + adaptiveColors map into a single resolved map.
 */
export function resolveAdaptiveColors(
  attributes: BlockAttributesLike
): AdaptiveColorsMap {
  const resolved: AdaptiveColorsMap = {};

  const legacyBackground = normalizeAdaptivePair(attributes.adaptiveBackground);
  const legacyText = normalizeAdaptivePair(attributes.adaptiveText);

  if (legacyBackground) {
    resolved.background = legacyBackground;
  }
  if (legacyText) {
    resolved.text = legacyText;
  }

  const map = attributes.adaptiveColors;
  if (map && typeof map === 'object') {
    for (const [rawId, rawPair] of Object.entries(map)) {
      const pair = normalizeAdaptivePair(rawPair as AdaptiveColorPair);
      if (pair) {
        resolved[rawId as AdaptiveChannelId] = pair;
      }
    }
  }

  return resolved;
}

/**
 * Whether any adaptive channel has a value.
 */
export function hasAnyAdaptiveColor(attributes: BlockAttributesLike): boolean {
  return Object.values(resolveAdaptiveColors(attributes)).some(pair =>
    hasAdaptivePairValue(pair)
  );
}

/**
 * Compose a CSS color value from a pair.
 */
export function pairToCssValue(
  pair: AdaptiveColorPair | undefined
): string | undefined {
  const normalized = normalizeAdaptivePair(pair);
  if (!normalized) {
    return undefined;
  }
  return composeLightDark(normalized.light, normalized.dark);
}

export interface AdaptiveStylePayload {
  /** Inline style declarations for the wrapper (cssProperty channels + CSS vars). */
  style: Record<string, string>;
  /** Marker classes for adaptive-block-colors.css rules. */
  classNames: string[];
  /** Attribute syncs for custom channels (e.g. overlayColor). */
  attributeSync: Record<string, string | undefined>;
}

/**
 * Build style/class/attribute payload from resolved adaptive colors.
 *
 * @param attributes - Block attributes.
 * @param channels   - Channels to apply (usually discovered for the block).
 */
export function buildAdaptiveStylePayload(
  attributes: BlockAttributesLike,
  channels: AdaptiveChannelDefinition[] = ADAPTIVE_COLOR_CHANNELS
): AdaptiveStylePayload {
  const resolved = resolveAdaptiveColors(attributes);
  const style: Record<string, string> = {};
  const classNames: string[] = [];
  const attributeSync: Record<string, string | undefined> = {};

  for (const channel of channels) {
    const cssValue = pairToCssValue(resolved[channel.id]);
    if (!cssValue) {
      continue;
    }

    if (channel.application.type === 'css') {
      style[channel.application.cssVar] = cssValue;
      classNames.push(channel.application.markerClass);

      if (channel.application.cssProperty) {
        // camelCase for React style objects in the editor save filter.
        if (channel.application.cssProperty === 'background') {
          style.background = cssValue;
        } else if (channel.application.cssProperty === 'border-color') {
          style.borderColor = cssValue;
        } else if (channel.application.cssProperty === 'color') {
          style.color = cssValue;
        }
      }
    } else {
      attributeSync[channel.application.attribute] = cssValue;
    }
  }

  return { style, classNames, attributeSync };
}

/**
 * Update one channel in the adaptiveColors map (and migrate off legacy keys).
 */
export function setAdaptiveColorChannel(
  attributes: BlockAttributesLike,
  channelId: AdaptiveChannelId,
  value: AdaptiveColorPair | undefined
): Partial<BlockAttributesLike> {
  const channel = getAdaptiveColorChannel(channelId);
  const nextPair = normalizeAdaptivePair(value);
  const current = { ...resolveAdaptiveColors(attributes) };
  const previousComposed = pairToCssValue(current[channelId]);

  if (nextPair) {
    current[channelId] = nextPair;
  } else {
    delete current[channelId];
  }

  const adaptiveColors = Object.keys(current).length > 0 ? current : undefined;

  const patch: Partial<BlockAttributesLike> = {
    adaptiveColors,
    // Stop writing legacy keys; clear them when touching migrated channels.
    ...(channelId === 'background' ? { adaptiveBackground: undefined } : {}),
    ...(channelId === 'text' ? { adaptiveText: undefined } : {}),
  };

  if (nextPair && channel) {
    Object.assign(patch, channel.clearCoreConflicts(attributes));

    if (channel.application.type === 'attribute') {
      patch[channel.application.attribute] = pairToCssValue(nextPair);
    }
  } else if (
    !nextPair &&
    channel?.application.type === 'attribute' &&
    previousComposed &&
    attributes[channel.application.attribute] === previousComposed
  ) {
    // Clearing adaptive overlay — only wipe the native attr if we synced it.
    patch[channel.application.attribute] = undefined;
  }

  return patch;
}
