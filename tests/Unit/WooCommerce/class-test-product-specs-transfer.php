<?php
/**
 * Moving the Size & Specs setup between sites.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Printful_Import;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Taxonomy;
use Aggressive_Apparel\WooCommerce\Product_Specs_Transfer;
use Aggressive_Apparel\WooCommerce\Product_Specs_Values;
use Aggressive_Apparel\WooCommerce\Size_Chart;
use Aggressive_Apparel\WooCommerce\Size_Guide_Assignments;
use Aggressive_Apparel\WooCommerce\Size_Guide_Post_Type;
use Aggressive_Apparel\WooCommerce\Size_Guide_Rest;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use Aggressive_Apparel\Tests\Support\Saves_Spec_Registry;
use WP_UnitTestCase;

/**
 * Covers export and import.
 */
class Test_Product_Specs_Transfer extends WP_UnitTestCase {

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
		Product_Specs_Transfer::flush();
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
		$file['guides']                  = array_values( $file['guides'] );
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

	/** Names with apostrophes and ampersands match as stored, so nothing is duplicated. */
	public function test_names_match_as_stored(): void {
		$this->save_defaults( array() );
		$guide = $this->guide( "Men's Tee & Hoodie" );
		$file  = $this->file();

		$this->assertSame( array(), Product_Specs_Transfer::plan( $file )['guides']['added'] );
		Product_Specs_Transfer::apply( $file );
		$this->assertSame( array( $guide ), Size_Guide_Post_Type::ids() );
	}

	/** A diagram the file couldn't carry, or that won't import, never removes the guide's own. */
	public function test_guides_keep_their_diagram(): void {
		$this->save_defaults( array() );
		$guide   = $this->setup_guide( 0 );
		$diagram = (int) get_post_meta( $guide, Size_Chart::DIAGRAM, true );
		$file    = $this->file();

		$file['guides'][0]['diagram'] = array( 'skipped' => true );
		$file['guides'][0]['notes']   = 'Changed.';
		$this->assertIsArray( Product_Specs_Transfer::apply( $file ) );
		$this->assertSame( $diagram, (int) get_post_meta( $guide, Size_Chart::DIAGRAM, true ) );

		$file['guides'][0]['diagram'] = array(
			'name' => 'x.png',
			'hash' => 'different',
			'data' => base64_encode( 'not an image' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Test data.
		);
		$done                         = Product_Specs_Transfer::apply( $file );
		$this->assertIsArray( $done );
		$this->assertCount( 1, $done['problems'], 'It says so.' );
		$this->assertSame( $diagram, (int) get_post_meta( $guide, Size_Chart::DIAGRAM, true ) );
	}

	/** Content and names keep their backslashes (block attributes are JSON). */
	public function test_backslashes_survive(): void {
		$this->save_defaults( array() );
		$content = '<!-- wp:paragraph {"placeholder":"\u003cb\u003e"} --><p>Old</p><!-- /wp:paragraph -->';
		$file    = Product_Specs_Transfer::read(
			array(
				'format' => Product_Specs_Transfer::FORMAT,
				'guides' => array(
					array(
						'title'   => 'Back\slash Tee',
						'content' => $content,
					),
				),
			)
		);
		$this->assertIsArray( $file );

		Product_Specs_Transfer::apply( $file );
		$post = get_post( Size_Guide_Post_Type::ids()[0] );
		$this->assertSame( 'Back\slash Tee', $post->post_title );
		$this->assertSame( $content, $post->post_content );
	}

	/** A Printful guide never takes over another product's guide that shares its name. */
	public function test_sourced_guides_match_their_own_product(): void {
		$this->save_defaults( array() );
		$other = $this->guide( 'Unisex Hoodie' );
		update_post_meta(
			$other,
			Printful_Import::SOURCE,
			array(
				'provider' => 'printful',
				'id'       => 456,
				'title'    => 'Other',
			) 
		);
		update_post_meta( $other, Printful_Import::SOURCE_KEY, 'printful:456' );
		$plain = $this->guide( 'Unisex Tee' );
		$file  = $this->file();
		foreach ( $file['guides'] as $i => $entry ) {
			$file['guides'][ $i ]['source'] = array(
				'provider' => 'printful',
				'id'       => 'Unisex Hoodie' === $entry['title'] ? 123 : 789,
				'title'    => '',
			);
		}

		$plan = Product_Specs_Transfer::plan( $file );
		$this->assertSame( array( 'Unisex Hoodie' ), $plan['guides']['added'], 'Product 123 is not product 456.' );

		Product_Specs_Transfer::apply( $file );
		$this->assertSame( 789, Printful_Import::source( $plain )['id'], 'A guide matched by name takes the file’s source.' );
		$again = Product_Specs_Transfer::plan( $file );
		$this->assertSame( array(), $again['guides']['added'] );
		$this->assertSame( array(), $again['guides']['changed'], 'And then settles.' );
	}

	/** The dimensions setting is shown as a change, and applied. */
	public function test_settings_are_shown_and_applied(): void {
		$this->save_defaults( array() );
		$file                             = $this->file();
		$file['specs']['hide_dimensions'] = true;

		$this->assertCount( 1, Product_Specs_Transfer::plan( $file )['settings'] );
		Product_Specs_Transfer::apply( $file );
		$this->assertTrue( Product_Specs_Registry::get()['hide_dimensions'] );
	}

	/** The file is uploaded once and imported by its token, once. */
	public function test_routes_upload_once_then_import_by_token(): void {
		$this->save_defaults( array() );
		$this->guide( 'Heavy Tee' );
		$path = wp_tempnam( 'specs.json' );
		file_put_contents( $path, (string) wp_json_encode( Product_Specs_Transfer::export() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test upload.
		wp_delete_post( Size_Guide_Post_Type::ids()[0], true );

		$settings = new Product_Specs_Settings();
		$request  = new \WP_REST_Request( 'POST' );
		$request->set_file_params(
			array(
				'file' => array(
					'tmp_name' => $path,
					'error'    => UPLOAD_ERR_OK,
				),
			)
		);
		$checked = $settings->import( $request )->get_data();
		$this->assertSame( array( 'Heavy Tee' ), $checked['plan']['guides']['added'] );

		$apply = new \WP_REST_Request( 'POST' );
		$apply->set_param( 'token', $checked['token'] );
		$this->assertNotWPError( $settings->import_apply( $apply ) );
		$this->assertCount( 1, Size_Guide_Post_Type::ids() );
		$this->assertWPError( $settings->import_apply( $apply ), 'A token works once.' );
		$this->assertWPError( $settings->import( new \WP_REST_Request( 'POST' ) ), 'No file, no import.' );
	}

	/**
	 * This site's export, read back as an imported file.
	 *
	 * @return array<string, mixed>
	 */
	private function file(): array {
		$file = Product_Specs_Transfer::read( json_decode( (string) wp_json_encode( Product_Specs_Transfer::export() ), true ) );
		$this->assertIsArray( $file );
		Product_Specs_Transfer::flush();

		return $file;
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
	 * @param int    $parent_id Parent ID.
	 */
	private function category( string $name, int $parent_id = 0 ): int {
		return self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => $name,
				'parent'   => $parent_id,
			)
		);
	}
}
