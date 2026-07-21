/**
 * Tests for the product-gallery lightbox stacking fix: while the WooCommerce
 * gallery dialog is open, its nearest positioned ancestor gets the elevation
 * class (so the sticky-trapped, fixed dialog wins the stacking order), and the
 * class is removed on close. The visual coverage itself is verified live.
 *
 * @jest-environment jsdom
 */

import {
  syncDialog,
  nearestPositionedAncestor,
} from '../product-gallery-lightbox';

const ELEVATED = 'aa-gallery-dialog-elevated';

function buildGallery(ancestorPosition: string): {
  ancestor: HTMLElement;
  dialog: HTMLElement;
} {
  const ancestor = document.createElement('div');
  ancestor.style.position = ancestorPosition;
  const inner = document.createElement('div'); // static wrapper between the two
  const dialog = document.createElement('dialog');
  dialog.className = 'wc-block-product-gallery-dialog';
  inner.appendChild(dialog);
  ancestor.appendChild(inner);
  document.body.appendChild(ancestor);
  return { ancestor, dialog };
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('nearestPositionedAncestor', () => {
  it('walks past static wrappers to the nearest positioned element', () => {
    const { ancestor, dialog } = buildGallery('sticky');
    expect(nearestPositionedAncestor(dialog)).toBe(ancestor);
  });

  it('returns null when nothing between the dialog and body is positioned', () => {
    const { dialog } = buildGallery('static');
    expect(nearestPositionedAncestor(dialog)).toBeNull();
  });
});

describe('syncDialog', () => {
  it('toggles the elevation class with the dialog open state', () => {
    const { ancestor, dialog } = buildGallery('sticky');

    syncDialog(dialog);
    expect(ancestor.classList.contains(ELEVATED)).toBe(false);

    dialog.setAttribute('open', '');
    syncDialog(dialog);
    expect(ancestor.classList.contains(ELEVATED)).toBe(true);

    dialog.removeAttribute('open');
    syncDialog(dialog);
    expect(ancestor.classList.contains(ELEVATED)).toBe(false);
  });

  it('is a no-op (no throw) when there is no positioned ancestor', () => {
    const { dialog } = buildGallery('static');
    dialog.setAttribute('open', '');
    expect(() => syncDialog(dialog)).not.toThrow();
  });
});
