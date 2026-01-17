<?php
/**
 * Integration tests for the Aggressive Apparel Navigation block.
 *
 * Tests real WordPress functionality and interactions.
 *
 * @package Aggressive_Apparel\Tests\Integration\Blocks
 * @since 1.0.0
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace Aggressive_Apparel\Tests\Integration\Blocks;

use WP_UnitTestCase;

/**
 * Test navigation block integration with WordPress.
 */
class Navigation_Block_Integration_Test extends WP_UnitTestCase {
    /**
     * Test navigation block renders correctly with inner blocks.
     */
    public function test_navigation_block_with_inner_content(): void {
        $inner_content = '<div class="inner-menu">Menu Items</div>';

        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'inner-content-test',
            ],
            'innerContent' => [$inner_content],
        ]);

        $this->assertStringContainsString(
            'wp-block-aggressive-apparel-navigation',
            $block_content,
            'Block should render with correct wrapper class'
        );

        $this->assertStringContainsString(
            'inner-menu',
            $block_content,
            'Block should include inner content'
        );
    }

    /**
     * Test navigation block global context propagation.
     */
    public function test_navigation_block_sets_global_context(): void {
        global $aggressive_apparel_current_nav_id, $aggressive_apparel_current_nav_breakpoint;

        // Reset globals
        $aggressive_apparel_current_nav_id = null;
        $aggressive_apparel_current_nav_breakpoint = null;

        render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'global-context-test',
                'breakpoint' => 768,
            ],
        ]);

        $this->assertEquals(
            'global-context-test',
            $aggressive_apparel_current_nav_id,
            'Global nav ID should be set after rendering'
        );

        $this->assertEquals(
            768,
            $aggressive_apparel_current_nav_breakpoint,
            'Global breakpoint should be set after rendering'
        );
    }

    /**
     * Test navigation block with different breakpoints.
     */
    public function test_navigation_block_breakpoint_variations(): void {
        $breakpoints = [480, 768, 1024, 1280];

        foreach ($breakpoints as $breakpoint) {
            $block_content = render_block([
                'blockName' => 'aggressive-apparel/navigation',
                'attrs' => [
                    'breakpoint' => $breakpoint,
                ],
            ]);

            $this->assertStringContainsString(
                "--navigation-breakpoint: {$breakpoint}px",
                $block_content,
                "Block should include {$breakpoint}px breakpoint"
            );

            $this->assertStringContainsString(
                "&quot;breakpoint&quot;:{$breakpoint}",
                $block_content,
                "Context should include {$breakpoint} breakpoint"
            );
        }
    }

    /**
     * Test navigation block caching behavior.
     */
    public function test_navigation_block_caching(): void {
        // Use a fixed navId to ensure consistent output between renders
        $fixed_nav_id = 'integration-cache-test-nav';

        // First render
        $block_content_1 = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => $fixed_nav_id,
            ],
        ]);

        // Second render (should be cached)
        $block_content_2 = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => $fixed_nav_id,
            ],
        ]);

        // Content should be identical
        $this->assertEquals($block_content_1, $block_content_2);
    }

    /**
     * Test navigation block generates unique IDs when navId not provided.
     */
    public function test_navigation_block_unique_id_generation(): void {
        $block_content_1 = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $block_content_2 = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        // Extract IDs using regex
        preg_match('/id="(nav-\d+)"/', $block_content_1, $matches1);
        preg_match('/id="(nav-\d+)"/', $block_content_2, $matches2);

        $this->assertNotEmpty($matches1[1], 'First block should have an auto-generated ID');
        $this->assertNotEmpty($matches2[1], 'Second block should have an auto-generated ID');
        $this->assertNotEquals(
            $matches1[1],
            $matches2[1],
            'Each block should get a unique ID when navId not provided'
        );
    }

    /**
     * Test navigation block aria-label escaping.
     */
    public function test_navigation_block_aria_label_escaping(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'ariaLabel' => 'Navigation with "quotes" & special <chars>',
            ],
        ]);

        // Should not contain unescaped special characters
        $this->assertStringNotContainsString(
            'aria-label="Navigation with "quotes"',
            $block_content,
            'Quotes should be escaped in aria-label'
        );

        $this->assertStringContainsString(
            'aria-label=',
            $block_content,
            'Block should still have aria-label attribute'
        );
    }

    /**
     * Test navigation block with openOn variations.
     */
    public function test_navigation_block_open_on_variations(): void {
        $open_on_values = ['hover', 'click'];

        foreach ($open_on_values as $open_on) {
            $block_content = render_block([
                'blockName' => 'aggressive-apparel/navigation',
                'attrs' => [
                    'openOn' => $open_on,
                ],
            ]);

            $this->assertStringContainsString(
                "&quot;openOn&quot;:&quot;{$open_on}&quot;",
                $block_content,
                "Context should include openOn={$open_on}"
            );
        }
    }

    /**
     * Test navigation block context JSON structure.
     */
    public function test_navigation_block_context_json_valid(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'json-test',
                'breakpoint' => 1024,
                'openOn' => 'hover',
            ],
        ]);

        // Extract context JSON
        preg_match('/data-wp-context="([^"]+)"/', $block_content, $matches);
        $this->assertNotEmpty($matches[1], 'Block should have context attribute');

        // Decode HTML entities and parse JSON
        $context_json = html_entity_decode($matches[1], ENT_QUOTES);
        $context = json_decode($context_json, true);

        $this->assertIsArray($context, 'Context should be valid JSON');
        $this->assertEquals('json-test', $context['navId']);
        $this->assertEquals(1024, $context['breakpoint']);
        $this->assertEquals('hover', $context['openOn']);
        $this->assertFalse($context['isOpen']);
        $this->assertFalse($context['isMobile']);
        $this->assertNull($context['activeSubmenuId']);
        $this->assertIsArray($context['drillStack']);
        $this->assertEmpty($context['drillStack']);
    }

    /**
     * Test navigation block announcer has correct ID format.
     */
    public function test_navigation_block_announcer_id_format(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'announcer-test',
            ],
        ]);

        $this->assertStringContainsString(
            'id="navigation-announcer-announcer-test"',
            $block_content,
            'Announcer ID should follow format: navigation-announcer-{navId}'
        );
    }

    /**
     * Test navigation block renders as semantic nav element.
     */
    public function test_navigation_block_semantic_markup(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertMatchesRegularExpression(
            '/<nav[^>]+>.*<\/nav>/s',
            $block_content,
            'Block should render as semantic <nav> element'
        );
    }

    /**
     * Test navigation block Interactivity API namespace.
     */
    public function test_navigation_block_interactivity_namespace(): void {
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $this->assertStringContainsString(
            'data-wp-interactive="aggressive-apparel/navigation"',
            $block_content,
            'Block should use correct Interactivity API namespace'
        );
    }
}
