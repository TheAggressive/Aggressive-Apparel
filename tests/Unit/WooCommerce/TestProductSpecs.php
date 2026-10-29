<?php
/**
 * Product Specs registry, resolution, admin saving, table, and rendering tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs;
use Aggressive_Apparel\WooCommerce\Product_Specs_Attributes;
use Aggressive_Apparel\WooCommerce\Product_Specs_Meta;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Renderer;
use Aggressive_Apparel\WooCommerce\Product_Specs_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Table;
use Aggressive_Apparel\WooCommerce\Size_Guide;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use WP_UnitTestCase;

/**
 * Covers the store-defined spec items end to end.
 */
class TestProductSpecs extends WP_UnitTestCase {

	use Restores_Taxonomies;

	/**
	 * Enable both features and start from the default registry.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->snapshot_taxonomies();
		update_option(
			Feature_Settings::OPTION_KEY,
			array( 'size_guide' => true ),
			false
		);
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		$_POST = array();
		$this->restore_taxonomies();

		parent::tearDown();
	}

	/** Without a saved list, the default items apply. */
	public function test_defaults_apply_until_saved(): void {
		$keys = array_column( Product_Specs_Registry::items(), 'key' );

		$this->assertSame( array( 'fit', 'weight', 'fabric', 'construction' ), $keys );
	}

	/** Validation rejects unusable items with a reason and keeps the rest. */
	public function test_registry_validation(): void {
		$result = Product_Specs_Registry::validate(
			array(
				'items' => array(
					array(
						'name'  => 'Stretch',
						'type'  => 'pick',
						'steps' => array( 'None', 'Some', 'Lots' ),
					),
					array(
						'name'  => 'Two steps',
						'type'  => 'pick',
						'steps' => array( 'A', 'B' ),
					),
					array(
						'name'       => 'Thickness',
						'type'       => 'number',
						'steps'      => array( 'Thin', 'Mid', 'Thick' ),
						'thresholds' => array( 5, 3 ),
					),
					array(
						'name' => 'Stretch',
						'type' => 'text',
					),
					array(
						'name' => 'Hidden',
						'type' => 'text',
					),
					array( 'type' => 'text' ),
				),
			)
		);

		$this->assertSame( array( 'stretch' ), array_column( $result['items'], 'key' ) );
		$this->assertCount( 5, $result['errors'] );
	}

	/** Empty scale labels fall back to the first, middle, and last steps. */
	public function test_edge_labels(): void {
		$this->assertSame(
			array(
				'start' => 'Low',
				'mid'   => 'Mid',
				'end'   => 'High',
			),
			Product_Specs_Registry::edge_labels( array( 'Low', 'Mid', 'High' ), '', '', '' )
		);
		$this->assertSame( '', Product_Specs_Registry::edge_labels( array( 'A', 'B', 'C', 'D' ), '', '', '' )['mid'] );
		$this->assertSame( 'Light', Product_Specs_Registry::edge_labels( array( 'A', 'B', 'C' ), 'Light', '', '' )['start'] );
	}

	/** Values come from each spec's default when the product sets none. */
	public function test_product_inherits_spec_default(): void {
		$this->save_defaults(
			array(
				'fit'    => '2',
				'weight' => '142',
				'fabric' => '100% cotton',
			)
		);
		$items = $this->items( $this->create_product() );

		$this->assertSame( 2, $items['fit']['step'] );
		$this->assertSame( Product_Specs::SOURCE_DEFAULT, $items['fit']['source'] );
		$this->assertSame( 0, $items['weight']['step'] );
		$this->assertSame( '100% cotton', $items['fabric']['display'] );
	}

	/** A product value wins item by item; other items still use the default. */
	public function test_product_value_wins_per_item(): void {
		$this->save_defaults(
			array(
				'fit'    => '2',
				'weight' => '142',
			)
		);
		$product_id = $this->create_product();
		update_post_meta( $product_id, '_aa_spec_fit', '4' );

		$items = $this->items( $product_id );

		$this->assertSame( 4, $items['fit']['step'] );
		$this->assertSame( Product_Specs::SOURCE_PRODUCT, $items['fit']['source'] );
		$this->assertSame( Product_Specs::SOURCE_DEFAULT, $items['weight']['source'] );
	}

	/** A matching attribute beats the default; unusable defaults are dropped on save. */
	public function test_attribute_beats_default_and_defaults_validate(): void {
		$this->save_defaults(
			array(
				'fit'    => '9',
				'weight' => '-5',
				'fabric' => '100% cotton',
			)
		);
		$items = Product_Specs_Registry::get()['items'];
		$this->assertSame( '', $items[0]['default'], 'Step index out of range.' );
		$this->assertSame( '', $items[1]['default'], 'Weight must be positive.' );
		$this->assertSame( '100% cotton', $items[2]['default'] );

		$items = $this->items( $this->create_product( array( 'Fabric' => 'Linen' ) ) );
		$this->assertSame( 'Linen', $items['fabric']['display'] );
		$this->assertSame( Product_Specs::SOURCE_ATTRIBUTE, $items['fabric']['source'] );
	}

	/** A guide with no chart still applies: its specs show, with no Size Guide button. */
	public function test_specs_only_guide_wins_without_a_button(): void {
		$this->save_defaults( array( 'fit' => '2' ) );
		$product_id = $this->create_product( array() );
		$tee        = $this->assign_guide( $product_id );
		update_post_meta( $tee, '_aa_spec_fit', '5' );
		$this->add_category( $product_id, array( '_aggressive_apparel_size_guide_id' => $tee ) );
		$hat = self::factory()->post->create(
			array(
				'post_type'   => 'aa_size_guide',
				'post_status' => 'publish',
				'post_title'   => 'Dad Hat',
				'post_content' => '',
			)
		);
		update_post_meta( $hat, '_aa_spec_fit', '3' );
		update_post_meta( $product_id, '_aggressive_apparel_size_guide_id', $hat );
		( new \Aggressive_Apparel\WooCommerce\Size_Guide() )->flush_all_caches();

		$guide = \Aggressive_Apparel\WooCommerce\Size_Guide::resolved( $product_id );
		$items = $this->items( $product_id );

		$this->assertSame( $hat, $guide['id'], 'The hat guide, not the category tee guide.' );
		$this->assertFalse( $guide['dialog'], 'No chart, so no Size Guide button or link.' );
		// The once-per-request guard is cleared, so only `dialog` can make this empty.
		$this->reset_did_render();
		$this->assertSame( '', ( new \Aggressive_Apparel\WooCommerce\Size_Guide() )->render_block_markup( $product_id ) );
		$this->assertSame( 3, $items['fit']['step'] );
		$this->assertSame( 'Dad Hat', $items['fit']['label'] );
	}

	/** An empty guide (no chart, content, or specs) never hides the category guide. */
	public function test_empty_guide_falls_through(): void {
		$product_id = $this->create_product( array() );
		$tee        = $this->assign_guide( $product_id );
		$this->add_category( $product_id, array( '_aggressive_apparel_size_guide_id' => $tee ) );
		$draft = self::factory()->post->create(
			array(
				'post_type'    => 'aa_size_guide',
				'post_status'  => 'publish',
				'post_title'   => 'Still building',
				'post_content' => "\n\n",
			)
		);
		update_post_meta( $product_id, '_aggressive_apparel_size_guide_id', $draft );
		( new \Aggressive_Apparel\WooCommerce\Size_Guide() )->flush_all_caches();

		$this->assertSame( $tee, \Aggressive_Apparel\WooCommerce\Size_Guide::resolved( $product_id )['id'] );

		update_post_meta( $draft, '_aa_spec_fit', '3' );
		( new \Aggressive_Apparel\WooCommerce\Size_Guide() )->flush_all_caches();
		$guide = \Aggressive_Apparel\WooCommerce\Size_Guide::resolved( $product_id );

		$this->assertSame( $draft, $guide['id'], 'With a spec it is a specs-only guide.' );
		$this->assertFalse( $guide['dialog'], 'Whitespace content is no dialog.' );
	}

	/** A category set to "No size guide" drops the store default; another category's guide still wins. */
	public function test_category_no_size_guide_drops_the_store_default(): void {
		$product_id = $this->create_product( array() );
		$tee        = $this->assign_guide( $product_id );
		delete_post_meta( $product_id, '_aggressive_apparel_size_guide_id' );
		update_option( \Aggressive_Apparel\WooCommerce\Size_Guide::CPT_OPTION_KEY, $tee );
		$hats = $this->add_category( $product_id, array( '_aggressive_apparel_size_guide_id' => \Aggressive_Apparel\WooCommerce\Size_Guide::NONE ) );
		( new \Aggressive_Apparel\WooCommerce\Size_Guide() )->flush_all_caches();

		$this->assertSame( 0, \Aggressive_Apparel\WooCommerce\Size_Guide::resolved( $product_id )['id'] );

		$sale = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		update_term_meta( $sale, '_aggressive_apparel_size_guide_id', $tee );
		wp_set_object_terms( $product_id, array( $hats, $sale ), 'product_cat' );
		( new \Aggressive_Apparel\WooCommerce\Size_Guide() )->flush_all_caches();

		$this->assertSame( $tee, \Aggressive_Apparel\WooCommerce\Size_Guide::resolved( $product_id )['id'] );
	}

	/** "No size guide" opts a product out of its category and store guides. */
	public function test_no_size_guide_opts_out_of_inherited_guides(): void {
		$this->save_defaults( array( 'fit' => '2' ) );
		$product_id = $this->create_product( array() );
		$tee        = $this->assign_guide( $product_id );
		update_post_meta( $tee, '_aa_spec_fit', '5' );
		$this->add_category( $product_id, array( '_aggressive_apparel_size_guide_id' => $tee ) );
		update_option( \Aggressive_Apparel\WooCommerce\Size_Guide::CPT_OPTION_KEY, $tee );
		update_post_meta( $product_id, '_aggressive_apparel_size_guide_id', \Aggressive_Apparel\WooCommerce\Size_Guide::NONE );
		( new \Aggressive_Apparel\WooCommerce\Size_Guide() )->flush_all_caches();

		$this->assertSame( array(), \Aggressive_Apparel\WooCommerce\Size_Guide::candidate_guide_ids( $product_id ) );
		$this->assertSame( 0, \Aggressive_Apparel\WooCommerce\Size_Guide::resolved( $product_id )['id'] );
		$this->assertSame( Product_Specs::SOURCE_DEFAULT, $this->items( $product_id )['fit']['source'], 'Specs still show, from the default.' );
	}

	/** The product panel saves "No size guide" and "category or store default". */
	public function test_product_panel_saves_no_size_guide(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$product_id = $this->create_product( array() );
		$guide      = new \Aggressive_Apparel\WooCommerce\Size_Guide_Admin_Fields();
		$_POST      = array(
			'aa_size_guide_nonce' => wp_create_nonce( 'aa_size_guide_save' ),
			'aa_size_guide_id'    => 'none',
		);

		$guide->save_product_data( $product_id );
		$this->assertSame( '-1', get_post_meta( $product_id, '_aggressive_apparel_size_guide_id', true ) );

		$_POST['aa_size_guide_id'] = '';
		$guide->save_product_data( $product_id );
		$this->assertSame( '', get_post_meta( $product_id, '_aggressive_apparel_size_guide_id', true ) );
		$_POST = array();
	}

	/** A pick whose guide was unpublished stays listed, so saving keeps it. */
	public function test_picker_keeps_an_unpublished_pick(): void {
		$guide = self::factory()->post->create(
			array(
				'post_type'   => 'aa_size_guide',
				'post_status' => 'draft',
				'post_title'  => 'Old Tee',
			)
		);
		$options = \Aggressive_Apparel\WooCommerce\Size_Guide_Admin_Fields::options( $guide, 'Inherit' );

		$this->assertSame( 'Old Tee (not published)', $options[ $guide ] );
		$this->assertSame( (string) $guide, \Aggressive_Apparel\WooCommerce\Size_Guide_Admin_Fields::value( $guide ) );
		$this->assertSame( 'none', \Aggressive_Apparel\WooCommerce\Size_Guide_Admin_Fields::value( \Aggressive_Apparel\WooCommerce\Size_Guide::NONE ) );
	}

	/** Size_Guide renders its dialog once per request; tests start fresh. */
	private function reset_did_render(): void {
		$property = new \ReflectionProperty( \Aggressive_Apparel\WooCommerce\Size_Guide::class, 'did_render' );
		$property->setValue( null, false );
	}

	/** A size guide's specs beat the default and lose to attributes and the product. */
	public function test_size_guide_specs_sit_between_attributes_and_default(): void {
		$this->save_defaults(
			array(
				'fit'    => '2',
				'weight' => '142',
			)
		);
		$product_id = $this->create_product( array( 'Fabric' => 'Linen' ) );
		$guide_id   = $this->assign_guide( $product_id );
		update_post_meta( $guide_id, '_aa_spec_fit', '3' );
		update_post_meta( $guide_id, '_aa_spec_weight', '240' );
		update_post_meta( $guide_id, '_aa_spec_fabric', '100% cotton' );
		update_post_meta( $product_id, '_aa_spec_weight', '180' );

		$items = $this->items( $product_id );

		$this->assertSame( 3, $items['fit']['step'] );
		$this->assertSame( Product_Specs::SOURCE_GUIDE, $items['fit']['source'] );
		$this->assertSame( 'Jersey Tee', $items['fit']['label'] );
		$this->assertSame( Product_Specs::SOURCE_PRODUCT, $items['weight']['source'] );
		$this->assertSame( 240.0, $items['weight']['inherited']['number'], 'The guide is what the product would inherit.' );
		$this->assertSame( 'Linen', $items['fabric']['display'] );
		$this->assertSame( Product_Specs::SOURCE_ATTRIBUTE, $items['fabric']['source'] );

		delete_post_meta( $guide_id, '_aa_spec_fit' );
		$this->assertSame( Product_Specs::SOURCE_DEFAULT, $this->items( $product_id )['fit']['source'], 'An empty guide value falls back to the default.' );
	}

	/** Attributes supply numbers, text, and matching step names. */
	public function test_attributes_supply_values(): void {
		$product_id = $this->create_product(
			array(
				'Fabric Weight' => '4.2 oz./yd.² (142 g/m²)',
				'Fabric'        => '100% combed and ring-spun cotton',
			)
		);
		$this->save_registry_with_attribute_fit();

		$items = $this->items( $product_id );

		$this->assertSame( 142.0, $items['weight']['number'] );
		$this->assertSame( Product_Specs::SOURCE_ATTRIBUTE, $items['weight']['source'] );
		$this->assertSame( 'Fabric Weight', $items['weight']['label'] );
		$this->assertSame( '100% combed and ring-spun cotton', $items['fabric']['display'] );

		$fit_product = $this->create_product( array( 'Cut' => 'relaxed' ) );
		$this->assertSame( 3, $this->items( $fit_product )['fit']['step'] );
	}

	/** Number text in either unit parses; other units take the first number. */
	public function test_parse_number(): void {
		$this->assertSame( 142.0, Product_Specs_Attributes::parse_number( '4.2 oz./yd.² (142 g/m²)', 'g/m²' ) );
		$this->assertSame( 180.0, Product_Specs_Attributes::parse_number( '180gsm', 'g/m²' ) );
		$this->assertSame( 207.0, Product_Specs_Attributes::parse_number( '6.1 oz', 'g/m²' ) );
		$this->assertSame( 0.0, Product_Specs_Attributes::parse_number( 'Heavyweight', 'g/m²' ) );
		$this->assertSame( 5.0, Product_Specs_Attributes::parse_number( '5% elastane', '%' ) );
	}

	/** Number steps use the nearest category's boundaries, else the spec's. */
	public function test_category_boundaries(): void {
		$product_id = $this->create_product();
		update_post_meta( $product_id, '_aa_spec_weight', '305' );

		$this->assertSame( 4, $this->items( $product_id )['weight']['step'] );

		$hoodies                     = $this->add_category( $product_id, array() );
		$items                       = Product_Specs_Registry::defaults();
		$items[1]['category_steps']  = array(
			array(
				'category'   => $hoodies,
				'thresholds' => array( 240, 280, 320, 360 ),
			),
		);
		$this->save_items( $items );

		$this->assertSame( 2, $this->items( $product_id )['weight']['step'] );
	}

	/** A product's wording overrides the spec's; empty fields keep it. */
	public function test_product_wording(): void {
		$product_id = $this->create_product();
		update_post_meta( $product_id, Product_Specs_Meta::WORDING, array( 'fit' => array( 'title' => 'Silhouette' ) ) );

		$fit = $this->items( $product_id )['fit'];

		$this->assertSame( 'Silhouette', $fit['title'] );
		$this->assertSame( 'Snug', $fit['start'] );
		$this->assertSame( 'Oversized', $fit['end'] );
	}

	/** Items scoped to a category reach its subcategories; "except" excludes. */
	public function test_category_scope(): void {
		$apparel = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$tees    = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'parent'   => $apparel,
			)
		);
		$hats    = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );

		$this->save_items(
			array(
				array(
					'key'        => 'fit',
					'name'       => 'Fit',
					'type'       => 'pick',
					'steps'      => array( 'Snug', 'Regular', 'Loose' ),
					'scope'      => 'only',
					'categories' => array( $apparel ),
				),
				array(
					'key'        => 'crown',
					'name'       => 'Crown',
					'type'       => 'pick',
					'steps'      => array( 'Low', 'Mid', 'High' ),
					'scope'      => 'except',
					'categories' => array( $apparel ),
				),
			)
		);

		$tee = $this->create_product();
		wp_set_object_terms( $tee, array( $tees ), 'product_cat' );
		$hat = $this->create_product();
		wp_set_object_terms( $hat, array( $hats ), 'product_cat' );

		$this->assertSame( array( 'fit' ), array_keys( $this->items( $tee ) ) );
		$this->assertSame( array( 'crown' ), array_keys( $this->items( $hat ) ) );
	}

	/** A custom 3-step scale renders as a meter sized to its steps. */
	public function test_custom_scale_renders(): void {
		$this->save_items(
			array(
				array(
					'key'      => 'stretch',
					'name'     => 'Stretch',
					'type'     => 'pick',
					'steps'    => array( 'None', 'Some', 'Lots' ),
					'advice'   => array( '', 'Moves with you.', '' ),
					'centered' => false,
				),
			)
		);
		$product_id = $this->create_product();
		update_post_meta( $product_id, '_aa_spec_stretch', '1' );

		$html = Product_Specs_Renderer::render( $product_id, array( 'showNudge' => true ) );

		$this->assertStringContainsString( 'aria-valuemax="3"', $html );
		$this->assertStringContainsString( 'aria-valuetext="Some, on a scale from None to Lots"', $html );
		$this->assertStringContainsString( '--aa-product-specs-steps:3', $html );
		$this->assertStringContainsString( 'Moves with you.', $html );
	}

	/** Compact cards keep advice outside the meter; the footer carries every size's line. */
	public function test_render_compact_card_and_footer(): void {
		$product_id = $this->create_product();
		update_post_meta( $product_id, '_aa_spec_fit', '2' );
		$guide_id = self::factory()->post->create(
			array(
				'post_type'   => \Aggressive_Apparel\WooCommerce\Size_Guide_Post_Type::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		update_post_meta(
			$guide_id,
			\Aggressive_Apparel\WooCommerce\Size_Chart::META,
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
						'size'   => 'M',
						'values' => array( '21' ),
					),
				),
			)
		);
		update_post_meta( $product_id, \Aggressive_Apparel\WooCommerce\Size_Guide::CPT_META_KEY, $guide_id );

		$html = Product_Specs_Renderer::render(
			$product_id,
			array(
				'layout'    => 'compact',
				'showNudge' => true,
			)
		);

		$this->assertMatchesRegularExpression( '#<div class="aa-product-specs__card"><div class="aa-product-specs__line is-centered" role="meter"[^>]*>.*?</div></div><p class="aa-product-specs__nudge">#s', $html, 'Advice follows the meter, outside it.' );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/product-specs"', $html );
		$this->assertStringContainsString(
			esc_attr(
				(string) wp_json_encode(
					array(
						array(
							'size'  => 'M',
							'title' => 'Size M',
							'in'    => array(
								array(
									'label' => 'Chest',
									'value' => '21 in',
								),
							),
							'cm'    => array(
								array(
									'label' => 'Chest',
									'value' => '53.5 cm',
								),
							),
						),
					)
				)
			),
			$html
		);
		$this->assertStringContainsString( 'aa-product-specs__guide-link', $html );

		$bare = Product_Specs_Renderer::render(
			$product_id,
			array(
				'showMeasurements' => false,
				'showGuideLink'    => false,
			)
		);
		$this->assertStringNotContainsString( 'aa-product-specs__footer', $bare );
	}

	/** Picking scales in the block never limits the details list. */
	public function test_scale_picker_keeps_details(): void {
		$product_id = $this->create_product();
		update_post_meta( $product_id, '_aa_spec_fit', '2' );
		update_post_meta( $product_id, '_aa_spec_fabric', 'Combed cotton' );

		$html = Product_Specs_Renderer::render(
			$product_id,
			array(
				'scales'      => array( 'fit' ),
				'showDetails' => true,
			)
		);

		$this->assertStringContainsString( 'aria-label="Fit"', $html );
		$this->assertStringContainsString( '<dd>Combed cotton</dd>', $html );
	}

	/** Saving a product keeps overrides for specs its form didn't show. */
	public function test_save_keeps_overrides_for_items_not_shown(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$product_id = $this->create_product();
		update_post_meta( $product_id, Product_Specs_Meta::HIDDEN, array( 'weight' ) );
		update_post_meta( $product_id, Product_Specs_Meta::WORDING, array( 'weight' => array( 'title' => 'Heft' ) ) );

		$_POST['aa_product_specs_nonce'] = wp_create_nonce( 'aa_product_specs_save' );
		$_POST['aa_specs']               = array(
			'form'    => '1',
			'shown'   => array( 'fit' ),
			'hidden'  => array( 'fit' ),
			'wording' => array( 'fit' => array( 'title' => 'Cut' ) ),
		);
		( new \Aggressive_Apparel\WooCommerce\Product_Specs_Admin() )->save_product( $product_id );
		unset( $_POST['aa_product_specs_nonce'], $_POST['aa_specs'] );

		$this->assertSame( array( 'weight', 'fit' ), array_values( (array) get_post_meta( $product_id, Product_Specs_Meta::HIDDEN, true ) ) );
		$wording = (array) get_post_meta( $product_id, Product_Specs_Meta::WORDING, true );
		$this->assertSame( 'Heft', $wording['weight']['title'] ?? '' );
		$this->assertSame( 'Cut', $wording['fit']['title'] ?? '' );
	}

	/** Hidden items, missing data, and a disabled feature render nothing. */
	public function test_render_skips_hidden_missing_and_disabled(): void {
		$product_id = $this->create_product();
		$this->assertSame( '', Product_Specs_Renderer::render( $product_id, array() ) );

		update_post_meta( $product_id, '_aa_spec_fit', '2' );
		$this->assertStringContainsString( 'aria-label="Fit"', Product_Specs_Renderer::render( $product_id, array() ) );
		$this->assertSame( '', Product_Specs_Renderer::render( $product_id, array( 'scales' => array( 'weight' ) ) ) );

		update_post_meta( $product_id, Product_Specs_Meta::HIDDEN, array( 'fit' ) );
		$this->assertSame( '', Product_Specs_Renderer::render( $product_id, array() ) );

		delete_post_meta( $product_id, Product_Specs_Meta::HIDDEN );
		update_option( Feature_Settings::OPTION_KEY, array(), false );
		$this->assertSame( '', Product_Specs_Renderer::render( $product_id, array() ) );
	}

	/** Specs add Additional information rows, skipping visible attributes. */
	public function test_table_rows(): void {
		$product_id = $this->create_product( array( 'Fabric' => 'Cotton' ) );
		update_post_meta( $product_id, '_aa_spec_fit', '2' );
		update_post_meta( $product_id, '_aa_spec_weight', '142' );

		$rows = ( new Product_Specs_Table() )->rows_for( $product_id );

		$this->assertSame( 'Regular', $rows['aa-spec-fit']['value'] ?? '' );
		$this->assertStringStartsWith( 'Lightweight · 142 g/m²', $rows['aa-spec-weight']['value'] ?? '' );
		$this->assertArrayNotHasKey( 'aa-spec-fabric', $rows );
	}

	/** The tab appears for products with specs but no attributes. */
	public function test_tab_added_for_specs_only_products(): void {
		$product_id = $this->create_product();
		update_post_meta( $product_id, '_aa_spec_fit', '2' );
		$this->go_to( get_permalink( $product_id ) );

		$tabs = ( new Product_Specs_Table() )->ensure_tab( array() );

		$this->assertArrayHasKey( 'additional_information', $tabs );
	}

	/** The store can hide shipping weight and dimensions. */
	public function test_hide_dimensions(): void {
		$table = new Product_Specs_Table();
		$this->assertTrue( $table->dimensions_display( true ) );

		Product_Specs_Registry::save(
			array(
				'items'           => Product_Specs_Registry::defaults(),
				'hide_dimensions' => true,
			)
		);
		$this->assertFalse( $table->dimensions_display( true ) );
	}

	/** Values are attributes now; only presentation meta stays registered. */
	public function test_only_presentation_meta_is_registered(): void {
		( new Product_Specs_Meta() )->register();

		$keys = get_registered_meta_keys( 'post', 'product' );
		$this->assertArrayNotHasKey( '_aa_spec_fit', $keys );
		$this->assertArrayHasKey( Product_Specs_Meta::HIDDEN, $keys );
		$this->assertArrayHasKey( Product_Specs_Meta::WORDING, $keys );
	}

	/** The REST save refuses an invalid list and keeps the stored one. */
	public function test_rest_save_reports_errors(): void {
		$request = new \WP_REST_Request( 'POST', '/aggressive-apparel/v1/product-specs' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'items' => array( array( 'type' => 'pick' ) ) ) ) );

		$response = ( new Product_Specs_Settings() )->save_registry( $request );

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertFalse( get_option( Product_Specs_Registry::OPTION_KEY ) );
	}

	/** Category boundaries must name a category and fit the step count. */
	public function test_category_steps_validation(): void {
		$items                      = Product_Specs_Registry::defaults();
		$items[1]['category_steps'] = array(
			array(
				'category'   => 5,
				'thresholds' => array( 240, 280 ),
			),
		);

		$result = Product_Specs_Registry::validate( array( 'items' => $items ) );

		$this->assertNotContains( 'weight', array_column( $result['items'], 'key' ) );
		$this->assertCount( 1, $result['errors'] );
	}

	/** Size guide usage finds category and direct product assignments. */
	public function test_size_guide_usage_reports_assignments(): void {
		$product_id = $this->create_product();
		$guide_id   = $this->assign_guide( $product_id );
		$term_id    = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Tees',
			)
		);
		update_term_meta( $term_id, '_aggressive_apparel_size_guide_id', $guide_id );

		$usage = \Aggressive_Apparel\WooCommerce\Size_Guide_Assignments::usage( $guide_id );

		$this->assertSame( array( 'Tees' ), $usage['categories'] );
		$this->assertSame( 1, $usage['products'] );
	}

	/**
	 * Resolved items for a product, keyed by item key.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, array<string, mixed>>
	 */
	private function items( int $product_id ): array {
		$items = array();
		foreach ( Product_Specs::for_product( $product_id )['items'] as $item ) {
			$items[ $item['key'] ] = $item;
		}

		return $items;
	}

	/**
	 * Save a registry made of the given items.
	 *
	 * @param array<int, array<string, mixed>> $items Raw items.
	 */
	private function save_items( array $items ): void {
		$result = Product_Specs_Registry::validate( array( 'items' => $items ) );
		$this->assertSame( array(), $result['errors'] );
		Product_Specs_Registry::save(
			array(
				'items'           => $result['items'],
				'hide_dimensions' => false,
			)
		);
	}

	/**
	 * The default items with these default values.
	 *
	 * @param array<string, string> $defaults Item key => default value.
	 */
	private function save_defaults( array $defaults ): void {
		$items = Product_Specs_Registry::defaults();
		foreach ( $items as $i => $item ) {
			$items[ $i ]['default'] = $defaults[ $item['key'] ] ?? '';
		}
		$result = Product_Specs_Registry::validate( array( 'items' => $items ) );
		Product_Specs_Registry::save(
			array(
				'items'           => $result['items'],
				'hide_dimensions' => false,
			)
		);
	}

	/**
	 * Defaults, with Fit also read from a "Cut" attribute.
	 */
	private function save_registry_with_attribute_fit(): void {
		$items                     = Product_Specs_Registry::defaults();
		$items[0]['attributes']    = array( 'Cut' );
		$this->save_items( $items );
	}

	/**
	 * Create a published simple product, optionally with custom attributes.
	 *
	 * @param array<string, string> $attributes Attribute name => value.
	 */
	private function create_product( array $attributes = array() ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Spec test product' );
		$product->set_status( 'publish' );

		$list = array();
		foreach ( $attributes as $name => $value ) {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_name( $name );
			$attribute->set_options( array( $value ) );
			$attribute->set_visible( true );
			$list[] = $attribute;
		}
		$product->set_attributes( $list );

		return $product->save();
	}

	/**
	 * Create a size guide and assign it to the product.
	 *
	 * @param int $product_id Product ID.
	 */
	private function assign_guide( int $product_id ): int {
		$guide_id = self::factory()->post->create(
			array(
				'post_type'    => 'aa_size_guide',
				'post_status'  => 'publish',
				'post_title'   => 'Jersey Tee',
				'post_content' => '<p>Guide</p>',
			)
		);
		update_post_meta( $product_id, '_aggressive_apparel_size_guide_id', $guide_id );

		return $guide_id;
	}

	/**
	 * Put the product in a new category carrying spec meta.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $meta       Category meta.
	 */
	private function add_category( int $product_id, array $meta ): int {
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		foreach ( $meta as $key => $value ) {
			update_term_meta( $term_id, $key, $value );
		}
		wp_set_object_terms( $product_id, array( $term_id ), 'product_cat' );

		return $term_id;
	}
}
