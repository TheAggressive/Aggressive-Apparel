/**
 * Navigation Scripts
 *
 * @package
 */

/**
 * Initialize navigation
 */
document.addEventListener( 'DOMContentLoaded', () => {
  initNavigationAccessibility();
  initSubMenus();
} );

/**
 * Initialize navigation accessibility
 */
function initNavigationAccessibility() {
  const nav = document.querySelector( '.wp-block-navigation' );
  if ( ! nav ) {
    return;
  }

  // Handle keyboard navigation
  const menuItems = nav.querySelectorAll( 'a' );
  menuItems.forEach( item => {
    item.addEventListener( 'focus', function () {
      this.closest( 'li' ).classList.add( 'is-focused' );
    } );

    item.addEventListener( 'blur', function () {
      this.closest( 'li' ).classList.remove( 'is-focused' );
    } );
  } );
}

/**
 * Initialize submenu toggles
 */
function initSubMenus() {
  const subMenuParents = document.querySelectorAll(
    '.wp-block-navigation-item.has-child'
  );

  subMenuParents.forEach( parent => {
    const toggle = parent.querySelector(
      '.wp-block-navigation-submenu__toggle'
    );
    if ( ! toggle ) {
      return;
    }

    toggle.addEventListener( 'click', e => {
      e.preventDefault();
      parent.classList.toggle( 'is-open' );
      toggle.setAttribute(
        'aria-expanded',
        parent.classList.contains( 'is-open' )
      );
    } );
  } );
}
