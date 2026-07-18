/**
 * @jest-environment jsdom
 */

import {
  __resetEditorCanvasSyncForTests,
  applySchemeToCanvas,
  injectEditorStyle,
  syncSchemeToEditorCanvas,
} from '../editor-color-scheme-canvas';

describe('editor-color-scheme-canvas', () => {
  beforeEach(() => {
    __resetEditorCanvasSyncForTests();
    document.body.innerHTML = '';
    document.documentElement.style.colorScheme = '';
    document.documentElement.removeAttribute('data-theme');
  });

  afterEach(() => {
    __resetEditorCanvasSyncForTests();
  });

  it('applies scheme to the current document when no canvas iframe exists', () => {
    applySchemeToCanvas('dark');

    expect(document.documentElement.style.colorScheme).toBe('dark');
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });

  it('applies scheme inside the editor canvas iframe when present', () => {
    const iframe = document.createElement('iframe');
    iframe.name = 'editor-canvas';
    document.body.appendChild(iframe);

    const iframeDoc = iframe.contentDocument;
    expect(iframeDoc).not.toBeNull();

    applySchemeToCanvas('light');

    expect(iframeDoc!.documentElement.style.colorScheme).toBe('light');
    expect(iframeDoc!.documentElement.getAttribute('data-theme')).toBe('light');
  });

  it('injects editor styles once, and replaces them when forced', () => {
    const iframe = document.createElement('iframe');
    iframe.name = 'editor-canvas';
    document.body.appendChild(iframe);
    const iframeDoc = iframe.contentDocument!;

    injectEditorStyle('aa-test-style', '.a{}');
    expect(iframeDoc.getElementById('aa-test-style')?.textContent).toBe('.a{}');

    injectEditorStyle('aa-test-style', '.b{}');
    expect(iframeDoc.getElementById('aa-test-style')?.textContent).toBe('.a{}');

    injectEditorStyle('aa-test-style', '.b{}', { force: true });
    expect(iframeDoc.getElementById('aa-test-style')?.textContent).toBe('.b{}');
  });

  it('re-applies scheme when the canvas iframe fires load', () => {
    const iframe = document.createElement('iframe');
    iframe.name = 'editor-canvas';
    document.body.appendChild(iframe);

    const cleanup = syncSchemeToEditorCanvas('dark');
    expect(iframe.contentDocument!.documentElement.style.colorScheme).toBe(
      'dark'
    );

    iframe.contentDocument!.documentElement.style.colorScheme = '';
    iframe.dispatchEvent(new Event('load'));

    expect(iframe.contentDocument!.documentElement.style.colorScheme).toBe(
      'dark'
    );

    cleanup();
  });
});
