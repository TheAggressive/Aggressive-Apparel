<?php
/**
 * Size Guide Class
 *
 * Renders a measurement-chart modal on single product pages.
 * Guides are managed via the aa_size_guide CPT and assigned
 * per-product, per-category, or as a global fallback.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\Core\Cache_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Size Guide
 *
 * @since 1.17.0
 * @phpstan-type Resolved array{id: int, html: string, chart: bool, unit: string, measures: bool}
 */
class Size_Guide {

	/**
	 * Meta key for CPT post ID references.
	 *
	 * @var string
	 */
	public const CPT_META_KEY = '_aggressive_apparel_size_guide_id';

	/**
	 * Product meta value for "no size guide": the product opts out of its
	 * category and store-wide guides (its own spec values still show).
	 *
	 * @var int
	 */
	public const NONE = -1;

	/**
	 * Option key for global CPT size guide reference.
	 *
	 * @var string
	 */
	public const CPT_OPTION_KEY = 'aggressive_apparel_size_guide_id';

	/**
	 * Nonce action for size guide fields.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'aa_size_guide_save';

	/**
	 * Nonce field name for size guide fields.
	 *
	 * @var string
	 */
	private const NONCE_NAME = 'aa_size_guide_nonce';

	/**
	 * Transient prefix for cached size guide content.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'aa_sg_';

	/**
	 * Version of the cached guide markup; bump whenever Size_Chart or the
	 * guide content markup changes, so a theme update never serves the old
	 * markup from cache (stale entries expire on their own).
	 *
	 * @var int
	 */
	private const MARKUP_VERSION = 5;

	/** Option storing the cache-key generation. */
	private const CACHE_GENERATION_OPTION = 'aa_size_guide_cache_generation';

	/**
	 * Cache TTL in seconds (15 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 900;

	/**
	 * Whether a size-guide instance has already rendered during this request.
	 *
	 * Prevents duplicate dialog controls when malformed template markup contains
	 * the block more than once.
	 *
	 * @var bool
	 */
	private static bool $did_render = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Product data tab for per-product size guides.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ) );
		add_action( 'admin_head', array( $this, 'add_tab_icon_style' ) );

		// Category assignment.
		add_action( 'product_cat_add_form_fields', array( $this, 'render_category_add_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'render_category_edit_field' ), 20 );
		add_action( 'created_product_cat', array( $this, 'save_category_field' ) );
		add_action( 'edited_product_cat', array( $this, 'save_category_field' ) );

		// The dialog without the block: "Size guide" links still open it.
		add_action( 'wp_footer', array( $this, 'render_standalone_dialog' ) );

		// Invalidate cache when size guide CPT posts are updated.
		add_action( 'save_post_' . Size_Guide_Post_Type::POST_TYPE, array( $this, 'flush_all_caches' ) );
	}

	/**
	 * Enqueue CSS and register Interactivity API script module on single product pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-size-guide',
			'build/styles/woocommerce/size-guide'
		);

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/size-guide',
			'build/interactivity/size-guide',
			array(
				'@aggressive-apparel/scroll-lock',
				'@aggressive-apparel/helpers',
				'@aggressive-apparel/use-overlay',
			)
		);
	}

	/**
	 * Print the size guide dialog on product pages that have a guide but no
	 * Size Guide block, so the "Size guide" links beside the size options and
	 * in the Product Specs block still open it. Its own trigger is hidden.
	 */
	public function render_standalone_dialog(): void {
		if ( self::$did_render || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$markup = $this->render_block_markup( (int) get_queried_object_id(), array( 'standalone' => true ) );
		if ( '' !== $markup ) {
			echo aggressive_apparel_trusted_html( $markup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building.
		}
	}

	/**
	 * Render the dynamic Size Guide block for a product context.
	 *
	 * @param int                  $product_id Product post ID from block context.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered trigger and dialog markup, or an empty string.
	 */
	public function render_block_markup( int $product_id, array $attributes = array() ): string {
		if ( self::$did_render || ! Feature_Settings::is_enabled( 'size_guide' ) ) {
			return '';
		}

		$guide = $this->get_size_guide_for_product( $product_id );
		if ( '' === $guide['html'] ) {
			return '';
		}

		$label = isset( $attributes['label'] )
			? sanitize_text_field( (string) $attributes['label'] )
			: '';
		if ( '' === trim( $label ) ) {
			$label = __( 'Size Guide', 'aggressive-apparel' );
		}

		$show_icon = ! isset( $attributes['showIcon'] ) || (bool) $attributes['showIcon'];

		$units_label = apply_filters(
			'aggressive_apparel_size_guide_units_label',
			__( 'Measurements in inches', 'aggressive-apparel' )
		);

		$dialog_id = wp_unique_id( 'aa-size-guide-' );
		$title_id  = $dialog_id . '-title';

		$root_attributes    = $this->get_root_attributes();
		$standalone         = ! empty( $attributes['standalone'] );
		$trigger_attributes = $standalone ? $this->get_hidden_trigger_attributes( $dialog_id ) : $this->get_trigger_attributes( $dialog_id );
		$markup             = '<div ' . $root_attributes . '>';
		$markup            .= sprintf(
			'<button %s>',
			$trigger_attributes
		);
		if ( $show_icon ) {
			$markup .= '<span class="aggressive-apparel-size-guide__trigger-icon" aria-hidden="true">';
			$markup .= $this->get_measuring_tape_icon( 22 );
			$markup .= '</span>';
		}
		$markup .= esc_html( $label );
		$markup .= '</button>';

		$markup .= sprintf(
			'<div id="%1$s" class="aggressive-apparel-overlay aggressive-apparel-size-guide__overlay" role="dialog" aria-modal="true" aria-labelledby="%2$s" data-wp-class--is-open="context.isOpen" data-wp-on--keydown="actions.handleKeydown" hidden>',
			esc_attr( $dialog_id ),
			esc_attr( $title_id )
		);
		$markup .= '<div class="aggressive-apparel-overlay__backdrop aggressive-apparel-size-guide__backdrop" data-wp-on--click="actions.close"></div>';
		$markup .= '<div class="aggressive-apparel-panel aggressive-apparel-panel--guide aggressive-apparel-size-guide__modal">';
		$markup .= '<div class="aggressive-apparel-size-guide__header">';
		$markup .= '<div class="aggressive-apparel-size-guide__heading">';
		$markup .= sprintf(
			'<h2 id="%s" class="aggressive-apparel-size-guide__title">%s</h2>',
			esc_attr( $title_id ),
			esc_html__( 'Size Guide', 'aggressive-apparel' )
		);
		// A structured chart's in/cm switch sits beside the heading.
		if ( $guide['measures'] ) {
			$markup .= Size_Chart::unit_switch( $guide['unit'] );
		}
		$markup .= '</div>';
		$markup .= '<button type="button" class="aggressive-apparel-size-guide__close aa-icon-button aa-icon-button--only aa-icon-button--square" data-wp-on--click="actions.close" aria-label="' . esc_attr__( 'Close', 'aggressive-apparel' ) . '">';
		$markup .= aggressive_apparel_get_icon(
			'close',
			array(
				'width'       => 20,
				'height'      => 20,
				'aria-hidden' => 'true',
			)
		);
		$markup .= '</button>';
		$markup .= '</div>';
		// A structured chart has its own unit switcher.
		if ( is_string( $units_label ) && '' !== $units_label && ! $guide['chart'] ) {
			$markup .= '<p class="aggressive-apparel-size-guide__units">' . esc_html( $units_label ) . '</p>';
		}
		$markup .= '<div class="aggressive-apparel-size-guide__body">';
		// Chart markup is built and escaped by Size_Chart (it carries
		// interactivity directives wp_kses_post would strip); old content was
		// kses-filtered when resolved.
		$markup .= $guide['html'];
		$markup .= '</div>';
		$markup .= '</div>';
		$markup .= '</div>';
		$markup .= '</div>';

		self::$did_render = true;

		return $markup;
	}

	/**
	 * Build the neutral Interactivity API root attributes.
	 *
	 * @return string Escaped HTML attributes.
	 */
	private function get_root_attributes(): string {
		return sprintf(
			'class="%1$s" data-wp-interactive="%2$s" data-wp-context="%3$s"',
			esc_attr( 'aggressive-apparel-size-guide-block' ),
			esc_attr( 'aggressive-apparel/size-guide' ),
			esc_attr( (string) wp_json_encode( array( 'isOpen' => false ) ) )
		);
	}

	/**
	 * Trigger attributes for the standalone dialog: hidden (the page's
	 * "Size guide" links click it), and built by hand because no block is
	 * rendering, so there are no block supports to apply.
	 *
	 * @param string $dialog_id Controlled dialog element ID.
	 * @return string Escaped HTML attributes.
	 */
	private function get_hidden_trigger_attributes( string $dialog_id ): string {
		return sprintf(
			'class="aggressive-apparel-size-guide__trigger" type="button" data-wp-on--click="actions.toggle" aria-haspopup="dialog" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="context.isOpen" hidden',
			esc_attr( $dialog_id )
		);
	}

	/**
	 * Build the trigger attributes, including native block design supports.
	 *
	 * Applying block supports to the actual control makes editor padding,
	 * color, typography, border, alignment, and shadow choices render without
	 * styling the fixed-position dialog sibling.
	 *
	 * @param string $dialog_id Controlled dialog element ID.
	 * @return string Escaped HTML attributes.
	 */
	private function get_trigger_attributes( string $dialog_id ): string {
		return get_block_wrapper_attributes(
			array(
				'class'                       => 'aggressive-apparel-size-guide__trigger',
				'type'                        => 'button',
				'data-wp-on--click'           => 'actions.toggle',
				'aria-haspopup'               => 'dialog',
				'aria-controls'               => $dialog_id,
				'aria-expanded'               => 'false',
				'data-wp-bind--aria-expanded' => 'context.isOpen',
			)
		);
	}

	/**
	 * Measuring-tape icon markup for the trigger.
	 *
	 * @param int $size Width and height in pixels.
	 * @return string SVG markup.
	 */
	private function get_measuring_tape_icon( int $size ): string {
		return aggressive_apparel_get_icon(
			'measuring-tape',
			array(
				'width'       => $size,
				'height'      => $size,
				'aria-hidden' => 'true',
			)
		);
	}

	/**
	 * The guide a product's size guide dialog shows: per-product assignment →
	 * category assignment → global, first one with content. Cached per
	 * product; `id` is 0 when there is none.
	 *
	 * The single resolver for the dialog and the Product Specs block, so both
	 * always agree on which guide (and whether it is a structured chart).
	 *
	 * @param int $product_id Product ID, or 0 for the global guide only.
	 * @return Resolved
	 */
	public static function resolved( int $product_id ): array {
		return ( new self() )->get_size_guide_for_product( $product_id );
	}

	/**
	 * Resolved guide for a product, with transient caching.
	 *
	 * @param int|false $product_id Product ID.
	 * @return Resolved
	 */
	private function get_size_guide_for_product( $product_id ): array {
		if ( $product_id ) {
			$cached = Cache_Helper::remember(
				$this->product_cache_key( (int) $product_id ),
				self::CACHE_TTL,
				fn(): array => $this->resolve_size_guide( (int) $product_id ),
				static fn( $value ): bool => is_array( $value ) && isset( $value['id'], $value['html'], $value['chart'], $value['unit'], $value['measures'] )
			);

			if ( ! is_array( $cached ) ) {
				return $this->resolve_size_guide( (int) $product_id );
			}

			return array(
				'id'       => (int) $cached['id'],
				'html'     => (string) $cached['html'],
				'chart'    => (bool) $cached['chart'],
				'unit'     => 'cm' === $cached['unit'] ? 'cm' : 'in',
				'measures' => (bool) $cached['measures'],
			);
		}

		// No product ID — resolve global only.
		return $this->resolve_size_guide( 0 );
	}

	/**
	 * Resolve the guide without caching: the first published guide assigned
	 * to the product. A guide without a chart still counts (a specs-only
	 * guide, e.g. for hats): its specs show, and with no html there is no
	 * Size Guide button or link.
	 *
	 * @param int $product_id Product ID.
	 * @return Resolved
	 */
	private function resolve_size_guide( int $product_id ): array {
		foreach ( self::candidate_guide_ids( $product_id ) as $guide_id ) {
			$guide = $this->guide_content( $guide_id );
			if ( $guide['id'] > 0 ) {
				return $guide;
			}
		}

		return self::no_guide();
	}

	/**
	 * Nothing to show.
	 *
	 * @return Resolved
	 */
	private static function no_guide(): array {
		return array(
			'id'       => 0,
			'html'     => '',
			'chart'    => false,
			'unit'     => 'in',
			'measures' => false,
		);
	}

	/**
	 * Size guide IDs assigned to a product, in resolution order.
	 *
	 * Per-product assignment, then each category assignment, then the global
	 * fallback. IDs are unvalidated references; callers check the post.
	 *
	 * @param int $product_id Product ID, or 0 for the global guide only.
	 * @return int[]
	 */
	public static function candidate_guide_ids( int $product_id ): array {
		$ids = array();

		if ( $product_id > 0 ) {
			$own = (int) get_post_meta( $product_id, self::CPT_META_KEY, true );
			if ( self::NONE === $own ) {
				return array();
			}
			$ids[] = $own;

			// get_the_terms() reads the object term cache, so repeat lookups on
			// one request (guide modal + Product Specs block) stay query-free.
			$cats = get_the_terms( $product_id, 'product_cat' );
			if ( is_array( $cats ) ) {
				foreach ( $cats as $cat ) {
					$ids[] = (int) get_term_meta( $cat->term_id, self::CPT_META_KEY, true );
				}
			}
		}

		$ids[] = (int) get_option( self::CPT_OPTION_KEY, 0 );

		return array_values( array_unique( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) ) );
	}

	/**
	 * A published size guide's dialog content: its structured chart, or its
	 * old free-form content (kses-filtered) until it is converted.
	 *
	 * @param int $post_id Size guide post ID.
	 * @return Resolved
	 */
	private function guide_content( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || Size_Guide_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return self::no_guide();
		}

		$chart = Size_Chart::get( $post_id );
		if ( null !== $chart ) {
			return array(
				'id'       => $post_id,
				'html'     => Size_Chart::render( $post_id ),
				'chart'    => true,
				'unit'     => $chart['unit'],
				'measures' => Size_Chart::has_measures( $chart ),
			);
		}

		return array(
			'id'   => $post_id,
			'html' => wp_kses_post( (string) do_blocks( $post->post_content ) ),
		) + self::no_guide();
	}

	/**
	 * Add a "Size Guide" tab to the WooCommerce Product Data panel.
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_product_data_tab( array $tabs ): array {
		$tabs['aa_size_guide'] = array(
			'label'    => __( 'Size Guide', 'aggressive-apparel' ),
			'target'   => 'aa_size_guide_product_data',
			'class'    => array(),
			'priority' => 65,
		);

		return $tabs;
	}

	/**
	 * Output inline CSS for the Size Guide tab icon on product edit screens.
	 *
	 * @return void
	 */
	public function add_tab_icon_style(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		echo '<style>#woocommerce-product-data ul.wc-tabs li.aa_size_guide_options a::before{content:"\f163";font-family:dashicons}</style>';
	}

	/**
	 * Render the Size Guide product data panel.
	 *
	 * @return void
	 */
	public function render_product_data_panel(): void {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$selected_id = (int) get_post_meta( $post->ID, self::CPT_META_KEY, true );

		$options = array(
			''     => __( 'Category or store default', 'aggressive-apparel' ),
			'none' => __( 'No size guide', 'aggressive-apparel' ),
		);
		foreach ( $this->get_published_guides() as $guide ) {
			$options[ (string) $guide->ID ] = $guide->post_title;
		}

		echo '<div id="aa_size_guide_product_data" class="panel woocommerce_options_panel">';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		if ( function_exists( 'woocommerce_wp_select' ) ) {
			woocommerce_wp_select(
				array(
					'id'          => 'aa_size_guide_id',
					'label'       => __( 'Size Guide', 'aggressive-apparel' ),
					'options'     => $options,
					'value'       => self::NONE === $selected_id ? 'none' : ( $selected_id > 0 ? (string) $selected_id : '' ),
					'desc_tip'    => true,
					'description' => __( '“No size guide” hides the size guide on this product, even when its category or the store has one. Its own specs still show.', 'aggressive-apparel' ),
				),
			);
		}

		$new_url = Product_Specs_Settings::url( 'guides', array( 'guide' => 'new' ) );
		echo '<p class="form-field" style="padding-left:12px;">';
		printf(
			/* translators: %s: link to create a new size guide */
			esc_html__( 'Select a size guide or %s.', 'aggressive-apparel' ),
			'<a href="' . esc_url( $new_url ) . '">' .
				esc_html__( 'create a new one', 'aggressive-apparel' ) .
			'</a>',
		);
		echo '</p>';

		/**
		 * Fires at the end of the product Size Guide panel.
		 *
		 * Product Specs renders its fields here, beside the guide they inherit from.
		 *
		 * @param \WP_Post $post Product being edited.
		 */
		do_action( 'aggressive_apparel_size_guide_product_panel', $post );

		echo '</div>';
	}

	/**
	 * Save the size guide selection when product meta is processed.
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public function save_product_data( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$choice   = isset( $_POST['aa_size_guide_id'] ) ? sanitize_key( wp_unslash( $_POST['aa_size_guide_id'] ) ) : '';
		$guide_id = 'none' === $choice ? self::NONE : absint( $choice );

		if ( self::NONE === $guide_id || $guide_id > 0 ) {
			update_post_meta( $post_id, self::CPT_META_KEY, $guide_id );
		} else {
			delete_post_meta( $post_id, self::CPT_META_KEY );
		}

		delete_transient( $this->product_cache_key( $post_id ) );
	}

	/**
	 * Render the size guide field on the Add Category form.
	 *
	 * @return void
	 */
	public function render_category_add_field(): void {
		$guides = $this->get_published_guides();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<div class="form-field">';
		echo '<label for="aa_cat_size_guide_id">' . esc_html__( 'Size Guide', 'aggressive-apparel' ) . '</label>';
		echo '<select name="aa_cat_size_guide_id" id="aa_cat_size_guide_id">';
		echo '<option value="0">' . esc_html__( '-- None --', 'aggressive-apparel' ) . '</option>';
		foreach ( $guides as $guide ) {
			printf(
				'<option value="%d">%s</option>',
				absint( $guide->ID ),
				esc_html( $guide->post_title ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Assign a size guide to all products in this category.', 'aggressive-apparel' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the size guide field on the Edit Category form.
	 *
	 * @param \WP_Term $term Current term.
	 * @return void
	 */
	public function render_category_edit_field( \WP_Term $term ): void {
		$selected_id = (int) get_term_meta( $term->term_id, self::CPT_META_KEY, true );
		$guides      = $this->get_published_guides();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<tr class="form-field">';
		echo '<th scope="row"><label for="aa_cat_size_guide_id">' . esc_html__( 'Size Guide', 'aggressive-apparel' ) . '</label></th>';
		echo '<td>';
		echo '<select name="aa_cat_size_guide_id" id="aa_cat_size_guide_id" class="postform">';
		echo '<option value="0">' . esc_html__( '-- None --', 'aggressive-apparel' ) . '</option>';
		foreach ( $guides as $guide ) {
			printf(
				'<option value="%d" %s>%s</option>',
				absint( $guide->ID ),
				selected( $selected_id, $guide->ID, false ),
				esc_html( $guide->post_title ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Assign a size guide to all products in this category.', 'aggressive-apparel' ) . '</p>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Save the category size guide field.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_category_field( int $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$guide_id = isset( $_POST['aa_cat_size_guide_id'] )
			? absint( wp_unslash( $_POST['aa_cat_size_guide_id'] ) )
			: 0;

		if ( $guide_id > 0 ) {
			update_term_meta( $term_id, self::CPT_META_KEY, $guide_id );
		} else {
			delete_term_meta( $term_id, self::CPT_META_KEY );
		}

		// Category change affects all products in the category.
		$this->flush_all_caches();
	}

	/**
	 * Invalidate all size-guide caches in constant time.
	 *
	 * Called when a size guide CPT is updated or a category assignment
	 * changes, since those affect multiple products.
	 *
	 * @return void
	 */
	public function flush_all_caches(): void {
		update_option( self::CACHE_GENERATION_OPTION, $this->cache_generation() + 1, false );
	}

	/** Current cache generation. */
	private function cache_generation(): int {
		return max( 1, (int) get_option( self::CACHE_GENERATION_OPTION, 1 ) );
	}

	/**
	 * Product cache key for the current generation.
	 *
	 * @param int $product_id Product ID.
	 */
	private function product_cache_key( int $product_id ): string {
		return self::CACHE_PREFIX . 'v' . self::MARKUP_VERSION . '_' . $this->cache_generation() . '_' . $product_id;
	}

	/**
	 * Query published size guides for the admin assignment selectors.
	 *
	 * Keep the result bounded so an unexpectedly large guide library cannot
	 * create an unbounded query or admin response.
	 *
	 * @return \WP_Post[]
	 */
	private function get_published_guides(): array {
		return get_posts(
			array(
				'post_type'      => Size_Guide_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			),
		);
	}
}
