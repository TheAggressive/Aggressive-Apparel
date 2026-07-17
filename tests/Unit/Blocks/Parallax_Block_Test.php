<?php
/**
 * Parallax block render coverage.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use WP_UnitTestCase;

/**
 * @group blocks
 * @group parallax
 */
class Parallax_Block_Test extends WP_UnitTestCase {

	/**
	 * Render the block with the given attributes and inner HTML.
	 */
	private function render_parallax( array $attributes, string $inner = '<p>Layer</p>' ): string {
		$block = array(
			'blockName'    => 'aggressive-apparel/parallax',
			'attrs'        => $attributes,
			'innerContent' => array( $inner ),
			'innerHTML'    => $inner,
			'innerBlocks'  => array(),
		);

		return (string) render_block( $block );
	}

	public function test_disable_on_mobile_class_and_context(): void {
		$html = $this->render_parallax(
			array(
				'disableOnMobile' => true,
				'intensity'       => 50,
			)
		);

		$this->assertStringContainsString(
			'aggressive-apparel-parallax--disable-on-mobile',
			$html
		);
		// Context is HTML-escaped inside data-wp-context.
		$this->assertStringContainsString( '&quot;disableOnMobile&quot;:true', $html );
		$this->assertStringNotContainsString( '__visual-layer', $html );
		$this->assertStringContainsString(
			'aggressive-apparel-parallax__content',
			$html
		);
	}

	public function test_default_keeps_mobile_motion_enabled(): void {
		$html = $this->render_parallax( array( 'intensity' => 40 ) );

		$this->assertStringNotContainsString(
			'aggressive-apparel-parallax--disable-on-mobile',
			$html
		);
		$this->assertStringContainsString( '&quot;disableOnMobile&quot;:false', $html );
	}
}
