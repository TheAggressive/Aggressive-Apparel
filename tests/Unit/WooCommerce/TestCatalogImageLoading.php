<?php
/**
 * Catalog card image loading tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Image_Loading;
use WP_UnitTestCase;

/**
 * On a catalog page, the first row of card images loads eagerly (the first at
 * high priority), and every card image is sized for the card.
 */
class TestCatalogImageLoading extends WP_UnitTestCase {

	private const CARD = '<figure><a href="/p/"><img loading="lazy" decoding="async" src="/a.jpg" srcset="/a.jpg 800w, /a-300.jpg 300w" sizes="auto, (max-width: 800px) 100vw, 800px" /></a></figure>';

	/**
	 * A published product to render cards for.
	 *
	 * @var int
	 */
	private int $product_id = 0;

	/**
	 * Create a product.
	 */
	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}
		$this->product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
	}

	/**
	 * Render $count card images through a fresh instance.
	 *
	 * @param int $count Cards to render.
	 * @return list<\WP_HTML_Tag_Processor> Each card's image, positioned on the <img>.
	 */
	private function render_cards( int $count ): array {
		$loading = new Catalog_Image_Loading();
		$images  = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$processor = new \WP_HTML_Tag_Processor( $loading->optimize_card_image( self::CARD, array( 'context' => array( 'postId' => $this->product_id ) ) ) );
			$processor->next_tag( array( 'tag_name' => 'IMG' ) );
			$images[] = $processor;
		}
		return $images;
	}

	/**
	 * The first row loads eagerly; later cards stay lazy.
	 */
	public function test_catalog_cards(): void {
		$this->go_to( get_post_type_archive_link( 'product' ) );
		$cards = $this->render_cards( 5 );

		$this->assertNull( $cards[0]->get_attribute( 'loading' ) );
		$this->assertSame( 'high', $cards[0]->get_attribute( 'fetchpriority' ) );
		$this->assertSame( '(max-width: 600px) 50vw, (max-width: 1024px) 33vw, 320px', $cards[0]->get_attribute( 'sizes' ) );

		$this->assertNull( $cards[3]->get_attribute( 'loading' ) );
		$this->assertNull( $cards[3]->get_attribute( 'fetchpriority' ) );

		$this->assertSame( 'lazy', $cards[4]->get_attribute( 'loading' ) );
		$this->assertSame( 'auto, (max-width: 600px) 50vw, (max-width: 1024px) 33vw, 320px', $cards[4]->get_attribute( 'sizes' ) );
	}

	/**
	 * Cards outside a catalog (related products, the homepage) are untouched.
	 */
	public function test_other_pages_are_untouched(): void {
		$this->go_to( home_url( '/' ) );
		$loading = new Catalog_Image_Loading();

		$this->assertSame( self::CARD, $loading->optimize_card_image( self::CARD, array( 'context' => array( 'postId' => $this->product_id ) ) ) );
	}
}
