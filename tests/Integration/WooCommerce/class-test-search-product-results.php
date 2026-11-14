<?php
/**
 * Search result product pricing and sale wording.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\Core\Search_Index;
use Aggressive_Apparel\Core\Search_Results;
use Aggressive_Apparel\Tests\Support\Creates_Variable_Products;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Search results describe a product the way its card does: the card's price
 * rule and the store's sale badge wording.
 */
class Test_Search_Product_Results extends WP_UnitTestCase {
	use Creates_Variable_Products;

	/** Search term every fixture's name contains. */
	private const TOKEN = 'zzsearchpricetoken';

	/**
	 * Index under test, flagged ready (the real build runs via Action Scheduler).
	 *
	 * @var Search_Index
	 */
	private Search_Index $index;

	/** Ready the index and store wording. */
	public function set_up(): void {
		parent::set_up();

		$this->index = new Search_Index();
		$this->index->maybe_install();
		$ref = new ReflectionClass( Search_Index::class );
		update_option( (string) $ref->getConstant( 'INDEX_OPTION' ), $ref->getConstant( 'INDEX_VERSION' ), false );

		update_option( Feature_Settings::SALE_BADGE_TEXT_OPTION, '{percent}% Off' );
	}

	/** Restore options. */
	public function tear_down(): void {
		delete_option( Feature_Settings::SALE_BADGE_TEXT_OPTION );
		delete_option( Feature_Settings::PRICE_STARTING_PREFIX_OPTION );
		delete_option( Feature_Settings::OPTION_KEY );

		parent::tear_down();
	}

	/**
	 * Search for the fixtures, keyed by product ID.
	 *
	 * @param int[] $ids Products to index.
	 * @return array<int, array<string, mixed>>
	 */
	private function search( array $ids ): array {
		$this->index->sync_ids( $ids );
		$items = ( new Search_Results( $this->index ) )->products( self::TOKEN );

		return array_column( $items, null, 'id' );
	}

	/** The sale pill reads the store's sale badge wording, with the discount. */
	public function test_sale_label_uses_the_store_wording(): void {
		$sale = new \WC_Product_Simple();
		$sale->set_name( 'Reduced ' . self::TOKEN );
		$sale->set_status( 'publish' );
		$sale->set_regular_price( '25.00' );
		$sale->set_sale_price( '20.00' );
		$sale_id = (int) $sale->save();

		$full = new \WC_Product_Simple();
		$full->set_name( 'Full ' . self::TOKEN );
		$full->set_status( 'publish' );
		$full->set_regular_price( '25.00' );
		$full_id = (int) $full->save();

		$items = $this->search( array( $sale_id, $full_id ) );

		$this->assertSame( '20% Off', $items[ $sale_id ]['saleLabel'] );
		$this->assertSame( '', $items[ $full_id ]['saleLabel'] );
	}

	/** A variable product's price follows Smart Price Display, like its card. */
	public function test_variable_price_follows_smart_price_display(): void {
		$id = $this->create_variable_product( array( array( 15, 12 ), array( 18, 0 ) ), 'Hat ' . self::TOKEN );

		update_option( Feature_Settings::OPTION_KEY, array( 'price_display' => true ) );
		update_option( Feature_Settings::PRICE_STARTING_PREFIX_OPTION, 'Starting at' );
		$price = (string) $this->search( array( $id ) )[ $id ]['price'];
		$this->assertStringStartsWith( 'Starting at ', $price );
		$this->assertStringContainsString( '12.00', $price );

		delete_option( Feature_Settings::OPTION_KEY );
		$range = (string) $this->search( array( $id ) )[ $id ]['price'];
		$this->assertStringNotContainsString( 'From', $range );
		$this->assertStringContainsString( '12.00', $range );
		$this->assertStringContainsString( '18.00', $range );
	}
}
