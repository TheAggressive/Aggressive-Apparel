/**
 * Undo/redo history for badge studio field edits.
 *
 * A past/present/future reducer rather than an index into an array plus a
 * "skip this one" ref. The previous shape produced two bugs that the structure
 * made almost inevitable: the skip flag was cleared inside a `setFields`
 * updater that undo/redo never ran (so the next edit was dropped from history),
 * and the trim index was captured from a render that went stale as soon as two
 * patches batched (so an undo entry vanished).
 *
 * Everything here is pure, so React may call it twice under StrictMode without
 * consequence — and it is testable without mounting a component.
 *
 * @package Aggressive_Apparel
 */

import type { BadgeFields } from './_types';

/** Maximum retained undo steps. */
export const HISTORY_LIMIT = 40;

export type HistoryState = {
  present: BadgeFields;
  past: BadgeFields[];
  future: BadgeFields[];
};

export type HistoryAction =
  | { type: 'patch'; patch: Partial<BadgeFields> }
  | { type: 'undo' }
  | { type: 'redo' };

/**
 * Seed history from the server-provided field map.
 *
 * @param fields Initial fields.
 */
export function initHistory(fields: BadgeFields): HistoryState {
  return { present: { ...fields }, past: [], future: [] };
}

/** Whether an undo step is available. */
export function canUndo(state: HistoryState): boolean {
  return state.past.length > 0;
}

/** Whether a redo step is available. */
export function canRedo(state: HistoryState): boolean {
  return state.future.length > 0;
}

/**
 * Apply a patch, ignoring non-string values.
 *
 * @param fields Current fields.
 * @param patch  Partial update.
 */
function applyPatch(
  fields: BadgeFields,
  patch: Partial<BadgeFields>
): BadgeFields {
  const next: BadgeFields = { ...fields };

  Object.entries(patch).forEach(([key, value]) => {
    if (typeof value === 'string') {
      next[key] = value;
    }
  });

  return next;
}

/**
 * Whether a patch would actually change anything.
 *
 * @param fields Current fields.
 * @param next   Candidate fields.
 */
function changed(fields: BadgeFields, next: BadgeFields): boolean {
  return Object.keys(next).some(key => next[key] !== fields[key]);
}

/**
 * Reduce a history action.
 *
 * @param state  Current history.
 * @param action Action to apply.
 */
export function historyReducer(
  state: HistoryState,
  action: HistoryAction
): HistoryState {
  switch (action.type) {
    case 'patch': {
      const next = applyPatch(state.present, action.patch);

      // A control re-emitting its current value is not an edit; recording it
      // would make Undo appear to do nothing.
      if (!changed(state.present, next)) {
        return state;
      }

      const past = [...state.past, state.present];

      return {
        present: next,
        past: past.length > HISTORY_LIMIT ? past.slice(-HISTORY_LIMIT) : past,
        // A fresh edit invalidates anything that was undone.
        future: [],
      };
    }

    case 'undo': {
      if (!canUndo(state)) {
        return state;
      }

      const previous = state.past[state.past.length - 1];

      return {
        present: previous,
        past: state.past.slice(0, -1),
        future: [state.present, ...state.future],
      };
    }

    case 'redo': {
      if (!canRedo(state)) {
        return state;
      }

      const [next, ...rest] = state.future;

      return {
        present: next,
        past: [...state.past, state.present],
        future: rest,
      };
    }

    default:
      return state;
  }
}
