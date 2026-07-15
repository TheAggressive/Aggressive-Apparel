import { installBlockSupportStyles } from '../helpers';

describe('dynamic block-support style registry', () => {
  const id = 'a'.repeat(64);

  afterEach(() => {
    document.head.innerHTML = '';
  });

  it('installs an immutable style ID once and forwards its CSP nonce', () => {
    const asset = {
      id,
      css: '.wp-elements-test a{color:white}',
      nonce: 'request-nonce',
    };

    installBlockSupportStyles([asset]);
    installBlockSupportStyles([asset]);

    const styles = document.querySelectorAll(`[data-dynamic-style-id="${id}"]`);
    expect(styles).toHaveLength(1);
    expect((styles[0] as HTMLStyleElement).nonce).toBe('request-nonce');
    expect(styles[0].textContent).toBe(asset.css);
  });

  it('rejects malformed IDs', () => {
    installBlockSupportStyles([
      { id: 'not-a-content-hash', css: 'body{display:none}' },
    ]);

    expect(document.querySelector('[data-dynamic-style-id]')).toBeNull();
  });
});
