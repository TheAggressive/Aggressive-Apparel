import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../../types/interactivity-shared';

export interface DropdownContext {
  dropdownId: string;
}

export interface PriceRange {
  min: number;
  max: number;
  currencyPrefix?: string;
  currencySuffix?: string;
  minorUnit?: number;
}

export interface CategoryTerm {
  slug: string;
  name: string;
  link?: string;
}

export interface ColorTerm {
  slug: string;
  name: string;
}

export interface SizeTerm {
  slug: string;
  name: string;
}

export interface CollectionTerm {
  slug: string;
  name: string;
}

export interface FilterPill {
  type: string;
  slug: string;
  label: string;
}

export type Facets = Record<string, string[] | undefined>;

export interface AttributeFilterConfig {
  taxonomy: string;
  urlParam: string;
  stateKey: 'selectedColors' | 'selectedSizes' | 'selectedCollections';
  selector: string;
}

export const ATTRIBUTE_FILTERS: readonly AttributeFilterConfig[] = [
  {
    taxonomy: 'pa_color',
    urlParam: 'color',
    stateKey: 'selectedColors',
    selector: '.aa-product-filters__color-swatch',
  },
  {
    taxonomy: 'pa_size',
    urlParam: 'size',
    stateKey: 'selectedSizes',
    selector: '.aa-product-filters__size-chip',
  },
  {
    taxonomy: 'pa_collection',
    urlParam: 'collection',
    stateKey: 'selectedCollections',
    selector: '.aa-product-filters__collection-chip',
  },
];

export interface ArchiveTerm {
  taxonomy: string;
  slug: string;
  link: string;
}

export interface ProductFiltersState {
  selectedCategories: string[];
  selectedColors: string[];
  selectedSizes: string[];
  priceMin: number;
  priceMax: number;
  priceRange: PriceRange;
  inStockOnly: boolean;
  onSaleOnly: boolean;
  /** True while a filter or sort navigation is in flight. */
  isLoading: boolean;
  isDrawerOpen: boolean;
  openDropdown: string;
  /** WooCommerce `orderby` value of the current URL ('' = default). */
  orderby: string;
  facetsUrl: string;
  restNonce: string;
  shopUrl: string;
  salesCategoryUrl: string;
  layout: string;
  categories: CategoryTerm[];
  colorTerms: ColorTerm[];
  sizeTerms: SizeTerm[];
  collectionTerms: CollectionTerm[];
  selectedCollections: string[];
  currentCategorySlug: string;
  /** Tag / brand / attribute archive being browsed; empty strings elsewhere. */
  archiveTerm: ArchiveTerm;
  salesCategorySlug: string;
  i18n: {
    filtersAppliedSingular: string;
    filtersAppliedPlural: string;
    activeFiltersOverflowTooltip: string;
    inStockLabel: string;
    onSaleLabel: string;
    allFiltersCleared?: string;
    noProductsFound?: string;
    oneProductFound?: string;
    productsFound?: string;
    removeFilterAria?: string;
  };
  _announcement: string;
  readonly hasActiveFilters: boolean;
  readonly hasNoActiveFilters: boolean;
  readonly activeFilterCount: number | string;
  readonly triggerCountLabel: string;
  readonly priceMinDisplay: string;
  readonly priceMaxDisplay: string;
  readonly announcement: string;
  readonly isCategoryDropdownOpen: boolean;
  readonly isColorDropdownOpen: boolean;
  readonly isSizeDropdownOpen: boolean;
  readonly isCollectionDropdownOpen: boolean;
  readonly isPriceDropdownOpen: boolean;
  readonly isStockDropdownOpen: boolean;
}

export interface ProductFiltersStore {
  state: ProductFiltersState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}
