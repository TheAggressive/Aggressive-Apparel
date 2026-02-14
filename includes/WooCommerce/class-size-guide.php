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
	 * Legacy option key for the global size guide (raw HTML).
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'aggressive_apparel_size_guide';

	/**
	 * Legacy meta key for raw HTML size guides.
	 *
	 * @var string
	 */
	private const META_KEY = '_aggressive_apparel_size_guide';

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

	/**
	 * Cache TTL in seconds (15 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 900;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_trigger_and_modal' ) );

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

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/size-guide.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-size-guide',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/size-guide.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/size-guide',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/size-guide.js',
				array( '@wordpress/interactivity', '@aggressive-apparel/scroll-lock', '@aggressive-apparel/helpers' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/size-guide' );
		}
	}

	/**
	 * Render the "Size Guide" link and the hidden modal.
	 *
	 * @return void
	 */
	public function render_trigger_and_modal(): void {
		$guide = $this->get_size_guide_for_product( get_the_ID() );
		if ( empty( $guide ) ) {
			return;
		}

		// Trigger link.
		echo '<div data-wp-interactive="aggressive-apparel/size-guide" data-wp-context=\'{"isOpen":false}\'>';
		echo '<button type="button" class="aggressive-apparel-size-guide__trigger" data-wp-on--click="actions.toggle" aria-haspopup="dialog">';
		echo esc_html__( 'Size Guide', 'aggressive-apparel' );
		echo '</button>';

		// Modal.
		echo '<div class="aggressive-apparel-size-guide__overlay" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Size Guide', 'aggressive-apparel' ) . '" data-wp-class--is-open="context.isOpen" hidden>';
		echo '<div class="aggressive-apparel-size-guide__backdrop" data-wp-on--click="actions.close"></div>';
		echo '<div class="aggressive-apparel-size-guide__modal">';
		echo '<div class="aggressive-apparel-size-guide__header">';
		echo '<h2 class="aggressive-apparel-size-guide__title">' . esc_html__( 'Size Guide', 'aggressive-apparel' ) . '</h2>';
		echo '<button type="button" class="aggressive-apparel-size-guide__close" data-wp-on--click="actions.close" aria-label="' . esc_attr__( 'Close', 'aggressive-apparel' ) . '">&times;</button>';
		echo '</div>';
		echo '<div class="aggressive-apparel-size-guide__body">';
		echo wp_kses_post( $guide );
		echo '</div>';
		echo '</div>'; // modal.
		echo '</div>'; // overlay.
		echo '</div>'; // interactive root.
	}

	/**
	 * Retrieve the size guide content for a product, with transient caching.
	 *
	 * Priority: per-product CPT → per-product legacy → category CPT →
	 * category legacy → global CPT → global legacy.
	 *
	 * @param int|false $product_id Product ID.
	 * @return string HTML content or empty string.
	 */
	private function get_size_guide_for_product( $product_id ): string {
		if ( $product_id ) {
			$cache_key = self::CACHE_PREFIX . $product_id;
			$cached    = get_transient( $cache_key );
			if ( is_string( $cached ) ) {
				return $cached;
			}

			$content = $this->resolve_size_guide( $product_id );
			set_transient( $cache_key, $content, self::CACHE_TTL );
			return $content;
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
			// Per-product: CPT reference.
			$cpt_id = (int) get_post_meta( $product_id, self::CPT_META_KEY, true );
			if ( $cpt_id > 0 ) {
				$content = $this->get_cpt_content( $cpt_id );
				if ( '' !== $content ) {
					return $content;
				}
			}

			// Per-product: legacy raw HTML.
			$legacy = (string) get_post_meta( $product_id, self::META_KEY, true );
			if ( '' !== $legacy ) {
				return $legacy;
			}

			// Per-category.
			$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( is_array( $cats ) ) {
				foreach ( $cats as $cat_id ) {
					// Category: CPT reference.
					$cat_cpt_id = (int) get_term_meta( $cat_id, self::CPT_META_KEY, true );
					if ( $cat_cpt_id > 0 ) {
						$content = $this->get_cpt_content( $cat_cpt_id );
						if ( '' !== $content ) {
							return $content;
						}
					}

					// Category: legacy raw HTML.
					$cat_legacy = (string) get_term_meta( $cat_id, self::META_KEY, true );
					if ( '' !== $cat_legacy ) {
						return $cat_legacy;
					}
				}
			}
		}

		// Global: CPT reference.
		$global_cpt_id = (int) get_option( self::CPT_OPTION_KEY, 0 );
		if ( $global_cpt_id > 0 ) {
			$content = $this->get_cpt_content( $global_cpt_id );
			if ( '' !== $content ) {
				return $content;
			}
		}

		// Global: legacy raw HTML.
		return (string) get_option( self::OPTION_KEY, '' );
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
		$legacy_html = (string) get_post_meta( $post->ID, self::META_KEY, true );

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

		if ( '' !== $legacy_html && 0 === $selected_id ) {
			echo '<div class="notice notice-warning inline" style="margin:0.5rem 12px;"><p>';
			esc_html_e(
				'This product has a legacy inline size guide. Select a size guide above to replace it.',
				'aggressive-apparel',
			);
			echo '</p></div>';
		}

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

		delete_transient( self::CACHE_PREFIX . $post_id );
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
	 * Delete all size guide transient caches.
	 *
	 * Called when a size guide CPT is updated or a category assignment
	 * changes, since those affect multiple products.
	 *
	 * @return void
	 */
	public function flush_all_caches(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%'
			)
		);
	}

	/**
	 * Query all published size guide posts.
	 *
	 * @return \WP_Post[]
	 */
	private function get_published_guides(): array {
		return get_posts(
			array(
				'post_type'      => Size_Guide_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			),
		);
	}
}
