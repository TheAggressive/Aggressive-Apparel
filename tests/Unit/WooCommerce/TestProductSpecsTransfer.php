<?php
/**
 * Moving the Size & Specs setup between sites, and the guides' value grid.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Taxonomy;
use Aggressive_Apparel\WooCommerce\Product_Specs_Transfer;
use Aggressive_Apparel\WooCommerce\Product_Specs_Values;
use Aggressive_Apparel\WooCommerce\Size_Chart;
use Aggressive_Apparel\WooCommerce\Size_Guide_Assignments;
use Aggressive_Apparel\WooCommerce\Size_Guide_Grid;
use Aggressive_Apparel\WooCommerce\Size_Guide_Post_Type;
use Aggressive_Apparel\WooCommerce\Size_Guide_Rest;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use Aggressive_Apparel\Tests\Support\Saves_Spec_Registry;
use WP_UnitTestCase;

/**
 * Covers export, import and the grid.
 */
class TestProductSpecsTransfer extends WP_UnitTestCase {

	use Restores_Taxonomies;
	use Saves_Spec_Registry;

	/**
	 * A 1×1 PNG.
	 *
	 * @var string
	 */
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';

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

	/** The file names things the way another site can find them, with no IDs from this one. */
	public function test_export_is_portable(): void {
		$hoodies = $this->category( 'Hoodies', $this->category( 'Clothing' ) );
		$this->save_defaults( array( 'fabric' => 'Cotton' ) );
		$this->retype(
			'weight',
			array(
				'category_steps' => array(
					array(
						'category'   => $hoodies,
						'thresholds' => array( 240, 270, 330, 360 ),
					),
				),
			)
		);
		$guide = $this->setup_guide( $hoodies );

		$file   = Product_Specs_Transfer::export();
		$weight = array_column( $file['specs']['items'], null, 'key' )['weight'];
		$fit    = array_column( $file['specs']['items'], null, 'key' )['fit'];

		$this->assertSame( Product_Specs_Transfer::FORMAT, $file['format'] );
		$this->assertSame( 'clothing/hoodies', $weight['category_steps'][0]['category'] );
		$this->assertArrayNotHasKey( 'taxonomy', $fit );
		$this->assertArrayNotHasKey( 'step_terms', $fit );
		$this->assertSame( 'Heavy Tee', $file['guides'][0]['title'] );
		$this->assertSame( array( 'clothing/hoodies' ), $file['guides'][0]['categories'] );
		$this->assertSame( 'Polyester', $file['guides'][0]['specs']['fabric'] );
		$this->assertSame( self::PNG, $file['guides'][0]['diagram']['data'], 'The diagram travels in the file.' );
		$this->assertGreaterThan( 0, $guide );
	}

	/** Importing into a site without the setup adds it all; importing again changes nothing. */
	public function test_import_round_trip(): void {
		$hoodies = $this->category( 'Hoodies', $this->category( 'Clothing' ) );
		$this->save_defaults( array( 'fabric' => 'Cotton' ) );
		$guide = $this->setup_guide( $hoodies );
		$file  = Product_Specs_Transfer::read( json_decode( (string) wp_json_encode( Product_Specs_Transfer::export() ), true ) );
		$this->assertIsArray( $file );

		// A site without it: no guides, the built-in specs only.
		wp_delete_post( $guide, true );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();

		$plan = Product_Specs_Transfer::plan( $file );
		$this->assertSame( array( 'Heavy Tee' ), $plan['guides']['added'] );
		$this->assertSame( array(), $plan['missing'] );

		$this->assertIsArray( Product_Specs_Transfer::apply( $file ) );
		$ids = Size_Guide_Post_Type::ids();
		$this->assertCount( 1, $ids );
		$copy = Size_Guide_Rest::guide( $ids[0] );
		$this->assertSame( 'Polyester', $copy['specs']['fabric'] );
		$this->assertSame( 'Cotton', $this->items()['fabric']['default'] );
		$this->assertSame( array( $hoodies ), $copy['categories'] );
		$this->assertSame( 'Lay it flat.', $copy['notes'] );
		$this->assertNotNull( $copy['diagram'] );

		$again = Product_Specs_Transfer::plan( $file );
		$this->assertSame( array(), $again['guides']['added'] );
		$this->assertSame( array(), $again['guides']['changed'], 'Matched by name, and nothing differs.' );
		$this->assertSame( array(), $again['specs']['changed'] );

		$diagram = $copy['diagram']['id'];
		Product_Specs_Transfer::apply( $file );
		$this->assertSame( $diagram, Size_Guide_Rest::guide( $ids[0] )['diagram']['id'], 'The same image is reused, not uploaded again.' );
	}

	/** A changed value updates the matching guide; nothing only on this site is touched. */
	public function test_import_updates_and_keeps_the_rest(): void {
		$this->save_defaults( array() );
		$guide = $this->setup_guide( 0 );
		$other = $this->guide( 'Bucket Hat' );
		$file  = Product_Specs_Transfer::read( Product_Specs_Transfer::export() );
		$this->assertIsArray( $file );
		foreach ( $file['guides'] as $i => $entry ) {
			if ( 'Heavy Tee' === $entry['title'] ) {
				$file['guides'][ $i ]['specs']['fabric'] = 'Nylon';
			} else {
				unset( $file['guides'][ $i ] );
			}
		}
		$file['guides'] = array_values( $file['guides'] );
		$file['guides'][0]['categories'] = array( 'gone/nowhere' );

		$plan = Product_Specs_Transfer::plan( $file );
		$this->assertSame( array( 'Heavy Tee' ), $plan['guides']['changed'] );
		$this->assertSame( array( 'gone/nowhere' ), $plan['missing'] );

		Product_Specs_Transfer::apply( $file );
		$this->assertSame( 'Nylon', Product_Specs_Values::guide( $guide )['values']['fabric'] );
		$this->assertSame( 'publish', get_post_status( $other ), 'A guide only on this site stays.' );
	}

	/** A file from somewhere else, or a newer theme, is refused. */
	public function test_reads_only_its_own_files(): void {
		$this->assertWPError( Product_Specs_Transfer::read( array( 'format' => 'other' ) ) );
		$this->assertWPError(
			Product_Specs_Transfer::read(
				array(
					'format'  => Product_Specs_Transfer::FORMAT,
					'version' => Product_Specs_Transfer::VERSION + 1,
				)
			)
		);
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

	/**
	 * A guide with a chart, notes, a diagram, a value and a category.
	 *
	 * @param int $category Category ID, or 0.
	 */
	private function setup_guide( int $category ): int {
		$guide = $this->guide();
		$image = wp_upload_bits( 'diagram.png', null, (string) base64_decode( self::PNG, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Test image.
		$file  = self::factory()->attachment->create_object(
			$image['file'],
			$guide,
			array( 'post_mime_type' => 'image/png' )
		);
		Size_Guide_Rest::save(
			$guide,
			array(
				'chart'      => array(
					'unit'    => 'in',
					'columns' => array(
						array(
							'label' => 'Chest',
							'type'  => 'measure',
						),
					),
					'rows'    => array(
						array(
							'size'   => 'M',
							'values' => array( '20' ),
						),
					),
				),
				'notes'      => 'Lay it flat.',
				'diagram'    => $file,
				'specs'      => array( 'fabric' => 'Polyester' ),
				'categories' => $category > 0 ? array( $category ) : array(),
			)
		);
		$this->assertNotNull( Size_Chart::get( $guide ) );
		if ( $category > 0 ) {
			$this->assertSame( array( $category ), Size_Guide_Assignments::categories( $guide ) );
		}

		return $guide;
	}

	/**
	 * A product category.
	 *
	 * @param string $name   Name.
	 * @param int    $parent Parent ID.
	 */
	private function category( string $name, int $parent = 0 ): int {
		return self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => $name,
				'parent'   => $parent,
			)
		);
	}
}
