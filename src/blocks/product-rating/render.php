<?php
/**
 * Product Rating Block Render
 *
 * Clones WooCommerce's `woocommerce/product-rating` block contract (gated on
 * review count + reviews enabled, product resolved from the `postId` context,
 * accessible `role="img"` rating + a reviews-count link) but renders the marks
 * with the brand icon via the shared Rating renderer.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Rating;

if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_reviews_enabled' ) ) {
	return;
}

$aa_post_id = $block->context['postId'] ?? get_the_ID();
$aa_product = $aa_post_id ? wc_get_product( $aa_post_id ) : null;

if (
	! $aa_product
	|| ! $aa_product->get_reviews_allowed()
	|| ! wc_reviews_enabled()
	|| $aa_product->get_review_count() < 1
) {
	return;
}

$aa_rating = (float) $aa_product->get_average_rating();

if ( $aa_rating <= 0 ) {
	return;
}

$aa_count     = (int) $aa_product->get_review_count();
$aa_align     = sanitize_text_field( $attributes['textAlign'] ?? '' );
$aa_on_single = (bool) ( $attributes['isDescendentOfSingleProductTemplate'] ?? false );

$aa_count_text = sprintf(
	/* translators: %s: number of customer reviews. */
	_n( '(%s customer review)', '(%s customer reviews)', $aa_count, 'aggressive-apparel' ),
	esc_html( number_format_i18n( $aa_count ) )
);

// Always link the count to the reviews: a bare "#reviews" anchor on the single
// product page, or the product permalink + "#reviews" anywhere else (e.g. a
// catalog card) so the link still resolves off the product page.
$aa_permalink = get_permalink( $aa_post_id );
$aa_href      = ( $aa_on_single || ! $aa_permalink ) ? '#reviews' : $aa_permalink . '#reviews';

$aa_count_html = sprintf(
	'<a class="woocommerce-review-link" rel="nofollow" href="%1$s">%2$s</a>',
	esc_url( $aa_href ),
	$aa_count_text
);

printf(
	'<div %1$s><div class="aa-product-rating__container">%2$s<span class="aa-product-rating__count">%3$s</span></div></div>',
	get_block_wrapper_attributes(
		array(
			'class' => 'aa-product-rating' . ( '' !== $aa_align ? ' has-text-align-' . $aa_align : '' ),
		)
	),
	Rating::stars( $aa_rating ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup; scalars escaped inside the renderer.
	wp_kses_post( $aa_count_html )
);
