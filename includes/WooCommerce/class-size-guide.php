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
 */
class Size_Guide {

	/**
	 * Meta key for CPT post ID references.
	 *
	 * @var string
	 */
	private const CPT_META_KEY = '_aggressive_apparel_size_guide_id';

	/**
	 * Option key for global CPT size guide reference.
	 *
	 * @var string
	 */
	private const CPT_OPTION_KEY = 'aggressive_apparel_size_guide_id';

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
		if ( empty( $guide ) ) {
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
		$trigger_attributes = $this->get_trigger_attributes( $dialog_id );
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
		$markup .= sprintf(
			'<h2 id="%s" class="aggressive-apparel-size-guide__title">',
			esc_attr( $title_id )
		);
		$markup .= '<span class="aggressive-apparel-size-guide__title-icon" aria-hidden="true">';
		$markup .= $this->get_measuring_tape_icon( 24 );
		$markup .= '</span>';
		$markup .= esc_html__( 'Size Guide', 'aggressive-apparel' );
		$markup .= '</h2>';
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
		if ( is_string( $units_label ) && '' !== $units_label ) {
			$markup .= '<p class="aggressive-apparel-size-guide__units">' . esc_html( $units_label ) . '</p>';
		}
		$markup .= '<div class="aggressive-apparel-size-guide__body">';
		$markup .= wp_kses_post( $guide );
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
	 * Measuring-tape icon markup for trigger/title.
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
	 * Retrieve the size guide content for a product, with transient caching.
	 *
	 * Priority: per-product assignment → category assignment → global
	 * assignment.
	 *
	 * @param int|false $product_id Product ID.
	 * @return string HTML content or empty string.
	 */
	private function get_size_guide_for_product( $product_id ): string {
		if ( $product_id ) {
			return Cache_Helper::remember(
				$this->product_cache_key( (int) $product_id ),
				self::CACHE_TTL,
				fn(): string => $this->resolve_size_guide( $product_id ),
				'is_string'
			);
		}

		// No product ID — resolve global only.
		return $this->resolve_size_guide( false );
	}

	/**
	 * Resolve the size guide content without caching.
	 *
	 * @param int|false $product_id Product ID.
	 * @return string HTML content or empty string.
	 */
	private function resolve_size_guide( $product_id ): string {
		if ( $product_id ) {
			// Per-product assignment.
			$cpt_id = (int) get_post_meta( $product_id, self::CPT_META_KEY, true );
			if ( $cpt_id > 0 ) {
				$content = $this->get_cpt_content( $cpt_id );
				if ( '' !== $content ) {
					return $content;
				}
			}

			// Per-category.
			$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( is_array( $cats ) ) {
				foreach ( $cats as $cat_id ) {
					// Category assignment.
					$cat_cpt_id = (int) get_term_meta( $cat_id, self::CPT_META_KEY, true );
					if ( $cat_cpt_id > 0 ) {
						$content = $this->get_cpt_content( $cat_cpt_id );
						if ( '' !== $content ) {
							return $content;
						}
					}
				}
			}
		}

		// Global assignment.
		$global_cpt_id = (int) get_option( self::CPT_OPTION_KEY, 0 );
		if ( $global_cpt_id > 0 ) {
			$content = $this->get_cpt_content( $global_cpt_id );
			if ( '' !== $content ) {
				return $content;
			}
		}

		return '';
	}

	/**
	 * Get rendered content from a size guide CPT post.
	 *
	 * @param int $post_id Size guide post ID.
	 * @return string Rendered HTML or empty string.
	 */
	private function get_cpt_content( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		if ( Size_Guide_Post_Type::POST_TYPE !== $post->post_type ) {
			return '';
		}
		if ( 'publish' !== $post->post_status ) {
			return '';
		}

		return wp_kses_post( (string) do_blocks( $post->post_content ) );
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

		$options = array( '' => __( '-- Inherit (category or global) --', 'aggressive-apparel' ) );
		foreach ( $this->get_published_guides() as $guide ) {
			$options[ (string) $guide->ID ] = $guide->post_title;
		}

		echo '<div id="aa_size_guide_product_data" class="panel woocommerce_options_panel">';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		if ( function_exists( 'woocommerce_wp_select' ) ) {
			woocommerce_wp_select(
				array(
					'id'      => 'aa_size_guide_id',
					'label'   => __( 'Size Guide', 'aggressive-apparel' ),
					'options' => $options,
					'value'   => $selected_id > 0 ? (string) $selected_id : '',
				),
			);
		}

		$new_url = admin_url( 'post-new.php?post_type=' . Size_Guide_Post_Type::POST_TYPE );
		echo '<p class="form-field" style="padding-left:12px;">';
		printf(
			/* translators: %s: link to create a new size guide */
			esc_html__( 'Select a size guide or %s.', 'aggressive-apparel' ),
			'<a href="' . esc_url( $new_url ) . '" target="_blank">' .
				esc_html__( 'create a new one', 'aggressive-apparel' ) .
			'</a>',
		);
		echo '</p>';
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

		$guide_id = isset( $_POST['aa_size_guide_id'] )
			? absint( wp_unslash( $_POST['aa_size_guide_id'] ) )
			: 0;

		if ( $guide_id > 0 ) {
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
		return self::CACHE_PREFIX . $this->cache_generation() . '_' . $product_id;
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
