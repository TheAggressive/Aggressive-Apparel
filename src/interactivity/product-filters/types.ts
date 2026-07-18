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

export interface FitTerm {
  slug: string;
  name: string;
}

export interface FilterPill {
  type: string;
  slug: string;
  label: string;
}

export interface SortedProductsResponse {
  total: number;
  totalPages: number;
  ids: number[];
}

export type Facets = Record<string, string[] | undefined>;

export interface RenderedResponse {
  html: string;
  styles?: Array<{ id: string; css: string; nonce?: string }>;
  total_products: number;
  total_pages: number;
  next_cursor?: string;
  facets?: Facets;
}

export interface AttributeFilterConfig {
  taxonomy: string;
  urlParam: string;
  stateKey: 'selectedColors' | 'selectedSizes' | 'selectedFit';
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
    taxonomy: 'pa_fit',
    urlParam: 'fit',
    stateKey: 'selectedFit',
    selector: '.aa-product-filters__fit-chip',
  },
];

export interface SortConfig {
  orderBy: string;
  orderDir: string;
  customSort?: string;
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
  products: number[];
  currentPage: number;
  totalPages: number;
  totalProducts: number;
  nextCursor: string;
  isLoading: boolean;
  hasError: boolean;
  isDrawerOpen: boolean;
  openDropdown: string;
  perPage: number;
  orderBy: string;
  orderDir: string;
  restBase: string;
  restNonce: string;
  templateSlug: string;
  shopUrl: string;
  salesCategoryUrl: string;
  layout: string;
  categories: CategoryTerm[];
  colorTerms: ColorTerm[];
  sizeTerms: SizeTerm[];
  fitTerms: FitTerm[];
  selectedFit: string[];
  currentCategorySlug: string;
  salesCategorySlug: string;
  i18n: {
    filtersAppliedSingular: string;
    filtersAppliedPlural: string;
    activeFiltersOverflowTooltip: string;
    inStockLabel: string;
    onSaleLabel: string;
    allFiltersCleared?: string;
    loadError?: string;
    noProductsFound?: string;
    oneProductFound?: string;
    productsFound?: string;
    removeFilterAria?: string;
  };
  _announcement: string;
  _customSort: string;
  readonly hasActiveFilters: boolean;
  readonly hasNoActiveFilters: boolean;
  readonly hasProducts: boolean;
  readonly hideNoResults: boolean;
  readonly hideError: boolean;
  readonly hasSinglePage: boolean;
  readonly activeFilterCount: number | string;
  readonly triggerCountLabel: string;
  readonly priceMinDisplay: string;
  readonly priceMaxDisplay: string;
  readonly announcement: string;
  readonly isCategoryDropdownOpen: boolean;
  readonly isColorDropdownOpen: boolean;
  readonly isSizeDropdownOpen: boolean;
  readonly isFitDropdownOpen: boolean;
  readonly isPriceDropdownOpen: boolean;
  readonly isStockDropdownOpen: boolean;
}

export interface ProductFiltersStore {
  state: ProductFiltersState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}
