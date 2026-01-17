<?php
/**
 * Performance tests for the Aggressive Apparel Navigation block.
 *
 * Tests rendering performance, caching efficiency, and scalability.
 *
 * @package Aggressive_Apparel\Tests\Performance
 * @since 1.0.0
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace Aggressive_Apparel\Tests\Performance;

use WP_UnitTestCase;

/**
 * Test navigation block performance characteristics.
 */
class Navigation_Block_Performance_Test extends WP_UnitTestCase {
    /**
     * Test basic navigation block rendering performance.
     */
    public function test_navigation_block_rendering_performance(): void {
        $start_time = microtime(true);

        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'perf-test',
            ],
        ]);

        $render_time = microtime(true) - $start_time;

        // Assert rendering is reasonably fast (less than 100ms)
        $this->assertLessThan(0.1, $render_time, 'Navigation block should render in less than 100ms');

        // Ensure content is generated
        $this->assertStringContainsString('wp-block-aggressive-apparel-navigation', $block_content);
    }

    /**
     * Test navigation block caching performance with fixed navId.
     */
    public function test_navigation_block_caching_performance(): void {
        $fixed_nav_id = 'cache-perf-test';

        // First render
        $start_time = microtime(true);
        $block_content_1 = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => $fixed_nav_id,
            ],
        ]);
        $first_render_time = microtime(true) - $start_time;

        // Second render (should produce identical output)
        $start_time = microtime(true);
        $block_content_2 = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => $fixed_nav_id,
            ],
        ]);
        $second_render_time = microtime(true) - $start_time;

        // Both renders should be fast
        $this->assertLessThan(0.1, $first_render_time, 'First render should be under 100ms');
        $this->assertLessThan(0.1, $second_render_time, 'Second render should be under 100ms');

        // Content should be identical when using same navId
        $this->assertEquals($block_content_1, $block_content_2);
    }

    /**
     * Test navigation block with different breakpoints performance.
     */
    public function test_breakpoint_variations_performance(): void {
        $breakpoints = [480, 768, 1024, 1280];

        foreach ($breakpoints as $breakpoint) {
            $start_time = microtime(true);

            $block_content = render_block([
                'blockName' => 'aggressive-apparel/navigation',
                'attrs' => [
                    'breakpoint' => $breakpoint,
                ],
            ]);

            $render_time = microtime(true) - $start_time;

            // Each breakpoint should render efficiently
            $this->assertLessThan(0.1, $render_time, "Breakpoint {$breakpoint} should render in less than 100ms");
            $this->assertStringContainsString('wp-block-aggressive-apparel-navigation', $block_content);
        }
    }

    /**
     * Test navigation block memory usage.
     */
    public function test_navigation_block_memory_usage(): void {
        $initial_memory = memory_get_usage();

        // Render multiple blocks
        for ($i = 0; $i < 10; $i++) {
            render_block([
                'blockName' => 'aggressive-apparel/navigation',
                'attrs' => [
                    'navId' => "memory-test-{$i}",
                ],
            ]);
        }

        $final_memory = memory_get_usage();
        $memory_used = $final_memory - $initial_memory;

        // Memory usage should be reasonable (less than 5MB for 10 blocks)
        $this->assertLessThan(
            5 * 1024 * 1024,
            $memory_used,
            'Navigation blocks should use less than 5MB of memory for 10 renders'
        );
    }

    /**
     * Test navigation block multiple renders performance.
     */
    public function test_multiple_renders_performance(): void {
        $render_times = [];

        // Perform multiple renders
        for ($i = 0; $i < 10; $i++) {
            $start_time = microtime(true);

            render_block([
                'blockName' => 'aggressive-apparel/navigation',
                'attrs' => [
                    'navId' => "multi-render-{$i}",
                ],
            ]);

            $render_times[] = microtime(true) - $start_time;
        }

        $average_render_time = array_sum($render_times) / count($render_times);
        $max_render_time = max($render_times);

        // Average render time should be reasonable
        $this->assertLessThan(0.05, $average_render_time, 'Average render time should be less than 50ms');

        // No single render should be excessively slow
        $this->assertLessThan(0.1, $max_render_time, 'Maximum render time should be less than 100ms');
    }

    /**
     * Test navigation block context generation performance.
     */
    public function test_context_generation_performance(): void {
        $start_time = microtime(true);

        // Render with various context options
        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'context-perf-test',
                'breakpoint' => 1024,
                'openOn' => 'hover',
            ],
        ]);

        $render_time = microtime(true) - $start_time;

        // Context generation should be fast
        $this->assertLessThan(0.05, $render_time, 'Context generation should complete in less than 50ms');

        // Verify context is present
        $this->assertStringContainsString('data-wp-context=', $block_content);
    }

    /**
     * Test navigation block with openOn variations performance.
     */
    public function test_open_on_variations_performance(): void {
        $open_on_values = ['hover', 'click'];

        foreach ($open_on_values as $open_on) {
            $start_time = microtime(true);

            $block_content = render_block([
                'blockName' => 'aggressive-apparel/navigation',
                'attrs' => [
                    'openOn' => $open_on,
                ],
            ]);

            $render_time = microtime(true) - $start_time;

            // All open modes should render efficiently
            $this->assertLessThan(
                0.1,
                $render_time,
                "Open mode '{$open_on}' should render in less than 100ms"
            );

            $this->assertStringContainsString(
                "&quot;openOn&quot;:&quot;{$open_on}&quot;",
                $block_content
            );
        }
    }

    /**
     * Test navigation block Interactivity API attribute rendering performance.
     */
    public function test_interactivity_attributes_performance(): void {
        $start_time = microtime(true);

        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [],
        ]);

        $render_time = microtime(true) - $start_time;

        // Interactivity attributes should be rendered efficiently
        $this->assertLessThan(0.05, $render_time, 'Interactivity attributes should render in less than 50ms');

        // Verify key attributes are present
        $this->assertStringContainsString('data-wp-interactive', $block_content);
        $this->assertStringContainsString('data-wp-context', $block_content);
        $this->assertStringContainsString('data-wp-init', $block_content);
    }

    /**
     * Test navigation block with inner content performance.
     */
    public function test_inner_content_rendering_performance(): void {
        $inner_content = str_repeat('<div class="menu-item">Menu Item</div>', 20);

        $start_time = microtime(true);

        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'inner-content-perf',
            ],
            'innerContent' => [$inner_content],
        ]);

        $render_time = microtime(true) - $start_time;

        // Inner content should be rendered efficiently
        $this->assertLessThan(0.1, $render_time, 'Inner content should render in less than 100ms');

        $this->assertStringContainsString('menu-item', $block_content);
    }

    /**
     * Test announcer element rendering performance.
     */
    public function test_announcer_rendering_performance(): void {
        $start_time = microtime(true);

        $block_content = render_block([
            'blockName' => 'aggressive-apparel/navigation',
            'attrs' => [
                'navId' => 'announcer-perf-test',
            ],
        ]);

        $render_time = microtime(true) - $start_time;

        // Announcer should be rendered efficiently
        $this->assertLessThan(0.05, $render_time, 'Announcer should render in less than 50ms');

        // Verify announcer is present
        $this->assertStringContainsString('navigation-announcer-', $block_content);
        $this->assertStringContainsString('aria-live="polite"', $block_content);
    }
}
