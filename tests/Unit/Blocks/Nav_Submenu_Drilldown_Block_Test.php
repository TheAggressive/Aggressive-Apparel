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
            [
                'blockName'    => 'aggressive-apparel/nav-submenu-drilldown',
                'attrs'        => $attrs,
                'innerBlocks'  => [],
                'innerContent' => [],
            ]
        );
    }

    public function test_block_is_registered(): void {
        $this->assertTrue(
            Blocks::is_block_registered( 'aggressive-apparel/nav-submenu-drilldown' ),
            'Drilldown block should be registered'
        );
    }

    public function test_trigger_has_menuitem_semantics(): void {
        $html = $this->render(
            [
                'label'     => 'Shop',
                'submenuId' => 'dd-shop',
            ]
        );

        // No URL → trigger is a button.
        $this->assertStringContainsString( '<button', $html );
        $this->assertStringContainsString( 'role="menuitem"', $html );
        $this->assertStringContainsString( 'aria-haspopup="menu"', $html );
        $this->assertStringContainsString( 'aria-controls="dd-shop"', $html );
        $this->assertStringContainsString( 'aria-expanded="false"', $html );
        $this->assertStringContainsString( 'data-wp-on--click="actions.drillInto"', $html );
    }

    public function test_panel_and_inner_menu_roles(): void {
        $html = $this->render(
            [
                'label'     => 'Shop',
                'submenuId' => 'dd-shop',
            ]
        );

        // Panel is a labelled group (not a landmark region) containing a menu.
        $this->assertMatchesRegularExpression(
            '/<div[^>]*id="dd-shop"[^>]*role="group"/',
            $html,
            'Panel should be a role=group with the submenu id'
        );
        $this->assertStringContainsString( 'role="menu"', $html );
    }

    public function test_back_button_drills_back_with_sr_label(): void {
        $html = $this->render(
            [
                'label'     => 'Shop',
                'submenuId' => 'dd-shop',
            ]
        );

        $this->assertStringContainsString( 'data-wp-on--click="actions.drillBack"', $html );
        $this->assertStringContainsString( 'screen-reader-text', $html );
        $this->assertStringContainsString( 'Back from Shop', $html );
    }

    public function test_animation_style_modifier_class(): void {
        $overlay = $this->render( [ 'label' => 'Shop', 'animationStyle' => 'overlay' ] );
        $this->assertStringContainsString(
            'wp-block-aggressive-apparel-nav-submenu-drilldown--overlay',
            $overlay
        );

        $push = $this->render( [ 'label' => 'Shop', 'animationStyle' => 'push' ] );
        $this->assertStringContainsString(
            'wp-block-aggressive-apparel-nav-submenu-drilldown--push',
            $push
        );
    }

    public function test_view_all_link_present_only_with_url(): void {
        $with_url = $this->render(
            [
                'label' => 'Shop',
                'url'   => 'https://example.com/shop',
            ]
        );
        $this->assertStringContainsString( '__view-all', $with_url );
        $this->assertStringContainsString( 'View all in Shop', $with_url );
        $this->assertStringContainsString( 'href="https://example.com/shop"', $with_url );
        // With a URL the trigger itself is a link.
        $this->assertStringContainsString( '<a ', $with_url );

        $without_url = $this->render( [ 'label' => 'Shop' ] );
        $this->assertStringNotContainsString( '__view-all', $without_url );
    }

    public function test_escapes_label(): void {
        $html = $this->render(
            [
                'label'     => '<script>x</script>Shop',
                'submenuId' => 'dd-x',
            ]
        );
        $this->assertStringNotContainsString( '<script>x</script>', $html );
    }
}
