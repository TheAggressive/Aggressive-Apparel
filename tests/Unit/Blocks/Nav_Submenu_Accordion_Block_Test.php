<?php
/**
 * Unit tests for the Aggressive Apparel Nav Accordion block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test the nav-submenu-accordion block render output.
 */
class Nav_Submenu_Accordion_Block_Test extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        Blocks::init();
    }

    /**
     * @param array<string,mixed> $attrs Block attributes.
     */
    private function render( array $attrs ): string {
        return render_block(
            [
                'blockName'    => 'aggressive-apparel/nav-submenu-accordion',
                'attrs'        => $attrs,
                'innerBlocks'  => [],
                'innerContent' => [],
            ]
        );
    }

    public function test_block_is_registered(): void {
        $this->assertTrue(
            Blocks::is_block_registered( 'aggressive-apparel/nav-submenu-accordion' )
        );
    }

    public function test_trigger_toggles_submenu_with_aria(): void {
        $html = $this->render(
            [
                'label'     => 'Shop',
                'submenuId' => 'acc-shop',
            ]
        );

        $this->assertStringContainsString( 'role="menuitem"', $html );
        $this->assertStringContainsString( 'aria-controls="acc-shop"', $html );
        $this->assertStringContainsString( 'data-wp-on--click="actions.toggleSubmenu"', $html );
        // aria-expanded is bound to the store. Server-side directive processing
        // resolves the initial value from the binding (the static
        // aria-expanded="false" is consumed), so assert the binding directive.
        $this->assertStringContainsString(
            'data-wp-bind--aria-expanded="callbacks.isSubmenuOpen"',
            $html
        );
    }

    public function test_open_state_class_bound_to_callback(): void {
        $html = $this->render( [ 'label' => 'Shop', 'submenuId' => 'acc-shop' ] );
        $this->assertStringContainsString(
            'data-wp-class--is-open="callbacks.isSubmenuOpen"',
            $html
        );
    }

    public function test_panel_contains_a_menu(): void {
        $html = $this->render( [ 'label' => 'Shop', 'submenuId' => 'acc-shop' ] );
        $this->assertMatchesRegularExpression(
            '/<div class="wp-block-aggressive-apparel-nav-submenu-accordion__panel" id="acc-shop"/',
            $html
        );
        $this->assertStringContainsString( 'role="menu"', $html );
    }

    public function test_escapes_label(): void {
        $html = $this->render(
            [
                'label'     => '<script>x</script>Shop',
                'submenuId' => 'acc-x',
            ]
        );
        $this->assertStringNotContainsString( '<script>x</script>', $html );
    }
}
