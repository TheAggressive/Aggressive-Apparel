import type {
	InteractivityActions,
	InteractivityCallbacks,
} from '../../../types/interactivity-shared';
import urlContract from './url-contract.json';

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

export type Facets = Record< string, string[] | undefined >;

export interface AttributeFilterConfig {
	taxonomy: string;
	urlParam: string;
	stateKey: 'selectedColors' | 'selectedSizes' | 'selectedCollections';
	selector: string;
}

/** Per-filter UI wiring; the URL parameter comes from url-contract.json. */
const ATTRIBUTE_UI: Record<
	string,
	Pick< AttributeFilterConfig, 'stateKey' | 'selector' >
> = {
	pa_color: {
		stateKey: 'selectedColors',
		selector: '.aa-product-filters__color-swatch',
	},
	pa_size: {
		stateKey: 'selectedSizes',
		selector: '.aa-product-filters__size-chip',
	},
	pa_collection: {
		stateKey: 'selectedCollections',
		selector: '.aa-product-filters__collection-chip',
	},
};

/**
 * Attribute filters, in URL-parameter order. url-contract.json is shared with
 * the server (Catalog_Url_Filters::ATTRIBUTE_PARAMS, checked in PHPUnit).
 */
export const ATTRIBUTE_FILTERS: readonly AttributeFilterConfig[] =
	Object.entries( urlContract.attributeParams ).map(
		( [ urlParam, taxonomy ] ) => ( {
			taxonomy,
			urlParam,
			...ATTRIBUTE_UI[ taxonomy ],
		} )
	);

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
	/** Collection slugs with landing pages (/shop/mens/, /shop/mens/t-shirts/). */
	audiences: string[];
	/** Pathname the landing pages live under, e.g. '/shop/'; '' when none. */
	audienceBase: string;
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
