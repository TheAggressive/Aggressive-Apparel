/**
 * Lookbook Block — popover.
 *
 * Renders the product card / message into the popover and keeps it inside
 * the viewport.
 *
 * @package Aggressive_Apparel
 */

import type { LookbookI18n, StoreApiProduct } from './types';
import { formatPrice, safeMediaUrl, safeUrl, stripTags } from './product';

const VIEWPORT_PADDING = 12;
let viewportListenersAttached = false;

function getViewportBounds() {
	const viewport = window.visualViewport;
	const left = viewport?.offsetLeft ?? 0;
	const top = viewport?.offsetTop ?? 0;
	const width = viewport?.width ?? document.documentElement.clientWidth;
	const height = viewport?.height ?? document.documentElement.clientHeight;

	return {
		left: left + VIEWPORT_PADDING,
		top: top + VIEWPORT_PADDING,
		right: left + width - VIEWPORT_PADDING,
		bottom: top + height - VIEWPORT_PADDING,
	};
}

function resetPopoverPlacement( popover: HTMLElement ): void {
	popover.classList.remove( 'is-below' );
	popover.style.setProperty( '--aa-lookbook-popover-shift-x', '0px' );
}

function applyPopoverEdgeDetection( popover: HTMLElement ): void {
	if ( popover.hidden ) {
		return;
	}

	const bounds = getViewportBounds();
	resetPopoverPlacement( popover );

	let rect = popover.getBoundingClientRect();
	if ( rect.top < bounds.top ) {
		popover.classList.add( 'is-below' );
		rect = popover.getBoundingClientRect();
	}

	let shiftX = 0;
	if ( rect.left < bounds.left ) {
		shiftX = bounds.left - rect.left;
	} else if ( rect.right > bounds.right ) {
		shiftX = bounds.right - rect.right;
	}

	popover.style.setProperty(
		'--aa-lookbook-popover-shift-x',
		`${ Math.round( shiftX ) }px`
	);
}

export function schedulePopoverPlacement( popover: HTMLElement ): void {
	window.requestAnimationFrame( () => applyPopoverEdgeDetection( popover ) );
}

function scheduleRootPopoverPlacement( root: HTMLElement ): void {
	const popover = root.querySelector< HTMLElement >(
		'.aggressive-apparel-lookbook__popover'
	);

	if ( popover ) {
		schedulePopoverPlacement( popover );
	}
}

function refreshVisiblePopovers(): void {
	document
		.querySelectorAll< HTMLElement >(
			'.aggressive-apparel-lookbook__popover'
		)
		.forEach( ( popover ) => schedulePopoverPlacement( popover ) );
}

export function attachViewportListeners(): void {
	if ( viewportListenersAttached ) {
		return;
	}

	viewportListenersAttached = true;
	window.addEventListener( 'resize', refreshVisiblePopovers );
	window.visualViewport?.addEventListener( 'resize', refreshVisiblePopovers );
	window.visualViewport?.addEventListener( 'scroll', refreshVisiblePopovers );
}

export function clearPopover( root: HTMLElement ): void {
	const content = root.querySelector< HTMLElement >(
		'.aggressive-apparel-lookbook__popover-content'
	);

	if ( content ) {
		content.replaceChildren();
	}
}

export function renderMessage(
	root: HTMLElement,
	message: string,
	focusCard = false
): void {
	const content = root.querySelector< HTMLElement >(
		'.aggressive-apparel-lookbook__popover-content'
	);

	if ( ! content ) {
		return;
	}

	const card = document.createElement( 'div' );
	card.className =
		'aggressive-apparel-lookbook__product-card aggressive-apparel-lookbook__product-card--message';

	const paragraph = document.createElement( 'p' );
	paragraph.className = 'aggressive-apparel-lookbook__message';
	paragraph.textContent = message;
	card.appendChild( paragraph );
	content.replaceChildren( card );

	if ( focusCard ) {
		// preventScroll: a scroll jump here would disturb the popover's entry
		// animation and the user's place on the page.
		card.tabIndex = -1;
		card.focus( { preventScroll: true } );
	}

	scheduleRootPopoverPlacement( root );
}

export function renderLoading( root: HTMLElement, label: string ): void {
	const content = root.querySelector< HTMLElement >(
		'.aggressive-apparel-lookbook__popover-content'
	);

	if ( ! content ) {
		return;
	}

	const loading = document.createElement( 'div' );
	loading.className =
		'aggressive-apparel-lookbook__product-card aggressive-apparel-lookbook__product-card--loading';
	loading.setAttribute( 'role', 'status' );
	loading.setAttribute( 'aria-live', 'polite' );
	loading.setAttribute( 'aria-atomic', 'true' );

	const spinner = document.createElement( 'span' );
	spinner.className = 'aggressive-apparel-lookbook__spinner';
	spinner.setAttribute( 'aria-hidden', 'true' );
	loading.appendChild( spinner );

	const labelEl = document.createElement( 'span' );
	labelEl.className = 'screen-reader-text';
	labelEl.textContent = label;
	loading.appendChild( labelEl );

	content.replaceChildren( loading );
	scheduleRootPopoverPlacement( root );
}

export function renderProduct(
	root: HTMLElement,
	product: StoreApiProduct,
	i18n: LookbookI18n,
	focusCard = false
): void {
	const content = root.querySelector< HTMLElement >(
		'.aggressive-apparel-lookbook__popover-content'
	);

	if ( ! content ) {
		return;
	}

	const name = product.name ?? '';
	const price =
		formatPrice( product.prices ) ||
		stripTags( product.price_html ?? '' ) ||
		String( product.price ?? '' );
	const image = product.images?.[ 0 ];
	const imageSrc = safeMediaUrl( image?.src );
	const link = safeUrl( product.permalink, '#' );

	const card = document.createElement( 'a' );
	card.className =
		'aggressive-apparel-lookbook__product-card aggressive-apparel-lookbook__product-card--link';
	card.href = link;
	card.setAttribute(
		'aria-label',
		name ? i18n.viewProductName.replace( '%s', name ) : i18n.viewProduct
	);

	if ( imageSrc ) {
		const img = document.createElement( 'img' );
		img.className = 'aggressive-apparel-lookbook__product-image';
		img.src = imageSrc;
		img.alt = image?.alt || name;
		card.appendChild( img );
	}

	const info = document.createElement( 'div' );
	info.className = 'aggressive-apparel-lookbook__product-info';

	const productName = document.createElement( 'p' );
	productName.className = 'aggressive-apparel-lookbook__product-name';
	productName.textContent = name;
	info.appendChild( productName );

	if ( price ) {
		const productPrice = document.createElement( 'p' );
		productPrice.className = 'aggressive-apparel-lookbook__product-price';
		productPrice.textContent = price;
		info.appendChild( productPrice );
	}

	card.appendChild( info );

	const action = document.createElement( 'span' );
	action.className = 'aggressive-apparel-lookbook__product-action';
	action.setAttribute( 'aria-hidden', 'true' );

	const chevron = document.createElement( 'span' );
	chevron.className = 'aggressive-apparel-lookbook__product-chevron';
	action.appendChild( chevron );
	card.appendChild( action );

	content.replaceChildren( card );

	if ( focusCard ) {
		card.focus( { preventScroll: true } );
	}

	scheduleRootPopoverPlacement( root );
}
