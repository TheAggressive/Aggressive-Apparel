import {
  initFeatureTabs,
  initStoreCopyPreviews,
} from '../store-enhancements-admin';

const TAB_STORAGE_KEY = 'aa_features_tab';

function renderTabs(): void {
  document.body.innerHTML = `
    <div class="aa-features-tabs" role="tablist">
      <a href="#tab-general" role="tab" data-tab="general">General</a>
      <a href="#tab-copy" role="tab" data-tab="copy">Store Copy</a>
    </div>
    <div class="aa-features-tab-panel" id="tab-general"></div>
    <div class="aa-features-tab-panel" id="tab-copy" hidden></div>
  `;
}

function setSearch(search: string): void {
  window.history.replaceState({}, '', `/wp-admin/themes.php${search}`);
}

function activePanelId(): string | undefined {
  return Array.from(
    document.querySelectorAll<HTMLElement>('.aa-features-tab-panel')
  ).find(panel => !panel.hidden)?.id;
}

describe('store enhancements tabs', () => {
  beforeEach(() => {
    localStorage.clear();
    setSearch('');
    renderTabs();
  });

  it('opens the tab named by a ?tab= deep link', () => {
    setSearch('?page=aa-features&tab=copy');

    initFeatureTabs();

    expect(activePanelId()).toBe('tab-copy');
    expect(
      document.querySelector('[data-tab="copy"]')?.getAttribute('aria-selected')
    ).toBe('true');
  });

  it('lets an explicit link win over the remembered tab', () => {
    localStorage.setItem(TAB_STORAGE_KEY, 'general');
    setSearch('?tab=copy');

    initFeatureTabs();

    expect(activePanelId()).toBe('tab-copy');
  });

  it('falls back to the remembered tab when there is no deep link', () => {
    localStorage.setItem(TAB_STORAGE_KEY, 'copy');

    initFeatureTabs();

    expect(activePanelId()).toBe('tab-copy');
  });

  it('ignores a ?tab= naming a panel that does not exist', () => {
    localStorage.setItem(TAB_STORAGE_KEY, 'copy');
    setSearch('?tab=nope');

    initFeatureTabs();

    // Neither the bogus link nor a silent reset — the remembered tab stands.
    expect(activePanelId()).toBe('tab-copy');
  });

  it('leaves the markup alone when there are no tabs', () => {
    document.body.innerHTML = '<p>no tabs here</p>';
    setSearch('?tab=copy');

    expect(() => initFeatureTabs()).not.toThrow();
  });
});

describe('store copy token preview', () => {
  const render = (value: string, placeholder = 'Save {percent}%'): void => {
    document.body.innerHTML = `
      <input type="text" id="aa_sale_badge_text" value="${value}" placeholder="${placeholder}" />
      <p class="aa-store-copy-preview"
         data-aa-copy-preview="aa_sale_badge_text"
         data-aa-tokens='{"{percent}":"20"}'>
        <span class="aa-store-copy-preview__label">Preview:</span>
        <span class="aa-store-copy-preview__value"></span>
      </p>
    `;
  };

  const previewText = (): string | null =>
    document.querySelector('.aa-store-copy-preview__value')?.textContent ??
    null;

  it('substitutes the sample value for the token', () => {
    render('Save {percent}%');

    initStoreCopyPreviews();

    expect(previewText()).toBe('Save 20%');
  });

  it('shows wording without a token verbatim', () => {
    render('Now on Sale');

    initStoreCopyPreviews();

    expect(previewText()).toBe('Now on Sale');
  });

  it('surfaces a mistyped token instead of hiding it', () => {
    render('Save {percnt}%');

    initStoreCopyPreviews();

    expect(previewText()).toBe('Save {percnt}%');
  });

  it('previews the placeholder when the field is empty', () => {
    render('');

    initStoreCopyPreviews();

    expect(previewText()).toBe('Save 20%');
  });

  it('updates as the merchant types', () => {
    render('Save {percent}%');
    initStoreCopyPreviews();

    const input = document.getElementById(
      'aa_sale_badge_text'
    ) as HTMLInputElement;
    input.value = '{percent}% off';
    input.dispatchEvent(new Event('input'));

    expect(previewText()).toBe('20% off');
  });
});
