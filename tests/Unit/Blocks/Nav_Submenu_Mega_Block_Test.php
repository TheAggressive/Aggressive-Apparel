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
                'blockName'    => 'aggressive-apparel/nav-submenu-mega',
                'attrs'        => $attrs,
                'innerBlocks'  => [],
                'innerContent' => [],
            ]
        );
    }

    public function test_block_is_registered(): void {
        $this->assertTrue(
            Blocks::is_block_registered( 'aggressive-apparel/nav-submenu-mega' )
        );
    }

    public function test_trigger_toggles_submenu_on_the_desktop_store(): void {
        $html = $this->render(
            [
                'label'     => 'Shop',
                'submenuId' => 'mega-shop',
            ]
        );

        $this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/navigation"', $html );
        $this->assertStringContainsString( 'role="menuitem"', $html );
        $this->assertStringContainsString( 'aria-haspopup="menu"', $html );
        $this->assertStringContainsString( 'aria-controls="mega-shop"', $html );
        $this->assertStringContainsString( 'data-wp-on--click="actions.toggleSubmenu"', $html );
        $this->assertStringContainsString(
            'data-wp-bind--aria-expanded="callbacks.isSubmenuOpen"',
            $html
        );
    }

    public function test_wrapper_modifier_class(): void {
        $html = $this->render( [ 'label' => 'Shop', 'submenuId' => 'mega-shop' ] );
        $this->assertStringContainsString(
            'wp-block-aggressive-apparel-nav-submenu--mega',
            $html
        );
    }

    public function test_panel_is_a_region_not_a_menu(): void {
        // The mega panel holds arbitrary inner blocks, so it's a labelled region
        // rather than a role=menu (which would impose menuitem semantics).
        $html = $this->render( [ 'label' => 'Shop', 'submenuId' => 'mega-shop' ] );
        $this->assertMatchesRegularExpression(
            '/<div class="wp-block-aggressive-apparel-nav-submenu__panel" id="mega-shop"[^>]*role="region"/',
            $html
        );
    }

    public function test_escapes_label(): void {
        $html = $this->render(
            [
                'label'     => '<script>x</script>Shop',
                'submenuId' => 'mega-x',
            ]
        );
        $this->assertStringNotContainsString( '<script>x</script>', $html );
    }
}
