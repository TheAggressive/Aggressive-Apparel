<?php
/**
 * Spec registry helpers for tests: save, change and resolve specs the way
 * the settings screen and product pages do.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Support;

use Aggressive_Apparel\WooCommerce\Product_Specs;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Settings;

/**
 * Saves and resolves specs through the real routes.
 */
trait Saves_Spec_Registry {

	/**
	 * Registry items by key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function items(): array {
		return array_column( Product_Specs_Registry::get()['items'], null, 'key' );
	}

	/**
	 * Resolved items for a product, by key.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, array<string, mixed>>
	 */
	private function resolved( int $product_id ): array {
		return array_column( Product_Specs::for_product( $product_id )['items'], null, 'key' );
	}

	/**
	 * Change one spec through the Specs tab's save route.
	 *
	 * @param string               $key   Spec key.
	 * @param array<string, mixed> $patch Fields to change.
	 */
	private function retype( string $key, array $patch ): void {
		$registry = Product_Specs_Registry::get();
		$items    = array_map( static fn( array $item ): array => $key === $item['key'] ? array_merge( $item, $patch ) : $item, $registry['items'] );
		$request  = new \WP_REST_Request( 'POST' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'items'    => $items,
					'revision' => $registry['revision'],
					'changed'  => array( $key ),
				)
			)
		);
		$this->assertNotWPError( ( new Product_Specs_Settings() )->save_registry( $request ) );
		Product_Specs::flush();
	}

	/**
	 * Save the default items with these default values (connected).
	 *
	 * @param array<string, string> $defaults Item key => default.
	 */
	private function save_defaults( array $defaults ): void {
		$items = Product_Specs_Registry::defaults();
		foreach ( $items as $i => $item ) {
			$items[ $i ]['default'] = $defaults[ $item['key'] ] ?? '';
		}
		Product_Specs_Registry::save(
			array(
				'items'           => Product_Specs_Registry::validate( array( 'items' => $items ) )['items'],
				'hide_dimensions' => false,
			)
		);
		if ( array() !== $defaults ) {
			$this->assertSame( array(), Product_Specs_Registry::ensure_connected() );
		}
	}

	/**
	 * A published size guide with content, so products resolve to it.
	 *
	 * @param string $title Title.
	 */
	private function guide( string $title = 'Heavy Tee' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'aa_size_guide',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '<p>Guide</p>',
			)
		);
	}

	/**
	 * A published product, assigned to a guide when one is given.
	 *
	 * @param int $guide Size guide ID, or 0.
	 */
	private function product( int $guide ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Spec product' );
		$product->set_status( 'publish' );
		$id = $product->save();
		if ( $guide > 0 ) {
			update_post_meta( $id, '_aggressive_apparel_size_guide_id', $guide );
		}

		return $id;
	}
}
