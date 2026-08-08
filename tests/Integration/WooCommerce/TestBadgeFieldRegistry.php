<?php
/**
 * Badge field registry and the taxonomy save path.
 *
 * The save path had no coverage at all before the registry refactor, so the
 * existing suite could not have caught a regression in it. These tests drive
 * save_fields() through $_POST the way the taxonomy screen does and assert on
 * stored term meta, which is what a later page load actually reads.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Badge_Field_Registry;
use Aggressive_Apparel\WooCommerce\Badge_Style_Schema;
use Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Badge_Field_Registry
 */
class TestBadgeFieldRegistry extends WP_UnitTestCase {

	private int $term_id = 0;

	private Custom_Badge_Taxonomy $taxonomy;

	/**
	 * Create a badge term and authenticate as someone allowed to edit it.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->taxonomy = new Custom_Badge_Taxonomy();
		$this->taxonomy->register_taxonomy();

		$term = wp_insert_term( 'Registry Test ' . wp_rand(), Custom_Badge_Taxonomy::TAXONOMY );
		$this->assertIsArray( $term );
		$this->term_id = (int) $term['term_id'];
	}

	/**
	 * Clear posted state between tests.
	 */
	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Post a badge form and run the save handler.
	 *
	 * @param array<string, mixed> $fields Raw `badge_*` values.
	 */
	private function submit( array $fields ): void {
		$_POST            = $fields;
		$_POST['_wpnonce'] = wp_create_nonce( 'update-tag_' . $this->term_id );

		$this->taxonomy->save_fields( $this->term_id );
	}

	/**
	 * Read a stored badge meta value.
	 *
	 * @param string $field Meta key.
	 */
	private function stored( string $field ): string {
		return (string) get_term_meta( $this->term_id, $field, true );
	}

	/** Every field the schema knows about is declared in the registry. */
	public function test_registry_covers_the_whole_schema(): void {
		$this->assertSame(
			array(),
			array_diff(
				array_keys( Badge_Style_Schema::base_defaults() ),
				array_keys( Badge_Field_Registry::fields() )
			),
			'Schema default has no registry entry.'
		);
	}

	/** Field keys are the `badge_*` names the form and meta both use. */
	public function test_field_keys_are_badge_prefixed(): void {
		$keys = Badge_Field_Registry::field_keys();

		$this->assertSame( 'badge_bg_color', $keys['bg_color'] );
		$this->assertSame( 'badge_offset_x', $keys['offset_x'] );
		// The one field whose key is not `badge_` + schema key.
		$this->assertSame( 'badge_type', $keys['badge_type'] );
	}

	/** A posted form round-trips through storage unchanged. */
	public function test_save_round_trips_valid_values(): void {
		$this->submit(
			array(
				'badge_bg_color'     => '#123456',
				'badge_text_color'   => '#abcdef',
				'badge_padding_x'    => '12',
				'badge_offset_x'     => '-20',
				'badge_letter_spacing' => 'extra-wide',
				'badge_glass'        => '1',
			)
		);

		$this->assertSame( '#123456', $this->stored( 'badge_bg_color' ) );
		$this->assertSame( '#abcdef', $this->stored( 'badge_text_color' ) );
		$this->assertSame( '12', $this->stored( 'badge_padding_x' ) );
		$this->assertSame( '-20', $this->stored( 'badge_offset_x' ) );
		$this->assertSame( 'extra-wide', $this->stored( 'badge_letter_spacing' ) );
		$this->assertSame( '1', $this->stored( 'badge_glass' ) );
	}

	/** Out-of-range numbers are clamped to the registry bounds, not stored raw. */
	public function test_save_clamps_out_of_range_numbers(): void {
		$this->submit(
			array(
				'badge_padding_x'  => '9999',
				'badge_frame_width' => '99',
				'badge_rotation'   => '-999',
				'badge_icon_size'  => '500',
			)
		);

		$this->assertSame( '50', $this->stored( 'badge_padding_x' ) );
		$this->assertSame( '8', $this->stored( 'badge_frame_width' ) );
		$this->assertSame( '-45', $this->stored( 'badge_rotation' ) );
		$this->assertSame( '64', $this->stored( 'badge_icon_size' ) );
	}

	/** Unknown enum values fall back rather than reaching storage. */
	public function test_save_rejects_unknown_enum_values(): void {
		$this->submit(
			array(
				'badge_position'   => 'somewhere-else',
				'badge_shape'      => '"><script>',
				'badge_fill_mode'  => 'plaid',
			)
		);

		$this->assertSame( 'top-left', $this->stored( 'badge_position' ) );
		$this->assertSame( 'rect', $this->stored( 'badge_shape' ) );
		$this->assertSame( 'solid', $this->stored( 'badge_fill_mode' ) );
	}

	/** Colours outside the allowlist are replaced by the declared fallback. */
	public function test_save_rejects_unsafe_colors(): void {
		$this->submit(
			array(
				'badge_bg_color'    => 'url(javascript:alert(1))',
				'badge_shadow_color' => 'rgba(0,0,0,.5)',
			)
		);

		$this->assertSame( '#000000', $this->stored( 'badge_bg_color' ) );
		$this->assertSame( '', $this->stored( 'badge_shadow_color' ) );
	}

	/** CSS `double` needs 3px to paint two lines, so a thinner width is raised. */
	public function test_save_bumps_double_border_width(): void {
		$this->submit(
			array(
				'badge_border_style' => 'double',
				'badge_border_width' => '1',
			)
		);

		$this->assertSame( 'double', $this->stored( 'badge_border_style' ) );
		$this->assertSame( '3', $this->stored( 'badge_border_width' ) );
	}

	/** A zero width means "no border" and must not be bumped. */
	public function test_save_leaves_zero_border_width_alone(): void {
		$this->submit(
			array(
				'badge_border_style' => 'double',
				'badge_border_width' => '0',
			)
		);

		$this->assertSame( '0', $this->stored( 'badge_border_width' ) );
	}

	/** Emoji icons are truncated so a pasted essay cannot reach the badge. */
	public function test_save_truncates_emoji_icon(): void {
		$this->submit( array( 'badge_icon' => str_repeat( 'x', 50 ) ) );

		$this->assertSame( 10, mb_strlen( $this->stored( 'badge_icon' ) ) );
	}

	/** A library icon that does not exist is cleared, never echoed back. */
	public function test_save_clears_unknown_library_icon(): void {
		$this->submit( array( 'badge_library_icon' => 'not-a-real-icon' ) );

		$this->assertSame( '', $this->stored( 'badge_library_icon' ) );
	}

	/** System badges own their type: the editor must never write it back. */
	public function test_save_never_writes_badge_type(): void {
		update_term_meta( $this->term_id, 'badge_type', 'sale' );

		$this->submit( array( 'badge_type' => 'custom' ) );

		$this->assertSame(
			'sale',
			$this->stored( 'badge_type' ),
			'A posted badge_type overwrote a system badge type.'
		);
	}

	/** Without a valid nonce nothing is written. */
	public function test_save_requires_a_valid_nonce(): void {
		$_POST = array(
			'badge_bg_color' => '#ff0000',
			'_wpnonce'       => 'not-a-nonce',
		);

		$this->taxonomy->save_fields( $this->term_id );

		$this->assertSame( '', $this->stored( 'badge_bg_color' ) );
	}

	/** Without the capability nothing is written, even with a good nonce. */
	public function test_save_requires_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->submit( array( 'badge_bg_color' => '#ff0000' ) );

		$this->assertSame( '', $this->stored( 'badge_bg_color' ) );
	}

	/** An unsent field stores its declared default, not a coerced empty string. */
	public function test_absent_field_stores_its_default(): void {
		$this->submit( array( 'badge_bg_color' => '#111111' ) );

		// absint('') would be 0 here; the registry default is 4.
		$this->assertSame( '4', $this->stored( 'badge_radius_tl' ) );
		$this->assertSame( '135', $this->stored( 'badge_gradient_angle' ) );
	}
}
