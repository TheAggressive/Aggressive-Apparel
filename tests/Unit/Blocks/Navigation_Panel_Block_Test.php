<?php
/**
 * Unit tests for the Aggressive Apparel Navigation Panel (mobile drawer) block.
 *
 * The block renders nothing inline — it buffers its markup and portals it to
 * wp_footer (so position:fixed escapes ancestor stacking contexts). These tests
 * render the block, then capture the wp_footer output to assert on the portal.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);


namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test the navigation-panel block portal output.
 */
class Navigation_Panel_Block_Test extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        Blocks::init();
    }

    public function test_block_is_registered(): void {
        $this->assertTrue(
            Blocks::is_block_registered( 'aggressive-apparel/navigation-panel' )
        );
    }

    public function test_renders_nothing_inline(): void {
        $inline = render_block(
            [
                'blockName'    => 'aggressive-apparel/navigation-panel',
                'attrs'        => [ 'panelSlug' => 'inline-check' ],
                'innerBlocks'  => [],
                'innerContent' => [],
            ]
        );
        // All markup is portaled to wp_footer, so the inline render is empty.
        $this->assertStringNotContainsString( 'aa-nav__panel', $inline );
    }

    /**
     * The portal wrapper (emitted by the wp_footer flush) wires the panel store.
     *
     * Tested directly via the flush function rather than capturing wp_footer:
     * the buffer registers its footer callback behind a process-static guard,
     * and WP_UnitTestCase resets hooks between tests, so do_action('wp_footer')
     * can't reliably re-emit the panel in the unit env. The flush function is
     * the unit under test for the portal wrapper; the inner dialog markup is
     * exercised end-to-end by the E2E/integration layer.
     */
    public function test_flush_wraps_panel_in_store_bound_portal(): void {
        ob_start();
        aggressive_apparel_flush_nav_panel_blocks(
            [ 'flush-test' => '<div class="aa-nav__panel" role="dialog">x</div>' ]
        );
        $out = (string) ob_get_clean();

        $this->assertStringContainsString( 'aa-nav-panel__portal', $out );
        $this->assertStringContainsString(
            'data-wp-interactive="aggressive-apparel/navigation-panel"',
            $out
        );
        $this->assertStringContainsString( 'data-wp-init="callbacks.initPanel"', $out );
        $this->assertStringContainsString(
            'data-wp-on-window--keydown="callbacks.onEscape"',
            $out
        );
        $this->assertStringContainsString( '&quot;panelSlug&quot;:&quot;flush-test&quot;', $out );
        // The buffered panel markup is wrapped verbatim.
        $this->assertStringContainsString( 'role="dialog"', $out );
    }

    /**
     * Header/footer slots are extracted; everything else keeps editor order,
     * with nav items in menu sections and rich blocks in content sections.
     */
    public function test_partitions_slots_and_keeps_section_order(): void {
        $content = implode(
            '',
            [
                '<div class="wp-block-aggressive-apparel-nav-panel-header"><p>Header</p></div>',
                '<li class="wp-block-aggressive-apparel-nav-link" role="none"><a href="/shop/">Shop</a></li>',
                '<div class="wp-block-buttons"><a class="wp-block-button__link" href="/account/">Account</a></div>',
                "\n",
                '<li class="wp-block-aggressive-apparel-nav-link" role="none"><a href="/sale/">Sale</a></li>',
                '<li class="wp-block-aggressive-apparel-nav-submenu-drilldown" role="none"><a href="/men/">Men</a></li>',
                '<div class="wp-block-aggressive-apparel-nav-panel-footer"><p>Footer</p></div>',
            ]
        );

        $parts    = aggressive_apparel_partition_nav_panel_content( $content );
        $sections = $parts['sections'];

        $this->assertSame( [ 'menu', 'content', 'menu' ], array_column( $sections, 'type' ) );
        $this->assertStringContainsString( 'Shop', $sections[0]['html'] );
        $this->assertStringContainsString( 'Account', $sections[1]['html'] );
        $this->assertStringContainsString( 'Sale', $sections[2]['html'] );
        $this->assertStringContainsString( 'Men', $sections[2]['html'] );
        $this->assertStringContainsString( '<p>Header</p>', $parts['panel_header_html'] );
        $this->assertStringContainsString( '<p>Footer</p>', $parts['panel_footer_html'] );
        $this->assertStringContainsString( 'wp-block-aggressive-apparel-nav-panel-header', $parts['panel_header_classes'] );
    }

    /**
     * Menu sections render as lists, content sections as wrappers, in order.
     */
    public function test_renders_sections_in_order(): void {
        $html = aggressive_apparel_render_nav_sections(
            [
                [ 'type' => 'menu', 'html' => '<li>A</li>' ],
                [ 'type' => 'content', 'html' => '<p>B</p>' ],
            ],
            '<ul class="menu">',
            'section'
        );

        $this->assertSame( '<ul class="menu"><li>A</li></ul><div class="section"><p>B</p></div>', $html );
    }

    /**
     * The view-all link leads the first list, starting one before leading content.
     */
    public function test_prepends_view_all_to_leading_menu(): void {
        $content  = [ [ 'type' => 'content', 'html' => '<p>Promo</p>' ] ];
        $sections = aggressive_apparel_prepend_nav_menu_html( $content, '<li>All</li>' );

        $this->assertSame( [ 'menu', 'content' ], array_column( $sections, 'type' ) );
        $this->assertSame( '<li>All</li>', $sections[0]['html'] );
        $this->assertSame( $content, aggressive_apparel_prepend_nav_menu_html( $content, '' ) );
        $this->assertSame( [ [ 'type' => 'menu', 'html' => '' ] ], aggressive_apparel_prepend_nav_menu_html( [], '' ) );
    }

    /**
     * Block spacing resolves presets, takes the vertical axis, and rejects
     * values that could break out of the style attribute.
     */
    public function test_resolves_block_gap(): void {
        $gap = static fn( $value ): string => aggressive_apparel_get_nav_block_gap( [ 'style' => [ 'spacing' => [ 'blockGap' => $value ] ] ] );

        $this->assertSame( 'var(--wp--preset--spacing--20)', $gap( 'var:preset|spacing|20' ) );
        $this->assertSame( '1.5rem', $gap( [ 'top' => '1.5rem', 'left' => '2rem' ] ) );
        $this->assertSame( 'calc(1rem + 2px)', $gap( 'calc(1rem + 2px)' ) );
        $this->assertSame( '', $gap( '1rem; color: red' ) );
        $this->assertSame( '', aggressive_apparel_get_nav_block_gap( [] ) );
    }
}
