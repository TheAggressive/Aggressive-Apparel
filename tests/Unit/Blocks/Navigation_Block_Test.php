<?php
/**
 * Unit tests for the Aggressive Apparel Navigation block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 * @since 1.0.0
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test navigation block functionality.
 */
class Navigation_Block_Test extends WP_UnitTestCase {
    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();

        // Ensure blocks are registered
        Blocks::init();
    }

    /**
     * Test that the navigation block is registered.
     */
    public function test_navigation_block_is_registered(): void {
        $this->assertTrue(
            Blocks::is_block_registered('aggressive-apparel/navigation'),
            'Navigation block should be registered'
        );
    }

    /**
     * Test navigation block renders with correct wrapper class.
     */
    public function test_navigation_block_renders_with_valid_attributes(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'breakpoint' => 1024,
                'ariaLabel' => 'Main navigation',
            ],
        ]);

        $this->assertStringContainsString(
            'wp-block-aggressive-apparel-navigation',
            $block_content,
            'Block should render with correct wrapper class'
        );

        $this->assertStringContainsString(
            '<nav',
            $block_content,
            'Block should render as nav element'
        );
    }

    /**
     * Test navigation block includes Interactivity API attributes.
     */
    public function test_navigation_block_includes_interactivity_attributes(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'breakpoint' => 1024,
            ],
        ]);

        $this->assertStringContainsString(
            'data-wp-interactive="aggressive-apparel/navigation"',
            $block_content,
            'Block should have interactive namespace'
        );

        $this->assertStringContainsString(
            'data-wp-context=',
            $block_content,
            'Block should have context attribute'
        );

        $this->assertStringContainsString(
            'data-wp-init="callbacks.init"',
            $block_content,
            'Block should have init callback'
        );
    }

    /**
     * Test navigation block includes accessibility attributes.
     */
    public function test_navigation_block_includes_accessibility_attributes(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'ariaLabel' => 'Primary Navigation',
            ],
        ]);

        $this->assertStringContainsString(
            'aria-label="Primary Navigation"',
            $block_content,
            'Navigation should have custom aria-label'
        );

        $this->assertStringContainsString(
            'aria-live="polite"',
            $block_content,
            'Navigation should have screen reader announcer'
        );

        $this->assertStringContainsString(
            'screen-reader-text',
            $block_content,
            'Navigation should have screen reader text element'
        );
    }

    /**
     * Test navigation block uses default aria-label when not provided.
     */
    public function test_navigation_block_default_aria_label(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertStringContainsString(
            'aria-label="Main navigation"',
            $block_content,
            'Navigation should use default aria-label'
        );
    }

    /**
     * Test navigation block includes breakpoint CSS variable.
     */
    public function test_navigation_block_includes_breakpoint_style(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'breakpoint' => 768,
            ],
        ]);

        $this->assertStringContainsString(
            '--navigation-breakpoint: 768px',
            $block_content,
            'Block should include breakpoint CSS variable'
        );
    }

    /**
     * Test navigation block default breakpoint.
     */
    public function test_navigation_block_default_breakpoint(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertStringContainsString(
            '--navigation-breakpoint: 1024px',
            $block_content,
            'Block should use default 1024px breakpoint'
        );
    }

    /**
     * Test navigation block context includes expected values.
     */
    public function test_navigation_block_context_structure(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'breakpoint' => 768,
                'openOn' => 'click',
            ],
        ]);

        // Check context contains expected keys (URL-encoded JSON)
        $this->assertStringContainsString(
            '&quot;breakpoint&quot;:768',
            $block_content,
            'Context should include breakpoint'
        );

        $this->assertStringContainsString(
            '&quot;openOn&quot;:&quot;click&quot;',
            $block_content,
            'Context should include openOn value'
        );

        // isOpen and isMobile are now in state._panels[navId], not in context.
        // Context only contains immutable config: navId, breakpoint, openOn.
        $this->assertStringNotContainsString(
            '&quot;isOpen&quot;',
            $block_content,
            'Mutable state should not be in context (moved to state._panels)'
        );
    }

    /**
     * Test navigation block with custom navId.
     */
    public function test_navigation_block_with_custom_nav_id(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'my-custom-nav',
            ],
        ]);

        $this->assertStringContainsString(
            'id="my-custom-nav"',
            $block_content,
            'Block should use custom navId as id attribute'
        );

        $this->assertStringContainsString(
            '&quot;navId&quot;:&quot;my-custom-nav&quot;',
            $block_content,
            'Context should include custom navId'
        );
    }

    /**
     * Test navigation block includes state binding classes.
     */
    public function test_navigation_block_includes_state_bindings(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertStringContainsString(
            'data-wp-class--is-open="state.isOpen"',
            $block_content,
            'Block should bind is-open class to state'
        );

        $this->assertStringContainsString(
            'data-wp-class--is-mobile="state.isMobile"',
            $block_content,
            'Block should bind is-mobile class to state'
        );
    }

    /**
     * Test navigation block includes window event handlers.
     */
    public function test_navigation_block_includes_event_handlers(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertStringContainsString(
            'data-wp-on-window--keydown="callbacks.onEscape"',
            $block_content,
            'Block should handle window keydown for escape'
        );

        $this->assertStringContainsString(
            'data-wp-on-window--aa-nav-state-change="callbacks.onStateChange"',
            $block_content,
            'Block should handle custom state change event'
        );
    }

    /**
     * Test navigation block caching with same navId.
     */
    public function test_navigation_block_caching(): void {
        // Use a fixed navId to ensure consistent output between renders
        $fixed_nav_id = 'cache-test-nav';

        // First render
        $block_content_1 = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => $fixed_nav_id,
            ],
        ]);

        // Second render (should produce identical output with same navId)
        $block_content_2 = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => $fixed_nav_id,
            ],
        ]);

        $this->assertEquals(
            $block_content_1,
            $block_content_2,
            'Identical attributes should produce identical output'
        );
    }

    /**
     * Test navigation block announcer element.
     */
    public function test_navigation_block_announcer_element(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'test-nav',
            ],
        ]);

        $this->assertStringContainsString(
            'id="navigation-announcer-test-nav"',
            $block_content,
            'Announcer should have correct ID based on navId'
        );

        $this->assertStringContainsString(
            'aria-atomic="true"',
            $block_content,
            'Announcer should have aria-atomic attribute'
        );
    }

    /**
     * Test navigation block openOn attribute.
     */
    public function test_navigation_block_open_on_attribute(): void {
        // Test hover (default)
        $block_content_hover = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'openOn' => 'hover',
            ],
        ]);

        $this->assertStringContainsString(
            '&quot;openOn&quot;:&quot;hover&quot;',
            $block_content_hover,
            'Context should include openOn=hover'
        );

        // Test click
        $block_content_click = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'openOn' => 'click',
            ],
        ]);

        $this->assertStringContainsString(
            '&quot;openOn&quot;:&quot;click&quot;',
            $block_content_click,
            'Context should include openOn=click'
        );
    }
}
