/**
 * Typed bridge from block.json metadata to registerBlockType().
 *
 * @package Aggressive_Apparel
 */

import {
  registerBlockType,
  type BlockConfiguration,
  type BlockVariation,
} from '@wordpress/blocks';

/**
 * Minimal block.json metadata shape (apiVersion 3).
 */
export interface BlockJsonMetadata {
  name: string;
  apiVersion?: number;
  [key: string]: unknown;
}

/**
 * A block variation supplies attribute OVERRIDES, so its attributes are a
 * subset of the block's own.
 *
 * `@wordpress/blocks` types `BlockVariation<T>.attributes` as the complete `T`
 * even though its own documentation describes the field as "values which
 * override block attributes". Modelling it as `Partial<T>` here keeps variation
 * definitions honest without casting at each call site.
 */
export type ThemeBlockVariation<T extends Record<string, unknown>> = Omit<
  BlockVariation<T>,
  'attributes'
> & {
  attributes?: Partial<T>;
};

/**
 * Editor settings accepted alongside block.json metadata.
 */
export type ThemeBlockSettings<T extends Record<string, unknown>> = Omit<
  Partial<BlockConfiguration<T>>,
  'variations'
> & {
  variations?: ThemeBlockVariation<T>[];
} & Record<string, unknown>;

/**
 * Register a theme block by merging block.json with editor settings.
 *
 * Uses a single BlockConfiguration assertion at this boundary so block
 * index files stay cast-free.
 */
export function registerThemeBlock<T extends Record<string, unknown>>(
  metadata: BlockJsonMetadata,
  settings: ThemeBlockSettings<T> = {}
): void {
  registerBlockType(
    metadata as BlockConfiguration<T>,
    settings as Partial<BlockConfiguration<T>>
  );
}
