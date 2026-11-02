<?php
/**
 * Printful syncs keep the store's product edits. These go through WooCommerce's
 * real REST controllers, the path Printful's servers use.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Printful_Sync_Guard;
use WP_UnitTestCase;

/**
 * Covers holding store edits against Printful syncs.
 */
class TestPrintfulSyncGuard extends WP_UnitTestCase {

	/**
	 * Hook the guard, as a store manager, with requests coming from Printful.
	 */
	public function setUp(): void {
		parent::setUp();

		( new Printful_Sync_Guard() )->init();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		rest_get_server();
		$_SERVER['HTTP_USER_AGENT'] = 'Printful WooCommerce Integration';
	}

	/**
	 * Clear the request's identity.
	 */
	public function tearDown(): void {
		unset( $_SERVER['HTTP_USER_AGENT'], $_SERVER['PHP_AUTH_USER'] );

		parent::tearDown();
	}

	/** A field nobody edited still takes Printful's updates. */
	public function test_untouched_fields_keep_syncing(): void {
		$id = $this->created();

		$this->assertSame( 'Printful two', $this->sync( $id, array( 'name' => 'Printful two' ) )['name'] );
		$this->assertSame( 'Printful three', $this->sync( $id, array( 'name' => 'Printful three' ) )['name'] );
		$this->assertSame( '', (string) get_post_meta( $id, Printful_Sync_Guard::KEPT, true ) );
	}

	/** An edited field stays, however often Printful sends it; others still sync. */
	public function test_store_edits_survive_a_sync(): void {
		$id = $this->created();
		$this->edit(
			$id,
			static function ( \WC_Product $product ): void {
				$product->set_name( 'Our title' );
				$product->set_regular_price( '39' );
				$product->set_status( 'draft' );
			}
		);

		for ( $i = 0; $i < 2; $i++ ) {
			$saved = $this->sync(
				$id,
				array(
					'name'          => 'Printful title ' . $i,
					'regular_price' => '20',
					'status'        => 'publish',
					'description'   => 'Printful copy ' . $i,
				)
			);
			$this->assertSame( 'Our title', $saved['name'] );
			$this->assertSame( '39', $saved['regular_price'] );
			$this->assertSame( 'draft', $saved['status'] );
			$this->assertStringContainsString( 'Printful copy ' . $i, $saved['description'], 'Untouched fields still sync.' );
		}

		$kept = get_post_meta( $id, Printful_Sync_Guard::KEPT, true )[ $id ];
		$this->assertSame( 'Printful title 1', $kept['name'] );
		$this->assertArrayNotHasKey( 'description', $kept );
	}

	/** Setting a field to Printful's value hands it back to the sync. */
	public function test_taking_printfuls_value_resumes_syncing(): void {
		$id = $this->created();
		$this->edit( $id, static fn( \WC_Product $product ) => $product->set_name( 'Our title' ) );
		$this->sync( $id, array( 'name' => 'Printful two' ) );

		$this->edit( $id, static fn( \WC_Product $product ) => $product->set_name( 'Printful two' ) );
		$this->sync( $id, array( 'name' => 'Printful two' ) );

		$this->assertSame( 'Printful three', $this->sync( $id, array( 'name' => 'Printful three' ) )['name'] );
		$this->assertSame( '', (string) get_post_meta( $id, Printful_Sync_Guard::KEPT, true ), 'Nothing is held back any more.' );
	}

	/** A product synced before the guard can't be told from an edit, so it stays. */
	public function test_products_from_before_the_guard_stay(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Already here' );
		$id = $product->save();

		$this->assertSame( 'Already here', $this->sync( $id, array( 'name' => 'Printful' ) )['name'] );
	}

	/** A variation's price is the store's too; its SKU stays with the sync. */
	public function test_variation_edits_survive(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Tee' );
		$parent_id = $parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent_id );
		$variation->set_regular_price( '20' );
		$id = $variation->save();

		$path = "/wc/v3/products/{$parent_id}/variations/{$id}";
		$this->request( 'PUT', $path, array( 'regular_price' => '21' ) );
		$this->edit( $id, static fn( \WC_Product $product ) => $product->set_regular_price( '45' ) );

		$saved = $this->request(
			'PUT',
			$path,
			array(
				'regular_price' => '22',
				'sku'           => 'PF-1',
			)
		);

		$this->assertSame( '45', $saved['regular_price'] );
		$this->assertSame( 'PF-1', $saved['sku'] );
		$this->assertSame( '22', get_post_meta( $parent_id, Printful_Sync_Guard::KEPT, true )[ $id ]['regular_price'], 'The note lands on the parent.' );
	}

	/** Attributes merge: the sync's variations, the store's everything else. */
	public function test_attributes_merge(): void {
		add_filter( 'aggressive_apparel_product_specs_attribute_names', static fn(): array => array( 'Fabric Blend' ) );

		$id = $this->created();
		$this->edit(
			$id,
			static function ( \WC_Product $product ): void {
				$ours = new \WC_Product_Attribute();
				$ours->set_name( 'Care' );
				$ours->set_options( array( 'Our wording' ) );
				$ours->set_visible( true );
				$product->set_attributes( array( $ours ) );
			}
		);

		$saved = $this->sync(
			$id,
			array(
				'attributes' => array(
					$this->attribute( 'Care', 'Printful wording' ),
					$this->attribute( 'Fabric Blend', 'Printful blend' ),
					$this->attribute( 'Print Area', 'Front' ),
					$this->attribute( 'Size', 'M', true ),
				),
			)
		);

		$options = array_column( $saved['attributes'], 'options', 'name' );
		$this->assertSame( array( 'Our wording' ), $options['Care'], 'The store’s value stays.' );
		$this->assertArrayNotHasKey( 'Fabric Blend', $options, 'It would outrank the size guide.' );
		$this->assertSame( array( 'Front' ), $options['Print Area'], 'Unrelated ones still arrive.' );
		$this->assertSame( array( 'M' ), $options['Size'], 'Variations belong to the sync.' );

		$saved = $this->sync( $id, array( 'attributes' => array( $this->attribute( 'Size', 'L', true ) ) ) );
		$names = array_column( $saved['attributes'], 'name' );
		$this->assertContains( 'Care', $names, 'A sync leaving it out doesn’t remove it.' );
	}

	/** Printful's API key identifies it even with another user agent. */
	public function test_recognises_printfuls_api_key(): void {
		global $wpdb;
		$id = $this->created();
		$this->edit( $id, static fn( \WC_Product $product ) => $product->set_name( 'Our title' ) );

		unset( $_SERVER['HTTP_USER_AGENT'] );
		$_SERVER['PHP_AUTH_USER'] = 'ck_printful';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			array(
				'user_id'         => get_current_user_id(),
				'description'     => 'Printful - API (2026)',
				'permissions'     => 'read_write',
				'consumer_key'    => wc_api_hash( 'ck_printful' ),
				'consumer_secret' => 'cs_x',
				'truncated_key'   => 'printfu',
			)
		);

		$this->assertSame( 'Our title', $this->sync( $id, array( 'name' => 'Printful' ) )['name'] );
	}

	/** Anyone else's API requests work as they always did. */
	public function test_other_requests_are_untouched(): void {
		$id = $this->created();
		$this->edit( $id, static fn( \WC_Product $product ) => $product->set_name( 'Our title' ) );

		$_SERVER['HTTP_USER_AGENT'] = 'SomeOtherTool/1.0';

		$this->assertSame( 'Their title', $this->sync( $id, array( 'name' => 'Their title' ) )['name'] );
	}

	/** The filter turns the guard off. */
	public function test_can_be_turned_off(): void {
		add_filter( 'aggressive_apparel_printful_keep_store_edits', '__return_false' );
		$id = $this->created();
		$this->edit( $id, static fn( \WC_Product $product ) => $product->set_name( 'Our title' ) );

		$this->assertSame( 'Printful', $this->sync( $id, array( 'name' => 'Printful' ) )['name'] );
	}

	/** The product editor says what was refused. */
	public function test_notice_names_what_was_kept(): void {
		$id = $this->created();
		$this->edit( $id, static fn( \WC_Product $product ) => $product->set_name( 'Our title' ) );
		$this->sync( $id, array( 'name' => 'Printful <b>wants</b> this' ) );

		set_current_screen( 'product' );
		$GLOBALS['post'] = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		ob_start();
		Printful_Sync_Guard::render_notice();
		$html = (string) ob_get_clean();
		set_current_screen( 'front' );

		$this->assertStringContainsString( 'your versions were kept', $html );
		$this->assertStringContainsString( 'Title: Printful', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	/**
	 * A product as Printful first creates it.
	 */
	private function created(): int {
		return (int) $this->request(
			'POST',
			'/wc/v3/products',
			array(
				'name'          => 'Printful one',
				'regular_price' => '20',
				'description'   => 'Printful copy',
				'status'        => 'publish',
			)
		)['id'];
	}

	/**
	 * Printful updating a product.
	 *
	 * @param int                  $id   Product ID.
	 * @param array<string, mixed> $body Fields.
	 * @return array<string, mixed>
	 */
	private function sync( int $id, array $body ): array {
		return $this->request( 'PUT', '/wc/v3/products/' . $id, $body );
	}

	/**
	 * A REST request's response data.
	 *
	 * @param string               $method Method.
	 * @param string               $path   Route.
	 * @param array<string, mixed> $body   Fields.
	 * @return array<string, mixed>
	 */
	private function request( string $method, string $path, array $body ): array {
		$request = new \WP_REST_Request( $method, $path );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		$response = rest_do_request( $request );
		$this->assertLessThan( 300, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

		return $response->get_data();
	}

	/**
	 * The store editing a product, outside any REST request.
	 *
	 * @param int      $id   Product ID.
	 * @param callable $edit Takes the product.
	 */
	private function edit( int $id, callable $edit ): void {
		$product = wc_get_product( $id );
		$this->assertInstanceOf( \WC_Product::class, $product );
		$edit( $product );
		$product->save();
	}

	/**
	 * An attribute as the REST API takes it.
	 *
	 * @param string $name      Name.
	 * @param string $option    Value.
	 * @param bool   $variation Whether it defines variations.
	 * @return array<string, mixed>
	 */
	private function attribute( string $name, string $option, bool $variation = false ): array {
		return array(
			'name'      => $name,
			'options'   => array( $option ),
			'visible'   => true,
			'variation' => $variation,
		);
	}
}
