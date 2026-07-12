<?php
/**
 * Unit tests for the Split Story block family (parent + two column children).
 *
 * Split Story is a static block, so its class/style output is produced by the
 * JS save (covered by the shared prop-helper unit tests). These tests guard the
 * registration contract: the fixed two-column structure, locked children, the
 * modern attribute set, and the v1 -> v2 deprecation.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Registration/metadata contract for the Split Story blocks.
 */
class Split_Story_Block_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! Blocks::is_block_registered( 'aggressive-apparel/split-story' ) ) {
			Blocks::register();
		}
	}

	/**
	 * Fetch a registered block type.
	 *
	 * @param string $name Block name.
	 * @return \WP_Block_Type|null
	 */
	private function block( string $name ) {
		return WP_Block_Type_Registry::get_instance()->get_registered( $name );
	}

	/**
	 * All three blocks register.
	 *
	 * @return void
	 */
	public function test_all_blocks_register(): void {
		$this->assertNotNull( $this->block( 'aggressive-apparel/split-story' ) );
		$this->assertNotNull( $this->block( 'aggressive-apparel/split-story-media' ) );
		$this->assertNotNull( $this->block( 'aggressive-apparel/split-story-content' ) );
	}

	/**
	 * The parent only allows its two column children.
	 *
	 * @return void
	 */
	public function test_parent_allows_only_its_columns(): void {
		$parent = $this->block( 'aggressive-apparel/split-story' );

		$this->assertSame(
			array(
				'aggressive-apparel/split-story-media',
				'aggressive-apparel/split-story-content',
			),
			$parent->allowed_blocks
		);
	}

	/**
	 * Each column is parented to split-story and hidden from the inserter, so
	 * the two-column structure can't be broken or duplicated.
	 *
	 * @return void
	 */
	public function test_columns_are_locked_children(): void {
		foreach ( array( 'media', 'content' ) as $side ) {
			$column = $this->block( "aggressive-apparel/split-story-{$side}" );

			$this->assertSame(
				array( 'aggressive-apparel/split-story' ),
				$column->parent,
				"{$side} column must declare split-story as its parent"
			);
			$this->assertFalse(
				$column->supports['inserter'] ?? true,
				"{$side} column must be hidden from the inserter"
			);
			$this->assertTrue(
				$column->supports['spacing']['margin'] ?? false,
				"{$side} column must support margin"
			);
		}
	}

	/**
	 * The parent exposes the modern attribute set and dropped the v1 names.
	 *
	 * @return void
	 */
	public function test_parent_attribute_contract(): void {
		$attributes = $this->block( 'aggressive-apparel/split-story' )->attributes;

		foreach ( array( 'mediaPosition', 'mediaWidth', 'mediaHeight', 'sticky', 'stickyTop', 'stackOrder' ) as $attr ) {
			$this->assertArrayHasKey( $attr, $attributes, "missing attribute {$attr}" );
		}

		$this->assertArrayNotHasKey( 'mediaColumn', $attributes );
		$this->assertArrayNotHasKey( 'mediaRatio', $attributes );
	}

	/**
	 * Block spacing uses the native axial control (vertical + horizontal) rather
	 * than custom gap attributes.
	 *
	 * @return void
	 */
	public function test_block_spacing_is_native_and_axial(): void {
		$supports = $this->block( 'aggressive-apparel/split-story' )->supports;

		$this->assertSame(
			array( 'horizontal', 'vertical' ),
			$supports['spacing']['blockGap'] ?? null,
			'Block spacing should be the native axial (horizontal + vertical) control'
		);
	}
}
