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
}
