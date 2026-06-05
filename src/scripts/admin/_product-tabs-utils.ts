/**
 * Product Tabs admin utility helpers.
 *
 * @package Aggressive_Apparel
 */

export function escapeHtml(value: string): string {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

export function getFieldValue(scope: ParentNode, field: string): string {
  const input = (scope as Element).querySelector<
    HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
  >(`[name$="[${field}]"]`);

  return input ? input.value : '';
}

export function renderParagraphs(value: string): string {
  const text = String(value || '').trim();

  if (!text) {
    return '';
  }

  return text
    .split(/\n{2,}/)
    .map(part => `<p>${escapeHtml(part).replace(/\n/g, '<br>')}</p>`)
    .join('');
}

export function getSelectedLabel(select: HTMLSelectElement | null): string {
  return select && select.selectedOptions.length
    ? select.selectedOptions[0].textContent || ''
    : '';
}

export function parseAttributeOptions(
  root: HTMLElement
): Record<string, string> {
  const raw = root.dataset.attributeOptions || '{}';

  try {
    const parsed = JSON.parse(raw) as Record<string, string>;
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}
