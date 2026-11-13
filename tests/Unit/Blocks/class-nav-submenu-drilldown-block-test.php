<?php
/**
 * Unit tests for the Aggressive Apparel Nav Drilldown block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);


namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test the nav-submenu-drilldown block render output.
 */
class Nav_Submenu_Drilldown_Block_Test extends WP_UnitTestCase {
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
	 * Render the block with the given attributes.
	 *
	 * @param array<string,mixed> $attrs Block attributes.
	 * @return string Rendered HTML.
	 */
	private function render( array $attrs ): string {
		return render_block(
			array(
				'blockName'    => 'aggressive-apparel/nav-submenu-drilldown',
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
			Blocks::is_block_registered( 'aggressive-apparel/nav-submenu-drilldown' ),
			'Drilldown block should be registered'
		);
	}

	/**
	 * Verify the trigger has menu item semantics.
	 *
	 * @return void
	 */
	public function test_trigger_has_menuitem_semantics(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'submenuId' => 'dd-shop',
			)
		);

		// No URL → trigger is a button.
		$this->assertStringContainsString( '<button', $html );
		$this->assertStringContainsString( 'role="menuitem"', $html );
		$this->assertStringContainsString( 'aria-haspopup="menu"', $html );
		$this->assertStringContainsString( 'aria-controls="dd-shop"', $html );
		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.drillInto"', $html );
	}

	/**
	 * Verify the panel and inner menu roles.
	 *
	 * @return void
	 */
	public function test_panel_and_inner_menu_roles(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'submenuId' => 'dd-shop',
			)
		);

		// Panel is a labelled group (not a landmark region) containing a menu.
		$this->assertMatchesRegularExpression(
			'/<div[^>]*id="dd-shop"[^>]*role="group"/',
			$html,
			'Panel should be a role=group with the submenu id'
		);
		$this->assertStringContainsString( 'role="menu"', $html );
	}

	/**
	 * Verify the back button drills back and has a screen reader label.
	 *
	 * @return void
	 */
	public function test_back_button_drills_back_with_sr_label(): void {
		$html = $this->render(
			array(
				'label'     => 'Shop',
				'submenuId' => 'dd-shop',
			)
		);

		$this->assertStringContainsString( 'data-wp-on--click="actions.drillBack"', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
		$this->assertStringContainsString( 'Back from Shop', $html );
	}

	/**
	 * Verify the animation style modifier class is applied.
	 *
	 * @return void
	 */
	public function test_animation_style_modifier_class(): void {
		$overlay = $this->render(
			array(
				'label'          => 'Shop',
				'animationStyle' => 'overlay',
			) 
		);
		$this->assertStringContainsString(
			'wp-block-aggressive-apparel-nav-submenu-drilldown--overlay',
			$overlay
		);

		$push = $this->render(
			array(
				'label'          => 'Shop',
				'animationStyle' => 'push',
			) 
		);
		$this->assertStringContainsString(
			'wp-block-aggressive-apparel-nav-submenu-drilldown--push',
			$push
		);
	}

	/**
	 * Verify the view-all link is rendered only when a URL is set.
	 *
	 * @return void
	 */
	public function test_view_all_link_present_only_with_url(): void {
		$with_url = $this->render(
			array(
				'label' => 'Shop',
				'url'   => 'https://example.com/shop',
			)
		);
		$this->assertStringContainsString( '__view-all', $with_url );
		$this->assertStringContainsString( 'View all in Shop', $with_url );
		$this->assertStringContainsString( 'href="https://example.com/shop"', $with_url );
		// With a URL the trigger itself is a link.
		$this->assertStringContainsString( '<a ', $with_url );

		$without_url = $this->render( array( 'label' => 'Shop' ) );
		$this->assertStringNotContainsString( '__view-all', $without_url );
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
				'submenuId' => 'dd-x',
			)
		);
		$this->assertStringNotContainsString( '<script>x</script>', $html );
	}

	/**
	 * Links and other blocks render in editor order: the view-all list, a
	 * content section, then the next list. Block spacing becomes a property.
	 */
	public function test_renders_links_and_content_in_editor_order(): void {
		$html = do_blocks(
			'<!-- wp:aggressive-apparel/nav-submenu-drilldown {"label":"Shop","url":"/shop/","submenuId":"drilldown-shop","style":{"spacing":{"blockGap":"var:preset|spacing|2"}}} -->'
			. '<!-- wp:core/paragraph --><p>Promo</p><!-- /wp:core/paragraph -->'
			. '<!-- wp:aggressive-apparel/nav-link {"label":"Tees","url":"/tees/"} /-->'
			. '<!-- /wp:aggressive-apparel/nav-submenu-drilldown -->'
		);

		$view_all = strpos( $html, 'View all in Shop' );
		$section  = strpos( $html, '<div class="wp-block-aggressive-apparel-nav-submenu-drilldown__panel-section"><p' );
		$tees     = strpos( $html, 'Tees' );

		$this->assertIsInt( $view_all );
		$this->assertIsInt( $section );
		$this->assertIsInt( $tees );
		$this->assertLessThan( $section, $view_all );
		$this->assertLessThan( $tees, $section );
		$this->assertStringContainsString( 'Promo</p></div>', substr( $html, $section, $tees - $section ) );
		$this->assertSame( 2, substr_count( $html, 'class="wp-block-aggressive-apparel-nav-submenu-drilldown__panel-inner" role="menu"' ) );
		$this->assertStringContainsString( '--aa-nav-submenu-gap: var(--wp--preset--spacing--2)', $html );
	}
}
