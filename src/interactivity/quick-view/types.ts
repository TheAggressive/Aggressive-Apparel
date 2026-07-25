/**
 * Quick View — shared types.
 *
 * Extracted from quick-view.ts so the store entry stays under the file-length
 * cap. Pure type declarations only; no runtime code.
 *
 * @package Aggressive_Apparel
 */

import type { StoreApiPrices } from '@aggressive-apparel/helpers';
import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../../types/interactivity-shared';

export interface QuickViewContext {
  productId: number;
  item?: QuickViewOption | GalleryImage;
}

export interface QuickViewOption {
  name: string;
  slug: string;
  varValue?: string;
  attrSlug: string;
}

export interface GalleryImage {
  id: number | string;
  src: string;
  alt: string;
  thumbnail: string;
}

export interface ColorSwatchEntry {
  name?: string;
  value?: string;
}

export interface StoreApiImage {
  id?: number;
  src: string;
  alt?: string;
  thumbnail?: string;
}

export interface StoreApiAttribute {
  name: string;
  taxonomy?: string;
  has_variations: boolean;
  terms?: StoreApiTerm[];
}

export interface StoreApiTerm {
  id?: number;
  slug: string;
  name: string;
}

export interface StoreApiVariationAttribute {
  attribute?: string;
  name?: string;
  value: string;
  taxonomy?: string;
}

export interface StoreApiVariation {
  id: number;
  attributes?: StoreApiVariationAttribute[];
  image?: StoreApiImage;
  prices?: Record<string, unknown>;
}

export interface StoreApiProduct {
  id: number;
  name: string;
  permalink: string;
  type?: string;
  description: string;
  short_description: string;
  images?: StoreApiImage[];
  prices: StoreApiPrices;
  attributes?: StoreApiAttribute[];
  variations?: StoreApiVariation[];
  has_options?: boolean;
  is_in_stock?: boolean;
  stock_quantity?: number | null;
  extensions?: Record<string, Record<string, Record<string, unknown>>>;
}

export interface StockInfo {
  status: string;
  quantity: number | null;
  label: string;
}

export interface ResolvedAttribute {
  name: string;
  slug: string;
  options: ResolvedOption[];
}

export interface ResolvedOption {
  name: string;
  slug: string;
  varValue: string;
  attrSlug: string;
}

export interface ResolvedVariation {
  id: number;
  attributes: Array<{
    attribute: string;
    name?: string;
    value: string;
    taxonomy?: string;
  }>;
  image: string;
  imageAlt: string;
  prices: Record<string, unknown> | null;
  inStock: boolean;
}

export interface CartAddBody {
  id: number;
  quantity: number;
  variation?: Array<{ attribute: string; value: string }>;
}

export interface QuickViewLabels {
  addToCartText?: string;
  addingToCartText?: string;
  addedToCartText?: string;
  outOfStockButtonText?: string;
  variableButtonText?: string;
  buyNowText?: string;
  redirectingText?: string;
  viewCartText?: string;
  continueShoppingText?: string;
  viewProductText?: string;
  addedToCartMessage?: string;
  outOfStockLabel?: string;
  inStockLabel?: string;
  onlyNLeft?: string;
  addedSuccessAnnounce?: string;
  addToCartError?: string;
  errorAnnounce?: string;
  unavailableLabel?: string;
}

export interface QuickViewState {
  restBase: string;
  cartApiUrl: string;
  collapseVariablePrice: boolean;
  priceStartingPrefix: string;
  i18n: QuickViewLabels;
  isOpen: boolean;
  isSuccessOpen: boolean;
  isLoading: boolean;
  hasError: boolean;
  hasProduct: boolean;
  productId: number;
  productType: string;
  productImage: string;
  productImageAlt: string;
  productName: string;
  productPrice: string;
  productRegularPrice: string;
  productOnSale: boolean;
  productDescription: string;
  productLink: string;
  productAttributes: ResolvedAttribute[];
  productVariations: ResolvedVariation[];
  selectedAttributes: Record<string, string>;
  matchedVariationId: number;
  availableOptions: Record<string, string[]>;
  quantity: number;
  isAddingToCart: boolean;
  isCartSuccess: boolean;
  isBuyingNow: boolean;
  cartError: string;
  cartNonce: string;
  isDrawerOpen: boolean;
  productImages: GalleryImage[];
  _originalImages: GalleryImage[];
  activeImageIndex: number;
  stockStatus: string;
  stockQuantity: number | null;
  stockStatusLabel: string;
  salePercentage: number;
  productPriceRange: string;
  colorSwatchData: Record<string, ColorSwatchEntry>;
  cartUrl: string;
  checkoutUrl: string;
  announcement: string;
  readonly isVariable: boolean;
  readonly canAddToCart: boolean;
  readonly addToCartLabel: string;
  readonly buyNowLabel: string;
  readonly selectOptionsLabel: string;
  readonly viewCartLabel: string;
  readonly continueShoppingLabel: string;
  readonly viewProductLabel: string;
  readonly addedToCartMessage: string;
  readonly isOptionSelected: boolean;
  readonly isOptionUnavailable: boolean;
  readonly optionAccessibleName: string;
  readonly currentImage: { src: string; alt: string };
  readonly hasMultipleImages: boolean;
  readonly isActiveImage: boolean;
  readonly imagePositionLabel: string;
  readonly saleBadgeText: string;
  readonly isInStock: boolean;
  readonly isLowStock: boolean;
  readonly isOutOfStock: boolean;
  readonly isNotLoading: boolean;
  readonly hasNoProduct: boolean;
  readonly isNotOnSale: boolean;
  readonly hasOneImage: boolean;
  readonly cannotAddToCart: boolean;
  readonly hasNoCartError: boolean;
  readonly hasNoError: boolean;
  readonly hideSelectOptionsBtn: boolean;
  readonly hideInlineAddToCart: boolean;
  readonly isDrawerClosed: boolean;
  readonly selectedOptionsLabel: string;
  readonly isColorAttribute: boolean;
  readonly isNotColorAttribute: boolean;
  readonly isColorSwatch: boolean;
  readonly colorSwatchValue: string;
  readonly colorSwatchName: string;
  readonly thumbnailsFitContainer: boolean;
}

export interface QuickViewStore {
  state: QuickViewState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}
