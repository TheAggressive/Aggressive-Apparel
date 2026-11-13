<?php
/**
 * Unit tests for the Aggressive Apparel Nav Dropdown block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);


namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test the nav-submenu-dropdown block render output.
 */
class Nav_Submenu_Dropdown_Block_Test extends WP_UnitTestCase {
	/**
	 * Set up the test fixture and register the blocks.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Blocks::init();
	}

	/**
	 * Render the dropdown block with the given attributes.
	 *
	 * @param array<string,mixed> $attrs Block attributes.
	 * @return string
	 */
	private function render( array $attrs ): string {
		return render_block(
			array(
				'blockName'    => 'aggressive-apparel/nav-submenu-dropdown',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Verify the block is registered.
	 *
	 * @return void
	 */
	public function test_block_is_registered(): void {
		$this->assertTrue(
			Blocks::is_block_registered( 'aggressive-apparel/nav-submenu-dropdown' )
		);
	}

	/**
	 * Verify the trigger toggles the submenu on the desktop store.
	 *
	 * @return void
	 */
	public function test_trigger_toggles_submenu_on_the_desktop_store(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'submenuId' => 'dropdown-shop',
			)
		);

		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/navigation"', $html );
		$this->assertStringContainsString( 'role="menuitem"', $html );
		$this->assertStringContainsString( 'aria-haspopup="menu"', $html );
		$this->assertStringContainsString( 'aria-controls="dropdown-shop"', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.toggleSubmenu"', $html );
		$this->assertStringContainsString(
			'data-wp-bind--aria-expanded="callbacks.isSubmenuOpen"',
			$html
		);
	}

	/**
	 * Verify the wrapper modifier class and open state.
	 *
	 * @return void
	 */
	public function test_wrapper_modifier_and_open_state(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'submenuId' => 'dropdown-shop',
			) 
		);
		$this->assertStringContainsString(
			'wp-block-aggressive-apparel-nav-submenu--dropdown',
			$html
		);
		$this->assertStringContainsString(
			'data-wp-class--is-open="callbacks.isSubmenuOpen"',
			$html
		);
	}

	/**
	 * Verify the panel is rendered as a menu.
	 *
	 * @return void
	 */
	public function test_panel_is_a_menu(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'submenuId' => 'dropdown-shop',
			) 
		);
		$this->assertMatchesRegularExpression(
			'/<div class="wp-block-aggressive-apparel-nav-submenu__panel" id="dropdown-shop"/',
			$html
		);
		$this->assertStringContainsString( 'role="menu"', $html );
	}

	/**
	 * Verify the parent URL is kept as a menu item.
	 *
	 * @return void
	 */
	public function test_parent_url_remains_available_as_a_menu_item(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'url'       => '/shop/',
				'submenuId' => 'dropdown-shop',
			)
		);

		$this->assertStringContainsString(
			'<a class="wp-block-aggressive-apparel-nav-submenu__view-all" href="/shop/" role="menuitem">View all in Shop</a>',
			$html
		);
	}

	/**
	 * Verify the label is escaped in the markup.
	 *
	 * @return void
	 */
	public function test_escapes_label(): void {
		$html = $this->render(
			array(
				'label'     => '<script>x</script>Shop',
				'submenuId' => 'dropdown-x',
			)
		);
		$this->assertStringNotContainsString( '<script>x</script>', $html );
	}
}
