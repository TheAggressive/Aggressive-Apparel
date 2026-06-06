<?php
/**
 * Wishlist Class
 *
 * Heart-icon toggle on product cards and single product pages.
 * All storage is handled client-side via localStorage for zero database impact.
 * Product details for the wishlist page are fetched from the public
 * WooCommerce Store API (read-only, no auth required).
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wishlist
 *
 * @since 1.17.0
 */
class Wishlist {

	/**
	 * Option key storing the auto-created wishlist page ID.
	 *
	 * @var string
	 */
	public const PAGE_ID_OPTION = 'aggressive_apparel_wishlist_page_id';

	/**
	 * Default wishlist page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'wishlist';

	/**
	 * Per-request flag set when the `aggressive-apparel/wishlist-button` block
	 * has rendered for the current product context. Used to short-circuit the
	 * legacy auto-injection on single product pages so we don't render the
	 * heart twice when the user has explicitly placed the block.
	 *
	 * @var bool
	 */
	private static bool $button_block_rendered = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'maybe_create_page' ), 20 );
		add_action( 'update_option_' . Feature_Settings::OPTION_KEY, array( $this, 'on_features_updated' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'inject_heart_icon' ), 10, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_heart' ), 6 );
		add_shortcode( 'aggressive_apparel_wishlist', array( $this, 'render_wishlist_page' ) );
	}

	/**
	 * Create the wishlist page when the feature is enabled and none exists.
	 *
	 * @return void
	 */
	public function maybe_create_page(): void {
		if ( ! Feature_Settings::is_enabled( 'wishlist' ) ) {
			return;
		}

		if ( self::get_page_id() > 0 ) {
			return;
		}

		$existing_id = self::find_existing_page_id();
		if ( $existing_id > 0 ) {
			update_option( self::PAGE_ID_OPTION, $existing_id );
			return;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		self::create_page();
	}

	/**
	 * Ensure a wishlist page exists when the feature flag is turned on.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public function on_features_updated( $old_value, $value ): void {
		if ( ! is_array( $value ) || empty( $value['wishlist'] ) ) {
			return;
		}

		$this->maybe_create_page();
	}

	/**
	 * Get the stored wishlist page ID when the page still exists.
	 *
	 * @return int Page ID, or 0 when unavailable.
	 */
	public static function get_page_id(): int {
		$page_id = (int) get_option( self::PAGE_ID_OPTION, 0 );
		if ( $page_id <= 0 ) {
			return 0;
		}

		$post = get_post( $page_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
			return 0;
		}

		return $page_id;
	}

	/**
	 * Resolve the public wishlist page URL.
	 *
	 * @return string
	 */
	public static function get_page_url(): string {
		$page_id = self::get_page_id();
		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		$existing_id = self::find_existing_page_id();
		if ( $existing_id > 0 ) {
			$url = get_permalink( $existing_id );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return home_url( '/' . self::PAGE_SLUG . '/' );
	}

	/**
	 * Find an existing published page that can serve as the wishlist page.
	 *
	 * @return int Page ID, or 0 when none is found.
	 */
	public static function find_existing_page_id(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off lookup for page discovery.
		$page_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'page'
				AND post_status = 'publish'
				AND post_content LIKE %s
				LIMIT 1",
				'%' . $wpdb->esc_like( 'aggressive-apparel/wishlist' ) . '%'
			)
		);

		if ( $page_id > 0 ) {
			return $page_id;
		}

		$page = get_page_by_path( self::PAGE_SLUG );
		if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
			return (int) $page->ID;
		}

		return 0;
	}

	/**
	 * Create and publish the default wishlist page.
	 *
	 * @return int Created page ID, or 0 on failure.
	 */
	public static function create_page(): int {
		$page_args = array(
			'post_title'   => __( 'Wishlist', 'aggressive-apparel' ),
			'post_name'    => self::PAGE_SLUG,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => self::get_default_page_content(),
		);

		/**
		 * Filters the arguments used when auto-creating the wishlist page.
		 *
		 * @since 1.77.0
		 *
		 * @param array<string, mixed> $page_args Page creation arguments.
		 */
		$page_args = apply_filters( 'aggressive_apparel_wishlist_page_args', $page_args );

		$page_id = wp_insert_post( $page_args, true );
		if ( is_wp_error( $page_id ) ) {
			return 0;
		}

		update_option( self::PAGE_ID_OPTION, (int) $page_id );

		return (int) $page_id;
	}

	/**
	 * Default block markup for the auto-created wishlist page.
	 *
	 * @return string Block content.
	 */
	public static function get_default_page_content(): string {
		$content = <<<'BLOCKS'
<!-- wp:aggressive-apparel/wishlist -->
<!-- wp:aggressive-apparel/wishlist-item-image /-->

<!-- wp:aggressive-apparel/wishlist-item-name /-->

<!-- wp:aggressive-apparel/wishlist-item-price /-->

<!-- wp:aggressive-apparel/wishlist-item-actions /-->
<!-- /wp:aggressive-apparel/wishlist -->
BLOCKS;

		/**
		 * Filters the default block content for the auto-created wishlist page.
		 *
		 * @since 1.77.0
		 *
		 * @param string $content Block markup.
		 */
		return (string) apply_filters( 'aggressive_apparel_wishlist_page_content', $content );
	}

	/**
	 * Enqueue styles and register Interactivity API script module on relevant pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! Asset_Loader::enqueue_feature_style( 'aggressive-apparel-wishlist', 'build/styles/woocommerce/wishlist' ) ) {
			return;
		}

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/wishlist',
			'build/interactivity/wishlist'
		);

		// Provide the public Store API URL so the wishlist page can fetch product details.
		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				'aggressive-apparel/wishlist',
				array(
					'productsApiUrl' => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
				),
			);
		}
	}

	/**
	 * Inject heart icon on product card images in archives.
	 *
	 * Skipped when the admin has set the placement option to `block`,
	 * so the user can manually drop the wishlist button into their
	 * Product Collection / Query Loop template instead.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_heart_icon( string $block_content, array $block ): string {
		if ( ! Block_Render_Helper::is_featured_image_block( $block ) ) {
			return $block_content;
		}

		// Only product cards in loops/collections — skip blog posts and other CPTs.
		if ( 'product' !== get_post_type() ) {
			return $block_content;
		}

		// Single product pages use the summary hook / Wishlist Button block.
		if ( function_exists( 'is_product' ) && is_product() ) {
			return $block_content;
		}

		// Admin setting wins: when set to `block`, the user is fully in
		// charge of placement via the Wishlist Button block.
		$placement = Feature_Settings::get_wishlist_button_placement();
		if ( 'block' === $placement ) {
			return $block_content;
		}

		/**
		 * Filters whether to automatically inject the wishlist heart on
		 * product card featured images.
		 *
		 * Set to false (or place the `aggressive-apparel/wishlist-button`
		 * block inside your card template) to suppress the legacy
		 * automatic placement.
		 *
		 * @since 1.17.0
		 *
		 * @param bool  $auto_inject Whether to inject. Defaults to true.
		 * @param array $block       The block being rendered.
		 */
		$auto_inject = (bool) apply_filters( 'aggressive_apparel_auto_inject_wishlist_card', true, $block );
		if ( ! $auto_inject ) {
			return $block_content;
		}

		$product_id = self::get_current_product_id();
		if ( $product_id <= 0 ) {
			return $block_content;
		}

		$button = $this->get_heart_button_html( $product_id );

		return Block_Render_Helper::append_before_wrapper_close( $block_content, $button );
	}

	/**
	 * Render heart icon on single product pages (next to title area).
	 *
	 * Skipped entirely when:
	 *   - The admin has set the placement option to `block`, OR
	 *   - The Wishlist Button block has already rendered earlier in the
	 *     request for this product (per-request opt-out for hybrid mode), OR
	 *   - A developer filter has explicitly suppressed auto-injection.
	 *
	 * @return void
	 */
	public function render_single_heart(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = (int) get_the_ID();
		if ( $product_id <= 0 ) {
			return;
		}

		$placement = Feature_Settings::get_wishlist_button_placement();
		if ( 'block' === $placement ) {
			return;
		}

		/**
		 * Filters whether to automatically inject the wishlist heart on
		 * the single product summary.
		 *
		 * @since 1.17.0
		 *
		 * @param bool $auto_inject Whether to inject. Defaults to true.
		 */
		$auto_inject = (bool) apply_filters( 'aggressive_apparel_auto_inject_wishlist_single', true );
		if ( self::$button_block_rendered || ! $auto_inject ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with esc_ functions.
		echo $this->get_heart_button_html( $product_id, true );
	}

	/**
	 * Build heart button HTML for the unified document-delegate contract.
	 *
	 * Public so callers (auto-injection, AJAX helpers) share one markup shape.
	 * Clicks are handled by `@aggressive-apparel/wishlist` — no IA click bindings.
	 *
	 * @param int    $product_id    Product ID.
	 * @param bool   $large         Whether to use the large variant.
	 * @param string $extra_classes Optional BEM modifier classes.
	 * @return string
	 */
	public function get_heart_button_html( int $product_id, bool $large = false, string $extra_classes = '' ): string {
		$class = 'aggressive-apparel-wishlist__toggle';
		if ( $large ) {
			$class .= ' aggressive-apparel-wishlist__toggle--large';
		}
		if ( '' !== $extra_classes ) {
			$class .= ' ' . $extra_classes;
		}

		return sprintf(
			'<button type="button" class="%1$s" data-aa-product-id="%2$d" aria-pressed="false" aria-label="%3$s" title="%4$s">
				<svg class="aggressive-apparel-wishlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
			</button>',
			esc_attr( $class ),
			$product_id,
			esc_attr__( 'Add to wishlist', 'aggressive-apparel' ),
			esc_attr__( 'Wishlist', 'aggressive-apparel' ),
		);
	}

	/**
	 * Render the wishlist shortcode output.
	 *
	 * Uses data-wp-each to iterate over wishlist products fetched client-side
	 * from the public WooCommerce Store API. The template element defines the
	 * markup for each product card.
	 *
	 * @return string Shortcode HTML.
	 */
	public function render_wishlist_page(): string {
		$context = (string) wp_json_encode(
			array(
				'loaded' => false,
			),
		);

		// phpcs:disable Generic.WhiteSpace.ScopeIndent -- Inline HTML concatenation.
		$html = '<div class="aggressive-apparel-wishlist-page"'
			. ' data-wp-interactive="aggressive-apparel/wishlist"'
			. ' data-wp-context=\'' . esc_attr( $context ) . '\''
			. ' data-wp-init="callbacks.loadWishlistPage">';

		// Product grid — rendered via data-wp-each over state.wishlistProducts.
		$html .= '<div class="aggressive-apparel-wishlist-page__grid"'
			. ' data-wp-bind--hidden="!context.loaded"'
			. ' hidden>';

		$html .= '<template data-wp-each="state.wishlistProducts">';
		$html .= '<div class="aggressive-apparel-wishlist-page__item">';
		$html .= '<a class="aggressive-apparel-wishlist-page__item-link" data-wp-bind--href="context.item.permalink">';
		$html .= '<img class="aggressive-apparel-wishlist-page__item-image no-lazy" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" alt=""'
			. ' data-wp-watch="callbacks.syncItemImage" />';
		$html .= '</a>';
		$html .= '<a class="aggressive-apparel-wishlist-page__item-name" data-wp-bind--href="context.item.permalink" data-wp-text="context.item.name"></a>';
		$html .= '<span class="aggressive-apparel-wishlist-page__item-price" data-wp-text="context.item.price"></span>';
		$html .= '</div>';
		$html .= '</template>';

		$html .= '</div>';

		// Empty state.
		$html .= '<p class="aggressive-apparel-wishlist-page__empty"'
			. ' data-wp-bind--hidden="state.hasWishlistItems">'
			. esc_html__( 'Your wishlist is empty.', 'aggressive-apparel' )
			. '</p>';

		// Loading state.
		$html .= '<p class="aggressive-apparel-wishlist-page__loading"'
			. ' data-wp-bind--hidden="context.loaded">'
			. esc_html__( 'Loading wishlist…', 'aggressive-apparel' )
			. '</p>';

		$html .= '</div>';
		// phpcs:enable Generic.WhiteSpace.ScopeIndent

		return $html;
	}

	/**
	 * Resolve the product ID for the current rendering context.
	 *
	 * Works on:
	 *   - Single product pages (uses the queried object).
	 *   - Inside Query Loop / Product Collection blocks (uses the
	 *     in-loop post via `get_the_ID()` once the block has set up
	 *     post data for the current iteration).
	 *
	 * Returns 0 when no product context is available — callers should
	 * treat that as "do not render".
	 *
	 * @return int Product post ID, or 0 when unavailable.
	 */
	public static function get_current_product_id(): int {
		return Product_Context::resolve_product_id();
	}

	/**
	 * Mark the Wishlist Button block as rendered for the current request.
	 *
	 * Allows the legacy single-product auto-injection to short-circuit
	 * once the user has placed the block earlier in the same request,
	 * preventing duplicate hearts.
	 *
	 * @return void
	 */
	public static function mark_button_block_rendered(): void {
		self::$button_block_rendered = true;
	}

	/**
	 * Whether the Wishlist Button block has already rendered this request.
	 *
	 * @return bool
	 */
	public static function has_button_block_rendered(): bool {
		return self::$button_block_rendered;
	}
}
