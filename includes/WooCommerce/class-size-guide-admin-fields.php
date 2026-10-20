<?php
/**
 * Size Guide Admin Fields
 *
 * The size guide pickers on a product (its own "Size Guide" tab) and on a
 * product category. Both offer the same choices: inherit (the category or
 * the store default), "No size guide", or a guide. A guide that is no longer
 * published stays listed while it is the current pick, so saving the form
 * for an unrelated change never drops the assignment.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product and category size guide pickers.
 */
final class Size_Guide_Admin_Fields {

	/**
	 * Nonce action for the pickers.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'aa_size_guide_save';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	private const NONCE_NAME = 'aa_size_guide_nonce';

	/**
	 * Picker value for "No size guide".
	 *
	 * @var string
	 */
	private const NONE = 'none';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ) );
		add_action( 'admin_head', array( $this, 'add_tab_icon_style' ) );

		add_action( 'product_cat_add_form_fields', array( $this, 'render_category_add_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'render_category_edit_field' ), 20 );
		add_action( 'created_product_cat', array( $this, 'save_category_field' ) );
		add_action( 'edited_product_cat', array( $this, 'save_category_field' ) );
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
	 */
	public function render_product_data_panel(): void {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$selected = (int) get_post_meta( $post->ID, Size_Guide::CPT_META_KEY, true );

		echo '<div id="aa_size_guide_product_data" class="panel woocommerce_options_panel">';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		if ( function_exists( 'woocommerce_wp_select' ) ) {
			woocommerce_wp_select(
				array(
					'id'          => 'aa_size_guide_id',
					'label'       => __( 'Size Guide', 'aggressive-apparel' ),
					'options'     => self::options( $selected, __( 'Category or store default', 'aggressive-apparel' ) ),
					'value'       => self::value( $selected ),
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
	 * Save the product's pick when product meta is processed.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_data( int $post_id ): void {
		if ( ! self::verified() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$guide_id = self::submitted( 'aa_size_guide_id' );
		if ( 0 !== $guide_id ) {
			update_post_meta( $post_id, Size_Guide::CPT_META_KEY, $guide_id );
		} else {
			delete_post_meta( $post_id, Size_Guide::CPT_META_KEY );
		}

		( new Size_Guide() )->flush_product( $post_id );
	}

	/**
	 * Render the picker on the Add Category form.
	 */
	public function render_category_add_field(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<div class="form-field">';
		echo '<label for="aa_cat_size_guide_id">' . esc_html__( 'Size Guide', 'aggressive-apparel' ) . '</label>';
		self::render_select( 0 );
		echo '<p class="description">' . esc_html( self::category_help() ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the picker on the Edit Category form.
	 *
	 * @param \WP_Term $term Category being edited.
	 */
	public function render_category_edit_field( \WP_Term $term ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<tr class="form-field">';
		echo '<th scope="row"><label for="aa_cat_size_guide_id">' . esc_html__( 'Size Guide', 'aggressive-apparel' ) . '</label></th>';
		echo '<td>';
		self::render_select( (int) get_term_meta( $term->term_id, Size_Guide::CPT_META_KEY, true ) );
		echo '<p class="description">' . esc_html( self::category_help() ) . '</p>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Save a category's pick.
	 *
	 * @param int $term_id Category term ID.
	 */
	public function save_category_field( int $term_id ): void {
		if ( ! self::verified() || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$guide_id = self::submitted( 'aa_cat_size_guide_id' );
		if ( 0 !== $guide_id ) {
			update_term_meta( $term_id, Size_Guide::CPT_META_KEY, $guide_id );
		} else {
			delete_term_meta( $term_id, Size_Guide::CPT_META_KEY );
		}

		// A category change affects every product in it.
		( new Size_Guide() )->flush_all_caches();
	}

	/**
	 * Picker choices: inherit, "No size guide", the published guides, and
	 * the current pick even when it is no longer published.
	 *
	 * @param int    $selected Stored assignment (guide ID, Size_Guide::NONE, or 0).
	 * @param string $inherit  Label for inheriting.
	 * @return array<int|string, string> Picker value => label (PHP stores numeric keys as ints).
	 */
	public static function options( int $selected, string $inherit ): array {
		$options = array(
			''         => $inherit,
			self::NONE => __( 'No size guide', 'aggressive-apparel' ),
		);
		$listed  = array();
		foreach ( self::published_guides() as $guide ) {
			$options[ (string) $guide->ID ] = '' !== $guide->post_title ? $guide->post_title : __( '(no title)', 'aggressive-apparel' );
			$listed[]                       = $guide->ID;
		}

		if ( $selected > 0 && ! in_array( $selected, $listed, true ) ) {
			$title                         = get_the_title( $selected );
			$options[ (string) $selected ] = sprintf(
				/* translators: %s: size guide title or ID. */
				__( '%s (not published)', 'aggressive-apparel' ),
				'' !== $title ? $title : '#' . $selected
			);
		}

		return $options;
	}

	/**
	 * The picker value for a stored assignment.
	 *
	 * @param int $selected Stored assignment.
	 */
	public static function value( int $selected ): string {
		if ( Size_Guide::NONE === $selected ) {
			return self::NONE;
		}

		return $selected > 0 ? (string) $selected : '';
	}

	/**
	 * The category picker.
	 *
	 * @param int $selected Stored assignment.
	 */
	private static function render_select( int $selected ): void {
		$current = self::value( $selected );
		echo '<select name="aa_cat_size_guide_id" id="aa_cat_size_guide_id" class="postform">';
		foreach ( self::options( $selected, __( 'Store default', 'aggressive-apparel' ) ) as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( (string) $value ),
				selected( $current, (string) $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/** Help text under the category picker. */
	private static function category_help(): string {
		return __( 'Products in this category use this guide unless they pick their own. “No size guide” leaves out the store default for them.', 'aggressive-apparel' );
	}

	/** Whether the request carries a valid picker nonce. */
	private static function verified(): bool {
		return isset( $_POST[ self::NONCE_NAME ] ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION );
	}

	/**
	 * The submitted pick: a guide ID, Size_Guide::NONE, or 0 to inherit.
	 *
	 * @param string $field Form field name.
	 */
	private static function submitted( string $field ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified() runs first.
		$choice = isset( $_POST[ $field ] ) ? sanitize_key( wp_unslash( $_POST[ $field ] ) ) : '';

		return self::NONE === $choice ? Size_Guide::NONE : absint( $choice );
	}

	/**
	 * Published guides for the pickers, bounded so a large library can't
	 * make an unbounded query.
	 *
	 * @return list<\WP_Post>
	 */
	private static function published_guides(): array {
		$posts = get_posts(
			array(
				'post_type'      => Size_Guide_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			),
		);

		return array_values( array_filter( $posts, static fn( $post ): bool => $post instanceof \WP_Post ) );
	}
}
