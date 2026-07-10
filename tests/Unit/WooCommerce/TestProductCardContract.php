<?php
/**
 * Product Card Contract unit tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Product_Card_Contract;
use WP_UnitTestCase;

/**
 * Test product card data-attribute contract stamping.
 */
class TestProductCardContract extends WP_UnitTestCase {

	/**
	 * Contract service under test.
	 *
	 * @var Product_Card_Contract
	 */
	private Product_Card_Contract $contract;

	/**
	 * Fixture product ID.
	 *
	 * @var int
	 */
	private int $product_id = 0;

	/**
	 * Create a product and init the contract.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->contract   = new Product_Card_Contract();
		$this->product_id = self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_title'  => 'Contract Tee',
			)
		);
	}

	/**
	 * Image blocks in product context receive image + link attrs.
	 *
	 * @return void
	 */
	public function test_stamps_image_and_link_on_product_image_block(): void {
		$html = '<figure class="wp-block-post-featured-image"><a href="https://example.com/p/"><img src="https://example.com/a.jpg" alt="Tee" /></a></figure>';

		$result = $this->contract->stamp_image_block(
			$html,
			array(
				'blockName' => 'core/post-featured-image',
				'attrs'     => array( 'isLink' => true ),
				'context'   => array( 'postId' => $this->product_id ),
			)
		);

		$this->assertStringContainsString( 'data-aa-product-image="true"', $result );
		$this->assertStringContainsString( 'data-aa-product-link="true"', $result );
	}

	/**
	 * Linked titles in product context receive the link attr.
	 *
	 * @return void
	 */
	public function test_stamps_link_on_product_title_block(): void {
		$html = '<h2 class="wp-block-post-title"><a href="https://example.com/p/">Contract Tee</a></h2>';

		$result = $this->contract->stamp_title_block(
			$html,
			array(
				'blockName' => 'core/post-title',
				'attrs'     => array( 'isLink' => true ),
				'context'   => array( 'postId' => $this->product_id ),
			)
		);

		$this->assertStringContainsString( 'data-aa-product-link="true"', $result );
		$this->assertStringNotContainsString( 'data-aa-product-image', $result );
	}

	/**
	 * Non-product contexts are left untouched.
	 *
	 * @return void
	 */
	public function test_skips_non_product_context(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Blog Post',
			)
		);

		$html = '<figure class="wp-block-post-featured-image"><img src="https://example.com/b.jpg" alt="" /></figure>';

		$result = $this->contract->stamp_image_block(
			$html,
			array(
				'blockName' => 'core/post-featured-image',
				'attrs'     => array(),
				'context'   => array( 'postId' => $post_id ),
			)
		);

		$this->assertSame( $html, $result );
	}

	/**
	 * Unlinked titles are not stamped.
	 *
	 * @return void
	 */
	public function test_skips_title_without_is_link(): void {
		$html = '<h2 class="wp-block-post-title">Contract Tee</h2>';

		$result = $this->contract->stamp_title_block(
			$html,
			array(
				'blockName' => 'core/post-title',
				'attrs'     => array( 'isLink' => false ),
				'context'   => array( 'postId' => $this->product_id ),
			)
		);

		$this->assertSame( $html, $result );
	}
}
