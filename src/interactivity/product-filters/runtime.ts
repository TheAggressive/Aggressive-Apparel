/**
 * Product Filters — shared module runtime state.
 *
 * The request manager (facet request coordination) and the staged-changes
 * flag are shared between the store entry and the extracted data layer, so
 * they live in one module both import — keeping the mutable state single-homed.
 *
 * @package Aggressive_Apparel
 */

import { FilterRequestManager } from './request-manager';

export const requests = new FilterRequestManager();

/** Whether filter selections changed but haven't been applied yet. */
export const filterFlags = {
  staged: false,
};
