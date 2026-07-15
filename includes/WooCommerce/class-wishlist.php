<?php
/**
 * Wishlist Class
 *
 * Wishlist heart toggles, client-side storage, and wishlist page rendering.
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
use Aggressive_Apparel\Core\Icons;

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
	/** Object-cache group for page discovery. */
	private const CACHE_GROUP = 'aggressive-apparel-wishlist';

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
	 * Product IDs whose `aggressive-apparel/wishlist-button` blocks have rendered
	 * during the current request. Used to avoid duplicating the automatic
	 * single-product heart without suppressing buttons for other products.
	 *
	 * @var array<int, true>
	 */
	private static array $button_block_product_ids = array();

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_create_page' ), 20 );
		add_action( 'update_option_' . Feature_Settings::OPTION_KEY, array( $this, 'on_features_updated' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_heart' ), 6 );
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
		$cache_key = 'page:' . wp_cache_get_last_changed( 'posts' );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_int( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Result is cached below using the core posts last-changed generation.
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
			wp_cache_set( $cache_key, $page_id, self::CACHE_GROUP, HOUR_IN_SECONDS );
			return $page_id;
		}

		$page = get_page_by_path( self::PAGE_SLUG );
		if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
			$page_id = (int) $page->ID;
			wp_cache_set( $cache_key, $page_id, self::CACHE_GROUP, HOUR_IN_SECONDS );
			return $page_id;
		}

		wp_cache_set( $cache_key, 0, self::CACHE_GROUP, 300 );
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
		if ( ! $this->request_needs_assets() ) {
			return;
		}

		self::ensure_assets();
	}

	/**
	 * Ensure wishlist CSS, module, and shared state are available.
	 *
	 * This is public so dynamic block render callbacks and the automatic single-
	 * product button can recover when a wishlist surface was not detectable during
	 * `wp_enqueue_scripts`. WordPress de-duplicates repeated enqueues.
	 *
	 * @return void
	 */
	public static function ensure_assets(): void {
		if ( is_admin() || ! Feature_Settings::is_enabled( 'wishlist' ) ) {
			return;
		}

		if ( ! Asset_Loader::enqueue_feature_style( 'aggressive-apparel-wishlist', 'build/styles/woocommerce/wishlist' ) ) {
			return;
		}

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/wishlist',
			'build/interactivity/wishlist'
		);

		// Provide the public Store API URL and translatable heart labels.
		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				'aggressive-apparel/wishlist',
				array(
					'productsApiUrl' => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
					'i18n'           => array(
						'addLabel'    => Feature_Settings::get_wishlist_button_text(),
						'removeLabel' => __( 'Remove from wishlist', 'aggressive-apparel' ),
					),
				),
			);
		}
	}

	/**
	 * Whether the current request is expected to render a wishlist surface.
	 *
	 * Single-product routes cover the automatic summary button. Stored content
	 * detection covers blocks. Render-time calls to
	 * ensure_assets() remain the final fallback for dynamic block output.
	 *
	 * @return bool
	 */
	private function request_needs_assets(): bool {
		$needed = false;

		if ( ! is_admin() ) {
			$needed = Product_Context::is_single_product()
				|| $this->is_wishlist_page()
				|| $this->queried_content_needs_assets()
				|| (
					Feature_Settings::quick_view_includes_wishlist()
					&& Product_Context::is_product_listing()
				);
		}

		/**
		 * Filters whether wishlist frontend assets should load.
		 *
		 * @since 1.80.0
		 *
		 * @param bool $needed Whether the current request needs the assets.
		 */
		return (bool) apply_filters( 'aggressive_apparel_wishlist_needs_assets', $needed );
	}

	/**
	 * Whether this is the configured wishlist page.
	 *
	 * @return bool
	 */
	private function is_wishlist_page(): bool {
		$page_id = self::get_page_id();

		return $page_id > 0 && is_page( $page_id );
	}

	/**
	 * Check queried post content for known wishlist-producing surfaces.
	 *
	 * @return bool
	 */
	private function queried_content_needs_assets(): bool {
		$post_id = get_queried_object_id();
		if ( $post_id <= 0 && is_home() && ! is_front_page() ) {
			$post_id = (int) get_option( 'page_for_posts' );
		}

		if ( $post_id <= 0 ) {
			return false;
		}

		$content = get_post_field( 'post_content', $post_id );
		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		return str_contains( $content, '<!-- wp:aggressive-apparel/wishlist' );
	}

	/**
	 * Render heart icon on single product pages (next to title area).
	 *
	 * Skipped entirely when:
	 *   - The admin has set the placement option to `block`, OR
	 *   - The Wishlist Button block has already rendered earlier in the
	 *     request for this product (per-request opt-out for hybrid mode), OR
	 *   - A developer filter has explicitly suppressed automatic placement.
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
		if ( self::has_button_block_rendered( $product_id ) || ! $auto_inject ) {
			return;
		}

		echo aggressive_apparel_trusted_html( self::get_heart_button_html( $product_id, true ) );
	}

	/**
	 * Build heart button HTML for the unified document-delegate contract.
	 *
	 * Public so automatic placement, Quick View media stacks, and custom
	 * integrations share one markup shape. Clicks are handled by
	 * `@aggressive-apparel/wishlist` — no IA click bindings.
	 *
	 * @param int    $product_id    Product ID.
	 * @param bool   $large         Whether to use the large variant.
	 * @param string $extra_classes Optional BEM modifier classes.
	 * @return string
	 */
	public static function get_heart_button_html( int $product_id, bool $large = false, string $extra_classes = '' ): string {
		if ( $product_id <= 0 ) {
			return '';
		}

		self::ensure_assets();

		$classes = array( 'aggressive-apparel-wishlist__toggle' );
		if ( $large ) {
			$classes[] = 'aggressive-apparel-wishlist__toggle--large';
		}

		$extra_parts = preg_split( '/\s+/', trim( $extra_classes ) );
		if ( is_array( $extra_parts ) ) {
			foreach ( $extra_parts as $part ) {
				$safe = sanitize_html_class( $part );
				if ( '' !== $safe ) {
					$classes[] = $safe;
				}
			}
		}

		$label   = Feature_Settings::get_wishlist_button_text();
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( $product instanceof \WC_Product ) {
			$label = sprintf(
				/* translators: 1: Wishlist action label, 2: product name. */
				__( '%1$s: %2$s', 'aggressive-apparel' ),
				$label,
				$product->get_name()
			);
		}

		$icon = self::get_toggle_icon_html();

		return sprintf(
			'<button type="button" class="%1$s" data-aa-product-id="%2$d" aria-pressed="false" aria-label="%3$s" title="%4$s">%5$s</button>',
			esc_attr( implode( ' ', $classes ) ),
			$product_id,
			esc_attr( $label ),
			esc_attr( Feature_Settings::get_wishlist_button_text() ),
			$icon,
		);
	}

	/**
	 * Dual-layer heart for wishlist toggles (stroke idle → fill on active).
	 *
	 * Stroking the silhouette alone looks muddy at chip size; a separate fill
	 * path fades in cleanly when the item is wishlisted.
	 *
	 * @return string
	 */
	public static function get_toggle_icon_html(): string {
		$path = esc_attr( Icons::heart_path() );

		return sprintf(
			'<svg class="aggressive-apparel-wishlist__icon" viewBox="%1$s" width="22" height="22" aria-hidden="true" focusable="false">'
			. '<path class="aggressive-apparel-wishlist__icon-fill" d="%2$s" />'
			. '<path class="aggressive-apparel-wishlist__icon-stroke" d="%2$s" />'
			. '</svg>',
			esc_attr( Icons::heart_viewbox() ),
			$path
		);
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
	 * Mark a Wishlist Button block as rendered for a product in this request.
	 *
	 * When no ID is provided, the current product context is resolved for
	 * backward compatibility with existing integrations.
	 *
	 * @param int $product_id Product ID, or 0 to resolve the current context.
	 * @return void
	 */
	public static function mark_button_block_rendered( int $product_id = 0 ): void {
		$product_id = $product_id > 0 ? $product_id : self::get_current_product_id();
		if ( $product_id > 0 ) {
			self::$button_block_product_ids[ $product_id ] = true;
		}
	}

	/**
	 * Whether the Wishlist Button block has rendered for a product this request.
	 *
	 * @param int $product_id Product ID, or 0 to resolve the current context.
	 * @return bool
	 */
	public static function has_button_block_rendered( int $product_id = 0 ): bool {
		$product_id = $product_id > 0 ? $product_id : self::get_current_product_id();

		return $product_id > 0 && isset( self::$button_block_product_ids[ $product_id ] );
	}
}
