<?php
/**
 * Unit tests for the Aggressive Apparel Nav Mega Menu block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);


namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test the nav-submenu-mega block render output.
 */
class Nav_Submenu_Mega_Block_Test extends WP_UnitTestCase {
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
	 * Render the mega block with the given attributes.
	 *
	 * @param array<string,mixed> $attrs Block attributes.
	 * @return string
	 */
	private function render( array $attrs ): string {
		return render_block(
			array(
				'blockName'    => 'aggressive-apparel/nav-submenu-mega',
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
			Blocks::is_block_registered( 'aggressive-apparel/nav-submenu-mega' )
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
				'submenuId' => 'mega-shop',
			)
		);

		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/navigation"', $html );
		$this->assertStringContainsString( 'role="menuitem"', $html );
		$this->assertStringNotContainsString( 'aria-haspopup="menu"', $html );
		$this->assertStringContainsString( 'aria-controls="mega-shop"', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.toggleSubmenu"', $html );
		$this->assertStringContainsString(
			'data-wp-bind--aria-expanded="callbacks.isSubmenuOpen"',
			$html
		);
	}

	/**
	 * Verify the wrapper modifier class is applied.
	 *
	 * @return void
	 */
	public function test_wrapper_modifier_class(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'submenuId' => 'mega-shop',
			) 
		);
		$this->assertStringContainsString(
			'wp-block-aggressive-apparel-nav-submenu--mega',
			$html
		);
	}

	/**
	 * Verify the mega panel is a labelled region rather than a menu.
	 *
	 * @return void
	 */
	public function test_panel_is_a_region_not_a_menu(): void {
		// The mega panel holds arbitrary inner blocks, so it's a labelled region
		// rather than a role=menu (which would impose menuitem semantics).
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'submenuId' => 'mega-shop',
			) 
		);
		$this->assertMatchesRegularExpression(
			'/<div class="wp-block-aggressive-apparel-nav-submenu__panel" id="mega-shop"[^>]*role="region"/',
			$html
		);
		$this->assertStringContainsString(
			'<div class="wp-block-aggressive-apparel-nav-submenu__panel-inner">',
			$html
		);
		$this->assertStringNotContainsString(
			'<ul class="wp-block-aggressive-apparel-nav-submenu__panel-inner">',
			$html
		);
	}

	/**
	 * Verify the parent URL remains available inside the region.
	 *
	 * @return void
	 */
	public function test_parent_url_remains_available_inside_the_region(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'url'       => '/shop/',
				'submenuId' => 'mega-shop',
			)
		);

		$this->assertStringContainsString( 'href="/shop/"', $html );
		$this->assertStringContainsString( 'View all in Shop', $html );
	}

	/**
	 * Verify nested nav links use markup that is safe inside a region.
	 *
	 * @return void
	 */
	public function test_nested_nav_links_use_region_safe_markup(): void {
		$html = do_blocks(
			'<!-- wp:aggressive-apparel/nav-submenu-mega {"label":"Shop","submenuId":"mega-shop"} -->'
			. '<!-- wp:aggressive-apparel/nav-link {"label":"Featured","url":"/shop/"} /-->'
			. '<!-- /wp:aggressive-apparel/nav-submenu-mega -->'
		);

		$this->assertMatchesRegularExpression(
			'/<div class="wp-block-aggressive-apparel-nav-submenu__panel-inner">'
			. '<div class="wp-block-aggressive-apparel-nav-link/',
			$html
		);
		$this->assertStringNotContainsString(
			'<li class="wp-block-aggressive-apparel-nav-link',
			$html
		);
		$this->assertStringContainsString(
			'<a class="wp-block-aggressive-apparel-nav-link__link" href="/shop/">',
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
				'submenuId' => 'mega-x',
			)
		);
		$this->assertStringNotContainsString( '<script>x</script>', $html );
	}
}
