<?php
/**
 * Unit tests for the Aggressive Apparel Modal block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test modal block render output.
 */
class Modal_Block_Test extends WP_UnitTestCase {

	/**
	 * Register theme blocks.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Blocks::init();
	}

	/**
	 * Render the modal block with the supplied attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	private function render_modal( array $attributes ): string {
		return render_block(
			array(
				'blockName'    => 'aggressive-apparel/modal',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Scalar radius values remain supported.
	 *
	 * @return void
	 */
	public function test_scalar_border_radius_is_forwarded(): void {
		$html = $this->render_modal(
			array(
				'style' => array(
					'border' => array( 'radius' => '12px' ),
				),
			)
		);

		$this->assertStringContainsString( '--aa-dialog-border-radius: 12px', $html );
	}

	/**
	 * Per-corner radius values are serialized in CSS shorthand order.
	 *
	 * @return void
	 */
	public function test_per_corner_border_radius_is_normalized(): void {
		$html = $this->render_modal(
			array(
				'style' => array(
					'border' => array(
						'radius' => array(
							'topLeft'     => '1px',
							'topRight'    => '2px',
							'bottomRight' => '3px',
							'bottomLeft'  => '4px',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( '--aa-dialog-border-radius: 1px 2px 3px 4px', $html );
		$this->assertStringNotContainsString( '--aa-dialog-border-radius: Array', $html );
	}
}
