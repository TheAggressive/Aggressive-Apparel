/**
 * @jest-environment jsdom
 */

import {
  buildExitAnimation,
  canRestoreFocus,
  getExitIntentStorageKey,
  getOpenOnLoadSeenKey,
  isExitIntentDismissed,
  markExitIntentDismissed,
  shouldSkipOpenOnLoadOnce,
} from '../logic';

function memoryStorage(seed: Record<string, string> = {}): Storage {
  const map = new Map(Object.entries(seed));
  return {
    get length() {
      return map.size;
    },
    clear: () => map.clear(),
    getItem: (key: string) => map.get(key) ?? null,
    setItem: (key: string, value: string) => {
      map.set(key, String(value));
    },
    removeItem: (key: string) => {
      map.delete(key);
    },
    key: (index: number) => Array.from(map.keys())[index] ?? null,
  };
}

describe('modal storage keys', () => {
  it('scopes exit-intent and open-once keys by modal id', () => {
    expect(getExitIntentStorageKey('promo')).toBe('aa_exit_intent_promo');
    expect(getOpenOnLoadSeenKey('promo')).toBe('aa_modal_seen_promo');
  });
});

describe('isExitIntentDismissed / markExitIntentDismissed', () => {
  it('is not dismissed when no timestamp is stored', () => {
    expect(isExitIntentDismissed('m1', 7, memoryStorage())).toBe(false);
  });

  it('is dismissed within the reshow window', () => {
    const now = 1_000_000;
    const storage = memoryStorage({
      [getExitIntentStorageKey('m1')]: String(now - 1000),
    });
    expect(isExitIntentDismissed('m1', 7, storage, now)).toBe(true);
  });

  it('is not dismissed after the reshow window elapses', () => {
    const now = 10_000_000;
    const storage = memoryStorage({
      [getExitIntentStorageKey('m1')]: String(now - 8 * 86400000),
    });
    expect(isExitIntentDismissed('m1', 7, storage, now)).toBe(false);
  });

  it('reshowDays 0 never keeps a prior dismissal active', () => {
    const now = 5_000;
    const storage = memoryStorage({
      [getExitIntentStorageKey('m1')]: String(now - 1),
    });
    expect(isExitIntentDismissed('m1', 0, storage, now)).toBe(false);
  });

  it('markExitIntentDismissed writes the current timestamp', () => {
    const storage = memoryStorage();
    markExitIntentDismissed('m1', storage, 42);
    expect(storage.getItem(getExitIntentStorageKey('m1'))).toBe('42');
  });
});

describe('shouldSkipOpenOnLoadOnce', () => {
  it('never skips when once is disabled', () => {
    const storage = memoryStorage({ [getOpenOnLoadSeenKey('m1')]: '1' });
    expect(shouldSkipOpenOnLoadOnce('m1', false, storage)).toBe(false);
  });

  it('opens on first visit and marks seen', () => {
    const storage = memoryStorage();
    expect(shouldSkipOpenOnLoadOnce('m1', true, storage)).toBe(false);
    expect(storage.getItem(getOpenOnLoadSeenKey('m1'))).toBe('1');
  });

  it('skips on subsequent visits once marked', () => {
    const storage = memoryStorage({ [getOpenOnLoadSeenKey('m1')]: '1' });
    expect(shouldSkipOpenOnLoadOnce('m1', true, storage)).toBe(true);
  });
});

describe('canRestoreFocus', () => {
  it('rejects null, body, and disconnected nodes', () => {
    expect(canRestoreFocus(null)).toBe(false);
    expect(canRestoreFocus(document.body)).toBe(false);

    const orphan = document.createElement('button');
    expect(canRestoreFocus(orphan)).toBe(false);
  });

  it('accepts a connected focusable element', () => {
    const button = document.createElement('button');
    document.body.appendChild(button);
    expect(canRestoreFocus(button)).toBe(true);
    button.remove();
  });
});

describe('buildExitAnimation', () => {
  it('builds a fade exit with the provided ease', () => {
    const anim = buildExitAnimation('fade', 200, 'linear');
    expect(anim.transition).toBe('opacity 200ms linear');
    expect(anim.styles.opacity).toBe('0');
    expect(anim.styles.transform).toBe('none');
  });

  it('builds directional and none exits', () => {
    expect(buildExitAnimation('slide-up', 100).styles.transform).toBe(
      'translateY(-2rem)'
    );
    expect(buildExitAnimation('none', 300).transition).toBe('opacity 0ms');
  });

  it('lets drawer position CSS supply the exit transform', () => {
    const anim = buildExitAnimation('position', 250, 'linear');
    expect(anim.transition).toBe(
      'opacity 250ms linear, transform 250ms linear'
    );
    expect(anim.styles.opacity).toBe('0');
    expect(anim.styles.transform).toBeUndefined();
  });
});
