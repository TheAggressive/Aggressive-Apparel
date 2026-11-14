/**
 * Quick View badges — the same Badge Studio set the product card shows.
 *
 * The badge markup comes from the Store API (Product_Badges, via the
 * `aggressive-apparel/badges` extension). Its sale badge starts with the
 * product-level wording the card uses; once a variation is chosen it shows
 * that variation's discount instead, and hides when the variation isn't
 * reduced. When the theme draws no sale badge (Product Badges or its Sale
 * rule off), the modal's own default sale chip is used.
 *
 * @package Aggressive_Apparel
 */

import { productBadgesHtml } from '../../utils/product-extensions';
import type { WithExtensions } from '../../utils/product-extensions';
import { state, saleBadgeText } from '../quick-view';

const HOST = '.aggressive-apparel-quick-view__badges';
const THEME_SALE = '.aggressive-apparel-product-badge--type-sale';
const LABEL = '.aggressive-apparel-product-badge__label';

/**
 * The text a badge shows, or its accessible name when it is icon-only.
 *
 * @param badge Badge element.
 */
function labelOf( badge: HTMLElement ): string {
	return (
		badge.querySelector( LABEL )?.textContent ??
		badge.getAttribute( 'aria-label' ) ??
		''
	);
}

/**
 * Put a product's badges on the main image.
 *
 * @param root    Element containing the badge host.
 * @param product Store API product.
 */
export function renderProductBadges(
	root: ParentNode,
	product: WithExtensions
): void {
	const host = root.querySelector< HTMLElement >( HOST );
	if ( ! host ) {
		state.themeSaleBadge = false;
		return;
	}

	host.innerHTML = productBadgesHtml( product );

	const sale = host.querySelector< HTMLElement >( THEME_SALE );
	if ( sale ) {
		sale.dataset.aaDefaultLabel = labelOf( sale );
	}
	state.themeSaleBadge = Boolean( sale );
}

/**
 * Keep the theme's sale badge in step with the chosen variation.
 *
 * @param root Element containing the badge host.
 */
export function syncThemeSaleBadge( root: ParentNode ): void {
	const sale = root.querySelector< HTMLElement >(
		`${ HOST } ${ THEME_SALE }`
	);
	if ( ! sale ) {
		return;
	}

	const text = state.matchedVariationId
		? saleBadgeText()
		: ( sale.dataset.aaDefaultLabel ?? '' );

	sale.hidden = ! text;
	if ( ! text ) {
		return;
	}

	const label = sale.querySelector( LABEL );
	if ( label ) {
		label.textContent = text;
	}
	if ( sale.getAttribute( 'role' ) === 'img' ) {
		sale.setAttribute( 'aria-label', text );
	}
}
