/**
 * Store Enhancements admin tab UI.
 *
 * @package Aggressive_Apparel
 * @since 1.81.0
 */

const TAB_STORAGE_KEY = 'aa_features_tab';

function getTabs(): HTMLElement[] {
  return Array.from(
    document.querySelectorAll<HTMLElement>('.aa-features-tabs [role="tab"]')
  );
}

function getPanels(): HTMLElement[] {
  return Array.from(
    document.querySelectorAll<HTMLElement>('.aa-features-tab-panel')
  );
}

function activateFeatureTab(
  id: string,
  options: { focus?: boolean } = {}
): void {
  const tabs = getTabs();
  const panels = getPanels();

  tabs.forEach(tab => {
    const isActive = tab.dataset.tab === id;
    tab.classList.toggle('nav-tab-active', isActive);
    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    tab.tabIndex = isActive ? 0 : -1;

    if (isActive && options.focus) {
      tab.focus();
    }
  });

  panels.forEach(panel => {
    const isActive = panel.id === `tab-${id}`;
    panel.hidden = !isActive;
  });

  try {
    localStorage.setItem(TAB_STORAGE_KEY, id);
  } catch {
    // Ignore storage failures in private browsing.
  }
}

function moveTabFocus(current: HTMLElement, delta: number): void {
  const tabs = getTabs();
  const index = tabs.indexOf(current);

  if (index < 0 || tabs.length === 0) {
    return;
  }

  const nextIndex = (index + delta + tabs.length) % tabs.length;
  const nextTab = tabs[nextIndex];
  const nextId = nextTab?.dataset.tab;

  if (nextId) {
    activateFeatureTab(nextId, { focus: true });
  }
}

export function initFeatureTabs(): void {
  const tablist = document.querySelector<HTMLElement>('.aa-features-tabs');
  const tabs = getTabs();

  if (!tablist || !tabs.length) {
    return;
  }

  tablist.addEventListener('click', event => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
      '[role="tab"]'
    );

    if (!target || !tablist.contains(target)) {
      return;
    }

    event.preventDefault();
    const tabId = target.dataset.tab;

    if (tabId) {
      activateFeatureTab(tabId);
    }
  });

  tablist.addEventListener('keydown', event => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
      '[role="tab"]'
    );

    if (!target || !tablist.contains(target)) {
      return;
    }

    switch (event.key) {
      case 'ArrowRight':
      case 'ArrowDown':
        event.preventDefault();
        moveTabFocus(target, 1);
        break;
      case 'ArrowLeft':
      case 'ArrowUp':
        event.preventDefault();
        moveTabFocus(target, -1);
        break;
      case 'Home':
        event.preventDefault();
        {
          const firstId = tabs[0]?.dataset.tab;
          if (firstId) {
            activateFeatureTab(firstId, { focus: true });
          }
        }
        break;
      case 'End':
        event.preventDefault();
        {
          const lastId = tabs[tabs.length - 1]?.dataset.tab;
          if (lastId) {
            activateFeatureTab(lastId, { focus: true });
          }
        }
        break;
      default:
        break;
    }
  });

  const requested = getRequestedTab();

  if (requested) {
    activateFeatureTab(requested);
    return;
  }

  try {
    const saved = localStorage.getItem(TAB_STORAGE_KEY);

    if (saved && document.getElementById(`tab-${saved}`)) {
      activateFeatureTab(saved);
    }
  } catch {
    // Ignore storage failures in private browsing.
  }
}

/**
 * Read a `?tab=` deep link, so other admin screens can point at one section.
 * Takes precedence over the remembered tab: an explicit link should win.
 */
function getRequestedTab(): string | null {
  const id = new URLSearchParams(window.location.search).get('tab');

  return id && document.getElementById(`tab-${id}`) ? id : null;
}

/**
 * Resolve a Store Copy field's placeholders as it is typed.
 *
 * The saved value is validated server-side, but by then the merchant has left
 * the screen. Showing "Save 20%" under a field containing "Save {percent}%" is
 * what makes a mistyped token obvious while it can still be corrected.
 */
export function initStoreCopyPreviews(): void {
  document
    .querySelectorAll<HTMLElement>('[data-aa-copy-preview]')
    .forEach(preview => {
      const input = document.getElementById(
        preview.dataset.aaCopyPreview ?? ''
      ) as HTMLInputElement | null;
      const output = preview.querySelector<HTMLElement>(
        '.aa-store-copy-preview__value'
      );

      if (!input || !output) {
        return;
      }

      let tokens: Record<string, string> = {};

      try {
        tokens = JSON.parse(preview.dataset.aaTokens ?? '{}') as Record<
          string,
          string
        >;
      } catch {
        return;
      }

      const render = (): void => {
        const source = input.value.trim() || input.placeholder;

        output.textContent = Object.entries(tokens).reduce(
          (text, [token, sample]) => text.split(token).join(sample),
          source
        );
      };

      input.addEventListener('input', render);
      render();
    });
}

function initSocialProofSourceSliders(): void {
  const sliders = document.querySelectorAll<HTMLInputElement>(
    '.aa-sp-source__slider'
  );

  sliders.forEach(slider => {
    const card = slider.closest('.aa-sp-source');
    const output = card?.querySelector<HTMLOutputElement>(
      '.aa-sp-source__value'
    );

    slider.addEventListener('input', () => {
      const value = Number(slider.value);

      if (output) {
        output.textContent =
          value === 0 ? (output.dataset.offLabel ?? '0') : String(value);
      }

      card?.classList.toggle('is-off', value === 0);
    });
  });
}

function initFeatureSubFields(): void {
  const DEPENDS_PREFIX = 'aa-features-depends-on-';
  const rows = Array.from(
    document.querySelectorAll<HTMLElement>('tr.aa-features-sub-field')
  );

  if (!rows.length) {
    return;
  }

  const syncSubFields = (): void => {
    rows.forEach(row => {
      const parents = Array.from(row.classList)
        .filter(className => className.startsWith(DEPENDS_PREFIX))
        .map(className => className.slice(DEPENDS_PREFIX.length));

      if (!parents.length) {
        return;
      }

      const visible = parents.every(parent => {
        const checkbox = document.getElementById(
          `aa-feature-${parent}`
        ) as HTMLInputElement | null;
        return Boolean(checkbox?.checked);
      });

      row.hidden = !visible;

      row
        .querySelectorAll<
          HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
        >('input, select, textarea')
        .forEach(control => {
          control.disabled = !visible;
        });
    });
  };

  document
    .querySelectorAll<HTMLInputElement>('input[id^="aa-feature-"]')
    .forEach(checkbox => {
      checkbox.addEventListener('change', syncSubFields);
    });

  syncSubFields();
}

function initStoreEnhancementsAdmin(): void {
  initSocialProofSourceSliders();
  initFeatureTabs();
  initFeatureSubFields();
  initStoreCopyPreviews();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initStoreEnhancementsAdmin);
} else {
  initStoreEnhancementsAdmin();
}
