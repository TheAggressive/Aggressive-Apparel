/**
 * Product Specs — editor data for the block preview, and a class helper
 * shared with the Products → Size & Specs screen.
 *
 * PHP (Product_Specs_Admin::enqueue_block_data) prints the store's spec
 * items as `window.aaProductSpecs` before the block's editor script runs.
 *
 * @package Aggressive_Apparel
 */

export type SpecEditorItem = {
  key: string;
  type: 'pick' | 'number' | 'text';
  name: string;
  steps: string[];
  start: string;
  mid: string;
  end: string;
  centered: boolean;
};

export type ProductSpecsEditorData = {
  items: SpecEditorItem[];
  manageUrl: string;
};

/** The spec items printed for the editor, or an empty set. */
export function getProductSpecsData(): ProductSpecsEditorData {
  const data = (window as Window & { aaProductSpecs?: ProductSpecsEditorData })
    .aaProductSpecs;

  return data ?? { items: [], manageUrl: '' };
}

/**
 * Join class names, dropping falsy parts.
 *
 * Used instead of `${base}${cond ? ' modifier' : ''}` template literals,
 * whose leading space the Tailwind Prettier plugin trims away.
 */
export function cx(...parts: Array<string | false | null | undefined>): string {
  return parts.filter(Boolean).join(' ');
}
