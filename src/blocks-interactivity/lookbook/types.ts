/**
 * Shared Lookbook block types.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

export interface LookbookHotspot {
  x: number;
  y: number;
  productId: number;
  productName: string;
}

export interface LookbookAttributes {
  mediaId: number;
  mediaUrl: string;
  mediaAlt: string;
  hotspots: LookbookHotspot[];
  hotspotBgColor: string;
  hotspotTextColor: string;
  hotspotSize: number;
  openOnHover: boolean;
  cardBgColor: string;
  cardTextColor: string;
  actionBgColor: string;
  actionIconColor: string;
}

export interface StoreApiImage {
  src?: string;
  alt?: string;
}

export interface StoreApiPrices {
  price?: string;
  currency_minor_unit?: number;
  currency_prefix?: string;
  currency_suffix?: string;
  currency_decimal_separator?: string;
  currency_thousand_separator?: string;
}

export interface StoreApiProduct {
  id?: number;
  name?: string;
  sku?: string;
  permalink?: string;
  images?: StoreApiImage[];
  prices?: StoreApiPrices;
  price_html?: string;
  price?: string;
}
