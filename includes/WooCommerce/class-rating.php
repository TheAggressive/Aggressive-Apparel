<?php
/**
 * Rating renderer.
 *
 * Renders a product/review rating using the brand mark instead of stars. The
 * markup mirrors WooCommerce's accessibility contract — a `role="img"` element
 * with an `aria-label="Rated N out of 5"`.
 *
 * The marks are intentionally *empty* spans drawn with a CSS `mask` (see the
 * product-rating block stylesheet), not inline SVG: the per-review rating is
 * output inside WooCommerce's reviews tab, which the theme runs through
 * `wp_kses` — that strips `<svg>` (and lowercases `viewBox`). Empty spans + a
 * CSS-driven mark survive sanitisation intact.
 *
 * A muted "track" of five marks sits under a adaptive accent "fill" clipped to the
 * rating percentage (the same proportional model WooCommerce uses), so partial
 * ratings render correctly.
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
 * Rating renderer.
 *
 * @since 1.17.0
 */
class Rating {

	/**
	 * Number of marks in a full rating.
	 */
	private const MARKS = 5;

	/**
	 * Register WooCommerce review-rating hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'woocommerce_init', array( self::class, 'register_review_hooks' ) );
	}

	/**
	 * Replace WooCommerce star glyphs with brand-mark ratings in review comments.
	 *
	 * @return void
	 */
	public static function register_review_hooks(): void {
		if ( ! function_exists( 'woocommerce_review_display_rating' ) ) {
			return;
		}

		remove_action( 'woocommerce_review_before_comment_meta', 'woocommerce_review_display_rating', 10 );
		add_action( 'woocommerce_review_before_comment_meta', array( self::class, 'display_for_comment' ), 10 );
	}

	/**
	 * Echo the per-review rating for a product comment.
	 *
	 * Hooked to `woocommerce_review_before_comment_meta` in place of
	 * WooCommerce's default star template.
	 *
	 * @param \WP_Comment $comment Review comment.
	 * @return void
	 */
	public static function display_for_comment( $comment ): void {
		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}

		$rating = (int) get_comment_meta( absint( $comment->comment_ID ), 'rating', true );

		if ( ! $rating || ! function_exists( 'wc_review_ratings_enabled' ) || ! wc_review_ratings_enabled() ) {
			return;
		}

		echo self::stars( (float) $rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted renderer; scalars escaped inside.
	}

	/**
	 * Render the rating marks.
	 *
	 * @param float $rating Rating value (0–5; clamped).
	 * @return string Accessible rating markup.
	 */
	public static function stars( float $rating ): string {
		$rating = max( 0.0, min( (float) self::MARKS, $rating ) );
		$width  = ( $rating / self::MARKS ) * 100;

		// Empty marks — the brand mark is drawn via CSS `mask` so the markup
		// survives the reviews-tab `wp_kses` pass (which strips inline SVG).
		$marks = str_repeat( '<span class="aa-rating__mark"></span>', self::MARKS );

		/* translators: %s: numeric rating value out of 5. */
		$label = sprintf( __( 'Rated %s out of 5', 'aggressive-apparel' ), self::format( $rating ) );

		return sprintf(
			'<span class="aa-rating" role="img" aria-label="%1$s">' .
				'<span class="aa-rating__track" aria-hidden="true">%2$s</span>' .
				'<span class="aa-rating__fill" style="width:%3$s%%" aria-hidden="true">%2$s</span>' .
			'</span>',
			esc_attr( $label ),
			$marks,
			esc_attr( (string) round( $width, 2 ) )
		);
	}

	/**
	 * Format a rating for display: drop the decimal on whole numbers.
	 *
	 * @param float $rating Rating value.
	 * @return string
	 */
	private static function format( float $rating ): string {
		$decimals = ( floor( $rating ) === $rating ) ? 0 : 1;

		return number_format_i18n( $rating, $decimals );
	}
}
