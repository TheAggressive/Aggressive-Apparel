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
  /** A text spec holds several values, like an attribute with several terms. */
  multiple: boolean;
  /** Values link to their archive pages (the attribute's "Enable archives"). */
  archives: boolean;
  /** A text spec's existing values (its terms) and their product counts. */
  terms?: { name: string; count: number }[];
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
  /** Global attributes after the save, taxonomy => label. */
  taxonomies?: Record<string, string>;
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
  /** No chart, but sets specs: a specs-only guide. */
  specs: boolean;
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
  /** Where the guide was imported from, if anywhere. */
  source: { provider: string; id: number; title: string } | null;
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

/** A Printful catalog product in search results. */
export type PrintfulProduct = {
  id: number;
  title: string;
  brand: string;
  model: string;
  image: string;
  group: string;
  /** The guide already imported from it (0 for none). */
  guide: number;
  /** A common blank, listed first before a search. */
  popular: boolean;
};

/** One description bullet and the spec it maps to (Printful_Import). */
export type ImportFact = {
  /** Rule key: the text with numbers generalized. */
  key: string;
  text: string;
  /** A spec key, 'skip', or '' when unsure. */
  target: string;
  value: string;
  status: 'sure' | 'guess' | 'unsure' | 'skipped';
};

/** A Printful product as a size guide draft to review. */
export type ImportDraft = {
  source: { provider: string; id: number; title: string };
  title: string;
  image: string;
  charts: { garment: Chart | null; body: Chart | null };
  notes: { garment: string; body: string };
  diagrams: { garment: string; body: string };
  facts: ImportFact[];
  /** A guide already imported from this product (0 for none). */
  guide: number;
};

/** A spec made while importing: new, or an existing attribute made a spec. */
export type ImportAttribute = {
  /** Spec key, fixed when first set up. */
  key: string;
  name: string;
  kind: 'text' | 'multiple' | 'pick' | 'number';
  /** This guide only, or every guide. */
  scope: 'guide' | 'store';
  archives: boolean;
  /** Comma-separated steps, for a scale. */
  steps: string;
  unit: string;
  /** An existing WooCommerce attribute to use ('' for a new one). */
  taxonomy: string;
};

/** Where one description line goes. */
export type ImportMapping = {
  /** Spec key, 'skip', 'new' (an ImportAttribute), or '' (undecided). */
  target: string;
  value: string;
  /** Set by the store (not inherited from another blank). */
  touched: boolean;
};

/** The store's edits to one blank before it becomes a guide. */
export type ImportBlankState = {
  title: string;
  kind: 'garment' | 'body';
  /** Notes per chart, so switching charts keeps edits to either. */
  notes: { garment: string; body: string };
  /** Whether the measuring diagram comes along. */
  diagram: boolean;
  categories: number[];
  isDefault: boolean;
  mappings: ImportMapping[];
};

/** What an import made of one blank, for the summary. */
export type ImportResult = {
  id: number;
  title: string;
  /** New guide ID; 0 when it failed. */
  guide: number;
  error: string;
  chart: '' | 'garment' | 'body';
  /** Rows in the chart. */
  sizes: number;
  /** Specs given a value. */
  specs: number;
  /** New attributes this guide made or joined. */
  attributes: string[];
  categories: string[];
  isDefault: boolean;
  /** A diagram is on its way (it downloads in the background). */
  diagram: boolean;
};
