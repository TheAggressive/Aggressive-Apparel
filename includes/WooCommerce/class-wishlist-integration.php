<?php
/**
 * Connect theme wishlist surfaces to WooCommerce Shopper Lists.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps WooCommerce data behind the theme's existing blocks. */
class Wishlist_Integration {
	/** Store-wide unsaved icon slug. */
	public const ICON_OPTION = 'aggressive_apparel_wishlist_icon';
	/** Store-wide saved icon slug. */
	public const SAVED_ICON_OPTION = 'aggressive_apparel_wishlist_saved_icon';

	/** Register integration hooks while the theme wishlist is enabled. */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_icon_settings' ), 20 );
		add_filter( 'pre_option_woocommerce_product_wishlist_enabled', array( $this, 'enable_backend' ) );
		add_filter( 'render_block_woocommerce/add-to-wishlist-button', array( $this, 'render_button' ), 10, 3 );
		add_filter( 'render_block_woocommerce/wishlist', array( $this, 'render_list' ), 10, 2 );
	}

	/** Register store defaults alongside the existing Wishlist settings. */
	public function register_icon_settings(): void {
		foreach ( array(
			self::ICON_OPTION       => 'heart-outline',
			self::SAVED_ICON_OPTION => 'heart',
		) as $option => $default ) {
			register_setting(
				Feature_Settings::SETTINGS_GROUP,
				$option,
				array(
					'type'              => 'string',
					'default'           => $default,
					'sanitize_callback' => static function ( $value ) use ( $default ): string {
						return is_string( $value ) && Icons::exists( $value ) ? $value : $default;
					},
				)
			);
			add_settings_field(
				$option,
				self::ICON_OPTION === $option ? __( 'Wishlist Unsaved Icon', 'aggressive-apparel' ) : __( 'Wishlist Saved Icon', 'aggressive-apparel' ),
				array( $this, 'render_icon_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_engagement',
				array(
					'label_for' => $option,
					'default'   => $default,
				),
			);
		}
	}

	/**
	 * Render a library-backed select for automatic card and native button icons.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_icon_field( array $args ): void {
		$option = $args['label_for'];
		$value  = (string) get_option( $option, $args['default'] );
		printf( '<select id="%1$s" name="%1$s">', esc_attr( $option ) );
		foreach ( Icons::list() as $slug ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $slug ), selected( $value, $slug, false ), esc_html( $slug ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Applies to product cards, Quick View and the WooCommerce product-page button. Individual Wishlist Button blocks can override these icons.', 'aggressive-apparel' ) . '</p>';
	}

	/** Detect registered routes, with an early-bootstrap check for the optional API. */
	public static function is_available(): bool {
		if ( did_action( 'rest_api_init' ) && ! doing_action( 'rest_api_init' ) ) {
			foreach ( rest_get_server()->get_routes() as $route => $endpoints ) {
				if ( 1 !== preg_match( '~^' . str_replace( '~', '\\~', $route ) . '$~', '/wc/store/v1/shopper-lists/wishlist/items' ) ) {
					continue;
				}
				foreach ( $endpoints as $endpoint ) {
					if ( ! empty( $endpoint['methods']['POST'] ) ) {
						return true;
					}
				}
			}
			return false;
		}
		// Before registration, the optional route class is the bootstrap signal.
		return class_exists( '\Automattic\WooCommerce\StoreApi\Routes\V1\ShopperListItems' );
	}

	/**
	 * Let the existing theme Wishlist toggle also enable its WooCommerce backend.
	 * No persistent WooCommerce setting is changed when switching themes.
	 *
	 * @param mixed $pre Existing short-circuit value.
	 * @return mixed
	 */
	public function enable_backend( $pre ) {
		return self::is_available() && Feature_Settings::is_enabled( 'wishlist' ) ? 'yes' : $pre;
	}

	/** Supply authentication to the browser without copying private WooCommerce stores. */
	public static function get_client_config(): array {
		$account = self::is_available() && Feature_Settings::is_enabled( 'wishlist' ) && is_user_logged_in();
		return array(
			'account'     => $account,
			'productsUrl' => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
			'itemsUrl'    => esc_url_raw( rest_url( 'wc/store/v1/shopper-lists/wishlist/items' ) ),
			'restNonce'   => $account ? wp_create_nonce( 'wp_rest' ) : '',
			'nonce'       => $account ? wp_create_nonce( 'wc_store_api' ) : '',
		);
	}

	/**
	 * Prefer the variation-aware button for the main product in block templates.
	 * Related-product cards keep their independently placed theme buttons.
	 *
	 * @param int $product_id Product to render.
	 * @return bool
	 */
	public static function uses_native_button( int $product_id ): bool {
		global $_wp_current_template_content;
		return self::is_available()
			&& Product_Context::is_single_product()
			&& get_queried_object_id() === $product_id
			&& is_string( $_wp_current_template_content )
			&& has_block( 'woocommerce/add-to-cart-with-options', $_wp_current_template_content );
	}

	/**
	 * Keep the native variation-aware button; replace only its visual icons.
	 * Guests receive the theme's local-save button in the same position.
	 *
	 * @param string    $html Rendered button.
	 * @param array     $parsed Parsed block.
	 * @param \WP_Block $block Block instance.
	 * @return string
	 */
	public function render_button( string $html, array $parsed, \WP_Block $block ): string {
		if ( ! Feature_Settings::is_enabled( 'wishlist' ) ) {
			return $html;
		}
		$product_id = (int) ( $block->context['postId'] ?? 0 );
		if ( $product_id <= 0 ) {
			return $html;
		}
		Wishlist::mark_button_block_rendered( $product_id );
		Wishlist::ensure_assets();
		if ( ! is_user_logged_in() ) {
			return Wishlist::get_heart_button_html( $product_id );
		}
		$icons = array(
			(string) get_option( self::ICON_OPTION, 'heart-outline' ),
			(string) get_option( self::SAVED_ICON_OPTION, 'heart' ),
		);
		// Target named icon slots, never unrelated SVGs (for example a spinner).
		$html      = (string) preg_replace_callback(
			'/<span\b[^>]*class="[^"]*\bwc-block-add-to-wishlist-button__icon--(empty|filled)\b[^"]*"[^>]*>\s*<svg\b[^>]*>.*?<\/svg>\s*<\/span>/s',
			static function ( array $matched ) use ( $icons ): string {
				$slug = $icons[ 'empty' === $matched[1] ? 0 : 1 ];
				$icon = Icons::get(
					$slug,
					array(
						'aria-hidden' => 'true',
						'width'       => 22,
						'height'      => 22,
					)
				);
				return '' !== $icon ? (string) preg_replace( '/<svg\b[^>]*>.*?<\/svg>/s', $icon, $matched[0], 1 ) : $matched[0];
			},
			$html
		);
		$processor = new \WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag(
			array(
				'tag_name'   => 'BUTTON',
				'class_name' => 'wc-block-add-to-wishlist-button__toggle',
			)
		) ) {
			$processor->set_attribute( 'data-aa-wishlist-native', '' );
		}
		return $processor->get_updated_html();
	}

	/**
	 * Use the same customizable list in My Account and merchant-placed Woo blocks.
	 *
	 * @param string $html Rendered list.
	 * @param array  $parsed Parsed block.
	 * @return string
	 */
	public function render_list( string $html, array $parsed ): string {
		if ( ! Feature_Settings::is_enabled( 'wishlist' ) ) {
			return $html;
		}
		$blocks                        = parse_blocks( Wishlist::get_default_page_content() );
		$blocks[0]['attrs']            = $parsed['attrs'] ?? array();
		$blocks[0]['attrs']['columns'] = min( 6, max( 1, (int) ( $parsed['attrs']['columnCount'] ?? 3 ) ) );
		unset( $blocks[0]['attrs']['columnCount'] );
		$heading = implode( '', array_map( 'render_block', $parsed['innerBlocks'] ?? array() ) );
		return $heading . render_block( $blocks[0] );
	}
}
