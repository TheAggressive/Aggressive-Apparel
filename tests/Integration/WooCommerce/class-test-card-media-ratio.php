<?php
/**
 * Card media ratio tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\Tests\Support\Builds_Editorial_Fixtures;
use Aggressive_Apparel\WooCommerce\Card_Media_Ratio;
use WP_UnitTestCase;

/**
 * Product cards take the catalog's own photo shape, so photos show whole.
 */
class Test_Card_Media_Ratio extends WP_UnitTestCase {

	use Builds_Editorial_Fixtures;

	/** Start from an empty cache. */
	public function setUp(): void {
		parent::setUp();
		Card_Media_Ratio::forget();
	}

	/** Square mockups (as in production) make a square slot. */
	public function test_square_catalog_gives_a_square_slot(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_product( array( 'image' => $this->create_image( 2000, 2000 ) ) );
		}
		$this->create_product( array( 'image' => $this->create_image() ) );

		$this->assertSame( 1.0, Card_Media_Ratio::height_ratio(), 'The median, not the outlier, decides.' );
		$this->assertSame( '1/1', Card_Media_Ratio::aspect_ratio() );
	}

	/** 2:3 model photos make a 2:3 slot; nearby shapes snap to the nearest standard. */
	public function test_portrait_catalog_snaps_to_two_by_three(): void {
		$this->create_product( array( 'image' => $this->create_image( 504, 757 ) ) );
		$this->create_product( array( 'image' => $this->create_image( 500, 740 ) ) );

		$this->assertSame( 1.5, Card_Media_Ratio::height_ratio() );
		$this->assertSame( '1/1.5', Card_Media_Ratio::aspect_ratio() );
	}

	/** Without photos the slot is square; saving a product recomputes it. */
	public function test_saving_a_product_recomputes_the_shape(): void {
		$this->assertSame( 1.0, Card_Media_Ratio::height_ratio() );

		$this->create_product( array( 'image' => $this->create_image( 504, 757 ) ) );

		$this->assertSame( 1.5, Card_Media_Ratio::height_ratio(), 'save_post_product cleared the cached square.' );
	}

	/** The shape reaches the page as one custom property on the main stylesheet. */
	public function test_ratio_is_printed_as_a_custom_property(): void {
		( new Card_Media_Ratio() )->init();
		wp_register_style( 'aggressive-apparel-main', false, array(), '1' );
		do_action( 'wp_enqueue_scripts' );

		$inline = (array) wp_styles()->get_data( 'aggressive-apparel-main', 'after' );

		$this->assertContains( ':root{--aa-card-media-h:1}', $inline );
	}
}
