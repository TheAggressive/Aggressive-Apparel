<?php
/**
 * Every size guide's spec values in one grid (Size_Guide_Grid).
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Taxonomy;
use Aggressive_Apparel\WooCommerce\Product_Specs_Values;
use Aggressive_Apparel\WooCommerce\Size_Guide_Grid;
use Aggressive_Apparel\WooCommerce\Size_Guide_Post_Type;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use Aggressive_Apparel\Tests\Support\Saves_Spec_Registry;
use WP_UnitTestCase;

/**
 * Covers listing and saving guides' values in bulk.
 */
class Test_Size_Guide_Grid extends WP_UnitTestCase {

	use Restores_Taxonomies;
	use Saves_Spec_Registry;

	/**
	 * Enable the feature and start from the default registry.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! post_type_exists( Size_Guide_Post_Type::POST_TYPE ) ) {
			( new Size_Guide_Post_Type() )->register();
		}
		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		Product_Specs_Taxonomy::flush();
		$this->snapshot_taxonomies();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		$this->restore_taxonomies();
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();

		parent::tearDown();
	}

	/** The grid lists every guide's values and saves only what's sent. */
	public function test_grid_sets_values_on_many_guides(): void {
		$this->save_defaults( array() );
		$tee  = $this->guide( 'Heavy Tee' );
		$hat  = $this->guide( 'Bucket Hat' );
		$post = self::factory()->post->create();
		Product_Specs_Values::save_guide( $tee, array( 'fabric' => 'Cotton' ) );

		$result = Size_Guide_Grid::save(
			array(
				$tee  => array( 'construction' => 'Taped seams' ),
				$hat  => array( 'construction' => 'Taped seams' ),
				$post => array( 'construction' => 'Taped seams' ),
			)
		);

		$this->assertSame(
			array(
				'saved'   => 2,
				'skipped' => 1,
			),
			$result
		);
		$rows = array_column( Size_Guide_Grid::rows(), 'values', 'id' );
		$this->assertSame( 'Taped seams', $rows[ $hat ]['construction'] );
		$this->assertSame( 'Cotton', $rows[ $tee ]['fabric'], 'Values not sent stay.' );
	}

	/** Names show as stored, as the guide list shows them. */
	public function test_rows_show_names_as_stored(): void {
		$this->save_defaults( array() );
		$this->guide( "Men's Tee & Hoodie" );

		$this->assertSame( "Men's Tee & Hoodie", Size_Guide_Grid::rows()[0]['title'] );
	}
}
