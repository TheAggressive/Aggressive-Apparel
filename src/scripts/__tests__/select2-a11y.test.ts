/**
 * @jest-environment jsdom
 */
import { initSelect2A11y, stripNestedTextboxRoles } from '../select2-a11y';

const container = (id: string): string => `
  <span class="select2 select2-container">
    <span class="select2-selection" role="combobox" aria-label="Country">
      <span class="select2-selection__rendered" id="${id}" role="textbox" aria-readonly="true" aria-required="true">Pick</span>
    </span>
  </span>`;

describe('select2-a11y', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('drops the nested textbox role but keeps the combobox', () => {
    document.body.innerHTML = container('a');

    stripNestedTextboxRoles();

    const rendered = document.getElementById('a');
    expect(rendered?.hasAttribute('role')).toBe(false);
    expect(rendered?.hasAttribute('aria-readonly')).toBe(false);
    expect(rendered?.hasAttribute('aria-required')).toBe(false);
    expect(document.querySelector('[role="combobox"]')).not.toBeNull();
  });

  it('leaves textboxes outside a combobox alone', () => {
    document.body.innerHTML =
      '<span class="select2-selection__rendered" id="b" role="textbox"></span>';

    stripNestedTextboxRoles();

    expect(document.getElementById('b')?.getAttribute('role')).toBe('textbox');
  });

  it('fixes containers selectWoo adds after init', async () => {
    document.body.innerHTML = '<form><select id="s"></select></form>';
    initSelect2A11y();

    document
      .querySelector('form')
      ?.insertAdjacentHTML('beforeend', container('c'));
    await Promise.resolve();

    expect(document.getElementById('c')?.hasAttribute('role')).toBe(false);
  });
});
