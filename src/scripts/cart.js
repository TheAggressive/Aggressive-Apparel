/**
 * WooCommerce Cart Scripts
 *
 * @package
 */

/* global aggressiveApparelData */

/**
 * Initialize cart functionality
 */
document.addEventListener( 'DOMContentLoaded', () => {
  if (
    typeof aggressiveApparelData === 'undefined' ||
    ! aggressiveApparelData.isWooCommerce
  ) {
    return;
  }

  initMiniCart();
  updateCartFragments();
} );

/**
 * Initialize mini cart
 */
function initMiniCart() {
  const cartToggle = document.querySelector( '.mini-cart-toggle' );
  const miniCart = document.querySelector( '.mini-cart' );

  if ( ! cartToggle || ! miniCart ) {
    return;
  }

  cartToggle.addEventListener( 'click', e => {
    e.preventDefault();
    miniCart.classList.toggle( 'is-open' );
    document.body.classList.toggle( 'mini-cart-open' );
  } );

  // Close on outside click
  document.addEventListener( 'click', e => {
    if (
      ! miniCart.contains( e.target ) &&
      ! cartToggle.contains( e.target )
    ) {
      miniCart.classList.remove( 'is-open' );
      document.body.classList.remove( 'mini-cart-open' );
    }
  } );

  // Close on escape key
  document.addEventListener( 'keydown', e => {
    if ( e.key === 'Escape' && miniCart.classList.contains( 'is-open' ) ) {
      miniCart.classList.remove( 'is-open' );
      document.body.classList.remove( 'mini-cart-open' );
    }
  } );
}

/**
 * Update cart fragments on add to cart
 */
function updateCartFragments() {
  jQuery( document.body ).on( 'added_to_cart', ( event, fragments, hash ) => {
    // Update cart count
    if ( fragments && fragments[ 'span.cart-count' ] ) {
      const cartCounts = document.querySelectorAll( '.cart-count' );
      cartCounts.forEach( element => {
        element.textContent = fragments[ 'span.cart-count' ];
      } );
    }

    // Show mini cart
    const miniCart = document.querySelector( '.mini-cart' );
    if ( miniCart ) {
      miniCart.classList.add( 'is-open' );
      document.body.classList.add( 'mini-cart-open' );
    }
  } );
}
