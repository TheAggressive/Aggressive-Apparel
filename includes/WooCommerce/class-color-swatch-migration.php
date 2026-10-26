<?php
/**
 * Color Swatch Migration
 *
 * Moves colour swatches onto WooCommerce's native visual attributes. The theme
 * used to keep each colour in its own term meta and inject swatches into the
 * variation selector; WooCommerce now draws them itself for a "Color / image"
 * attribute (`wc-visual`), from `color` (hex) / `image` (attachment) term meta.
 *
 * Run from an admin page a store manager opens, it:
 *
 * 1. copies each term's colour (`color_value`, an `oklch()` value converted to
 *    hex) or pattern (`color_pattern_id`) into WooCommerce's meta, leaving any
 *    term that already has a WooCommerce swatch alone — on any WooCommerce;
 * 2. where WooCommerce supports visual attributes, turns on its "Color swatches
 *    for attributes" feature (the "Color / image" type requires it) and sets
 *    `pa_color` to that type.
 *
 * It is recorded as done only once WooCommerce reports the type really
 * changed; until then it retries hourly and the admin notice says why. A term
 * with no colour stores nothing: Color_Data_Manager serves the colour guessed
 * from its name, exactly as before. The old meta (`color_value`,
 * `color_format`, `color_type`, `color_pattern_id`) stays as a rollback copy,
 * and the storefront still falls back to it, so nothing depends on this having
 * run.
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
 * Theme colour meta → WooCommerce visual attribute.
 */
final class Color_Swatch_Migration {

	/**
	 * Option recording the finished migration version.
	 *
	 * @var string
	 */
	public const OPTION = 'aggressive_apparel_color_swatches_native';

	/**
	 * Current migration version.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * WooCommerce's option for the "Color swatches for attributes" feature.
	 *
	 * @var string
	 */
	public const FEATURE_OPTION = 'woocommerce_feature_wc_visual_attribute_enabled';

	/**
	 * WooCommerce's "Color / image" attribute type.
	 *
	 * @var string
	 */
	public const VISUAL_TYPE = 'wc-visual';

	/**
	 * Transient that spaces out retries of an unfinished migration.
	 *
	 * @var string
	 */
	public const RETRY = 'aggressive_apparel_color_swatches_retry';

	/**
	 * Colour attribute taxonomy.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'pa_color';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		// After Color_Attribute_Manager's setup (priority 5) creates pa_color.
		add_action( 'admin_init', array( $this, 'maybe_run' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Run from an admin page a store manager opens, until it finishes.
	 */
	public function maybe_run(): void {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION || ! current_user_can( 'manage_woocommerce' ) || wp_doing_ajax() || false !== get_transient( self::RETRY ) ) {
			return;
		}

		if ( ! self::run() ) {
			set_transient( self::RETRY, 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Move pa_color's swatches and switch it to WooCommerce's visual attribute.
	 *
	 * @return bool Whether WooCommerce now draws the swatches.
	 */
	public static function run(): bool {
		$attribute_id = wc_attribute_taxonomy_id_by_name( self::TAXONOMY );
		if ( ! $attribute_id || ! taxonomy_exists( self::TAXONOMY ) ) {
			return false;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return false;
		}
		foreach ( $terms as $term ) {
			self::move_term( $term->term_id );
		}
		Color_Data_Manager::flush_swatch_data_memo();

		if ( ! self::is_supported() ) {
			return false;
		}

		update_option( self::FEATURE_OPTION, 'yes' );

		$attribute = wc_get_attribute( $attribute_id );
		if ( $attribute && self::VISUAL_TYPE !== $attribute->type ) {
			// wc_update_attribute() resets any field it isn't given, and
			// silently keeps "select" when "Color / image" isn't available,
			// so success is judged by re-reading the type below.
			wc_update_attribute(
				$attribute_id,
				array(
					'name'         => $attribute->name,
					'slug'         => wc_attribute_taxonomy_slug( $attribute->slug ),
					'type'         => self::VISUAL_TYPE,
					'order_by'     => $attribute->order_by,
					'has_archives' => $attribute->has_archives,
				)
			);
		}

		if ( ! self::is_active() ) {
			return false;
		}

		update_option( self::OPTION, self::VERSION, false );

		return true;
	}

	/**
	 * Whether WooCommerce draws visual attributes.
	 */
	public static function is_supported(): bool {
		return class_exists( '\Automattic\WooCommerce\Internal\ProductAttributes\VisualAttributeTermMeta' );
	}

	/**
	 * Whether product pages currently get WooCommerce's swatches.
	 */
	public static function is_active(): bool {
		$attribute_id = wc_attribute_taxonomy_id_by_name( self::TAXONOMY );
		$attribute    = $attribute_id ? wc_get_attribute( $attribute_id ) : null;

		return 'yes' === get_option( self::FEATURE_OPTION ) && $attribute && self::VISUAL_TYPE === $attribute->type;
	}

	/**
	 * Tell a store manager when product pages show colours as text, and why.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || ! current_user_can( 'manage_woocommerce' ) || ! wc_attribute_taxonomy_id_by_name( self::TAXONOMY ) || self::is_active() ) {
			return;
		}
		if ( ! in_array( $screen->id, array( 'dashboard', 'plugins', 'woocommerce_page_wc-settings', 'product_page_product_attributes', 'edit-' . self::TAXONOMY ), true ) ) {
			return;
		}

		$reason = self::is_supported()
			? __( 'WooCommerce draws them only while its "Color swatches for attributes" feature is on (Settings → Advanced → Features) and the Color attribute\'s type is "Color / image", which needs a block theme.', 'aggressive-apparel' )
			: __( 'This WooCommerce version has no "Color swatches for attributes" feature; update WooCommerce to show them.', 'aggressive-apparel' );

		printf(
			'<div class="notice notice-warning"><p><strong>Aggressive Apparel:</strong> %1$s %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Product pages show colours as text, not swatches.', 'aggressive-apparel' ),
			esc_html( $reason ),
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' ) ),
			esc_html__( 'Open settings', 'aggressive-apparel' )
		);
	}

	/**
	 * Copy one term's stored theme swatch into WooCommerce's meta.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	private static function move_term( int $term_id ): void {
		if ( Color_Data_Manager::has_swatch_meta( $term_id ) ) {
			return;
		}

		if ( 'pattern' === get_term_meta( $term_id, 'color_type', true ) ) {
			$image_id = absint( get_term_meta( $term_id, 'color_pattern_id', true ) );
			if ( $image_id && wp_attachment_is_image( $image_id ) ) {
				update_term_meta( $term_id, Color_Data_Manager::IMAGE_META, $image_id );
				return;
			}
			// The pattern image is gone: fall back to the term's colour, if any.
		}

		$hex = Color_Data_Manager::to_hex( (string) get_term_meta( $term_id, 'color_value', true ) );
		if ( '' !== $hex ) {
			update_term_meta( $term_id, Color_Data_Manager::COLOR_META, $hex );
		}
	}
}
