<?php
/**
 * Image output format tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Image_Output_Format;
use WP_UnitTestCase;

/**
 * Verify generated PNG/JPEG images are saved as WebP.
 */
class TestImageOutputFormat extends WP_UnitTestCase {

	/**
	 * Remove the opt-out filter between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'aggressive_apparel_webp_image_output' );

		parent::tearDown();
	}

	/**
	 * PNG and JPEG map to WebP; GIF and existing mappings are left alone.
	 *
	 * @return void
	 */
	public function test_png_and_jpeg_map_to_webp(): void {
		$formats = ( new Image_Output_Format() )->filter_output_format(
			array( 'image/jpeg' => 'image/avif' )
		);

		$this->assertSame( 'image/webp', $formats['image/png'] );
		$this->assertSame( 'image/avif', $formats['image/jpeg'] );
		$this->assertArrayNotHasKey( 'image/gif', $formats );
	}

	/**
	 * The opt-out filter restores the source formats.
	 *
	 * @return void
	 */
	public function test_filter_disables_conversion(): void {
		add_filter( 'aggressive_apparel_webp_image_output', '__return_false' );

		$this->assertSame( array(), ( new Image_Output_Format() )->filter_output_format( array() ) );
	}
}
