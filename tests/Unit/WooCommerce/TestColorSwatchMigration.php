<?php
/**
 * Colour swatch data and migration tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Color_Data_Manager;
use Aggressive_Apparel\WooCommerce\Color_Swatch_Migration;
use WP_UnitTestCase;

/**
 * Swatches live in WooCommerce's visual-attribute term meta (`color` /
 * `image`); the one-off migration moves the theme's old meta there, switches
 * pa_color to "Color / image", and keeps every term painted the colour the
 * theme already showed.
 */
class TestColorSwatchMigration extends WP_UnitTestCase {

	/**
	 * Register pa_color as a WooCommerce "select" attribute.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( Color_Swatch_Migration::OPTION );
		delete_option( Color_Swatch_Migration::FEATURE_OPTION );

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'woocommerce_attribute_taxonomies', array( 'attribute_name' => 'color' ) );
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_attribute_taxonomies',
			array(
				'attribute_name'    => 'color',
				'attribute_label'   => 'Color',
				'attribute_type'    => 'select',
				'attribute_orderby' => 'menu_order',
				'attribute_public'  => 1,
			)
		);
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );

		if ( ! taxonomy_exists( 'pa_color' ) ) {
			register_taxonomy( 'pa_color', 'product', array( 'hierarchical' => false ) );
		}
		Color_Data_Manager::register_hooks();
	}

	/**
	 * Remove the attribute row.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'woocommerce_attribute_taxonomies', array( 'attribute_name' => 'color' ) );
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
		Color_Data_Manager::flush_swatch_data_memo();

		parent::tearDown();
	}

	/**
	 * A colour term carrying the theme's old meta, without the creation hook
	 * filling in a swatch (as on a store before this change).
	 *
	 * @param string               $name Term name.
	 * @param array<string, mixed> $meta Old term meta.
	 * @return int Term ID.
	 */
	private function legacy_term( string $name, array $meta ): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'pa_color',
				'name'     => $name,
			)
		);
		delete_term_meta( $term_id, Color_Data_Manager::COLOR_META );
		foreach ( $meta as $key => $value ) {
			update_term_meta( $term_id, $key, $value );
		}

		return $term_id;
	}

	/**
	 * The attribute's stored type.
	 *
	 * @return string
	 */
	private function attribute_type(): string {
		$attribute = wc_get_attribute( wc_attribute_taxonomy_id_by_name( 'pa_color' ) );

		return $attribute ? (string) $attribute->type : '';
	}

	/**
	 * A stored meta value, bypassing the storefront fallback.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 * @return mixed Null when nothing is stored.
	 */
	private function stored( int $term_id, string $key ) {
		return get_metadata_raw( 'term', $term_id, $key, true );
	}

	/**
	 * Old colours and patterns land in WooCommerce's meta; a term with no
	 * colour stores nothing.
	 *
	 * @return void
	 */
	public function test_moves_each_kind_of_swatch(): void {
		$image = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$hex   = $this->legacy_term( 'Sky', array( 'color_value' => '#1E73BE' ) );
		$oklch = $this->legacy_term(
			'Signal',
			array(
				'color_value'  => 'oklch(62.8% 0.25768 29.2339)',
				'color_format' => 'oklch',
			)
		);
		$plaid = $this->legacy_term(
			'Plaid',
			array(
				'color_type'       => 'pattern',
				'color_pattern_id' => $image,
			)
		);
		$navy  = $this->legacy_term( 'Navy', array() );

		$this->assertTrue( Color_Swatch_Migration::run() );

		$this->assertSame( '#1e73be', $this->stored( $hex, 'color' ) );
		$this->assertSame( '#ff0000', $this->stored( $oklch, 'color' ), 'oklch() becomes the matching sRGB hex.' );
		$this->assertSame( $image, (int) $this->stored( $plaid, 'image' ) );
		$this->assertNull( $this->stored( $navy, 'color' ), 'A guess is served, never stored.' );
		$this->assertSame( '#1E73BE', $this->stored( $hex, 'color_value' ), 'Old meta stays as a rollback copy.' );
	}

	/**
	 * Edge shapes the old storefront still drew: an oklch() value without its
	 * format flag, a hex flagged as oklch, a short hex, and a pattern whose
	 * image was deleted (falls back to the term's colour, if any).
	 *
	 * @return void
	 */
	public function test_moves_edge_cases(): void {
		$unflagged  = $this->legacy_term( 'Signal', array( 'color_value' => 'oklch(62.8% 0.25768 29.2339)' ) );
		$misflagged = $this->legacy_term(
			'Ink',
			array(
				'color_value'  => '#1A2B3C',
				'color_format' => 'oklch',
			)
		);
		$lost_with  = $this->legacy_term(
			'Faded',
			array(
				'color_type'       => 'pattern',
				'color_pattern_id' => 999999,
				'color_value'      => '#123456',
			)
		);
		$lost_bare  = $this->legacy_term(
			'Navy Check',
			array(
				'color_type'       => 'pattern',
				'color_pattern_id' => 999999,
			)
		);
		$short_hex  = $this->legacy_term( 'Snow', array( 'color_value' => '#FFF' ) );

		Color_Swatch_Migration::run();

		$this->assertSame( '#ff0000', $this->stored( $unflagged, 'color' ) );
		$this->assertSame( '#1a2b3c', $this->stored( $misflagged, 'color' ), 'The value, not a stale flag, decides.' );
		$this->assertSame( '#123456', $this->stored( $lost_with, 'color' ) );
		$this->assertNull( $this->stored( $lost_with, 'image' ) );
		$this->assertNull( $this->stored( $lost_bare, 'color' ) );
		$this->assertSame( '#1e3a5f', get_term_meta( $lost_bare, 'color', true ), 'The storefront still gets the guess.' );
		$this->assertSame( '#fff', $this->stored( $short_hex, 'color' ) );
	}

	/**
	 * The feature turns on and pa_color becomes a "Color / image" attribute.
	 *
	 * @return void
	 */
	public function test_switches_woocommerce_to_visual_swatches(): void {
		$this->assertFalse( Color_Swatch_Migration::is_active() );

		$this->assertTrue( Color_Swatch_Migration::run() );

		$this->assertSame( 'yes', get_option( Color_Swatch_Migration::FEATURE_OPTION ) );
		$this->assertSame( Color_Swatch_Migration::VISUAL_TYPE, $this->attribute_type() );
		$this->assertTrue( Color_Swatch_Migration::is_active() );
		$this->assertSame( Color_Swatch_Migration::VERSION, (int) get_option( Color_Swatch_Migration::OPTION ) );
	}

	/**
	 * When WooCommerce silently keeps "select", the switch isn't recorded as
	 * done and retries later; the colours are copied regardless.
	 *
	 * @return void
	 */
	public function test_unfinished_switch_is_not_recorded(): void {
		$term = $this->legacy_term( 'Sky', array( 'color_value' => '#1e73be' ) );
		add_filter(
			'product_attributes_type_selector',
			static fn( $types ) => array_diff_key( (array) $types, array( 'wc-visual' => true ) )
		);

		$this->assertFalse( Color_Swatch_Migration::run() );

		$this->assertSame( 'select', $this->attribute_type() );
		$this->assertFalse( get_option( Color_Swatch_Migration::OPTION ) );
		$this->assertSame( '#1e73be', $this->stored( $term, 'color' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		( new Color_Swatch_Migration() )->maybe_run();
		$this->assertNotFalse( get_transient( Color_Swatch_Migration::RETRY ), 'Retries are spaced out.' );
		delete_transient( Color_Swatch_Migration::RETRY );
	}

	/**
	 * A swatch already set in WooCommerce is never overwritten.
	 *
	 * @return void
	 */
	public function test_keeps_existing_woocommerce_swatches(): void {
		$term = $this->legacy_term(
			'Sky',
			array(
				'color_value' => '#1e73be',
				'color'       => '#00ff00',
			)
		);

		Color_Swatch_Migration::run();

		$this->assertSame( '#00ff00', $this->stored( $term, 'color' ) );
	}

	/**
	 * It runs once, from a store manager's admin page.
	 *
	 * @return void
	 */
	public function test_runs_once_for_store_managers(): void {
		$migration = new Color_Swatch_Migration();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$migration->maybe_run();
		$this->assertFalse( get_option( Color_Swatch_Migration::OPTION ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$migration->maybe_run();
		$this->assertSame( Color_Swatch_Migration::VERSION, (int) get_option( Color_Swatch_Migration::OPTION ) );

		// Turned off afterwards: the move doesn't rerun and force it back on.
		update_option( Color_Swatch_Migration::FEATURE_OPTION, 'no' );
		$migration->maybe_run();
		$this->assertSame( 'no', get_option( Color_Swatch_Migration::FEATURE_OPTION ) );
		$this->assertFalse( Color_Swatch_Migration::is_active() );
	}

	/**
	 * Before the migration runs (or where WooCommerce can't draw swatches),
	 * every surface still shows the old colours: the theme's swatch data and
	 * WooCommerce's own storefront reads fall back to the old meta, then to a
	 * guess. Admin screens see only what is stored.
	 *
	 * @return void
	 */
	public function test_storefront_falls_back_before_migration(): void {
		$image = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$sky   = $this->legacy_term( 'Sky', array( 'color_value' => '#1E73BE' ) );
		$plaid = $this->legacy_term(
			'Plaid',
			array(
				'color_type'       => 'pattern',
				'color_pattern_id' => $image,
			)
		);
		$navy  = $this->legacy_term( 'Navy', array() );

		$colors = ( new Color_Data_Manager() )->get_color_terms();
		$this->assertSame( '#1e73be', $colors['sky']['value'] );
		$this->assertSame( wp_get_attachment_url( $image ), $colors['plaid']['value'] );
		$this->assertSame( '#1e3a5f', $colors['navy']['value'] );

		$this->assertSame( '#1e73be', get_term_meta( $sky, 'color', true ) );
		$this->assertSame( $image, (int) get_term_meta( $plaid, 'image', true ) );
		$this->assertSame( '', get_term_meta( $plaid, 'color', true ), 'A pattern term reports no colour.' );
		$this->assertSame( '#1e3a5f', get_term_meta( $navy, 'color', true ) );

		set_current_screen( 'edit-pa_color' );
		$this->assertSame( '', get_term_meta( $sky, 'color', true ), "WooCommerce's term fields show the stored value." );
		set_current_screen( 'front' );
	}

	/**
	 * A guessed swatch follows the name and an admin can clear a set one:
	 * nothing guessed is ever stored.
	 *
	 * @return void
	 */
	public function test_guesses_follow_renames_and_clear(): void {
		$term = wp_insert_term( 'Custom 1', 'pa_color' );
		$this->assertIsArray( $term );
		$id = (int) $term['term_id'];

		$this->assertNull( $this->stored( $id, 'color' ) );
		$this->assertSame( '#000000', get_term_meta( $id, 'color', true ) );

		wp_update_term( $id, 'pa_color', array( 'name' => 'Navy' ) );
		$this->assertSame( '#1e3a5f', get_term_meta( $id, 'color', true ), 'The guess follows a rename.' );

		update_term_meta( $id, 'color', '#abcdef' );
		$this->assertSame( '#abcdef', get_term_meta( $id, 'color', true ) );

		delete_term_meta( $id, 'color' );
		wp_update_term( $id, 'pa_color', array( 'name' => 'Navy' ) );
		$this->assertNull( $this->stored( $id, 'color' ), 'Clearing stays cleared.' );
		$this->assertSame( '#1e3a5f', get_term_meta( $id, 'color', true ) );
	}

	/**
	 * The swatch data every theme surface reads comes from WooCommerce's meta,
	 * an image winning over a colour.
	 *
	 * @return void
	 */
	public function test_swatch_data_reads_woocommerce_meta(): void {
		$image = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->legacy_term( 'Sky', array( 'color' => '#1e73be' ) );
		$this->legacy_term(
			'Plaid',
			array(
				'color' => '#000000',
				'image' => $image,
			)
		);

		$colors = ( new Color_Data_Manager() )->get_color_terms();

		$this->assertSame( '#1e73be', $colors['sky']['value'] );
		$this->assertSame( 'solid', $colors['sky']['type'] );
		$this->assertSame( 'pattern', $colors['plaid']['type'] );
		$this->assertSame( wp_get_attachment_url( $image ), $colors['plaid']['value'] );
	}

	/**
	 * Once a term has WooCommerce meta, the old meta (kept as a rollback copy)
	 * is never consulted: a migrated pattern switched to a solid colour in
	 * WooCommerce's fields shows that colour.
	 *
	 * @return void
	 */
	public function test_woocommerce_meta_outranks_the_rollback_copy(): void {
		$image = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$term  = $this->legacy_term(
			'Plaid',
			array(
				'color_type'       => 'pattern',
				'color_pattern_id' => $image,
			)
		);
		Color_Swatch_Migration::run();

		// What WooCommerce's term fields save when switching to a colour.
		delete_term_meta( $term, 'image' );
		update_term_meta( $term, 'color', '#336699' );
		Color_Data_Manager::flush_swatch_data_memo();

		$colors = ( new Color_Data_Manager() )->get_color_terms();
		$this->assertSame( 'solid', $colors['plaid']['type'] );
		$this->assertSame( '#336699', $colors['plaid']['value'] );
		$this->assertSame( '', get_term_meta( $term, 'image', true ), 'WooCommerce draws no image either.' );
	}

	/**
	 * Hex and oklch() values convert to the hex WooCommerce keeps, read from
	 * the value itself.
	 *
	 * @return void
	 */
	public function test_to_hex(): void {
		$this->assertSame( '#abcdef', Color_Data_Manager::to_hex( '#ABCDEF' ) );
		$this->assertSame( '#000000', Color_Data_Manager::to_hex( 'oklch(0% 0 0)' ) );
		$this->assertSame( '#ffffff', Color_Data_Manager::to_hex( 'oklch(100% 0 0)' ) );
		$this->assertSame( '#ff0000', Color_Data_Manager::to_hex( 'oklch(0.628 0.25768 29.2339 / 0.5)' ) );
		$this->assertSame( '', Color_Data_Manager::to_hex( 'oklch(nope)' ) );
		$this->assertSame( '', Color_Data_Manager::to_hex( 'red' ) );
	}
}
