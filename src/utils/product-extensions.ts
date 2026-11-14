/**
 * Theme data the Store API adds to each product (`extensions`), so
 * client-rendered product surfaces show exactly what the server-rendered
 * cards show:
 *
 * - `aggressive-apparel/badges` → `html`: the product's Badge Studio badge
 *   set (Product_Badges), present while Product Badges is on.
 * - `aggressive-apparel/pricing` → `savings`: the Smart Price Display savings
 *   note (Price_Display::savings_text), present while that feature is on.
 *
 * Dependency-free on purpose: block view modules bundle it directly.
 *
 * @package Aggressive_Apparel
 */

export const BADGES_EXTENSION = 'aggressive-apparel/badges';
export const PRICING_EXTENSION = 'aggressive-apparel/pricing';

/** Anything shaped like a Store API product. */
export interface WithExtensions {
	extensions?: Record< string, unknown > | null;
}

function field( product: WithExtensions, namespace: string, key: string ) {
	const data = product.extensions?.[ namespace ];
	if ( ! data || typeof data !== 'object' ) {
		return '';
	}
	const value = ( data as Record< string, unknown > )[ key ];
	return typeof value === 'string' ? value : '';
}

/**
 * The product's badge markup, as the cards render it. Server-built and
 * escaped there (Product_Badges::render_badge), so it is inserted as HTML.
 *
 * @param product Store API product.
 * @return Badge wrappers HTML, or '' when the product has none.
 */
export function productBadgesHtml( product: WithExtensions ): string {
	return field( product, BADGES_EXTENSION, 'html' );
}

/**
 * The product's savings note ("Save 20%"), or '' when there is none.
 *
 * @param product Store API product or variation.
 * @return Plain text.
 */
export function productSavingsText( product: WithExtensions ): string {
	return field( product, PRICING_EXTENSION, 'savings' );
}
