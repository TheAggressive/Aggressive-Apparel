/**
 * Product Tabs admin manager and override UI.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { getConfig } from './_product-tabs-config';
import { getTabItems, renderPreview } from './_product-tabs-preview';
import type {
  ProductTabsAdminConfig,
  TabItem,
  TabTemplate,
} from './_product-tabs-types';
import {
  escapeHtml,
  getFieldValue,
  getSelectedLabel,
  parseAttributeOptions,
} from './_product-tabs-utils';

function getTemplate(
  template: string,
  templates: ProductTabsAdminConfig['templates']
): TabTemplate {
  return templates[template] || templates.blank;
}

function buildAttributeField(
  name: string,
  attributeOptions: Record<string, string>,
  strings: ProductTabsAdminConfig['strings']
): string {
  if (!Object.keys(attributeOptions).length) {
    return `<input type="text" name="${name}" value="" class="regular-text" placeholder="pa_material">`;
  }

  const options = Object.entries(attributeOptions)
    .map(
      ([slug, label]) =>
        `<option value="${escapeHtml(slug)}">${escapeHtml(label)}</option>`
    )
    .join('');

  return `<select name="${name}" class="regular-text"><option value="">${escapeHtml(strings.selectAttribute)}</option>${options}</select>`;
}

function initRepeater(root: HTMLElement): void {
  const config = getConfig();
  const strings = config.strings;
  const rows = root.querySelector<HTMLElement>(
    '.aa-custom-tabs-repeater__rows'
  );
  const list = root.querySelector<HTMLElement>('.aa-custom-tabs-manager__list');
  const count = root.querySelector<HTMLElement>(
    '.aa-custom-tabs-manager__count'
  );
  const prefix = root.dataset.namePrefix || 'custom_tabs';
  let nextIndex = parseInt(root.dataset.nextIndex || '0', 10);
  const attributeOptions = parseAttributeOptions(root);

  if (!rows || !list) {
    return;
  }

  const rowsContainer = rows;
  const listContainer = list;

  function getRows(): HTMLElement[] {
    return Array.from(
      rowsContainer.querySelectorAll<HTMLElement>(
        '.aa-custom-tabs-repeater__row'
      )
    );
  }

  function validateRow(row: Element): string {
    const title = getFieldValue(row, 'title').trim();
    const layout = (row as HTMLElement).dataset.layout || 'rich_text';
    const source = (row as HTMLElement).dataset.source || 'manual';
    const intro = getFieldValue(row, 'content').trim();
    const items = getTabItems(row);

    if (!title) {
      return strings.addTabTitle;
    }

    if (source === 'product_meta' && !getFieldValue(row, 'metaField').trim()) {
      return strings.addMetaKey;
    }

    if (
      source === 'product_attribute' &&
      !getFieldValue(row, 'attribute').trim()
    ) {
      return strings.addAttribute;
    }

    if (!intro && !items.length) {
      return strings.addContent;
    }

    if ((layout === 'rich_text' || layout === 'custom_html') && !intro) {
      return strings.needsIntro;
    }

    return '';
  }

  function syncRow(row: Element): void {
    const titleInput = row.querySelector<HTMLInputElement>('[name$="[title]"]');
    const enabledInput = row.querySelector<HTMLInputElement>(
      '[name$="[enabled]"][type="checkbox"]'
    );
    const layout = row.querySelector<HTMLSelectElement>(
      '.aa-custom-tabs-layout-select'
    );
    const source = row.querySelector<HTMLSelectElement>(
      '.aa-custom-tabs-source-select'
    );
    const help = row.querySelector<HTMLElement>('.aa-custom-tabs-layout-help');
    const title = row.querySelector<HTMLElement>('.aa-custom-tabs-card__title');
    const enabledBadge = row.querySelector<HTMLElement>(
      '.aa-tabs-badge--enabled'
    );
    const layoutBadge = row.querySelector<HTMLElement>(
      '.aa-tabs-badge--layout'
    );
    const renderBadge = row.querySelector<HTMLElement>(
      '.aa-tabs-badge--render'
    );
    const validation = row.querySelector<HTMLElement>(
      '.aa-custom-tabs-validation'
    );

    if (layout) {
      (row as HTMLElement).dataset.layout = layout.value;
      if (help) {
        help.textContent = config.layoutHelp[layout.value] || '';
      }
      if (layoutBadge) {
        layoutBadge.textContent = getSelectedLabel(layout);
      }
    }

    if (source) {
      (row as HTMLElement).dataset.source = source.value;
    }

    if (title) {
      title.textContent = titleInput?.value
        ? titleInput.value
        : strings.untitledTab;
    }

    if (enabledBadge && enabledInput) {
      enabledBadge.textContent = enabledInput.checked
        ? strings.enabled
        : strings.disabled;
      enabledBadge.classList.toggle(
        'aa-tabs-badge--success',
        enabledInput.checked
      );
      enabledBadge.classList.toggle(
        'aa-tabs-badge--muted',
        !enabledInput.checked
      );
    }

    const warning = validateRow(row);
    row.classList.toggle('has-warning', Boolean(warning));

    if (renderBadge) {
      renderBadge.textContent = warning
        ? strings.needsAttention
        : strings.ready;
    }

    if (validation) {
      validation.textContent = warning;
    }

    renderPreview(row, strings);
  }

  function itemRow(
    tabIndex: string,
    itemIndex: number,
    item: Partial<TabItem> = {}
  ): string {
    const base = `${prefix}[${tabIndex}][items][${itemIndex}]`;

    return (
      `<div class="aa-custom-tab-items__row">` +
      `<p class="aa-custom-tab-items__row-fields">` +
      `<label>${escapeHtml(strings.icon)}<br><input type="text" name="${base}[icon]" value="${escapeHtml(item.icon || '')}" class="aa-custom-tab-items__icon-input" placeholder="✓"></label>` +
      `<label>${escapeHtml(strings.label)}<br><input type="text" name="${base}[title]" value="${escapeHtml(item.title || '')}" class="regular-text"></label>` +
      `<label>${escapeHtml(strings.detail)}<br><input type="text" name="${base}[meta]" value="${escapeHtml(item.meta || '')}" class="regular-text"></label>` +
      `<button type="button" class="button button-small aa-custom-tab-items__remove">${escapeHtml(strings.removeItem)}</button>` +
      `</p>` +
      `<p><label>${escapeHtml(strings.text)}<br><textarea name="${base}[text]" rows="3" class="large-text">${escapeHtml(item.text || '')}</textarea></label></p>` +
      `</div>`
    );
  }

  function layoutOptions(selected = 'rich_text'): string {
    return Object.entries(config.layouts)
      .map(
        ([value, label]) =>
          `<option value="${escapeHtml(value)}"${value === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`
      )
      .join('');
  }

  function sourceOptions(selected = 'manual'): string {
    return Object.entries(config.sources)
      .map(
        ([value, label]) =>
          `<option value="${escapeHtml(value)}"${value === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`
      )
      .join('');
  }

  function row(index: number, templateName = 'blank'): string {
    const template = getTemplate(templateName, config.templates);
    const enabled = `${prefix}[${index}][enabled]`;
    const key = `${prefix}[${index}][key]`;
    const title = `${prefix}[${index}][title]`;
    const priority = `${prefix}[${index}][priority]`;
    const source = `${prefix}[${index}][source]`;
    const layout = `${prefix}[${index}][layout]`;
    const metaField = `${prefix}[${index}][metaField]`;
    const attribute = `${prefix}[${index}][attribute]`;
    const content = `${prefix}[${index}][content]`;
    const layoutLabel =
      config.layouts[template.layout] ||
      config.layouts.rich_text ||
      'Rich Text';
    const itemRows = (template.items.length ? template.items : [{}])
      .map((item, itemIndex) => itemRow(String(index), itemIndex, item))
      .join('');

    return (
      `<div class="aa-custom-tabs-repeater__row aa-custom-tabs-card" data-tab-index="${index}" data-layout="${escapeHtml(template.layout)}" data-source="manual">` +
      `<div class="aa-custom-tabs-card__header">` +
      `<div><p class="aa-custom-tabs-card__eyebrow">Selected tab</p><h3 class="aa-custom-tabs-card__title">${escapeHtml(template.title || strings.untitledTab)}</h3></div>` +
      `<span class="aa-custom-tabs-card__meta">` +
      `<span class="aa-tabs-badge aa-tabs-badge--enabled aa-tabs-badge--success">${escapeHtml(strings.enabled)}</span>` +
      `<span class="aa-tabs-badge aa-tabs-badge--layout">${escapeHtml(layoutLabel)}</span>` +
      `<span class="aa-tabs-badge aa-tabs-badge--render">${escapeHtml(strings.ready)}</span>` +
      `</span>` +
      `</div>` +
      `<div class="aa-custom-tabs-card__body">` +
      `<input type="hidden" name="${key}" value="">` +
      `<p class="aa-custom-tabs-repeater__toolbar">` +
      `<label><input type="hidden" name="${enabled}" value="0"><input type="checkbox" name="${enabled}" value="1" checked> ${escapeHtml(strings.enabledLabel)}</label>` +
      `<label class="aa-custom-tabs-advanced-field">${escapeHtml(strings.priority)} <input type="number" min="1" max="999" step="1" name="${priority}" value="40" class="aa-custom-tabs-repeater__priority-input"></label>` +
      `<button type="button" class="button button-secondary aa-custom-tabs-repeater__remove">${escapeHtml(strings.remove)}</button>` +
      `</p>` +
      `<div class="aa-custom-tabs-repeater__grid">` +
      `<p><label>${escapeHtml(strings.tabTitle)}<br><input type="text" name="${title}" value="${escapeHtml(template.title)}" class="regular-text" placeholder="${escapeHtml(strings.tabTitlePlaceholder)}"></label></p>` +
      `<p><label>${escapeHtml(strings.contentLayout)}<br><select class="aa-custom-tabs-layout-select" name="${layout}">${layoutOptions(template.layout)}</select></label><span class="description aa-custom-tabs-layout-help" aria-live="polite"></span></p>` +
      `<p><label>${escapeHtml(strings.contentSource)}<br><select class="aa-custom-tabs-source-select" name="${source}">${sourceOptions()}</select></label></p>` +
      `</div>` +
      `<div class="aa-custom-tabs-source-fields">` +
      `<p data-source-field="meta"><label>${escapeHtml(strings.productMetaField)}<br><input type="text" name="${metaField}" value="" class="regular-text" placeholder="_my_meta_key"></label><span class="description">${escapeHtml(strings.productMetaHelp)}</span></p>` +
      `<p data-source-field="attribute"><label>${escapeHtml(strings.productAttribute)}<br>${buildAttributeField(attribute, attributeOptions, strings)}</label><span class="description">${escapeHtml(strings.productAttributeHelp)}</span></p>` +
      `</div>` +
      `<p><label class="aa-custom-tabs-content-label">${escapeHtml(strings.introContent)}<br><textarea name="${content}" rows="5" class="large-text">${escapeHtml(template.content)}</textarea></label><span class="description aa-custom-tabs-content-help">${escapeHtml(strings.introHelp)}</span></p>` +
      `<p class="aa-custom-tabs-validation" aria-live="polite"></p>` +
      `<div class="aa-custom-tab-items" data-next-item-index="${Math.max(1, template.items.length)}">` +
      `<p class="aa-custom-tab-items__header"><strong>${escapeHtml(strings.layoutItemsHeader)}</strong><br><span class="description">${escapeHtml(strings.layoutItemsHelp)}</span></p>` +
      `<div class="aa-custom-tab-items__rows">${itemRows}</div>` +
      `<button type="button" class="button aa-custom-tab-items__add">${escapeHtml(strings.addLayoutItem)}</button>` +
      `<p class="description">${escapeHtml(strings.layoutItemsFooter)}</p>` +
      `</div>` +
      `<div class="aa-custom-tabs-preview">` +
      `<p class="aa-custom-tabs-preview__label"><strong>${escapeHtml(strings.previewLabel)}</strong><br><span class="description">${escapeHtml(strings.previewHelp)}</span></p>` +
      `<div class="aa-custom-tabs-preview__surface">` +
      `<h4 class="aa-custom-tabs-preview__title"></h4>` +
      `<div class="aa-product-info__content aa-custom-tabs-preview__content"></div>` +
      `</div>` +
      `</div>` +
      `</div>` +
      `</div>`
    );
  }

  function selectRow(row: HTMLElement): void {
    getRows().forEach(rowElement => {
      const isActive = rowElement === row;
      rowElement.classList.toggle('is-active', isActive);
      rowElement.hidden = !isActive;
    });

    root.dataset.activeIndex = row.dataset.tabIndex || '';
    renderList();
  }

  function updatePriorities(): void {
    getRows().forEach((rowElement, index) => {
      const priority = rowElement.querySelector<HTMLInputElement>(
        '[name$="[priority]"]'
      );
      if (priority) {
        priority.value = String((index + 1) * 10);
      }
    });
  }

  function renderList(): void {
    const activeIndex =
      root.dataset.activeIndex || getRows()[0]?.dataset.tabIndex || '';
    const rowsList = getRows();

    listContainer.innerHTML = rowsList
      .map((rowElement, index) => {
        const tabIndex = rowElement.dataset.tabIndex || String(index);
        const title = getFieldValue(rowElement, 'title') || strings.untitledTab;
        const layout =
          rowElement.querySelector<HTMLElement>('.aa-tabs-badge--layout')
            ?.textContent || '';
        const status =
          rowElement.querySelector<HTMLElement>('.aa-tabs-badge--render')
            ?.textContent || strings.ready;
        const enabled = rowElement.querySelector<HTMLInputElement>(
          '[name$="[enabled]"][type="checkbox"]'
        )?.checked;
        const active = tabIndex === activeIndex ? ' is-active' : '';
        const disabled = enabled ? '' : ' is-disabled';

        return (
          `<div class="aa-custom-tabs-manager__list-item${active}${disabled}" data-tab-index="${escapeHtml(tabIndex)}" draggable="true" role="option" aria-selected="${active ? 'true' : 'false'}">` +
          `<button type="button" class="aa-custom-tabs-manager__select" data-tab-index="${escapeHtml(tabIndex)}">` +
          `<span class="aa-custom-tabs-manager__drag" aria-hidden="true">::</span>` +
          `<span class="aa-custom-tabs-manager__item-body">` +
          `<strong>${escapeHtml(title)}</strong>` +
          `<span>${escapeHtml(layout)} - ${escapeHtml(status)}</span>` +
          `</span>` +
          `</button>` +
          `<span class="aa-custom-tabs-manager__item-actions">` +
          `<button type="button" class="button-link aa-custom-tabs-manager__move-up" data-tab-index="${escapeHtml(tabIndex)}" aria-label="Move ${escapeHtml(title)} up">Up</button>` +
          `<button type="button" class="button-link aa-custom-tabs-manager__move-down" data-tab-index="${escapeHtml(tabIndex)}" aria-label="Move ${escapeHtml(title)} down">Down</button>` +
          `</span>` +
          `</div>`
        );
      })
      .join('');

    if (count) {
      count.textContent = `${rowsList.length}`;
    }
  }

  function syncManager(): void {
    getRows().forEach(rowElement => {
      syncRow(rowElement);
    });
    updatePriorities();

    const active =
      getRows().find(
        rowElement => rowElement.dataset.tabIndex === root.dataset.activeIndex
      ) || getRows()[0];
    if (active) {
      selectRow(active);
    }
  }

  function addRow(templateName = 'blank'): void {
    rowsContainer.insertAdjacentHTML('beforeend', row(nextIndex, templateName));
    const lastRow = rowsContainer.lastElementChild as HTMLElement | null;
    nextIndex += 1;
    root.dataset.nextIndex = String(nextIndex);

    if (lastRow) {
      syncRow(lastRow);
      updatePriorities();
      selectRow(lastRow);
    }
  }

  function findRow(tabIndex: string): HTMLElement | null {
    return (
      getRows().find(rowElement => rowElement.dataset.tabIndex === tabIndex) ||
      null
    );
  }

  function moveRow(tabIndex: string, direction: 'up' | 'down'): void {
    const current = findRow(tabIndex);
    if (!current) {
      return;
    }

    if (direction === 'up' && current.previousElementSibling) {
      rowsContainer.insertBefore(current, current.previousElementSibling);
    }

    if (direction === 'down' && current.nextElementSibling) {
      rowsContainer.insertBefore(current.nextElementSibling, current);
    }

    updatePriorities();
    selectRow(current);
  }

  root
    .querySelectorAll<HTMLButtonElement>(
      '.aa-custom-tabs-repeater__add, .aa-custom-tabs-template'
    )
    .forEach(button => {
      button.addEventListener('click', () => {
        addRow(button.dataset.template || 'blank');
      });
    });

  syncManager();

  root.addEventListener('change', event => {
    const target = event.target as Element;
    const changedRow = target.closest('.aa-custom-tabs-repeater__row');

    if (!changedRow) {
      return;
    }

    syncRow(changedRow);
    renderList();
  });

  root.addEventListener('input', event => {
    const target = event.target as Element;
    const changedRow = target.closest('.aa-custom-tabs-repeater__row');

    if (!changedRow) {
      return;
    }

    syncRow(changedRow);
    renderList();
  });

  root.addEventListener('click', event => {
    const target = event.target as Element;
    const select = target.closest<HTMLElement>(
      '.aa-custom-tabs-manager__select'
    );
    const moveUp = target.closest<HTMLElement>(
      '.aa-custom-tabs-manager__move-up'
    );
    const moveDown = target.closest<HTMLElement>(
      '.aa-custom-tabs-manager__move-down'
    );

    if (select) {
      event.preventDefault();
      const selectedRow = findRow(select.dataset.tabIndex || '');
      if (selectedRow) {
        selectRow(selectedRow);
      }
      return;
    }

    if (moveUp) {
      event.preventDefault();
      moveRow(moveUp.dataset.tabIndex || '', 'up');
      return;
    }

    if (moveDown) {
      event.preventDefault();
      moveRow(moveDown.dataset.tabIndex || '', 'down');
      return;
    }

    if (target.classList.contains('aa-custom-tabs-repeater__remove')) {
      event.preventDefault();
      const currentRows = getRows();
      const currentRow = target.closest<HTMLElement>(
        '.aa-custom-tabs-repeater__row'
      );

      if (!currentRow) {
        return;
      }

      if (currentRows.length <= 1) {
        currentRow
          .querySelectorAll<
            HTMLInputElement | HTMLTextAreaElement
          >('input[type="text"], textarea')
          .forEach(field => {
            field.value = '';
          });
        const numberInput = currentRow.querySelector<HTMLInputElement>(
          'input[type="number"]'
        );
        if (numberInput) {
          numberInput.value = '40';
        }
        const checkbox = currentRow.querySelector<HTMLInputElement>(
          'input[type="checkbox"]'
        );
        if (checkbox) {
          checkbox.checked = true;
        }
        const layout = currentRow.querySelector<HTMLSelectElement>(
          '.aa-custom-tabs-layout-select'
        );
        if (layout) {
          layout.value = 'rich_text';
        }
        syncManager();
        return;
      }

      const nextActive =
        currentRow.previousElementSibling || currentRow.nextElementSibling;
      currentRow.remove();
      updatePriorities();
      if (nextActive instanceof HTMLElement) {
        selectRow(nextActive);
      } else {
        syncManager();
      }
      return;
    }

    if (target.classList.contains('aa-custom-tab-items__add')) {
      event.preventDefault();
      const itemRoot = target.closest('.aa-custom-tab-items');
      const tabRow = target.closest('.aa-custom-tabs-repeater__row');

      if (!itemRoot || !tabRow) {
        return;
      }

      const tabIndex = (tabRow as HTMLElement).dataset.tabIndex || '0';
      const itemRows = itemRoot.querySelector('.aa-custom-tab-items__rows');
      let nextItemIndex = parseInt(
        itemRoot.getAttribute('data-next-item-index') || '0',
        10
      );

      if (itemRows) {
        itemRows.insertAdjacentHTML(
          'beforeend',
          itemRow(tabIndex, nextItemIndex)
        );
        nextItemIndex += 1;
        itemRoot.setAttribute('data-next-item-index', String(nextItemIndex));
        syncRow(tabRow);
        renderList();
      }
      return;
    }

    if (target.classList.contains('aa-custom-tab-items__remove')) {
      event.preventDefault();
      const itemRowsContainer = target.closest('.aa-custom-tab-items__rows');
      const parentRow = target.closest('.aa-custom-tabs-repeater__row');

      if (!itemRowsContainer || !parentRow) {
        return;
      }

      const currentItemRows = itemRowsContainer.querySelectorAll(
        '.aa-custom-tab-items__row'
      );

      if (currentItemRows.length <= 1) {
        currentItemRows[0]
          .querySelectorAll<
            HTMLInputElement | HTMLTextAreaElement
          >('input[type="text"], textarea')
          .forEach(field => {
            field.value = '';
          });
        syncRow(parentRow);
        renderList();
        return;
      }

      target.closest('.aa-custom-tab-items__row')?.remove();
      syncRow(parentRow);
      renderList();
    }
  });

  listContainer.addEventListener('dragstart', event => {
    const item = (event.target as Element).closest<HTMLElement>(
      '.aa-custom-tabs-manager__list-item'
    );
    if (!item || !event.dataTransfer) {
      return;
    }

    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', item.dataset.tabIndex || '');
  });

  listContainer.addEventListener('dragover', event => {
    event.preventDefault();
  });

  listContainer.addEventListener('drop', event => {
    event.preventDefault();

    const targetItem = (event.target as Element).closest<HTMLElement>(
      '.aa-custom-tabs-manager__list-item'
    );
    const draggedIndex = event.dataTransfer?.getData('text/plain') || '';
    const draggedRow = findRow(draggedIndex);
    const targetRow = targetItem
      ? findRow(targetItem.dataset.tabIndex || '')
      : null;

    if (!draggedRow || !targetRow || draggedRow === targetRow) {
      return;
    }

    rowsContainer.insertBefore(draggedRow, targetRow);
    updatePriorities();
    selectRow(draggedRow);
  });
}

function initTabOverrides(): void {
  if (window.aggressiveApparelTabOverridesReady) {
    return;
  }

  window.aggressiveApparelTabOverridesReady = true;

  document.addEventListener('change', event => {
    const target = event.target as HTMLSelectElement;

    if (!target.classList.contains('aa-tab-override__mode')) {
      return;
    }

    const card = target.closest('.aa-tab-override') as HTMLElement | null;
    const badge =
      card?.querySelector<HTMLElement>('.aa-tabs-badge--mode') ?? null;

    if (card) {
      card.dataset.mode = target.value;
    }

    if (badge && target.selectedOptions.length) {
      badge.textContent = target.selectedOptions[0].textContent || '';
    }
  });
}

function initProductTabsAdmin(): void {
  document
    .querySelectorAll<HTMLElement>('.aa-custom-tabs-repeater')
    .forEach(root => {
      initRepeater(root);
    });

  initTabOverrides();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initProductTabsAdmin);
} else {
  initProductTabsAdmin();
}
