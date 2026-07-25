/**
 * Quick View — pure Store API → view-model transforms.
 *
 * Extracted from quick-view.ts. Every function here is pure (inputs → output,
 * no store/DOM access), so it's unit-testable in isolation and keeps the store
 * entry under the file-length cap.
 *
 * @package Aggressive_Apparel
 */

import { stripTags } from '@aggressive-apparel/helpers';
import type {
  ColorSwatchEntry,
  GalleryImage,
  ResolvedAttribute,
  ResolvedOption,
  ResolvedVariation,
  StoreApiAttribute,
  StoreApiImage,
  StoreApiProduct,
  StoreApiTerm,
  StoreApiVariation,
  StoreApiVariationAttribute,
} from './types';

/**
 * Whether an attribute slug represents the canonical color taxonomy.
 */
export function isColorSlug(slug: string): boolean {
  return (slug || '').toLowerCase() === 'pa_color';
}

/**
 * Pick the best description from a Store API product.
 */
export function pickDescription(product: StoreApiProduct): string {
  const short = stripTags(product.short_description);
  if (short.length > 30) {
    return short;
  }
  const full = stripTags(product.description);
  if (!full) {
    return short;
  }
  if (full.length <= 200) {
    return full;
  }
  const truncated = full.substring(0, 200);
  const lastSpace = truncated.lastIndexOf(' ');
  return (
    (lastSpace > 120 ? truncated.substring(0, lastSpace) : truncated) + '…'
  );
}

/**
 * Build gallery images array from Store API product.
 */
export function buildGalleryImages(product: StoreApiProduct): GalleryImage[] {
  if (!product.images || product.images.length === 0) {
    return [];
  }
  return product.images.map((img: StoreApiImage, index: number) => ({
    id: img.id || index,
    src: img.src,
    alt: stripTags(img.alt || product.name),
    thumbnail: img.thumbnail || img.src,
  }));
}

/**
 * Calculate sale percentage from prices.
 */
export function calculateSalePercentage(
  regularPrice: number,
  salePrice: number
): number {
  if (!regularPrice || !salePrice || regularPrice <= salePrice) {
    return 0;
  }
  return Math.round(((regularPrice - salePrice) / regularPrice) * 100);
}

/**
 * Return a numeric rank for an apparel size string.
 * Handles: XS, S, M, L, XL, and multiplied variants like 2XS, 3XL, 7XL.
 * Unknown sizes get Infinity so they sort to the end.
 */
export function sizeRank(size: string): number {
  const s = size.trim().toUpperCase();

  // Multiplied small: 2XS, 3XS, etc. — smaller means lower rank.
  // 3XS < 2XS < XS, so we invert: rank = -(multiplier).
  const xsMatch = s.match(/^(\d+)XS$/);
  if (xsMatch) return -parseInt(xsMatch[1], 10);

  const bases: Record<string, number> = { XS: 1, S: 2, M: 3, L: 4, XL: 5 };
  if (bases[s] !== undefined) return bases[s];

  // Multiplied large: 2XL, 3XL, 4XL, … 7XL.
  const xlMatch = s.match(/^(\d+)XL$/);
  if (xlMatch) return 5 + parseInt(xlMatch[1], 10);

  // Numeric sizes (e.g., shoe sizes): parse directly.
  const num = parseFloat(s);
  if (!isNaN(num)) return 100 + num;

  return Infinity;
}

/**
 * Check if an attribute is a size attribute.
 */
export function isSizeAttr(slug: string, name: string): boolean {
  const s = (slug || '').toLowerCase();
  const n = (name || '').toLowerCase();
  return s === 'pa_size' || s === 'size' || n === 'size';
}

/**
 * Build attribute data from a Store API product for template rendering.
 */
export function buildAttributes(
  product: StoreApiProduct,
  colorSwatchData: Record<string, ColorSwatchEntry>,
  variations?: StoreApiVariation[]
): ResolvedAttribute[] {
  if (!product.attributes || product.attributes.length === 0) {
    return [];
  }

  // Build a mapping from term values to the attribute keys that
  // variations actually use (e.g. "red" → "pa_color"). This lets us
  // resolve the correct slug when product-level attr.taxonomy is empty.
  const varKeyByValue: Record<string, string> = {};
  const rawVariations = variations || product.variations || [];
  if (rawVariations.length > 0) {
    for (const v of rawVariations) {
      for (const va of v.attributes || []) {
        const key = va.attribute || va.name || '';
        if (va.value && key) {
          varKeyByValue[va.value.toLowerCase()] = key;
        }
      }
    }
  }

  // Helper: get all candidate names for a term — slug, name, and the
  // display name from our Color_Data_Manager swatch data.
  const termCandidates = (term: StoreApiTerm): string[] => {
    const candidates: string[] = [];
    if (term.slug) candidates.push(term.slug.toLowerCase());
    if (term.name) candidates.push(term.name.toLowerCase());
    // Swatch data may have the real display name when slug/name are IDs.
    if (colorSwatchData) {
      const sw =
        colorSwatchData[term.slug] ||
        colorSwatchData[term.name] ||
        (term.id ? colorSwatchData[String(term.id)] : null);
      if (sw && sw.name) candidates.push(sw.name.toLowerCase());
    }
    return [...new Set(candidates)];
  };

  const attrSlugFor = (attr: StoreApiAttribute): string => {
    if (attr.taxonomy) return attr.taxonomy;
    // No taxonomy — resolve from variation data by trying every
    // candidate name for each term against the varKeyByValue map.
    for (const term of attr.terms || []) {
      for (const candidate of termCandidates(term)) {
        if (varKeyByValue[candidate]) return varKeyByValue[candidate];
      }
    }
    return attr.name;
  };

  // Collect all variation values keyed by attribute slug so we can
  // resolve each term to its variation-compatible value.
  const varValuesByAttr: Record<string, Set<string>> = {};
  for (const v of rawVariations) {
    for (const va of v.attributes || []) {
      const key = va.attribute || va.name || '';
      if (key && va.value) {
        if (!varValuesByAttr[key]) varValuesByAttr[key] = new Set();
        varValuesByAttr[key].add(va.value);
      }
    }
  }

  return product.attributes
    .filter((attr: StoreApiAttribute) => attr.has_variations)
    .map((attr: StoreApiAttribute) => {
      const slug = attrSlugFor(attr);
      const colorAttr = isColorSlug(slug);
      const varValues = varValuesByAttr[slug] || new Set<string>();

      const options: ResolvedOption[] = (attr.terms || []).map(
        (term: StoreApiTerm) => {
          const termSlug = term.slug || term.name;
          // For color attributes, prefer the display name from our
          // Color_Data_Manager swatch data.
          const swatch =
            colorAttr && colorSwatchData
              ? colorSwatchData[termSlug] ||
                (term.id ? colorSwatchData[String(term.id)] : null)
              : null;

          // Resolve the variation-compatible value for this term.
          let varValue = termSlug;
          for (const vv of varValues) {
            const vvLower = vv.toLowerCase();
            for (const candidate of termCandidates(term)) {
              if (vvLower === candidate) {
                varValue = vv;
                break;
              }
            }
            if (varValue !== termSlug) break;
          }

          return {
            name: swatch && swatch.name ? swatch.name : term.name,
            slug: termSlug,
            varValue,
            attrSlug: slug,
          };
        }
      );

      // Sort size options in logical apparel order (XS → S → M → … → 7XL).
      // Compare rather than subtract: unknown sizes rank Infinity, and
      // `Infinity - Infinity` is NaN, which makes the comparator incoherent and
      // the ordering of two unranked sizes implementation-defined.
      if (isSizeAttr(slug, attr.name)) {
        options.sort((a: ResolvedOption, b: ResolvedOption) => {
          const rankA = sizeRank(a.name);
          const rankB = sizeRank(b.name);
          if (rankA === rankB) return 0;
          return rankA < rankB ? -1 : 1;
        });
      }

      return { name: attr.name, slug, options };
    });
}

/**
 * Build simplified variation objects from a Store API product.
 *
 * The Store API returns variation attributes with only the display
 * name (e.g. "Size") but no taxonomy slug. The `nameToSlug` map
 * (built from resolved product attributes) lets us add the taxonomy
 * slug so matchVariation() can find the correct selectedAttributes key.
 */
export function buildVariations(
  product: StoreApiProduct,
  nameToSlug: Record<string, string> = {}
): ResolvedVariation[] {
  if (!product.variations || product.variations.length === 0) {
    return [];
  }

  // Per-variation prices provided by our PHP ExtendSchema extension.
  const varPrices: Record<string, Record<string, unknown>> = (product
    .extensions?.['aggressive-apparel/variation-prices'] as Record<
    string,
    Record<string, unknown>
  >) || {};

  return product.variations.map((v: StoreApiVariation) => {
    // Merge per-variation prices with parent currency metadata so
    // parsePrice() has everything it needs.
    const extPrices = varPrices[String(v.id)];
    const prices = extPrices
      ? { ...product.prices, ...extPrices }
      : v.prices || product.prices;

    // Per-variation stock from our PHP extension; default in-stock when the
    // extension didn't run (keeps options selectable rather than falsely dim).
    const inStock = extPrices
      ? (extPrices as Record<string, unknown>).is_in_stock !== false
      : true;

    return {
      id: v.id,
      attributes: (v.attributes || []).map(
        (attr: StoreApiVariationAttribute) => ({
          ...attr,
          // Add the resolved taxonomy slug so matchVariation can use it.
          attribute:
            attr.attribute ||
            nameToSlug[(attr.name || '').toLowerCase()] ||
            attr.name ||
            '',
        })
      ),
      image:
        v.image && v.image.src
          ? v.image.src
          : product.images && product.images.length > 0
            ? product.images[0].src
            : '',
      imageAlt: v.image && v.image.alt ? v.image.alt : product.name || '',
      prices: prices as Record<string, unknown>,
      inStock,
    };
  });
}
