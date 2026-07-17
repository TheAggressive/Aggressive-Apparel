<?php
/**
 * Render smoke tests for high-traffic theme blocks.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);


namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Filters;
use WP_UnitTestCase;

/**
 * Smoke-test that key blocks render expected shell markup.
 */
class Block_Render_Smoke_Test extends WP_UnitTestCase {

	/**
	 * Ensure theme blocks are registered.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! Blocks::is_block_registered( 'aggressive-apparel/search' ) ) {
			Blocks::register();
		}
	}

	/**
	 * Clear feature flags and any assets enqueued by render helpers.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		remove_all_filters( 'aggressive_apparel_free_shipping_threshold' );

		foreach ( array( 'aggressive-apparel-wishlist', 'aggressive-apparel-product-filters' ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}

		if ( function_exists( 'wp_dequeue_script_module' ) ) {
			wp_dequeue_script_module( '@aggressive-apparel/wishlist' );
			wp_deregister_script_module( '@aggressive-apparel/wishlist' );
			wp_dequeue_script_module( '@aggressive-apparel/product-filters' );
			wp_deregister_script_module( '@aggressive-apparel/product-filters' );
		}

		$assets_flag = new \ReflectionProperty( Product_Filters::class, 'assets_enqueued' );
		$assets_flag->setAccessible( true );
		$assets_flag->setValue( null, false );

		parent::tearDown();
	}

	/**
	 * Render a registered aggressive-apparel block.
	 *
	 * @param string               $name       Block name without namespace prefix when omitted.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<int, mixed>    $inner      Optional innerBlocks tree.
	 * @param array<string, mixed> $context    Optional block context (e.g. postId).
	 * @return string
	 */
	private function render( string $name, array $attributes = array(), array $inner = array(), array $context = array() ): string {
		$block_name = str_starts_with( $name, 'aggressive-apparel/' )
			? $name
			: 'aggressive-apparel/' . $name;

		$parsed = array(
			'blockName'    => $block_name,
			'attrs'        => $attributes,
			'innerBlocks'  => $inner,
			'innerContent' => array(),
		);

		if ( array() === $context ) {
			return (string) render_block( $parsed );
		}

		$block = new \WP_Block( $parsed, $context );

		return (string) $block->render();
	}

	/**
	 * Search trigger renders a button wired to the shared search store.
	 *
	 * @return void
	 */
	public function test_search_block_renders_trigger(): void {
		$html = $this->render(
			'search',
			array(
				'label'     => 'Search catalog',
				'showLabel' => true,
			)
		);

		$this->assertStringContainsString( 'aa-search-trigger', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/search"', $html );
		$this->assertStringContainsString( 'aria-haspopup="dialog"', $html );
		$this->assertStringContainsString( 'Search catalog', $html );
	}

	/**
	 * Search icon-only mode keeps the accessible name on the button.
	 *
	 * @return void
	 */
	public function test_search_block_icon_only_keeps_aria_label(): void {
		$html = $this->render(
			'search',
			array(
				'label'     => 'Find products',
				'showLabel' => false,
			)
		);

		$this->assertStringContainsString( 'aa-search-trigger', $html );
		$this->assertStringContainsString( 'aria-label="Find products"', $html );
		$this->assertStringNotContainsString( 'aa-search-trigger__label', $html );
	}

	/**
	 * Hero carousel renders region shell with cover slide content.
	 *
	 * @return void
	 */
	public function test_hero_carousel_renders_region_and_slide(): void {
		$html = $this->render(
			'hero-carousel',
			array(
				'transition' => 'fade',
				'autoplay'   => false,
			),
			array(
				array(
					'blockName'    => 'core/cover',
					'attrs'        => array(
						'url'      => 'https://example.com/hero.jpg',
						'dimRatio' => 0,
					),
					'innerBlocks'  => array(
						array(
							'blockName'    => 'core/paragraph',
							'attrs'        => array(),
							'innerBlocks'  => array(),
							'innerContent' => array( '<p>Drop live</p>' ),
						),
					),
					'innerContent' => array( '<p>Drop live</p>' ),
				),
			)
		);

		$this->assertNotSame( '', $html );
		$this->assertStringContainsString( 'aa-hero', $html );
		$this->assertStringContainsString( 'role="region"', $html );
		$this->assertStringContainsString( 'aria-roledescription="carousel"', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/hero-carousel"', $html );
	}

	/**
	 * Hero carousel locks Cover backgrounds to the editor sizeSlug.
	 *
	 * Prevents core responsive-image rewrites from serving a softer
	 * candidate than the Resolution control selected in the editor.
	 *
	 * @return void
	 */
	public function test_hero_carousel_respects_cover_image_resolution(): void {
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
			self::factory()->post->create()
		);

		$full = wp_get_attachment_image_src( $attachment_id, 'full' );
		$this->assertIsArray( $full );
		$this->assertNotEmpty( $full[0] );

		// Saved markup deliberately uses a smaller URL so the tuner must
		// re-resolve from sizeSlug=full (editor Resolution control).
		$medium = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$wrong_src = is_array( $medium ) && ! empty( $medium[0] ) ? $medium[0] : 'https://example.com/soft.jpg';

		$cover_img = sprintf(
			'<img class="wp-block-cover__image-background wp-image-%1$d size-full" src="%2$s" data-object-fit="cover" alt="" />',
			$attachment_id,
			esc_url( $wrong_src )
		);

		$html = $this->render(
			'hero-carousel',
			array(
				'transition' => 'fade',
				'autoplay'   => false,
			),
			array(
				array(
					'blockName'    => 'core/cover',
					'attrs'        => array(
						'url'      => $full[0],
						'id'       => $attachment_id,
						'sizeSlug' => 'full',
						'dimRatio' => 0,
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '<div class="wp-block-cover">' . $cover_img . '<div class="wp-block-cover__inner-container"></div></div>',
					'innerContent' => array(
						'<div class="wp-block-cover">' . $cover_img . '<div class="wp-block-cover__inner-container"></div></div>',
					),
				),
			)
		);

		$this->assertStringContainsString( 'src="' . esc_url( $full[0] ) . '"', $html );
		$this->assertStringContainsString( 'sizes="100vw"', $html );
		$this->assertStringContainsString( esc_url( $full[0] ) . ' ' . (int) $full[1] . 'w', $html );
		$this->assertStringNotContainsString( 'src="' . esc_url( $wrong_src ) . '"', $html );
	}

	/**
	 * Wishlist page shell renders when the feature flag is enabled.
	 *
	 * @return void
	 */
	public function test_wishlist_block_renders_shell_when_enabled(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist block render.' );
		}

		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'wishlist' => true,
			)
		);

		$html = $this->render(
			'wishlist',
			array(
				'showCount'    => true,
				'emptyMessage' => 'Nothing saved yet.',
			)
		);

		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/wishlist"', $html );
		$this->assertStringContainsString( 'aa-wishlist-page__empty', $html );
		$this->assertStringContainsString( 'Nothing saved yet.', $html );
		$this->assertStringContainsString( 'data-wp-each="state.wishlistProducts"', $html );
	}

	/**
	 * Wishlist returns empty markup when the feature is disabled.
	 *
	 * @return void
	 */
	public function test_wishlist_block_is_empty_when_disabled(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist block render.' );
		}

		update_option( Feature_Settings::OPTION_KEY, array() );

		$html = $this->render( 'wishlist' );
		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * Enter a product post-type archive so is_shop() / is_product_archive() are true.
	 *
	 * @return void
	 */
	private function pretend_shop_archive(): void {
		$archive = get_post_type_archive_link( 'product' );
		$this->assertIsString( $archive );
		$this->assertNotSame( '', $archive );
		$this->go_to( $archive );
		$this->assertTrue( function_exists( 'is_shop' ) && is_shop() );
	}

	/**
	 * Filter toggle renders on a product archive when filters are enabled.
	 *
	 * @return void
	 */
	public function test_filter_toggle_renders_on_shop_archive(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for filter toggle render.' );
		}

		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'product_filters' => true,
			)
		);

		$this->pretend_shop_archive();

		$html = $this->render(
			'filter-toggle',
			array(
				'label'     => 'Filters',
				'showLabel' => true,
			)
		);

		$this->assertStringContainsString( 'aa-filter-toggle', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/product-filters"', $html );
		$this->assertStringContainsString( 'aria-haspopup="dialog"', $html );
	}

	/**
	 * Active filter bar renders Clear All control on shop archives.
	 *
	 * Starts hidden so Clear All does not flash before Interactivity hydrates.
	 *
	 * @return void
	 */
	public function test_filter_active_bar_renders_on_shop_archive(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for filter active bar render.' );
		}

		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'product_filters' => true,
			)
		);

		$this->pretend_shop_archive();

		$html = $this->render( 'filter-active-bar' );

		$this->assertStringContainsString( 'aa-filter-active-bar', $html );
		$this->assertStringContainsString( 'data-wp-bind--hidden="state.hasNoActiveFilters"', $html );
		$this->assertMatchesRegularExpression(
			'/<div\b[^>]*\baa-filter-active-bar\b[^>]*\bhidden\b/s',
			$html
		);
		$this->assertStringContainsString( 'aa-filter-active-bar__clear-all', $html );
		$this->assertStringContainsString( 'Clear All', $html );
	}

	/**
	 * Free shipping bar renders progress UI when a threshold is available.
	 *
	 * @return void
	 */
	public function test_free_shipping_bar_renders_progress(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			$this->markTestSkipped( 'WooCommerce cart is required for free shipping bar render.' );
		}

		add_filter(
			'aggressive_apparel_free_shipping_threshold',
			static fn(): float => 100.0
		);

		$html = $this->render(
			'free-shipping-bar',
			array(
				'customThreshold' => 100,
			)
		);

		$this->assertStringContainsString( 'aggressive-apparel-shipping-bar', $html );
		$this->assertStringContainsString( 'role="progressbar"', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/free-shipping-bar"', $html );
	}

	/**
	 * Free shipping message renders interactive message shell.
	 *
	 * @return void
	 */
	public function test_free_shipping_message_renders_shell(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			$this->markTestSkipped( 'WooCommerce cart is required for free shipping message render.' );
		}

		add_filter(
			'aggressive_apparel_free_shipping_threshold',
			static fn(): float => 75.0
		);

		$html = $this->render(
			'free-shipping-message',
			array(
				'customThreshold' => 75,
				'emphasisText'    => 'FREE Shipping',
			)
		);

		$this->assertStringContainsString( 'aggressive-apparel-free-shipping-message', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/free-shipping-message"', $html );
	}

	/**
	 * Wishlist item image renders linked placeholder wired for IA sync.
	 *
	 * @return void
	 */
	public function test_wishlist_item_image_renders_linked_placeholder(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist item image render.' );
		}

		$html = $this->render(
			'wishlist-item-image',
			array(
				'imageRatio'    => '3/4',
				'linkToProduct' => true,
			)
		);

		$this->assertStringContainsString( 'aa-wl-item-image', $html );
		$this->assertStringContainsString( 'aa-wl-item-image__link', $html );
		$this->assertStringContainsString( 'data-wp-bind--href="context.item.permalink"', $html );
		$this->assertStringContainsString( 'data-wp-bind--aria-label="context.item.name"', $html );
		$this->assertStringContainsString( 'data-wp-watch="callbacks.syncItemImage"', $html );
		$this->assertStringContainsString( 'aspect-ratio:3/4', $html );
	}

	/**
	 * Wishlist item image can render without a product link.
	 *
	 * @return void
	 */
	public function test_wishlist_item_image_renders_without_link(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist item image render.' );
		}

		$html = $this->render(
			'wishlist-item-image',
			array(
				'linkToProduct' => false,
			)
		);

		$this->assertStringContainsString( 'aa-wl-item-image__img', $html );
		$this->assertStringNotContainsString( 'aa-wl-item-image__link', $html );
	}

	/**
	 * Wishlist item name renders product link bindings.
	 *
	 * @return void
	 */
	public function test_wishlist_item_name_renders_link_bindings(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist item name render.' );
		}

		$html = $this->render( 'wishlist-item-name' );

		$this->assertStringContainsString( 'aa-wl-item-name', $html );
		$this->assertStringContainsString( 'aa-wl-item-name__link', $html );
		$this->assertStringContainsString( 'data-wp-bind--href="context.item.permalink"', $html );
		$this->assertStringContainsString( 'data-wp-text="context.item.name"', $html );
	}

	/**
	 * Wishlist item price renders price text binding.
	 *
	 * @return void
	 */
	public function test_wishlist_item_price_renders_text_binding(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist item price render.' );
		}

		$html = $this->render( 'wishlist-item-price' );

		$this->assertStringContainsString( 'aa-wl-item-price', $html );
		$this->assertStringContainsString( 'aa-wl-item-price__text', $html );
		$this->assertStringContainsString( 'data-wp-text="context.item.price"', $html );
	}

	/**
	 * Wishlist item actions render remove control by default.
	 *
	 * @return void
	 */
	public function test_wishlist_item_actions_renders_remove(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist item actions render.' );
		}

		$html = $this->render( 'wishlist-item-actions' );

		$this->assertStringContainsString( 'aa-wl-item-actions', $html );
		$this->assertStringContainsString( 'aa-wl-item-actions__remove', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.removeItem"', $html );
		$this->assertStringContainsString( 'Remove from wishlist', $html );
		$this->assertStringNotContainsString( 'aa-wl-item-actions__atc', $html );
	}

	/**
	 * Wishlist item actions can include Add to Cart.
	 *
	 * @return void
	 */
	public function test_wishlist_item_actions_renders_add_to_cart(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist item actions render.' );
		}

		$html = $this->render(
			'wishlist-item-actions',
			array(
				'showAddToCart'  => true,
				'addToCartLabel' => 'Buy now',
			)
		);

		$this->assertStringContainsString( 'aa-wl-item-actions__atc', $html );
		$this->assertStringContainsString( 'data-wp-bind--href="context.item.addToCartUrl"', $html );
		$this->assertStringContainsString( 'Buy now', $html );
	}

	/**
	 * Wishlist item actions return empty markup when all controls are off.
	 *
	 * @return void
	 */
	public function test_wishlist_item_actions_empty_when_all_disabled(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for wishlist item actions render.' );
		}

		$html = $this->render(
			'wishlist-item-actions',
			array(
				'showRemove'    => false,
				'showAddToCart' => false,
			)
		);

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * Lookbook returns empty markup without a media URL.
	 *
	 * @return void
	 */
	public function test_lookbook_is_empty_without_media(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for lookbook render.' );
		}

		$html = $this->render( 'lookbook' );
		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * Lookbook renders image, hotspot controls, and popover shell.
	 *
	 * @return void
	 */
	public function test_lookbook_renders_hotspots_and_popover(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for lookbook render.' );
		}

		$html = $this->render(
			'lookbook',
			array(
				'mediaUrl' => 'https://example.com/look.jpg',
				'mediaAlt' => 'Studio look',
				'hotspots' => array(
					array(
						'x'           => 40,
						'y'           => 55,
						'productId'   => 12,
						'productName' => 'Bomber Jacket',
					),
				),
			)
		);

		$this->assertStringContainsString( 'aggressive-apparel-lookbook', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/lookbook"', $html );
		$this->assertStringContainsString( 'aggressive-apparel-lookbook__image', $html );
		$this->assertStringContainsString( 'https://example.com/look.jpg', $html );
		$this->assertStringContainsString( 'Studio look', $html );
		$this->assertStringContainsString( 'aggressive-apparel-lookbook__hotspot', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.toggleHotspot"', $html );
		// Keyboard reachability: deterministic keydown activation on the hotspot
		// and Tab handling on the popover so focus can enter/leave the card.
		$this->assertStringContainsString( 'data-wp-on--keydown="actions.onHotspotKeydown"', $html );
		$this->assertStringContainsString( 'data-wp-on--keydown="actions.onPopoverKeydown"', $html );
		$this->assertStringContainsString( 'View product: Bomber Jacket', $html );
		$this->assertStringContainsString( 'aggressive-apparel-lookbook__popover', $html );
		$this->assertStringContainsString( 'aggressive-apparel-lookbook__popover-content', $html );
		$this->assertStringNotContainsString( 'data-wp-html="state.popoverHtml"', $html );
	}

	/**
	 * Render a card-flip whose inner content carries a sentinel per face.
	 *
	 * @param string $flip_on Flip variant.
	 * @return string
	 */
	private function render_card_flip( string $flip_on ): string {
		$face = static function ( string $sentinel ): array {
			return array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerContent' => array( '<p>' . $sentinel . '</p>' ),
			);
		};

		// innerContent must carry a null placeholder per inner block so
		// WP_Block::render() stitches the rendered faces into $content.
		$parsed = array(
			'blockName'    => 'aggressive-apparel/card-flip',
			'attrs'        => array( 'flipOn' => $flip_on ),
			'innerBlocks'  => array( $face( 'FRONT_SENTINEL' ), $face( 'BACK_SENTINEL' ) ),
			'innerContent' => array( null, null ),
		);

		return (string) render_block( $parsed );
	}

	/**
	 * Card flip always renders the accessible disclosure shell: a flip button
	 * with toggle bindings and the reactive is-flipped / inert plumbing, and it
	 * passes its inner face content straight through.
	 *
	 * @return void
	 */
	public function test_card_flip_renders_disclosure_shell(): void {
		$html = $this->render_card_flip( 'click' );

		$this->assertStringContainsString( 'aa-card-flip aa-card-flip--click', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/card-flip"', $html );
		$this->assertStringContainsString( 'aa-card-flip__inner', $html );

		// Accessible flip control is present for every variant.
		$this->assertStringContainsString( 'aa-card-flip__toggle', $html );
		$this->assertStringContainsString( 'type="button"', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.toggle"', $html );
		$this->assertStringContainsString( 'data-wp-bind--aria-pressed="context.isFlipped"', $html );
		$this->assertStringContainsString( 'data-wp-class--is-flipped="context.isFlipped"', $html );
		$this->assertStringContainsString( 'data-wp-watch--faces="callbacks.syncFaces"', $html );

		// Inner face content passes through untouched.
		$this->assertStringContainsString( 'FRONT_SENTINEL', $html );
		$this->assertStringContainsString( 'BACK_SENTINEL', $html );

		// Removed legacy heuristics.
		$this->assertStringNotContainsString( 'has-multiple-faces', $html );
		$this->assertStringNotContainsString( 'role="group"', $html );
	}

	/**
	 * Only the hover variant wires the pointer-driven flip; click does not.
	 *
	 * @return void
	 */
	public function test_card_flip_hover_variant_adds_pointer_handlers(): void {
		$hover = $this->render_card_flip( 'hover' );
		$this->assertStringContainsString( 'aa-card-flip--hover', $hover );
		$this->assertStringContainsString( 'data-wp-on--mouseenter="actions.pointerEnter"', $hover );
		$this->assertStringContainsString( 'data-wp-on--mouseleave="actions.pointerLeave"', $hover );

		$click = $this->render_card_flip( 'click' );
		$this->assertStringNotContainsString( 'data-wp-on--mouseenter', $click );
		$this->assertStringNotContainsString( 'data-wp-on--mouseleave', $click );
	}

	/**
	 * Recently viewed renders Store API shell with heading and init callback.
	 *
	 * @return void
	 */
	public function test_recently_viewed_renders_shell(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for recently viewed render.' );
		}

		$html = $this->render(
			'recently-viewed',
			array(
				'maxDisplay' => 6,
				'heading'    => 'You looked at',
			)
		);

		$this->assertStringContainsString( 'aggressive-apparel-recently-viewed', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/recently-viewed"', $html );
		$this->assertStringContainsString( 'data-wp-init="callbacks.init"', $html );
		$this->assertStringContainsString( 'aggressive-apparel-recently-viewed__title', $html );
		$this->assertStringContainsString( 'You looked at', $html );
		$this->assertStringContainsString( 'aggressive-apparel-recently-viewed__grid', $html );
		$this->assertStringContainsString( 'data-wp-html="state.productsHtml"', $html );

		$context = html_entity_decode( $html, ENT_QUOTES );
		$this->assertStringContainsString( 'wc\/store\/v1\/products', $context );
		$this->assertStringContainsString( '"maxDisplay":6', $context );
	}

	/**
	 * Dark mode toggle renders accessible switch markup.
	 *
	 * @return void
	 */
	public function test_dark_mode_toggle_renders_button(): void {
		$html = $this->render(
			'dark-mode-toggle',
			array(
				'label'     => 'Theme',
				'labelDark' => 'Light Theme',
				'showLabel' => true,
			)
		);

		$this->assertStringContainsString( 'dark-mode-toggle__button', $html );
		$this->assertStringContainsString( 'aria-pressed="false"', $html );
		$this->assertStringContainsString( 'data-label-light=', $html );
		$this->assertStringContainsString( 'data-label-dark=', $html );
		$this->assertStringContainsString( 'data-text-label-light="Theme"', $html );
		$this->assertStringContainsString( 'data-text-label-dark="Light Theme"', $html );
		$this->assertStringContainsString( 'dark-mode-toggle__icon--sun', $html );
		$this->assertStringContainsString( 'dark-mode-toggle__icon--moon', $html );
		$this->assertStringContainsString( 'dark-mode-toggle__label', $html );
		$this->assertStringContainsString( 'Theme', $html );
	}

	/**
	 * Dark mode toggle renders custom icon, background, and outline styles.
	 *
	 * @return void
	 */
	public function test_dark_mode_toggle_renders_custom_visual_styles(): void {
		$html = $this->render(
			'dark-mode-toggle',
			array(
				'iconStyle'                  => 'outline',
				'iconStrokeWidth'            => 2.25,
				'iconColor'                  => '#111111',
				'iconHoverColor'             => '#222222',
				'toggleBackgroundColor'      => '#333333',
				'toggleBackgroundHoverColor' => '#444444',
			)
		);

		$this->assertStringContainsString( 'is-icon-style-outline', $html );
		$this->assertStringContainsString( 'fill="none"', $html );
		$this->assertStringContainsString( 'stroke="currentColor"', $html );
		// Thickness comes from the CSS custom property (stroke-width lives in
		// style.css — var() is invalid in SVG presentation attributes).
		$this->assertStringNotContainsString( 'stroke-width=', $html );
		$this->assertStringContainsString( '--aa-dark-mode-toggle-icon-stroke-width:2.25', $html );
		$this->assertStringContainsString( '--aa-dark-mode-toggle-icon-color:#111111', $html );
		$this->assertStringContainsString( '--aa-dark-mode-toggle-icon-hover-color:#222222', $html );
		$this->assertStringContainsString( '--aa-dark-mode-toggle-bg:#333333', $html );
		$this->assertStringContainsString( '--aa-dark-mode-toggle-bg-hover:#444444', $html );
	}

	/**
	 * Dark mode toggle maps stored preset references to preset variables, so
	 * adaptive light-dark() palette values survive safecss_filter_attr().
	 *
	 * @return void
	 */
	public function test_dark_mode_toggle_maps_preset_color_references(): void {
		$html = $this->render(
			'dark-mode-toggle',
			array(
				'iconColor'             => 'var:preset|color|accent',
				'toggleBackgroundColor' => 'var:preset|color|transparent',
			)
		);

		$this->assertStringContainsString( '--aa-dark-mode-toggle-icon-color:var(--wp--preset--color--accent)', $html );
		$this->assertStringContainsString( '--aa-dark-mode-toggle-bg:var(--wp--preset--color--transparent)', $html );
	}

	/**
	 * Product rating renders brand marks for a reviewed product.
	 *
	 * @return void
	 */
	public function test_product_rating_renders_for_reviewed_product(): void {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for product rating render.' );
		}

		update_option( 'woocommerce_enable_reviews', 'yes' );

		$product = new \WC_Product_Simple();
		$product->set_name( 'Rated Tee' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '29' );
		$product->set_reviews_allowed( true );
		$product->save();

		$product_id = $product->get_id();
		$this->assertGreaterThan( 0, $product_id );

		wp_insert_comment(
			array(
				'comment_post_ID'  => $product_id,
				'comment_author'   => 'Reviewer',
				'comment_content'  => 'Great fit.',
				'comment_approved' => 1,
				'comment_type'     => 'review',
				'comment_meta'     => array(
					'rating' => 4,
				),
			)
		);

		\WC_Comments::clear_transients( $product_id );
		clean_post_cache( $product_id );
		wc_delete_product_transients( $product_id );

		$html = $this->render(
			'product-rating',
			array(),
			array(),
			array( 'postId' => $product_id )
		);

		$this->assertStringContainsString( 'aa-product-rating', $html );
		$this->assertStringContainsString( 'role="img"', $html );
		$this->assertStringContainsString( 'woocommerce-review-link', $html );
		$this->assertStringContainsString( '#reviews', $html );
	}

	/**
	 * Product rating is empty without reviews.
	 *
	 * @return void
	 */
	public function test_product_rating_empty_without_reviews(): void {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for product rating render.' );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( 'Unrated Tee' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '19' );
		$product->set_reviews_allowed( true );
		$product->save();

		$html = $this->render(
			'product-rating',
			array(),
			array(),
			array( 'postId' => $product->get_id() )
		);

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * Ticker renders marquee shell with duplicated track content.
	 *
	 * @return void
	 */
	public function test_ticker_renders_marquee_shell(): void {
		$html = $this->render(
			'ticker',
			array(
				'speed'     => 40,
				'direction' => 'left',
				'showLabel' => true,
				'labelText' => 'LIVE',
			),
			array(
				array(
					'blockName'    => 'core/paragraph',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerContent' => array( '<p>Drop soon</p>' ),
				),
			)
		);

		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/ticker"', $html );
		$this->assertStringContainsString( 'role="marquee"', $html );
		$this->assertStringContainsString( 'aria-live="off"', $html );
		$this->assertStringContainsString( 'data-ticker-speed="40"', $html );
		$this->assertStringContainsString( 'data-ticker-direction="left"', $html );
		$this->assertStringContainsString( 'ticker__track', $html );
		$this->assertStringContainsString( 'ticker__content', $html );
		$this->assertStringContainsString( 'ticker__pause', $html );
		$this->assertStringContainsString( 'ticker__label', $html );
		$this->assertStringContainsString( 'LIVE', $html );
		$this->assertGreaterThanOrEqual( 2, substr_count( $html, 'ticker__content' ) );
	}

	/**
	 * Horizontal scroll renders carousel shell with progress and init.
	 *
	 * @return void
	 */
	public function test_horizontal_scroll_renders_carousel_shell(): void {
		$html = $this->render(
			'horizontal-scroll',
			array(
				'showProgress' => true,
				'activation'   => 'top',
			),
			array(
				array(
					'blockName'    => 'core/paragraph',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerContent' => array( '<p>Slide</p>' ),
				),
			)
		);

		$this->assertStringContainsString( 'aa-hscroll', $html );
		$this->assertStringContainsString( 'aa-hscroll--top', $html );
		$this->assertStringContainsString( 'role="region"', $html );
		$this->assertStringContainsString( 'aria-roledescription="carousel"', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/horizontal-scroll"', $html );
		$this->assertStringContainsString( 'aa-hscroll__control--prev', $html );
		$this->assertStringContainsString( 'aa-hscroll__control--next', $html );
		$this->assertStringContainsString( 'aria-label="Previous slide"', $html );
		$this->assertStringContainsString( 'aria-label="Next slide"', $html );
		$this->assertStringContainsString( 'data-wp-init="callbacks.init"', $html );
		$this->assertStringContainsString( 'aa-hscroll__range', $html );
		$this->assertStringContainsString( 'aa-hscroll__viewport', $html );
		$this->assertStringContainsString( 'data-aa-hscroll', $html );
		$this->assertStringContainsString( 'aa-hscroll__progress', $html );
		$this->assertStringContainsString( 'role="progressbar"', $html );
	}

	/**
	 * Horizontal scroll forwards editor blockGap onto --aa-hscroll-gap.
	 *
	 * @return void
	 */
	public function test_horizontal_scroll_forwards_block_gap(): void {
		$html = $this->render(
			'horizontal-scroll',
			array(
				'showProgress' => false,
				'style'        => array(
					'spacing' => array(
						'blockGap' => 'var:preset|spacing|12',
					),
				),
			)
		);

		$this->assertStringContainsString(
			'--aa-hscroll-gap: var(--wp--preset--spacing--12)',
			$html
		);
	}

	/**
	 * Horizontal scroll normalizes legacy proximity and forwards stepDuration.
	 *
	 * @return void
	 */
	public function test_horizontal_scroll_normalizes_proximity_and_step_duration(): void {
		$html = $this->render(
			'horizontal-scroll',
			array(
				'showProgress' => false,
				'snapBehavior' => 'proximity',
				'stepDuration' => 0.8,
			)
		);

		$this->assertStringContainsString( '&quot;snapBehavior&quot;:&quot;off&quot;', $html );
		$this->assertStringContainsString( '&quot;stepDuration&quot;:0.8', $html );
	}

	/**
	 * Grid/list toggle renders both view controls.
	 *
	 * @return void
	 */
	public function test_grid_list_toggle_renders_controls(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for grid-list-toggle render.' );
		}

		$html = $this->render(
			'grid-list-toggle',
			array(
				'showLabels' => true,
			)
		);

		$this->assertStringContainsString( 'aa-grid-list-toggle', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/grid-list-toggle"', $html );
		$this->assertStringContainsString( 'data-wp-init="callbacks.init"', $html );
		$this->assertStringContainsString( 'aa-grid-list-toggle__btn--grid', $html );
		$this->assertStringContainsString( 'aa-grid-list-toggle__btn--list', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.setGrid"', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.setList"', $html );
		$this->assertStringContainsString( 'Grid', $html );
		$this->assertStringContainsString( 'List', $html );
	}

	/**
	 * Countdown timer is empty when the product is not on sale.
	 *
	 * @return void
	 */
	public function test_countdown_timer_empty_when_not_on_sale(): void {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for countdown timer render.' );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( 'Full Price Tee' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '40' );
		$product->save();

		$this->go_to( get_permalink( $product->get_id() ) );

		$html = $this->render( 'countdown-timer' );
		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * Countdown timer renders segments for an active timed sale.
	 *
	 * @return void
	 */
	public function test_countdown_timer_renders_for_timed_sale(): void {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for countdown timer render.' );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( 'Flash Sale Tee' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '50' );
		$product->set_sale_price( '30' );
		$product->set_date_on_sale_to( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$product->save();

		$this->go_to( get_permalink( $product->get_id() ) );

		$html = $this->render( 'countdown-timer' );

		$this->assertStringContainsString( 'aggressive-apparel-countdown', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/countdown-timer"', $html );
		$this->assertStringContainsString( 'data-wp-init="callbacks.startTicker"', $html );
		$this->assertStringContainsString( 'aggressive-apparel-countdown__segment', $html );
		$this->assertStringContainsString( 'Sale ends in', $html );
		$this->assertGreaterThanOrEqual( 4, substr_count( $html, 'aggressive-apparel-countdown__segment' ) );

		// Accessible timer semantics: the wrapper carries a human-readable
		// label while the visual d/h/m/s segments are hidden from AT.
		$this->assertStringContainsString( 'role="timer"', $html );
		$this->assertStringContainsString( 'aria-label="Sale ends in ', $html );
		$this->assertStringContainsString( 'data-wp-bind--aria-label="context.ariaLabel"', $html );
		$this->assertSame( 5, substr_count( $html, 'aria-hidden="true"' ) );

		// Fallback contract for AJAX-injected copies: the dynamic scanner in
		// view.ts ticks non-hydrated markup via these plain attributes.
		$this->assertStringContainsString( 'data-aa-countdown="', $html );
		$this->assertSame( 4, substr_count( $html, 'data-aa-countdown-segment="' ) );
	}

	/**
	 * Countdown timer renders for a manually configured deadline.
	 *
	 * @return void
	 */
	public function test_countdown_timer_renders_for_manual_end_date(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for countdown timer registration.' );
		}

		$html = $this->render(
			'countdown-timer',
			array(
				'endDateTime' => gmdate( 'c', time() + DAY_IN_SECONDS ),
			)
		);

		$this->assertStringContainsString( 'aggressive-apparel-countdown', $html );
		$this->assertStringContainsString( 'aggressive-apparel-countdown--inline', $html );
		$this->assertStringContainsString( 'data-wp-context', $html );
		$this->assertStringContainsString( 'data-wp-init="callbacks.startTicker"', $html );
		$this->assertGreaterThanOrEqual( 4, substr_count( $html, 'aggressive-apparel-countdown__segment' ) );
	}

	/**
	 * Countdown timer applies the selected visual variation class.
	 *
	 * @return void
	 */
	public function test_countdown_timer_renders_selected_display_style(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for countdown timer registration.' );
		}

		$html = $this->render(
			'countdown-timer',
			array(
				'displayStyle' => 'hero',
				'endDateTime'  => gmdate( 'c', time() + DAY_IN_SECONDS ),
			)
		);

		$this->assertStringContainsString( 'aggressive-apparel-countdown--hero', $html );
	}

	/**
	 * Countdown timer renders granular color controls as CSS variables.
	 *
	 * @return void
	 */
	public function test_countdown_timer_renders_custom_color_variables(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for countdown timer registration.' );
		}

		$html = $this->render(
			'countdown-timer',
			array(
				'endDateTime'      => gmdate( 'c', time() + DAY_IN_SECONDS ),
				'saleLabelColor'   => '#111111',
				'timeValueColor'   => '#222222',
				'unitLabelColor'   => '#333333',
				'timerBorderColor' => '#444444',
			)
		);

		$this->assertStringContainsString( '--aa-countdown-label-color:#111111', $html );
		$this->assertStringContainsString( '--aa-countdown-value-color:#222222', $html );
		$this->assertStringContainsString( '--aa-countdown-unit-color:#333333', $html );
		$this->assertStringContainsString( '--aa-countdown-border-color:#444444', $html );
	}

	/**
	 * Nav panel header/footer wrappers render for parent extraction.
	 *
	 * @return void
	 */
	public function test_nav_panel_chrome_wrappers_render(): void {
		$header = (string) render_block(
			array(
				'blockName'    => 'aggressive-apparel/nav-panel-header',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Menu brand</p>',
						'innerContent' => array( '<p>Menu brand</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$footer_empty = $this->render( 'nav-panel-footer' );
		$footer       = (string) render_block(
			array(
				'blockName'    => 'aggressive-apparel/nav-panel-footer',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Account links</p>',
						'innerContent' => array( '<p>Account links</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$this->assertStringContainsString( 'wp-block-aggressive-apparel-nav-panel-header', $header );
		$this->assertStringContainsString( 'Menu brand', $header );
		$this->assertSame( '', trim( $footer_empty ) );
		$this->assertStringContainsString( 'wp-block-aggressive-apparel-nav-panel-footer', $footer );
		$this->assertStringContainsString( 'Account links', $footer );
	}
}
