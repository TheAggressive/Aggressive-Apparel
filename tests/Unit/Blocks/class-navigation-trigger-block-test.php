<?php
/**
 * Unit tests for the Aggressive Apparel Navigation Trigger (hamburger) block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);


namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test the navigation-trigger block render output.
 */
class Navigation_Trigger_Block_Test extends WP_UnitTestCase {
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
	 * Render the trigger block with the given attributes.
	 *
	 * @param array<string,mixed> $attrs Block attributes.
	 * @return string
	 */
	private function render( array $attrs = array() ): string {
		return render_block(
			array(
				'blockName'    => 'aggressive-apparel/navigation-trigger',
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
			Blocks::is_block_registered( 'aggressive-apparel/navigation-trigger' )
		);
	}

	/**
	 * Verify the trigger drives the panel store through toggle.
	 *
	 * @return void
	 */
	public function test_drives_the_panel_store_via_toggle(): void {
		$html = $this->render();

		$this->assertStringContainsString(
			'data-wp-interactive="aggressive-apparel/navigation-panel"',
			$html
		);
		$this->assertStringContainsString( 'data-wp-on--click="actions.toggle"', $html );
		$this->assertStringContainsString( 'aa-nav-trigger', $html );
	}

	/**
	 * Verify the ARIA state is bound to the store.
	 *
	 * @return void
	 */
	public function test_aria_state_is_bound_to_store(): void {
		$html = $this->render();

		// Controls a panel and reflects the open state. aria-expanded is bound
		// to the store; server-side directive processing resolves its initial
		// value from the binding (the static aria-expanded="false" is consumed),
		// so assert the binding directive rather than the static attribute.
		$this->assertStringContainsString( 'aria-controls=', $html );
		$this->assertStringContainsString(
			'data-wp-bind--aria-expanded="state.isOpen"',
			$html
		);
		$this->assertStringContainsString( 'data-wp-class--is-active="state.isOpen"', $html );
	}

	/**
	 * Verify the context carries the panel slug.
	 *
	 * @return void
	 */
	public function test_context_carries_the_panel_slug(): void {
		$html = $this->render( array( 'panelSlug' => 'main-menu' ) );
		$this->assertStringContainsString( '&quot;panelSlug&quot;:&quot;main-menu&quot;', $html );
	}
}
