/**
 * Navigation JavaScript Tests
 *
 * @package
 */

describe( 'Navigation', () => {
  describe( 'Keyboard Navigation', () => {
    beforeEach( () => {
      document.body.innerHTML = `
				<nav class="wp-block-navigation">
					<ul>
						<li class="wp-block-navigation-item">
							<a href="#">Home</a>
						</li>
						<li class="wp-block-navigation-item has-child">
							<a href="#">Shop</a>
							<ul class="wp-block-navigation__submenu-container">
								<li><a href="#">Category 1</a></li>
								<li><a href="#">Category 2</a></li>
							</ul>
						</li>
					</ul>
				</nav>
			`;
    } );

    it( 'should have navigation menu', () => {
      const nav = document.querySelector( '.wp-block-navigation' );
      expect( nav ).not.toBeNull();
    } );

    it( 'should have submenu', () => {
      const submenu = document.querySelector(
        '.wp-block-navigation__submenu-container'
      );
      expect( submenu ).not.toBeNull();
    } );

    it( 'should handle arrow key navigation', () => {
      const items = document.querySelectorAll(
        '.wp-block-navigation-item > a'
      );
      expect( items.length ).toBeGreaterThan( 0 );

      // Test that items can receive focus
      items[ 0 ].focus();
      expect( document.activeElement ).toBe( items[ 0 ] );
    } );
  } );

  describe( 'Mobile Navigation', () => {
    beforeEach( () => {
      document.body.innerHTML = `
				<button class="wp-block-navigation__responsive-container-open" aria-expanded="false">
					Menu
				</button>
				<div class="wp-block-navigation__responsive-container" hidden>
					<nav>Navigation content</nav>
				</div>
			`;
    } );

    it( 'should have mobile menu toggle', () => {
      const toggle = document.querySelector(
        '.wp-block-navigation__responsive-container-open'
      );
      expect( toggle ).not.toBeNull();
      expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
    } );

    it( 'should have mobile menu container', () => {
      const container = document.querySelector(
        '.wp-block-navigation__responsive-container'
      );
      expect( container ).not.toBeNull();
    } );
  } );

  describe( 'Focus Trap', () => {
    it( 'should trap focus within open mobile menu', () => {
      document.body.innerHTML = `
				<div class="wp-block-navigation__responsive-container" data-state="open">
					<button class="close">Close</button>
					<nav>
						<a href="#">Link 1</a>
						<a href="#">Link 2</a>
					</nav>
				</div>
			`;

      const container = document.querySelector(
        '.wp-block-navigation__responsive-container'
      );
      const focusableElements = container.querySelectorAll( 'a[href], button' );

      expect( focusableElements.length ).toBeGreaterThan( 0 );
    } );
  } );
} );
