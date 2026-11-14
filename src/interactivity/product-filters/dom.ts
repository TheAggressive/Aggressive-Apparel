import {
	formatMoneyAmount,
	fromCurrencyContext,
} from '../../utils/store-api-money';
import type { CurrencyContext } from '../../utils/store-api-money';

export { escapeHtml } from '../../utils/escape-html';

/**
 * Format a whole-unit filter bound (the slider steps in whole currency units)
 * in the store's currency format.
 *
 * @param amount   Whole-unit amount.
 * @param currency Store currency format seeded with the price range.
 */
export function formatWholePrice(
	amount: number,
	currency: CurrencyContext | null | undefined
): string {
	return formatMoneyAmount( amount, fromCurrencyContext( currency ), {
		trimZeroDecimals: true,
	} );
}

/** Whether a filter option is unavailable (the one marker CSS styles too). */
export function isUnavailable( option: Element ): boolean {
	return option.getAttribute( 'aria-disabled' ) === 'true';
}

/**
 * Mark filter options rejected by a predicate as unavailable.
 *
 * `aria-disabled` keeps them focusable and announced as unavailable, rather
 * than hidden from assistive tech or the tab order; chosenOption() ignores
 * their activation, which `aria-disabled` does not block.
 */
export function setFilterVisibility(
	selector: string,
	predicate: ( element: HTMLElement ) => boolean
): void {
	document
		.querySelectorAll< HTMLElement >( selector )
		.forEach( ( element ) => {
			if ( predicate( element ) ) {
				element.removeAttribute( 'aria-disabled' );
			} else {
				element.setAttribute( 'aria-disabled', 'true' );
			}
		} );
}

/**
 * The value of the filter option a click activated, or '' when it hit no
 * option or an unavailable one.
 */
export function chosenOption( event: Event, selector: string ): string {
	const option = ( event.target as Element | null )?.closest< HTMLElement >(
		selector
	);
	if ( ! option || isUnavailable( option ) ) return '';
	return option.dataset.filterValue ?? '';
}
