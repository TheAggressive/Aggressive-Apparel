/**
 * Product Filters — shared module runtime state.
 *
 * The request manager (in-flight fetch coordination) and the staged-changes
 * flag are shared between the store entry and the extracted fetch layer, so
 * they live in one module both import — keeping the mutable state single-homed.
 *
 * @package Aggressive_Apparel
 */

import { FilterRequestManager } from './request-manager';

export const requests = new FilterRequestManager();

/** Whether filter selections changed but haven't been applied (fetched) yet. */
export const filterFlags = {
  staged: false,
  pendingNavUrl: null as string | null,
};
