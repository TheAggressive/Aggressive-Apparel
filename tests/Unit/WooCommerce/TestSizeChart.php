<?php
/**
 * Structured size chart and Size Guides editor REST tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Size_Chart;
use Aggressive_Apparel\WooCommerce\Size_Chart_Import;
use Aggressive_Apparel\WooCommerce\Size_Guide_Assignments;
use Aggressive_Apparel\WooCommerce\Size_Guide_Post_Type;
use Aggressive_Apparel\WooCommerce\Size_Guide_Rest;
use WP_UnitTestCase;

/**
 * Covers chart parsing, conversion, accessible markup, and guide editing.
 */
class TestSizeChart extends WP_UnitTestCase {

	/**
	 * Enable the feature and register the post type.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		if ( ! post_type_exists( Size_Guide_Post_Type::POST_TYPE ) ) {
			( new Size_Guide_Post_Type() )->register();
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );

		parent::tearDown();
	}

	/** Values in common formats parse to numbers. */
	public function test_numbers(): void {
		$this->assertSame( array( 11.75 ), Size_Chart::numbers( '11 3/4' ) );
		$this->assertSame( array( 11.75 ), Size_Chart::numbers( '11¾' ) );
		$this->assertSame( array( 0.75 ), Size_Chart::numbers( '3/4' ) );
		$this->assertSame( array( 38.0, 40.0 ), Size_Chart::numbers( '38-40' ) );
		$this->assertSame( array(), Size_Chart::numbers( 'S/M' ) );
	}

	/** Conversion rounds sensibly and keeps ranges; text stays as typed. */
	public function test_convert(): void {
		$this->assertSame( '30', Size_Chart::convert( '11¾', 'in' ) );
		$this->assertSame( '96.5–101.5', Size_Chart::convert( '38-40', 'in' ) );
		$this->assertSame( '42', Size_Chart::convert( '106.5', 'cm' ) );
		$this->assertSame( '11½', Size_Chart::convert( '29', 'cm' ) );
		$this->assertSame( 'One size', Size_Chart::convert( 'One size', 'cm' ) );
	}

	/** Typed fractions show as glyphs; ranges and other text are untouched. */
	public function test_display_value(): void {
		$this->assertSame( '17 ½', Size_Chart::display_value( '17 1/2' ) );
		$this->assertSame( '12 ½', Size_Chart::display_value( '12-1/2' ) );
		$this->assertSame( '⅜', Size_Chart::display_value( '3/8' ) );
		$this->assertSame( '38-40', Size_Chart::display_value( '38-40' ) );
		$this->assertSame( '11 ¾', Size_Chart::display_value( '11 ¾' ) );
	}

	/** Size lines: capitals become sentence case, fractions glyphs, both units. */
	public function test_size_lines(): void {
		$lines = Size_Chart::size_lines(
			Size_Chart::sanitize(
				array(
					'unit'    => 'in',
					'columns' => array(
						array(
							'label' => '1/2 WAIST WIDTH',
							'type'  => 'measure',
						),
						array(
							'label' => 'US',
							'type'  => 'text',
						),
					),
					'rows'    => array(
						array(
							'size'   => '2XL',
							'values' => array( '17 1/2', '38' ),
						),
					),
				)
			)
		);

		$this->assertSame( 'Size 2XL', $lines[0]['title'] );
		$this->assertSame(
			array(
				array(
					'label' => '½ waist width',
					'value' => '17 ½ in',
				),
				array(
					'label' => 'US',
					'value' => '38',
				),
			),
			$lines[0]['in']
		);
		$this->assertSame( '44.5 cm', $lines[0]['cm'][0]['value'] );
	}

	/** Sanitizing drops sizeless rows and squares values to the columns. */
	public function test_sanitize(): void {
		$chart = Size_Chart::sanitize(
			array(
				'unit'    => 'mm',
				'columns' => array(
					array(
						'label' => 'Chest',
						'type'  => 'measure',
					),
					array(
						'label' => 'US',
						'type'  => 'text',
					),
				),
				'rows'    => array(
					array(
						'size'   => 'M',
						'values' => array( '42' ),
					),
					array(
						'size'   => '',
						'values' => array( '1', '2' ),
					),
				),
			)
		);

		$this->assertSame( 'in', $chart['unit'] );
		$this->assertCount( 1, $chart['rows'] );
		$this->assertSame( array( '42', '' ), $chart['rows'][0]['values'] );
		$this->assertSame( 'text', $chart['columns'][1]['type'] );
	}

	/** The chart renders an accessible table with both units in the markup. */
	public function test_render_markup(): void {
		$guide_id = $this->guide( 'Jersey Tee' );
		update_post_meta(
			$guide_id,
			Size_Chart::META,
			Size_Chart::sanitize(
				array(
					'unit'    => 'in',
					'columns' => array(
						array(
							'label' => 'Chest',
							'type'  => 'measure',
						),
					),
					'rows'    => array(
						array(
							'size'   => '2XL',
							'values' => array( '26' ),
						),
					),
				)
			)
		);

		$html = Size_Chart::render( $guide_id );

		$this->assertStringContainsString( '<caption class="screen-reader-text">Size chart', $html );
		$this->assertStringNotContainsString( 'Jersey Tee', $html, 'The guide name is admin-only.' );
		$this->assertStringContainsString( '<th scope="col">Chest</th>', $html, 'Units sit beside values, not in headers.' );
		$this->assertStringContainsString( '<tr data-size="2xl"><th scope="row">2XL <span class="screen-reader-text aa-size-chart__current" hidden>', $html );
		$this->assertStringContainsString( 'data-show="in">26&nbsp;<span class="aa-size-chart__unit">in</span>', $html );
		$this->assertStringContainsString( 'data-show="cm">66&nbsp;<span class="aa-size-chart__unit">cm</span>', $html );
		$switch = Size_Chart::unit_switch( 'in' );
		$this->assertStringContainsString( 'role="group" aria-label="Measurement units"', $switch );
		$this->assertStringContainsString( 'data-unit="in" aria-pressed="true"', $switch );

		$resolved = \Aggressive_Apparel\WooCommerce\Size_Guide::resolved( 0 );
		$this->assertSame( 0, $resolved['id'], 'No assignment, no guide.' );
		$this->assertStringContainsString( 'aria-live="polite"', $html );
	}

	/** Units come from headers first, then unambiguous words, then magnitude. */
	public function test_import_detects_unit(): void {
		$table = static fn( string $head, string $value ): string => '<table><tr><th>Size</th><th>' . $head . '</th></tr><tr><td>M</td><td>' . $value . '</td></tr><tr><td>L</td><td>' . $value . '</td></tr></table>';

		$this->assertSame( 'cm', Size_Chart_Import::from_html( $table( 'Chest (cm)', '20' ) )['chart']['unit'] ?? '' );
		$this->assertSame( 'in', Size_Chart_Import::from_html( $table( 'Chest (in)', '60' ) )['chart']['unit'] ?? '' );
		$this->assertSame( 'cm', Size_Chart_Import::from_html( '<p>All measurements in centimetres.</p>' . $table( 'Chest', '20' ) )['chart']['unit'] ?? '' );
		// A bare "in" in a sentence is not a unit; the numbers decide.
		$this->assertSame( 'cm', Size_Chart_Import::from_html( '<p>Measure in a mirror.</p>' . $table( 'Chest', '106' ) )['chart']['unit'] ?? '' );
		$this->assertSame( 'in', Size_Chart_Import::from_html( $table( 'Chest', '21 1/2' ) )['chart']['unit'] ?? '' );
	}

	/** A same-size second table is the other unit; a different one goes to notes. */
	public function test_import_multiple_tables(): void {
		$inches = '<table><thead><tr><th>Size</th><th>Chest</th></tr></thead><tbody><tr><td>S</td><td>19</td></tr><tr><td>M</td><td>21</td></tr></tbody></table>';
		$cm     = '<table><thead><tr><th>Size</th><th>Chest</th></tr></thead><tbody><tr><td>S</td><td>48</td></tr><tr><td>M</td><td>53</td></tr></tbody></table>';
		$youth  = '<table><thead><tr><th>Size</th><th>Chest</th></tr></thead><tbody><tr><td>YS</td><td>14</td></tr></tbody></table>';

		$same = Size_Chart_Import::from_html( $inches . $cm );
		$this->assertNotNull( $same );
		$this->assertCount( 2, $same['chart']['rows'] );
		$this->assertSame( '', $same['notes'] );

		$other = Size_Chart_Import::from_html( '<p>Fits true to size.</p>' . $inches . $youth );
		$this->assertNotNull( $other );
		$this->assertSame( "Fits true to size.\n\nYS — Chest 14", $other['notes'] );
	}

	/** A same-size table with other numbers is not a unit copy: it is kept. */
	public function test_import_keeps_a_different_table_with_the_same_sizes(): void {
		$body    = '<table><thead><tr><th>Size</th><th>Chest</th></tr></thead><tbody><tr><td>S</td><td>19</td></tr><tr><td>M</td><td>21</td></tr></tbody></table>';
		$garment = '<table><thead><tr><th>Size</th><th>Chest</th></tr></thead><tbody><tr><td>S</td><td>22</td></tr><tr><td>M</td><td>24</td></tr></tbody></table>';

		$imported = Size_Chart_Import::from_html( $body . $garment );

		$this->assertNotNull( $imported );
		$this->assertStringContainsString( 'S — Chest 22', $imported['notes'] );
		$this->assertFalse( $imported['lossless'], 'A table flattened to notes is not automatic.' );
	}

	/** Text keeps document order and link addresses; one library image becomes the diagram. */
	public function test_import_notes_links_and_diagram(): void {
		$image = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		update_post_meta( $image, '_wp_attached_file', '2026/10/measure.jpg' );

		$imported = Size_Chart_Import::from_html(
			'<h3>How to measure</h3><p>Lay it flat. See <a href="https://example.com/fit">fit notes</a>.</p>'
			. '<figure class="wp-block-image"><img class="wp-image-' . $image . '" src="https://example.com/measure.jpg" alt=""></figure>'
			. '<table><tr><th>Size</th><th>Chest</th></tr><tr><td>M</td><td>21</td></tr></table>'
		);

		$this->assertNotNull( $imported );
		$this->assertSame( "How to measure\n\nLay it flat. See fit notes (https://example.com/fit).", $imported['notes'] );
		$this->assertSame( $image, $imported['diagram'] );
		$this->assertTrue( $imported['lossless'] );
	}

	/** Guides with content the chart can't hold are left for a manual convert. */
	public function test_migrate_skips_guides_that_would_lose_content(): void {
		$guide_id = $this->guide( 'Video guide', '<table><tr><th>Size</th><th>Chest</th></tr><tr><td>M</td><td>21</td></tr></table><iframe src="https://example.com/video"></iframe>' );

		$this->assertFalse( Size_Chart_Import::migrate( $guide_id ) );
		$this->assertNull( Size_Chart::get( $guide_id ) );
		$this->assertNotNull( Size_Chart_Import::from_html( (string) get_post_field( 'post_content', $guide_id ) ), 'Still convertible from the editor.' );
	}

	/** Usage for many guides comes from one grouped lookup. */
	public function test_usage_map(): void {
		$first  = $this->guide( 'Tees' );
		$second = $this->guide( 'Hoodies' );
		$term   = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Hoodies',
			)
		);
		for ( $i = 0; $i < 2; $i++ ) {
			$product = self::factory()->post->create( array( 'post_type' => 'product' ) );
			update_post_meta( $product, \Aggressive_Apparel\WooCommerce\Size_Guide::CPT_META_KEY, $first );
		}
		update_term_meta( $term, \Aggressive_Apparel\WooCommerce\Size_Guide::CPT_META_KEY, $second );
		Size_Guide_Assignments::set_default( $first, true );

		$map = Size_Guide_Assignments::usage_map( array( $first, $second ) );

		$this->assertSame( 2, $map[ $first ]['products'] );
		$this->assertTrue( $map[ $first ]['global'] );
		$this->assertSame( array( 'Hoodies' ), $map[ $second ]['categories'] );
		$this->assertSame( 0, $map[ $second ]['products'] );
	}

	/** Size attribute names get a "Size guide" link when the product has a guide. */
	public function test_size_label_link(): void {
		$link    = new \Aggressive_Apparel\WooCommerce\Size_Guide_Label_Link();
		$product = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$label   = '<span class="wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute-name" id="x_label">Size</span>';
		$block   = new \WP_Block( array( 'blockName' => 'core/paragraph' ) );
		$GLOBALS['post'] = get_post( $product ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the block renders in the product's loop.

		$this->assertTrue( \Aggressive_Apparel\WooCommerce\Size_Guide_Label_Link::is_size( 'Taille' ) );
		$this->assertFalse( \Aggressive_Apparel\WooCommerce\Size_Guide_Label_Link::is_size( 'Color' ) );
		$this->assertSame( $label, $link->render( $label, array(), $block ), 'No guide, no link.' );

		update_post_meta( $product, \Aggressive_Apparel\WooCommerce\Size_Guide::CPT_META_KEY, $this->guide( 'Tees', '<p>Measure flat.</p>' ) );
		( new \Aggressive_Apparel\WooCommerce\Size_Guide() )->flush_all_caches();
		$html = $link->render( $label, array(), $block );

		$this->assertStringContainsString( $label . '<button type="button" class="aa-size-label__link is-pending" aria-haspopup="dialog">', $html );
		$this->assertStringContainsString( '<span class="screen-reader-text">Size </span><span aria-hidden="true">(</span>Guide<span aria-hidden="true">)</span></button>', $html );
		$this->assertSame( str_replace( 'Size<', 'Color<', $label ), $link->render( str_replace( 'Size<', 'Color<', $label ), array(), $block ) );
		unset( $GLOBALS['post'] );
	}

	/** Every guide is migrated, beyond a single query page. */
	public function test_migrate_all_pages_through_every_guide(): void {
		$table = '<table><tr><th>Size</th><th>Chest</th></tr><tr><td>M</td><td>21</td></tr></table>';
		$ids   = array();
		for ( $i = 0; $i < 55; $i++ ) {
			$ids[] = $this->guide( 'Guide ' . $i, $table );
		}

		$this->assertSame( 55, Size_Chart_Import::migrate_all() );
		$this->assertNotNull( Size_Chart::get( (int) end( $ids ) ) );
		$this->assertSame( 0, Size_Chart_Import::migrate_all(), 'Converted guides are left alone.' );
	}

	/** A guide is created, edited, duplicated, and trashed in one place. */
	public function test_rest_lifecycle(): void {
		$rest = new Size_Guide_Rest();
		$term = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );

		$created = $rest->create(
			$this->request(
				'POST',
				array(
					'title'      => 'Pullover Hoodie',
					'chart'      => array(
						'unit'    => 'cm',
						'columns' => array(
							array(
								'label' => 'Chest',
								'type'  => 'measure',
							),
						),
						'rows'    => array(
							array(
								'size'   => 'M',
								'values' => array( '112' ),
							),
						),
					),
					'notes'      => 'Measure flat.',
					'specs'      => array(
						'fabric' => '100% cotton',
						'fit'    => '9',
					),
					'categories' => array( $term ),
					'isDefault'  => true,
				)
			)
		)->get_data();

		$id = (int) $created['id'];
		$this->assertSame( 'Pullover Hoodie', $created['title'] );
		$this->assertSame( 'cm', $created['chart']['unit'] );
		$this->assertSame( array( $term ), $created['categories'] );
		$this->assertSame( '100% cotton', $created['specs']['fabric'] );
		$this->assertSame( '', $created['specs']['fit'], 'Out-of-range steps are dropped.' );
		$this->assertTrue( Size_Guide_Assignments::is_default( $id ) );

		$copy = $rest->duplicate( $this->request( 'POST', array(), $id ) )->get_data();
		$this->assertSame( 'Pullover Hoodie (copy)', $copy['title'] );
		$this->assertSame( '112', $copy['chart']['rows'][0]['values'][0] );
		$this->assertSame( '100% cotton', $copy['specs']['fabric'], 'A copy keeps its specs.' );
		$this->assertSame( array(), $copy['categories'], 'A copy does not take over assignments.' );

		$rest->trash( $this->request( 'DELETE', array(), $id ) );
		$this->assertSame( 'trash', get_post_status( $id ) );
		$this->assertFalse( Size_Guide_Assignments::is_default( $id ) );
		$this->assertSame( array(), Size_Guide_Assignments::categories( $id ) );
	}

	/** Guides without a chart keep rendering their old content. */
	public function test_legacy_guides_still_render(): void {
		$guide_id = $this->guide( 'Old', '<p>Legacy chart</p>' );
		$method   = new \ReflectionMethod( \Aggressive_Apparel\WooCommerce\Size_Guide::class, 'guide_content' );
		$content  = $method->invoke( new \Aggressive_Apparel\WooCommerce\Size_Guide(), $guide_id );

		$this->assertIsArray( $content );
		$this->assertStringContainsString( 'Legacy chart', $content['html'] );
		$this->assertFalse( $content['chart'] );
		$this->assertTrue( Size_Guide_Rest::summaries()[0]['legacy'] ?? false );
	}

	/**
	 * A published size guide.
	 *
	 * @param string $title   Title.
	 * @param string $content Content.
	 */
	private function guide( string $title, string $content = '' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => Size_Guide_Post_Type::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * A JSON REST request, optionally for one guide.
	 *
	 * @param string               $method HTTP method.
	 * @param array<string, mixed> $body   JSON body.
	 * @param int                  $id     Guide ID.
	 * @return \WP_REST_Request<array<string, mixed>>
	 */
	private function request( string $method, array $body, int $id = 0 ): \WP_REST_Request {
		$request = new \WP_REST_Request( $method, '/aggressive-apparel/v1/size-guides' . ( $id > 0 ? '/' . $id : '' ) );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		if ( $id > 0 ) {
			$request->set_url_params( array( 'id' => (string) $id ) );
		}

		return $request;
	}
}
