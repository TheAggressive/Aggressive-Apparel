<?php
/**
 * Animate On Scroll render tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Locks sequence wrapping and screen-reader announce defaults.
 */
class Animate_On_Scroll_Block_Test extends WP_UnitTestCase {

	/**
	 * Register theme blocks once for render tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! Blocks::is_block_registered( 'aggressive-apparel/animate-on-scroll' ) ) {
			Blocks::register();
		}
	}

	/**
	 * Sequence mode wraps each inner block with animation data attributes.
	 *
	 * @return void
	 */
	public function test_sequence_mode_wraps_children_with_sequence_attributes(): void {
		$markup = '<!-- wp:aggressive-apparel/animate-on-scroll {"useSequence":true,"animationSequence":[{"animation":"fade"},{"animation":"slide","direction":"left"}]} -->'
			. '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->'
			. '<!-- /wp:aggressive-apparel/animate-on-scroll -->';

		$html = do_blocks( $markup );

		$this->assertStringContainsString( 'has-animation-sequence', $html );
		$this->assertStringContainsString( 'data-animate-sequence-type="fade"', $html );
		$this->assertStringContainsString( 'data-animate-sequence-type="slide"', $html );
		$this->assertStringContainsString( 'data-animate-sequence-direction="left"', $html );
		$this->assertSame( 2, substr_count( $html, 'data-animate-sequence-type=' ) );
	}

	/**
	 * Screen-reader announcements default off in Interactivity context.
	 *
	 * @return void
	 */
	public function test_announce_to_screen_reader_defaults_false(): void {
		$markup = '<!-- wp:aggressive-apparel/animate-on-scroll -->'
			. '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->'
			. '<!-- /wp:aggressive-apparel/animate-on-scroll -->';

		$html = do_blocks( $markup );

		$this->assertSame(
			1,
			preg_match( '/data-wp-context="([^"]+)"/', $html, $matches ),
			'Rendered block should expose a data-wp-context attribute.'
		);

		$context = json_decode( html_entity_decode( $matches[1], ENT_QUOTES ), true );
		$this->assertIsArray( $context );
		$this->assertFalse( $context['announceToScreenReader'] );
	}
}
