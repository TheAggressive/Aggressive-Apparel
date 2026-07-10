<?php
/**
 * Unit tests for supports.requiresPlugins block registration gating.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);


namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Test Blocks plugin-requirement gating helpers.
 */
class Blocks_Requires_Plugins_Test extends WP_UnitTestCase {

	/**
	 * Evaluate decoded metadata via the private gate helper.
	 *
	 * @param array<string, mixed> $metadata Decoded block.json payload.
	 * @return bool
	 */
	private function is_metadata_allowed( array $metadata ): bool {
		$method = new ReflectionMethod( Blocks::class, 'metadata_required_plugins_active' );
		$method->setAccessible( true );

		return (bool) $method->invoke( null, $metadata );
	}

	/**
	 * Blocks without requiresPlugins always register.
	 *
	 * @return void
	 */
	public function test_missing_requires_plugins_is_allowed(): void {
		$this->assertTrue(
			$this->is_metadata_allowed(
				array(
					'name'     => 'aggressive-apparel/logo',
					'supports' => array( 'html' => false ),
				)
			)
		);
	}

	/**
	 * Empty requiresPlugins lists do not block registration.
	 *
	 * @return void
	 */
	public function test_empty_requires_plugins_is_allowed(): void {
		$this->assertTrue(
			$this->is_metadata_allowed(
				array(
					'name'     => 'aggressive-apparel/logo',
					'supports' => array(
						'requiresPlugins' => array(),
						'html'              => false,
					),
				)
			)
		);
	}

	/**
	 * WooCommerce-required blocks follow class_exists( 'WooCommerce' ).
	 *
	 * @return void
	 */
	public function test_woocommerce_requirement_respects_class_existence(): void {
		$allowed = $this->is_metadata_allowed(
			array(
				'name'     => 'aggressive-apparel/product-color-swatches',
				'supports' => array(
					'requiresPlugins' => array( 'woocommerce' ),
					'html'              => false,
				),
			)
		);

		$this->assertSame( class_exists( 'WooCommerce' ), $allowed );
	}

	/**
	 * Unknown plugin slugs can be approved via filter.
	 *
	 * @return void
	 */
	public function test_unknown_plugin_can_be_approved_via_filter(): void {
		$metadata = array(
			'name'     => 'aggressive-apparel/demo',
			'supports' => array(
				'requiresPlugins' => array( 'some-plugin' ),
			),
		);

		$this->assertFalse( $this->is_metadata_allowed( $metadata ) );

		add_filter(
			'aggressive_apparel_block_required_plugin_active',
			static function ( bool $active, string $plugin ): bool {
				return 'some-plugin' === $plugin ? true : $active;
			},
			10,
			2
		);

		$this->assertTrue( $this->is_metadata_allowed( $metadata ) );
	}

	/**
	 * Readable theme block.json with requiresPlugins is decoded and gated.
	 *
	 * @return void
	 */
	public function test_block_json_file_gate_reads_theme_metadata(): void {
		$block_json = get_template_directory() . '/build/blocks-interactivity/product-color-swatches/block.json';

		if ( ! is_readable( $block_json ) ) {
			$this->markTestSkipped( 'Built product-color-swatches block.json is not available.' );
		}

		$method = new ReflectionMethod( Blocks::class, 'block_required_plugins_active' );
		$method->setAccessible( true );

		$allowed = (bool) $method->invoke( null, $block_json );

		$this->assertSame( class_exists( 'WooCommerce' ), $allowed );
	}
}
