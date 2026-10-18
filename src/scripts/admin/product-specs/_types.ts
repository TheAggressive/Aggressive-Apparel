/**
 * Product Specs settings — shared types.
 *
 * @package Aggressive_Apparel
 */

export type ItemType = 'pick' | 'number' | 'text';
export type Scope = 'all' | 'only' | 'except';

/** One spec item as stored by Product_Specs_Registry. */
export type SpecItem = {
  key: string;
  type: ItemType;
  name: string;
  enabled: boolean;
  steps: string[];
  start: string;
  mid: string;
  end: string;
  centered: boolean;
  unit: string;
  thresholds: number[];
  advice: string[];
  attributes: string[];
  scope: Scope;
  categories: number[];
  category_steps: CategorySteps[];
  in_table: boolean;
  /** Value every product gets unless it sets its own ('' for none). */
  default: string;
  /** Size guides that own the spec; empty for a store-wide spec. */
  guides: number[];
  /** WooCommerce attribute holding the values ('' until first saved). */
  taxonomy: string;
  /** A picked scale's terms, one per step (kept through renames). */
  step_terms: number[];
  default_term: number;
  /** A text spec's existing values (its terms), to pick from. */
  terms?: string[];
};

/** Number-scale boundaries for one category. */
export type CategorySteps = { category: number; thresholds: number[] };

/** Editor-side item: a stable React key and whether it was ever saved. */
export type DraftItem = SpecItem & { uid: string; isNew: boolean };

export type Registry = {
  items: SpecItem[];
  hide_dimensions: boolean;
  /** Bumped on every save, so a stale editor's save merges instead. */
  revision: number;
};

export type Category = { id: number; name: string };

export type Tab = 'guides' | 'specs' | 'products';

export type Usage = { global: boolean; categories: string[]; products: number };

export type ChartColumn = { label: string; type: 'measure' | 'text' };
export type ChartRow = { size: string; values: string[] };
export type Chart = {
  unit: 'in' | 'cm';
  columns: ChartColumn[];
  rows: ChartRow[];
};

/** A size guide in the list. */
export type GuideSummary = {
  id: number;
  title: string;
  sizes: number;
  columns: number;
  usage: Usage;
  legacy: boolean;
};

/** A size guide being edited. */
export type GuideDetail = {
  id: number;
  title: string;
  chart: Chart;
  notes: string;
  diagram: { id: number; url: string } | null;
  /** Spec values products using the guide inherit (item key => value). */
  specs: Record<string, string>;
  /** Store-wide specs hidden on this guide's products. */
  hidden: string[];
  categories: number[];
  isDefault: boolean;
  usage: Usage;
  legacyHtml: string;
  legacyEditUrl: string;
};

/** An attribute the store already uses that could become a spec. */
export type Suggestion = {
  name: string;
  products: number;
  values: string[];
  type: ItemType;
  unit: string;
  steps: string[];
  thresholds: number[];
};

/** One spec on one product in the Products grid. */
export type GridCell = {
  applies: boolean;
  own: string;
  shown: string;
  inherited: string;
  hidden: boolean;
};

export type GridRow = {
  id: number;
  title: string;
  editUrl: string;
  thumb: string;
  categories: string[];
  /** The size guide the product uses (0 for none). */
  guide: number;
  specs: Record<string, GridCell>;
};

export type Config = {
  registry: Registry;
  defaults: SpecItem[];
  categories: Category[];
  restPath: string;
  gridPath: string;
  guidesPath: string;
  guides: GuideSummary[];
  suggest: Suggestion[];
  /** Attribute names in use on products. */
  attributes: string[];
  /** Global attributes, taxonomy => label. */
  taxonomies: Record<string, string>;
  /** Terms screen URL; append the taxonomy. */
  termsUrl: string;
  tab: Tab;
  guide: number | 'new';
  limits: {
    items: number;
    minSteps: number;
    maxSteps: number;
    columns: number;
    rows: number;
  };
};
