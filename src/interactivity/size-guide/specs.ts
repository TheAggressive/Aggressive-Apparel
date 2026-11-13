/**
 * Product Specs block — footer behaviour.
 *
 * The footer under the specs shows the picked size's measurements ("Size M ·
 * Chest 42 in · Length 29 in") and a link that opens the size guide. The
 * server prints every size's line in both units as hidden ghosts (product
 * data, so page-cache safe); this copies the one for the shopper's size and
 * unit into the live line, and follows both as they change. The footer only
 * renders on the product's own page, so reading the pick page-wide is safe.
 * The link clicks the guide's own trigger, so focus returns to it on close.
 *
 * @package Aggressive_Apparel
 */

import { store, getElement } from '@wordpress/interactivity';
import { selectedSizes, sizeKey } from './sizes';
import { openSizeGuide } from './open';
import { preferredUnit, UNIT_EVENT, type Unit } from './units';

const GHOST = '.aa-product-specs__fit-ghost';

/**
 * What the line shows: the prompt until a size is picked, then that size's
 * line in the unit, or nothing when the chart has no row for it.
 */
type Shown = HTMLElement | 'prompt' | null;

function ghostFor( ghosts: HTMLElement[], unit: Unit ): Shown {
	const picked = selectedSizes();
	if ( picked.length === 0 ) {
		return 'prompt';
	}
	return (
		ghosts.find(
			( ghost ) =>
				ghost.dataset.unit === unit &&
				picked.includes( sizeKey( ghost.dataset.size ?? '' ) )
		) ?? null
	);
}

store( 'aggressive-apparel/product-specs', {
	callbacks: {
		initFooter() {
			const { ref } = getElement();
			if ( ! ( ref instanceof HTMLElement ) ) {
				return;
			}

			// Wired here, not with data-wp-on: kses strips that in tab content.
			const link = ref.querySelector< HTMLElement >(
				'.aa-product-specs__guide-link'
			);
			link?.addEventListener( 'click', () => openSizeGuide( link ) );

			const fit = ref.querySelector< HTMLElement >(
				'.aa-product-specs__fit'
			);
			const ghosts = [ ...ref.querySelectorAll< HTMLElement >( GHOST ) ];
			const lines = ghosts.filter( ( ghost ) => ghost.dataset.size );
			if ( ! fit || lines.length === 0 ) {
				return;
			}

			// The server's prompt stays until a pick changes it, so nothing is
			// announced on load.
			const prompt =
				ghosts.find( ( ghost ) => ! ghost.dataset.size )?.textContent ??
				'';
			let unit = preferredUnit();
			let frame = 0;
			let shown: Shown = 'prompt';

			const update = () => {
				const line = ghostFor( lines, unit );
				if ( line === shown ) {
					return;
				}
				shown = line;
				if ( line === 'prompt' ) {
					fit.textContent = prompt;
				} else if ( line === null ) {
					fit.textContent = '';
				} else {
					fit.replaceChildren( ...line.cloneNode( true ).childNodes );
				}
			};
			// Variation pickers update their own markup after the event, so read
			// the pick a frame later, once their render has landed.
			const schedule = () => {
				cancelAnimationFrame( frame );
				frame = requestAnimationFrame( () => {
					frame = requestAnimationFrame( update );
				} );
			};

			document.addEventListener( 'change', schedule );
			document.addEventListener( 'click', schedule );
			document.addEventListener( UNIT_EVENT, ( event ) => {
				unit = ( event as CustomEvent< Unit > ).detail;
				update();
			} );
			update();
		},
	},
} );
