/**
 * Countdown Timer display style metadata.
 *
 * @package Aggressive_Apparel
 * @since 1.144.0
 */

import type { BlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import type { CSSProperties } from 'react';

export const COUNTDOWN_DISPLAY_STYLES = [
  'inline',
  'compact',
  'minimal',
  'card',
  'chips',
  'banner',
  'strip',
  'pill',
  'boxed',
  'outline',
  'editorial',
  'magazine',
  'hero',
  'hero-center',
  'hero-split',
  'hero-panel',
] as const;

export type CountdownDisplayStyle = (typeof COUNTDOWN_DISPLAY_STYLES)[number];

export const DEFAULT_COUNTDOWN_DISPLAY_STYLE: CountdownDisplayStyle = 'inline';

export interface CountdownTimerAttributes {
  displayStyle: CountdownDisplayStyle;
  endDateTime: string;
  saleLabelColor: string;
  timeValueColor: string;
  unitLabelColor: string;
  timerBorderColor: string;
  dropPageUrl: string;
}

const COUNTDOWN_COLOR_STYLE_VARIABLES = {
  saleLabelColor: '--aa-countdown-label-color',
  timeValueColor: '--aa-countdown-value-color',
  unitLabelColor: '--aa-countdown-unit-color',
  timerBorderColor: '--aa-countdown-border-color',
} as const;

interface CountdownDisplayStyleMeta {
  slug: CountdownDisplayStyle;
  variationName: string;
  title: string;
  description: string;
  isDefault?: boolean;
}

export function isCountdownDisplayStyle(
  value: unknown
): value is CountdownDisplayStyle {
  return (
    typeof value === 'string' &&
    COUNTDOWN_DISPLAY_STYLES.includes(value as CountdownDisplayStyle)
  );
}

export function normalizeCountdownDisplayStyle(
  value: unknown
): CountdownDisplayStyle {
  return isCountdownDisplayStyle(value)
    ? value
    : DEFAULT_COUNTDOWN_DISPLAY_STYLE;
}

function normalizeColorValue(value: string): string {
  const match = value.match(/^var:preset\|color\|([a-z0-9_-]+)$/i);

  if (match) {
    return `var(--wp--preset--color--${match[1]})`;
  }

  if (/^[a-z0-9_-]+$/i.test(value)) {
    return `var(--wp--preset--color--${value})`;
  }

  return value;
}

export function getCountdownColorStyles(
  attributes: CountdownTimerAttributes
): CSSProperties {
  return Object.entries(COUNTDOWN_COLOR_STYLE_VARIABLES).reduce(
    (styles, [attributeName, cssVariable]) => {
      const value = attributes[attributeName as keyof CountdownTimerAttributes];

      if (typeof value === 'string' && value) {
        styles[cssVariable] = normalizeColorValue(value);
      }

      return styles;
    },
    {} as Record<string, string>
  ) as CSSProperties;
}

function displayStyleMeta(): CountdownDisplayStyleMeta[] {
  return [
    {
      slug: 'inline',
      variationName: 'inline-sale',
      title: __('Inline Sale Timer', 'aggressive-apparel'),
      description: __(
        'A compact inline countdown for single product sale details.',
        'aggressive-apparel'
      ),
      isDefault: true,
    },
    {
      slug: 'compact',
      variationName: 'product-card',
      title: __('Product Card Timer', 'aggressive-apparel'),
      description: __(
        'A tight sale countdown designed for product cards and archive grids.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'minimal',
      variationName: 'minimal-meta',
      title: __('Minimal Meta Timer', 'aggressive-apparel'),
      description: __(
        'A quiet countdown for dense product details, small content rows, and utility areas.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'card',
      variationName: 'card-stack',
      title: __('Card Stack Timer', 'aggressive-apparel'),
      description: __(
        'A small two-row timer for tight product cards, quick-view panels, and collection grids.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'chips',
      variationName: 'segment-chips',
      title: __('Segment Chips Timer', 'aggressive-apparel'),
      description: __(
        'Individual compact chips for filter bars, sale modules, and dense promotional areas.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'banner',
      variationName: 'promo-banner',
      title: __('Promo Banner Timer', 'aggressive-apparel'),
      description: __(
        'A horizontal countdown for announcement bars and promotional strips.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'strip',
      variationName: 'sale-strip',
      title: __('Sale Strip Timer', 'aggressive-apparel'),
      description: __(
        'A slim full-width strip for section dividers, top bars, and collection headers.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'pill',
      variationName: 'urgency-pill',
      title: __('Urgency Pill Timer', 'aggressive-apparel'),
      description: __(
        'A rounded compact countdown for badges, sticky regions, and high-urgency sale modules.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'boxed',
      variationName: 'boxed-launch',
      title: __('Boxed Launch Timer', 'aggressive-apparel'),
      description: __(
        'Individual time boxes for landing pages, launch sections, and centered campaign blocks.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'outline',
      variationName: 'outline-grid',
      title: __('Outline Grid Timer', 'aggressive-apparel'),
      description: __(
        'A crisp outlined grid for launch cards, waitlist sections, and product drops.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'editorial',
      variationName: 'editorial-stack',
      title: __('Editorial Stack Timer', 'aggressive-apparel'),
      description: __(
        'A restrained stacked countdown for lookbooks, story sections, and content-led pages.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'magazine',
      variationName: 'magazine-feature',
      title: __('Magazine Feature Timer', 'aggressive-apparel'),
      description: __(
        'A larger editorial treatment for lookbooks, campaign stories, and feature sections.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'hero',
      variationName: 'drop-hero',
      title: __('Drop Hero Timer', 'aggressive-apparel'),
      description: __(
        'A larger countdown treatment for campaign heroes and drop pages.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'hero-center',
      variationName: 'centered-hero',
      title: __('Centered Hero Timer', 'aggressive-apparel'),
      description: __(
        'A centered hero countdown for landing pages, launch countdowns, and waitlist pages.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'hero-split',
      variationName: 'split-hero',
      title: __('Split Hero Timer', 'aggressive-apparel'),
      description: __(
        'A split label-and-numbers hero layout for campaign mastheads and editorial hero sections.',
        'aggressive-apparel'
      ),
    },
    {
      slug: 'hero-panel',
      variationName: 'launch-panel-hero',
      title: __('Launch Panel Hero Timer', 'aggressive-apparel'),
      description: __(
        'A framed hero panel for drops, collection launches, and high-emphasis countdown sections.',
        'aggressive-apparel'
      ),
    },
  ];
}

export function getCountdownDisplayStyleOptions(): Array<{
  label: string;
  value: CountdownDisplayStyle;
}> {
  return displayStyleMeta().map(({ title, slug }) => ({
    label: title,
    value: slug,
  }));
}

export function getCountdownVariations(
  icon: NonNullable<BlockVariation['icon']>
): BlockVariation[] {
  return displayStyleMeta().map(
    ({ variationName, title, description, slug, isDefault }) => ({
      name: variationName,
      title,
      description,
      icon,
      isDefault,
      attributes: {
        displayStyle: slug,
      },
      scope: ['inserter', 'block', 'transform'],
    })
  );
}
