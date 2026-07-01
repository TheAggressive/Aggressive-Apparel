<?php
/**
 * Wishlist Block — Server Render
 *
 * Outputs the grid shell and the data-wp-each template populated by
 * the WooCommerce Store API client-side.  The inner blocks (wishlist-item-image,
 * wishlist-item-name, etc.) are rendered into $content and placed inside the
 * <template> tag so the Interactivity API clones them per product.
 *
 * @var array    $attributes
 * @var string   $content    Inner blocks HTML (the card template).
 * @var WP_Block $block
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Wishlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	! class_exists( Feature_Settings::class ) ||
	! class_exists( Wishlist::class ) ||
	! Feature_Settings::is_enabled( 'wishlist' )
) {
	return;
}

Wishlist::ensure_assets();

// ── Attributes ────────────────────────────────────────────────────────────────

$columns        = max( 1, (int) ( $attributes['columns'] ?? 3 ) );
$mobile_columns = max( 1, (int) ( $attributes['mobileColumns'] ?? 1 ) );
$gap            = sanitize_text_field( $attributes['gap'] ?? '' );
$show_count     = ! empty( $attributes['showCount'] );
$empty_message  = sanitize_text_field( $attributes['emptyMessage'] ?? __( 'Your wishlist is empty.', 'aggressive-apparel' ) );

// ── CSS vars (grid layout only — card design lives on inner blocks) ───────────

$css_vars = array(
	'--aa-wl-columns'        => (string) $columns,
	'--aa-wl-columns-mobile' => (string) $mobile_columns,
);
if ( $gap ) {
	$css_vars['--aa-wl-gap'] = $gap;
}

$inline_style = implode(
	'; ',
	array_map(
		fn( $k, $v ) => esc_attr( $k ) . ': ' . esc_attr( $v ),
		array_keys( $css_vars ),
		array_values( $css_vars )
	)
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'data-wp-interactive' => 'aggressive-apparel/wishlist',
		'data-wp-context'     => wp_json_encode( array( 'loaded' => false ) ),
		'data-wp-init'        => 'callbacks.loadWishlistPage',
		'style'               => $inline_style,
	)
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( $show_count ) : ?>
	<p class="aa-wishlist-page__count" data-wp-bind--hidden="!state.hasWishlistItems" hidden>
		<span data-wp-text="state.items.length"></span>
		<?php echo esc_html__( 'items saved', 'aggressive-apparel' ); ?>
	</p>
	<?php endif; ?>

	<?php /* Shown while fetch is in-flight */ ?>
	<p class="aa-wishlist-page__loading" data-wp-bind--hidden="context.loaded">
		<?php esc_html_e( 'Loading wishlist…', 'aggressive-apparel' ); ?>
	</p>

	<?php /* Shown when loaded and nothing in the list */ ?>
	<p class="aa-wishlist-page__empty" data-wp-bind--hidden="state.hasWishlistItems" hidden>
		<?php echo esc_html( $empty_message ); ?>
	</p>

	<?php /* Grid — hidden until loaded, then data-wp-each populates it */ ?>
	<div class="aa-wishlist-page__grid" data-wp-bind--hidden="!context.loaded" hidden>
		<template data-wp-each="state.wishlistProducts">
			<div class="aa-wishlist-page__card">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</template>
	</div>

</div>
