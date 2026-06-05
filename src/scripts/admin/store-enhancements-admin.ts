/**
 * Store Enhancements admin tab UI.
 *
 * @package Aggressive_Apparel
 * @since 1.81.0
 */

const TAB_STORAGE_KEY = 'aa_features_tab';

function activateFeatureTab(id: string): void {
  const tabs = document.querySelectorAll<HTMLElement>(
    '.aa-features-tabs .nav-tab'
  );
  const panels = document.querySelectorAll<HTMLElement>(
    '.aa-features-tab-panel'
  );

  tabs.forEach(tab => {
    tab.classList.toggle('nav-tab-active', tab.dataset.tab === id);
  });

  panels.forEach(panel => {
    panel.hidden = panel.id !== `tab-${id}`;
  });

  try {
    localStorage.setItem(TAB_STORAGE_KEY, id);
  } catch {
    // Ignore storage failures in private browsing.
  }
}

function initStoreEnhancementsAdmin(): void {
  const tabs = document.querySelectorAll<HTMLElement>(
    '.aa-features-tabs .nav-tab'
  );

  if (!tabs.length) {
    return;
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', event => {
      event.preventDefault();
      const tabId = tab.dataset.tab;

      if (tabId) {
        activateFeatureTab(tabId);
      }
    });
  });

  try {
    const saved = localStorage.getItem(TAB_STORAGE_KEY);

    if (saved && document.getElementById(`tab-${saved}`)) {
      activateFeatureTab(saved);
    }
  } catch {
    // Ignore storage failures in private browsing.
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initStoreEnhancementsAdmin);
} else {
  initStoreEnhancementsAdmin();
}
