<?php
/**
 * Reviewer rating in reviews — Aggressive Apparel override.
 *
 * Overrides WooCommerce's default `single-product/review-rating.php` to render
 * the per-review rating with the brand mark (via the shared Rating renderer)
 * instead of WC's star glyphs, matching the product-summary rating block.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package Aggressive_Apparel
 * @version 3.6.0
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Rating;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $comment;

$aa_rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );

if ( $aa_rating && function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() ) {
	echo Rating::stars( (float) $aa_rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted renderer; scalars escaped inside.
}
