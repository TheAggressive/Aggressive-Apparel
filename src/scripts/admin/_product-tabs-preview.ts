/**
 * Product Tabs admin frontend-style preview rendering.
 *
 * @package Aggressive_Apparel
 */

import type { ProductTabsAdminConfig, TabItem } from './_product-tabs-types';
import {
  escapeHtml,
  getFieldValue,
  renderParagraphs,
} from './_product-tabs-utils';

function getItems(row: Element): TabItem[] {
  return Array.from(row.querySelectorAll('.aa-custom-tab-items__row'))
    .map(itemRow => ({
      icon: getFieldValue(itemRow, 'icon'),
      title: getFieldValue(itemRow, 'title'),
      meta: getFieldValue(itemRow, 'meta'),
      text: getFieldValue(itemRow, 'text'),
    }))
    .filter(item => item.icon || item.title || item.meta || item.text);
}

function renderCardLikeItems(layout: string, items: TabItem[]): string {
  const className = `aa-product-tab-layout aa-product-tab-layout--${layout.replace(/_/g, '-')}`;
  const list = layout === 'shipping_timeline' ? 'ol' : 'ul';

  return (
    `<${list} class="${className}">` +
    items
      .map(item => {
        const icon = item.icon
          ? `<span class="aa-product-tab-layout__icon" aria-hidden="true">${escapeHtml(item.icon)}</span>`
          : '';
        const meta = item.meta
          ? `<p class="aa-product-tab-layout__meta">${escapeHtml(item.meta)}</p>`
          : '';
        const title = item.title
          ? `<h4 class="aa-product-tab-layout__title">${escapeHtml(item.title)}</h4>`
          : '';
        const text = item.text
          ? `<div class="aa-product-tab-layout__text">${renderParagraphs(item.text)}</div>`
          : '';

        return `<li class="aa-product-tab-layout__item">${icon}<div class="aa-product-tab-layout__body">${meta}${title}${text}</div></li>`;
      })
      .join('') +
    `</${list}>`
  );
}

function renderSpecsTable(items: TabItem[]): string {
  return (
    '<table class="aa-product-tab-layout aa-product-tab-layout--specs-table"><tbody>' +
    items
      .map(
        item =>
          `<tr><th scope="row">${escapeHtml(item.title || item.meta || '')}</th><td>${escapeHtml(item.text || '')}</td></tr>`
      )
      .join('') +
    '</tbody></table>'
  );
}

function renderFaq(items: TabItem[]): string {
  return (
    '<div class="aa-product-tab-layout aa-product-tab-layout--faq">' +
    items
      .map(
        (item, index) =>
          `<details class="aa-product-tab-layout__faq-item"${index === 0 ? ' open' : ''}><summary class="aa-product-tab-layout__faq-question">${escapeHtml(item.title || '')}</summary><div class="aa-product-tab-layout__faq-answer">${renderParagraphs(item.text || '')}</div></details>`
      )
      .join('') +
    '</div>'
  );
}

function renderLayout(layout: string, items: TabItem[]): string {
  if (!items.length || layout === 'rich_text' || layout === 'custom_html') {
    return '';
  }

  if (layout === 'specs_table') {
    return renderSpecsTable(items);
  }

  if (layout === 'faq') {
    return renderFaq(items);
  }

  return renderCardLikeItems(layout, items);
}

export function getTabItems(row: Element): TabItem[] {
  return getItems(row);
}

export function renderPreview(
  row: Element,
  strings: ProductTabsAdminConfig['strings']
): void {
  const titleInput = row.querySelector<HTMLInputElement>('[name$="[title]"]');
  const previewTitle = row.querySelector<HTMLElement>(
    '.aa-custom-tabs-preview__title'
  );
  const previewContent = row.querySelector<HTMLElement>(
    '.aa-custom-tabs-preview__content'
  );
  const layout = (row as HTMLElement).dataset.layout || 'rich_text';
  const intro = getFieldValue(row, 'content');
  const title = titleInput?.value ? titleInput.value : strings.productDetails;
  const content = renderParagraphs(intro) + renderLayout(layout, getItems(row));

  if (!previewTitle || !previewContent) {
    return;
  }

  previewTitle.textContent = title;
  previewContent.innerHTML =
    content || `<p class="description">${escapeHtml(strings.previewEmpty)}</p>`;
}
