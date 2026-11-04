<?php
/**
 * A spec changing under its products and guides: a new type, and steps that
 * move, go, or get renamed.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Meta;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Taxonomy;
use Aggressive_Apparel\WooCommerce\Product_Specs_Values;
use Aggressive_Apparel\WooCommerce\Product_Specs_Wording_Sync;
use Aggressive_Apparel\WooCommerce\Size_Guide_Spec_Items;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use Aggressive_Apparel\Tests\Support\Saves_Spec_Registry;
use WP_UnitTestCase;

/**
 * Covers what keeps defaults and wording right as specs change.
 */
class TestProductSpecsChanges extends WP_UnitTestCase {

	use Restores_Taxonomies;
	use Saves_Spec_Registry;

	/**
	 * Enable the feature and start from the default registry.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		wp_cache_flush();
		delete_transient( 'wc_attribute_taxonomies' );
		Product_Specs_Registry::flush();
		Product_Specs_Taxonomy::flush();
		$this->snapshot_taxonomies();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Unregister attribute taxonomies a test created.
	 */
	public function tearDown(): void {
		$this->restore_taxonomies();
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		Product_Specs_Taxonomy::flush();

		parent::tearDown();
	}

	/** A scale's default left as saved becomes its step's name as text, never "2". */
	public function test_type_change_converts_the_saved_default(): void {
		$this->save_defaults( array( 'fit' => '2' ) );
		$step = $this->items()['fit']['steps'][2];

		$this->retype(
			'fit',
			array(
				'type'  => 'text',
				'steps' => array(),
			)
		);

		$fit = $this->items()['fit'];
		$this->assertSame( $step, $fit['default'] );
		$this->assertSame( array(), $fit['step_terms'], 'Step terms belong to the old steps.' );
		$this->assertSame( $step, $this->resolved( $this->product( 0 ) )['fit']['display'] );
	}

	/** A default the editor already converted is kept as sent. */
	public function test_type_change_keeps_a_converted_default(): void {
		$this->save_defaults( array( 'fit' => '2' ) );

		$this->retype(
			'fit',
			array(
				'type'    => 'text',
				'steps'   => array(),
				'default' => 'Boxy',
			)
		);

		$this->assertSame( 'Boxy', $this->items()['fit']['default'] );
	}

	/** A text default that names a step becomes that step on a scale. */
	public function test_text_default_becomes_its_step(): void {
		$this->save_defaults( array() );
		$this->assertSame(
			array(),
			Size_Guide_Spec_Items::add(
				array(
					array(
						'name'    => 'Cut',
						'type'    => 'text',
						'default' => 'Relaxed',
					),
				)
			)
		);

		$this->retype(
			'cut',
			array(
				'type'  => 'pick',
				'steps' => array( 'Snug', 'Regular', 'Relaxed' ),
			)
		);

		$this->assertSame( '2', $this->items()['cut']['default'] );
		$this->assertSame( 'Relaxed', $this->resolved( $this->product( 0 ) )['cut']['display'] );
	}

	/** Only a scale keeps step terms, however it was sent. */
	public function test_step_terms_belong_to_scales_only(): void {
		$items = Product_Specs_Registry::validate(
			array(
				'items' => array(
					array(
						'key'        => 'cut',
						'name'       => 'Cut',
						'type'       => 'text',
						'step_terms' => array( 5, 6 ),
					),
				),
			)
		)['items'];

		$this->assertSame( array(), $items[0]['step_terms'] );
	}

	/** Reworded steps follow their step when steps are reordered, and go with a removed one. */
	public function test_step_wording_follows_its_step(): void {
		$this->save_defaults( array( 'fit' => '0' ) );
		$steps   = $this->items()['fit']['steps'];
		$last    = count( $steps ) - 1;
		$guide   = $this->guide();
		$product = $this->product( $guide );
		Product_Specs_Values::save_wording( $guide, array( 'fit' => array( 'step1' => 'Close' ) ) );
		update_post_meta( $product, Product_Specs_Meta::WORDING, array( 'fit' => array( 'step2' => 'Easy' ) ) );

		$reversed = array_reverse( $steps );
		$this->retype( 'fit', array( 'steps' => $reversed ) );

		$this->assertSame( array( 'fit' => array( 'step' . ( $last - 1 ) => 'Close' ) ), Product_Specs_Values::guide( $guide )['wording'] );
		$this->assertSame( array( 'fit' => array( 'step' . ( $last - 2 ) => 'Easy' ) ), get_post_meta( $product, Product_Specs_Meta::WORDING, true ) );

		$this->retype( 'fit', array( 'steps' => array_values( array_diff( $reversed, array( $steps[1] ) ) ) ) );
		$this->assertSame( array(), Product_Specs_Values::guide( $guide )['wording'] );
	}

	/** Moves follow the term, then the name, then the place; text keeps no steps. */
	public function test_step_moves(): void {
		$fit = $this->spec(
			array(
				'steps'      => array( 'Snug', 'Regular', 'Relaxed' ),
				'step_terms' => array( 11, 12, 13 ),
			)
		);

		$this->assertNull( Product_Specs_Wording_Sync::moves( $fit, $fit ) );
		$this->assertSame(
			array(
				0 => 1,
				1 => 0,
			),
			Product_Specs_Wording_Sync::moves(
				$fit,
				$this->spec(
					array(
						'steps'      => array( 'Classic', 'Snug' ),
						'step_terms' => array( 12, 11 ),
					)
				)
			),
			'A renamed step keeps its label (same term); a removed one loses it.'
		);
		$band = $this->spec(
			array(
				'type'  => 'number',
				'steps' => array( 'Light', 'Heavy' ),
			)
		);
		$this->assertNull(
			Product_Specs_Wording_Sync::moves( $band, $this->spec( array( 'steps' => array( 'Lite', 'Heavy' ) ) + $band ) ),
			'A band renamed in place stays put.'
		);
		$this->assertSame( array(), Product_Specs_Wording_Sync::moves( $fit, $this->spec( array( 'type' => 'text' ) ) ) );
		$this->assertFalse( Product_Specs_Wording_Sync::moves( $fit, null ), 'A deleted spec takes its wording with it.' );
		$this->assertSame(
			array( 'fit' => array( 'title' => 'Tee fit' ) ),
			Product_Specs_Wording_Sync::apply(
				array(
					'fit' => array(
						'title' => 'Tee fit',
						'step0' => 'Close',
					),
				),
				array( 'fit' => array() )
			),
			'Only step labels move; the title stays.'
		);
	}

	/**
	 * An item in its saved shape, for the move rules.
	 *
	 * @param array<string, mixed> $fields Fields to set.
	 * @return array<string, mixed>
	 */
	private function spec( array $fields ): array {
		return array_merge(
			Product_Specs_Registry::validate(
				array(
					'items' => array(
						array(
							'key'   => 'fit',
							'name'  => 'Fit',
							'type'  => 'text',
							'steps' => array(),
						),
					),
				)
			)['items'][0],
			array( 'type' => 'pick' ),
			$fields
		);
	}
}
