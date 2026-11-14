/**
 * Shared Lookbook block types.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import type { StoreApiCurrency } from '../../utils/store-api-money';

export interface LookbookHotspot {
	x: number;
	y: number;
	productId: number;
	productName: string;
}

export type LookbookAttributes = {
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
};

export interface StoreApiImage {
	src?: string;
	alt?: string;
}

export interface StoreApiPrices extends StoreApiCurrency {
	price?: string;
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

export interface LookbookI18n {
	loading: string;
	noProduct: string;
	loadError: string;
	viewProduct: string;
	viewProductName: string;
}
