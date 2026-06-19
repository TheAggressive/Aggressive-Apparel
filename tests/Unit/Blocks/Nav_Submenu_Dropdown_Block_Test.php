<?php
/**
 * Unit tests for the Aggressive Apparel Nav Dropdown block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test the nav-submenu-dropdown block render output.
 */
class Nav_Submenu_Dropdown_Block_Test extends WP_UnitTestCase {
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
                'blockName'    => 'aggressive-apparel/nav-submenu-dropdown',
                'attrs'        => $attrs,
                'innerBlocks'  => [],
                'innerContent' => [],
            ]
        );
    }

    public function test_block_is_registered(): void {
        $this->assertTrue(
            Blocks::is_block_registered( 'aggressive-apparel/nav-submenu-dropdown' )
        );
    }

    public function test_trigger_toggles_submenu_on_the_desktop_store(): void {
        $html = $this->render(
            [
                'label'     => 'Shop',
                'submenuId' => 'dropdown-shop',
            ]
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

    public function test_wrapper_modifier_and_open_state(): void {
        $html = $this->render( [ 'label' => 'Shop', 'submenuId' => 'dropdown-shop' ] );
        $this->assertStringContainsString(
            'wp-block-aggressive-apparel-nav-submenu--dropdown',
            $html
        );
        $this->assertStringContainsString(
            'data-wp-class--is-open="callbacks.isSubmenuOpen"',
            $html
        );
    }

    public function test_panel_is_a_menu(): void {
        $html = $this->render( [ 'label' => 'Shop', 'submenuId' => 'dropdown-shop' ] );
        $this->assertMatchesRegularExpression(
            '/<div class="wp-block-aggressive-apparel-nav-submenu__panel" id="dropdown-shop"/',
            $html
        );
        $this->assertStringContainsString( 'role="menu"', $html );
    }

    public function test_escapes_label(): void {
        $html = $this->render(
            [
                'label'     => '<script>x</script>Shop',
                'submenuId' => 'dropdown-x',
            ]
        );
        $this->assertStringNotContainsString( '<script>x</script>', $html );
    }
}
