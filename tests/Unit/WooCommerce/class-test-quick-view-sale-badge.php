<?php
/**
 * Quick View sale badge markup tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Quick_View_Sale_Badge;
use WP_UnitTestCase;

/** Quick View draws the catalog's Badge Studio sale design, or its own chip. */
class Test_Quick_View_Sale_Badge extends WP_UnitTestCase {

	/** Seed the system badges and turn Product Badges on. */
	public function set_up(): void {
		parent::set_up();

		if ( ! taxonomy_exists( Custom_Badge_Taxonomy::TAXONOMY ) ) {
			( new Custom_Badge_Taxonomy() )->register_taxonomy();
		}
		delete_option( 'aggressive_apparel_system_badges_version' );
		Custom_Badge_Taxonomy::maybe_seed_system_badges();

		update_option( Feature_Settings::OPTION_KEY, array( 'product_badges' => true ) );
		delete_option( Custom_Badge_Taxonomy::RULES_OPTION );
		wp_cache_flush();
	}

	/** Restore the feature and rule options. */
	public function tear_down(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Custom_Badge_Taxonomy::RULES_OPTION );
		wp_cache_flush();

		parent::tear_down();
	}

	/**
	 * The opening tags of some markup, in document order, with their attributes.
	 *
	 * @param string $html Markup.
	 * @return array<int, array<string, string|true>> One attribute map per tag.
	 */
	private function tags( string $html ): array {
		$tags      = array();
		$processor = new \WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag() ) {
			$attrs = array();
			foreach ( $processor->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
				$value          = $processor->get_attribute( $name );
				$attrs[ $name ] = is_string( $value ) ? $value : true;
			}
			$tags[] = $attrs;
		}
		$this->assertNotEmpty( $tags );

		return $tags;
	}

	/**
	 * The tags carrying one class.
	 *
	 * @param array<int, array<string, string|true>> $tags  Tags from tags().
	 * @param string                                 $class_name Class to match.
	 * @return array<int, array<string, string|true>> Matching tags.
	 */
	private function with_class( array $tags, string $class_name ): array {
		return array_values(
			array_filter(
				$tags,
				static fn( array $tag ): bool => in_array( $class_name, explode( ' ', (string) ( $tag['class'] ?? '' ) ), true )
			)
		);
	}

	/**
	 * Term ID of the seeded sale badge.
	 *
	 * @return int Term ID.
	 */
	private function sale_term_id(): int {
		$terms = get_terms(
			array(
				'taxonomy'   => Custom_Badge_Taxonomy::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_key'   => 'badge_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test fixture lookup.
				'meta_value' => 'sale', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- test fixture lookup.
			)
		);
		$this->assertIsArray( $terms );
		$this->assertNotEmpty( $terms, 'The sale system badge should be seeded.' );

		return (int) $terms[0];
	}

	/** The catalog's sale design is rendered in its own corner, with a text slot. */
	public function test_uses_the_badge_studio_sale_design(): void {
		update_term_meta( $this->sale_term_id(), 'badge_position', 'top-right' );
		update_term_meta( $this->sale_term_id(), 'badge_bg_color', '#123456' );
		wp_cache_flush();

		$tags = $this->tags( Quick_View_Sale_Badge::markup() );
		$root = $tags[0];

		$this->assertContains( 'aggressive-apparel-product-badge__wrapper--top-right', explode( ' ', (string) $root['class'] ) );
		$this->assertContains( Quick_View_Sale_Badge::CONTAINER_CLASS, explode( ' ', (string) $root['class'] ) );
		$this->assertNotContains( Quick_View_Sale_Badge::CONTAINER_CLASS . '--default', explode( ' ', (string) $root['class'] ) );
		$this->assertTrue( $root['hidden'] );
		$this->assertSame( 'state.hasNoSaleBadge', $root['data-wp-bind--hidden'] );

		$badges = $this->with_class( $tags, 'aggressive-apparel-product-badge' );
		$this->assertCount( 1, $badges );
		$this->assertStringContainsString( '#123456', (string) $badges[0]['style'] );

		$slots = $this->with_class( $tags, Quick_View_Sale_Badge::TEXT_CLASS );
		$this->assertCount( 1, $slots );
		$this->assertSame( 'state.saleBadgeText', $slots[0]['data-wp-text'] );
		$this->assertGreaterThan( array_search( $badges[0], $tags, true ), array_search( $slots[0], $tags, true ), 'The text slot sits inside the designed badge.' );
	}

	/** An icon-only design takes its accessible name from the live badge text. */
	public function test_icon_only_design_binds_its_accessible_name(): void {
		update_term_meta( $this->sale_term_id(), 'badge_icon_position', 'only' );
		update_term_meta( $this->sale_term_id(), 'badge_icon', '🔥' );
		wp_cache_flush();

		$badges = $this->with_class( $this->tags( Quick_View_Sale_Badge::markup() ), 'aggressive-apparel-product-badge' );

		$this->assertCount( 1, $badges );
		$this->assertSame( 'img', $badges[0]['role'] );
		$this->assertSame( 'state.saleBadgeText', $badges[0]['data-wp-bind--aria-label'] );
	}

	/**
	 * Without the theme's sale badge, WooCommerce owns the sale flash and
	 * Quick View keeps its default chip.
	 *
	 * @dataProvider woocommerce_owns_the_sale_flash
	 *
	 * @param array<string, bool> $features Store Enhancements flags.
	 * @param array<string, bool> $rules    Badge rules.
	 */
	public function test_falls_back_to_the_default_chip( array $features, array $rules ): void {
		update_option( Feature_Settings::OPTION_KEY, $features );
		update_option( Custom_Badge_Taxonomy::RULES_OPTION, $rules );
		wp_cache_flush();

		$tags  = $this->tags( Quick_View_Sale_Badge::markup() );
		$class = explode( ' ', (string) $tags[0]['class'] );

		$this->assertContains( Quick_View_Sale_Badge::CONTAINER_CLASS . '--default', $class );
		$this->assertNotContains( 'aggressive-apparel-product-badge__wrapper', $class );
		$this->assertSame( 'state.hasNoSaleBadge', $tags[0]['data-wp-bind--hidden'] );
		$this->assertCount( 0, $this->with_class( $tags, 'aggressive-apparel-product-badge' ) );
		$this->assertCount( 1, $this->with_class( $tags, Quick_View_Sale_Badge::TEXT_CLASS ) );
	}

	/**
	 * Settings under which the theme does not draw the sale badge.
	 *
	 * @return array<string, array{array<string, bool>, array<string, bool>}>
	 */
	public function woocommerce_owns_the_sale_flash(): array {
		return array(
			'product badges off' => array( array(), array() ),
			'sale rule off'      => array( array( 'product_badges' => true ), array( 'sale_enabled' => false ) ),
		);
	}
}
