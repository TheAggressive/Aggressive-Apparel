/**
 * Sticky Add to Cart — store types.
 *
 * @package Aggressive_Apparel
 */

import type {
	InteractivityActions,
	InteractivityCallbacks,
} from '../../../types/interactivity-shared';
import type { Variation } from '@aggressive-apparel/helpers';

export interface StickyCartState {
	isVisible: boolean;
	isAdding: boolean;
	isBuyingNow: boolean;
	isSuccess: boolean;
	hasError: boolean;
	isDrawerOpen: boolean;
	isInStock: boolean;
	productType: string;
	productId: number;
	matchedVariationId: number;
	quantity: number;
	selectedAttrs: Record< string, string >;
	attributes: ProductAttribute[];
	variations: Variation[];
	displayPrice: string;
	regularPrice: string;
	/** Smart Price Display savings note ("Save 20%"), or ''. */
	savingsText: string;
	originalSavingsText: string;
	originalPrice: string;
	originalRegularPrice: string;
	drawerImage: string;
	drawerImageAlt: string;
	originalDrawerImage: string;
	originalDrawerImageAlt: string;
	drawerView: string;
	cartApiUrl: string;
	checkoutUrl: string;
	nonce: string;
	announcement: string;
	i18n: StickyCartLabels;
	_syncing: boolean;
	readonly ariaHidden: string;
	readonly isVariable: boolean;
	readonly isAddDisabled: boolean;
	readonly buttonText: string;
	readonly isDrawerAddDisabled: boolean;
	readonly isOnSale: boolean;
	readonly drawerButtonText: string;
	readonly buyNowLabel: string;
	readonly viewCartLabel: string;
	readonly continueShoppingLabel: string;
	readonly addedToCartMessage: string;
	readonly hideDrawerHeader: boolean;
	readonly hideDrawerSelection: boolean;
	readonly hideDrawerSuccess: boolean;
}

export interface StickyCartLabels {
	addToCartText?: string;
	outOfStockButtonText?: string;
	variableButtonText?: string;
	buyNowText?: string;
	redirectingText?: string;
	viewCartText?: string;
	continueShoppingText?: string;
	addedToCartMessage?: string;
	addedSuccessAnnounce?: string;
	addToCartErrorAnnounce?: string;
	unavailableLabel?: string;
}

export interface CartAddBody {
	id: number;
	quantity: number;
	variation?: Array< { attribute: string; value: string } >;
}

export interface ProductAttribute {
	name: string;
}

export interface StickyCartStore {
	state: StickyCartState;
	actions: InteractivityActions;
	callbacks: InteractivityCallbacks;
}
