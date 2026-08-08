/**
 * Undo/redo history for the badge studio.
 *
 * The two bugs this replaced both lost a user's edit: an edit made straight
 * after an Undo was dropped from history, and batched patches dropped an undo
 * entry. Both are asserted here directly rather than through a mounted
 * component, because the reducer is where the guarantee lives.
 */

import {
  HISTORY_LIMIT,
  canRedo,
  canUndo,
  historyReducer,
  initHistory,
  type HistoryState,
} from '../badge-studio/_history';

/** Apply a sequence of actions from a seeded state. */
const run = (
  start: HistoryState,
  ...actions: Parameters<typeof historyReducer>[1][]
): HistoryState => actions.reduce(historyReducer, start);

const patch = (p: Record<string, string>) =>
  ({ type: 'patch', patch: p }) as const;
const undo = { type: 'undo' } as const;
const redo = { type: 'redo' } as const;

describe('badge studio history', () => {
  const seed = () => initHistory({ badge_bg_color: '#000000' });

  it('starts with nothing to undo or redo', () => {
    const state = seed();

    expect(canUndo(state)).toBe(false);
    expect(canRedo(state)).toBe(false);
    expect(state.present.badge_bg_color).toBe('#000000');
  });

  it('records an edit and steps back to the previous value', () => {
    const state = run(seed(), patch({ badge_bg_color: '#ff0000' }));

    expect(state.present.badge_bg_color).toBe('#ff0000');
    expect(canUndo(state)).toBe(true);

    const undone = historyReducer(state, undo);

    expect(undone.present.badge_bg_color).toBe('#000000');
    expect(canRedo(undone)).toBe(true);
  });

  it('keeps an edit made immediately after an undo', () => {
    // The old implementation cleared its skip-flag inside a setFields updater
    // that undo never ran, so this edit vanished from history.
    const state = run(
      seed(),
      patch({ badge_bg_color: '#ff0000' }),
      undo,
      patch({ badge_bg_color: '#00ff00' })
    );

    expect(state.present.badge_bg_color).toBe('#00ff00');
    expect(canUndo(state)).toBe(true);

    expect(historyReducer(state, undo).present.badge_bg_color).toBe('#000000');
  });

  it('discards the redo branch once a new edit lands', () => {
    const state = run(
      seed(),
      patch({ badge_bg_color: '#ff0000' }),
      undo,
      patch({ badge_bg_color: '#00ff00' })
    );

    expect(canRedo(state)).toBe(false);
  });

  it('keeps every entry when patches arrive back to back', () => {
    // Two patches in one batch previously shared a stale index, losing one.
    const state = run(
      seed(),
      patch({ badge_bg_color: '#111111' }),
      patch({ badge_text_color: '#222222' })
    );

    expect(state.past).toHaveLength(2);

    const once = historyReducer(state, undo);
    expect(once.present.badge_text_color).toBeUndefined();
    expect(once.present.badge_bg_color).toBe('#111111');

    const twice = historyReducer(once, undo);
    expect(twice.present.badge_bg_color).toBe('#000000');
  });

  it('round-trips undo and redo', () => {
    const state = run(
      seed(),
      patch({ badge_bg_color: '#ff0000' }),
      patch({ badge_bg_color: '#0000ff' })
    );

    const back = run(state, undo, undo);
    expect(back.present.badge_bg_color).toBe('#000000');

    const forward = run(back, redo, redo);
    expect(forward.present.badge_bg_color).toBe('#0000ff');
    expect(canRedo(forward)).toBe(false);
  });

  it('ignores a patch that changes nothing', () => {
    const state = historyReducer(seed(), patch({ badge_bg_color: '#000000' }));

    expect(canUndo(state)).toBe(false);
  });

  it('ignores non-string patch values', () => {
    const state = historyReducer(
      seed(),
      patch({ badge_bg_color: undefined as unknown as string })
    );

    expect(state.present.badge_bg_color).toBe('#000000');
  });

  it('is a no-op when there is nothing to undo or redo', () => {
    const state = seed();

    expect(historyReducer(state, undo)).toBe(state);
    expect(historyReducer(state, redo)).toBe(state);
  });

  it('caps retained history and still undoes to the oldest kept entry', () => {
    let state = seed();

    for (let i = 0; i < HISTORY_LIMIT + 10; i++) {
      state = historyReducer(
        state,
        patch({ badge_bg_color: `#00000${i % 10}` })
      );
    }

    expect(state.past).toHaveLength(HISTORY_LIMIT);

    for (let i = 0; i < HISTORY_LIMIT; i++) {
      state = historyReducer(state, undo);
    }

    expect(canUndo(state)).toBe(false);
  });
});
