/**
 * Free-shipping wording — editor conversions.
 *
 * Stored wording is plain text: `{amount}` for the live amount and
 * `*asterisks*` around highlighted words (see Free_Shipping_Message). The
 * editor shows it as RichText, where highlights are bold. This module converts
 * between the two, and derives the default wording from the same translated
 * templates the server uses.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import { isDefaultEmphasis } from './cart-data';

export type WordingState = 'progress' | 'unlocked';

/** Wording placeholder for the amount (Free_Shipping_Message::AMOUNT_TOKEN). */
export const AMOUNT_TOKEN = '{amount}';

/** Store Copy options holding the site wording (Free_Shipping_Message). */
export const WORDING_OPTIONS: Record<WordingState, string> = {
  progress: 'aggressive_apparel_free_shipping_progress_text',
  unlocked: 'aggressive_apparel_free_shipping_unlocked_text',
};

/** Pair of asterisks on one line; mirrors wording_to_template(). */
const HIGHLIGHT = /\*([^*\r\n]+)\*/g;

const KNOWN_TOKENS: Record<WordingState, string[]> = {
  progress: [AMOUNT_TOKEN],
  unlocked: [],
};

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/**
 * Convert a translated template into wording.
 *
 * @param template Template with printf args and `{em}` markers.
 * @param args     Wording for each printf arg, in template order.
 */
export function templateToWording(template: string, args: string[]): string {
  let nextArg = 0;

  return template.replace(
    /%%|%(?:(\d+)\$)?s|\{em\}|\{em_end\}/g,
    (token, argNumber?: string) => {
      if (token === '{em}' || token === '{em_end}') {
        return '*';
      }
      if (token === '%%') {
        return '%';
      }
      return args[argNumber ? parseInt(argNumber, 10) - 1 : nextArg++] ?? '';
    }
  );
}

/**
 * The theme's default wording for one state.
 *
 * Msgids match Free_Shipping_Message::get_default_templates(), so the editor
 * shows the translation the storefront would. A legacy emphasis phrase fills
 * the phrase arg, as it does on the storefront.
 */
export function defaultWording(state: WordingState, emphasisText = ''): string {
  const emphasis = emphasisText.trim();
  const phrase = isDefaultEmphasis(emphasis) ? '' : `*${emphasis}*`;

  if (state === 'unlocked') {
    return phrase
      ? templateToWording(
          /* translators: %s: highlighted free-shipping phrase set in the block. */
          __('%s UNLOCKED!', 'aggressive-apparel'),
          [phrase]
        )
      : templateToWording(
          /* translators: Shown once the cart qualifies for free shipping. {em} and {em_end} wrap the highlighted phrase; keep both. */
          __('{em}FREE Shipping{em_end} UNLOCKED!', 'aggressive-apparel'),
          []
        );
  }

  return phrase
    ? templateToWording(
        /* translators: 1: formatted amount the customer still has to spend, 2: highlighted free-shipping phrase set in the block. */
        __('%1$s Away from %2$s!', 'aggressive-apparel'),
        [AMOUNT_TOKEN, phrase]
      )
    : templateToWording(
        /* translators: %s: formatted amount the customer still has to spend to get free shipping. {em} and {em_end} wrap the highlighted phrase; keep both. */
        __('%s Away from {em}FREE Shipping{em_end}!', 'aggressive-apparel'),
        [AMOUNT_TOKEN]
      );
}

/** Wording → RichText HTML (highlights become bold). */
export function wordingToHtml(wording: string): string {
  return escapeHtml(wording).replace(HIGHLIGHT, '<strong>$1</strong>');
}

/** RichText HTML → wording (bold becomes asterisks; other markup drops). */
export function htmlToWording(html: string): string {
  const body = new DOMParser().parseFromString(html, 'text/html').body;

  return Array.from(body.childNodes)
    .map(node => {
      const text = (node.textContent ?? '').replace(/\u00a0/g, ' ');
      const isBold =
        node instanceof HTMLElement && /^(STRONG|B)$/.test(node.tagName);
      return isBold && text.trim() ? `*${text}*` : text;
    })
    .join('');
}

/** `{tokens}` in wording that the state cannot resolve (typos included). */
export function unknownTokens(wording: string, state: WordingState): string[] {
  const found = wording.match(/\{[A-Za-z_][A-Za-z0-9_]*\}/g) ?? [];

  return [...new Set(found)].filter(
    token => !KNOWN_TOKENS[state].includes(token)
  );
}
