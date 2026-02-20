<?php
/**
 * Accessibility tests for the Aggressive Apparel Navigation block.
 *
 * Ensures WCAG 2.1 AA compliance and proper accessibility features.
 *
 * @package Aggressive_Apparel\Tests\Accessibility
 * @since 1.0.0
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace Aggressive_Apparel\Tests\Accessibility;

use WP_UnitTestCase;

/**
 * Test navigation block accessibility compliance.
 */
class Navigation_Block_Accessibility_Test extends WP_UnitTestCase {
    /**
     * Test navigation block has semantic nav element.
     */
    public function test_navigation_block_semantic_element(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        // Test semantic nav element
        $this->assertMatchesRegularExpression(
            '/<nav[^>]+>.*<\/nav>/s',
            $block_content,
            'Block should use semantic <nav> element'
        );
    }

    /**
     * Test navigation block ARIA attributes.
     */
    public function test_navigation_block_aria_attributes(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'ariaLabel' => 'Main Navigation',
            ],
        ]);

        // Test aria-label
        $this->assertStringContainsString(
            'aria-label="Main Navigation"',
            $block_content,
            'Navigation should have aria-label'
        );
    }

    /**
     * Test navigation block default aria-label.
     */
    public function test_navigation_block_default_aria_label(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertStringContainsString(
            'aria-label="Main navigation"',
            $block_content,
            'Navigation should have default aria-label'
        );
    }

    /**
     * Test keyboard navigation support via Interactivity API.
     */
    public function test_keyboard_navigation_support(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        // Verify keyboard navigation event handler
        $this->assertStringContainsString(
            'data-wp-on-window--keydown="callbacks.onEscape"',
            $block_content,
            'Block should handle keyboard events for escape key'
        );
    }

    /**
     * Test screen reader announcer element.
     */
    public function test_screen_reader_announcer(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'sr-test',
            ],
        ]);

        // Test screen reader text class
        $this->assertStringContainsString(
            'screen-reader-text',
            $block_content,
            'Block should have screen reader text element'
        );

        // Test aria-live for announcements
        $this->assertStringContainsString(
            'aria-live="polite"',
            $block_content,
            'Announcer should have aria-live="polite"'
        );

        // Test aria-atomic for complete announcements
        $this->assertStringContainsString(
            'aria-atomic="true"',
            $block_content,
            'Announcer should have aria-atomic="true"'
        );
    }

    /**
     * Test announcer has unique ID based on navId.
     */
    public function test_announcer_unique_id(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'unique-nav',
            ],
        ]);

        $this->assertStringContainsString(
            'id="navigation-announcer-unique-nav"',
            $block_content,
            'Announcer should have unique ID based on navId'
        );
    }

    /**
     * Test state change event handler for accessibility.
     */
    public function test_state_change_handler(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        // Verify state change event handler exists
        $this->assertStringContainsString(
            'data-wp-on-window--aa-nav-state-change="callbacks.onStateChange"',
            $block_content,
            'Block should handle state change events'
        );
    }

    /**
     * Test dynamic class bindings for state.
     */
    public function test_dynamic_state_classes(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        // Test is-open class binding
        $this->assertStringContainsString(
            'data-wp-class--is-open="state.isOpen"',
            $block_content,
            'Block should have is-open class binding'
        );

        // Test is-mobile class binding
        $this->assertStringContainsString(
            'data-wp-class--is-mobile="state.isMobile"',
            $block_content,
            'Block should have is-mobile class binding'
        );
    }

    /**
     * Test navigation context includes accessibility-related config.
     *
     * Mutable state (isOpen, isMobile, activeSubmenuId, drillStack) has been
     * moved to state._panels[navId] so both the <nav> and the portaled panel
     * share reactive state. Context now only holds immutable config.
     */
    public function test_context_accessibility_state(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        // Context should include navId for ARIA relationships
        $this->assertStringContainsString(
            '&quot;navId&quot;',
            $block_content,
            'Context should include navId'
        );

        // aria-expanded is bound via state.isOpen (store-level, not context)
        $this->assertStringContainsString(
            'data-wp-bind--aria-expanded="state.isOpen"',
            $block_content,
            'Toggle should bind aria-expanded to state.isOpen'
        );
    }

    /**
     * Test block has unique ID for ARIA relationships.
     */
    public function test_unique_id_for_aria(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'aria-test-nav',
            ],
        ]);

        $this->assertStringContainsString(
            'id="aria-test-nav"',
            $block_content,
            'Block should have unique ID for ARIA relationships'
        );
    }

    /**
     * Test initialization callback for accessibility setup.
     */
    public function test_init_callback(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertStringContainsString(
            'data-wp-init="callbacks.init"',
            $block_content,
            'Block should have init callback for accessibility setup'
        );
    }

    /**
     * Test navigation wrapper class exists.
     */
    public function test_wrapper_class(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertStringContainsString(
            'wp-block-aggressive-apparel-navigation',
            $block_content,
            'Block should have proper wrapper class for styling hooks'
        );
    }
}
