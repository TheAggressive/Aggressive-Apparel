/**
 * Main Theme JavaScript
 *
 * @package
 */

// Import styles (will be extracted to separate CSS file)
import '../styles/main.css';

/**
 * Document ready
 */
document.addEventListener( 'DOMContentLoaded', () => {
  console.log( 'Aggressive Apparel Theme Loaded' );

  // Initialize theme features here
  initTheme();
} );

/**
 * Initialize theme
 */
function initTheme() {
  // Mobile menu toggle
  initMobileMenu();

  // Smooth scroll
  initSmoothScroll();
}

/**
 * Initialize mobile menu
 */
function initMobileMenu() {
  const menuToggle = document.querySelector( '.mobile-menu-toggle' );
  const menu = document.querySelector( '.primary-navigation' );

  if ( menuToggle && menu ) {
    menuToggle.addEventListener( 'click', () => {
      menu.classList.toggle( 'is-open' );
      menuToggle.setAttribute(
        'aria-expanded',
        menu.classList.contains( 'is-open' )
      );
    } );
  }
}

/**
 * Initialize smooth scroll
 */
function initSmoothScroll() {
  document.querySelectorAll( 'a[href^="#"]' ).forEach( anchor => {
    anchor.addEventListener( 'click', function ( e ) {
      const href = this.getAttribute( 'href' );
      if ( href === '#' ) {
        return;
      }

      const target = document.querySelector( href );
      if ( target ) {
        e.preventDefault();
        target.scrollIntoView( {
          behavior: 'smooth',
          block: 'start',
        } );
      }
    } );
  } );
}
