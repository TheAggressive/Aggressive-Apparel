/**
 * Typed bridge from block.json metadata to registerBlockType().
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';

/**
 * Minimal block.json metadata shape (apiVersion 3).
 */
export interface BlockJsonMetadata {
  name: string;
  apiVersion?: number;
  [key: string]: unknown;
}

/**
 * Register a theme block by merging block.json with editor settings.
 *
 * Uses a single BlockConfiguration assertion at this boundary so block
 * index files stay cast-free.
 */
export function registerThemeBlock<T extends object>(
  metadata: BlockJsonMetadata,
  settings: Partial<BlockConfiguration<T>> & Record<string, unknown> = {}
): void {
  registerBlockType(metadata as BlockConfiguration<T>, settings);
}
