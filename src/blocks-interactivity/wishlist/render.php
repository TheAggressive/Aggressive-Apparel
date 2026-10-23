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

$columns        = min( 6, max( 1, (int) ( $attributes['columns'] ?? 4 ) ) );
$mobile_columns = min( 3, max( 1, (int) ( $attributes['mobileColumns'] ?? 2 ) ) );
$gap            = sanitize_text_field( $attributes['gap'] ?? '' );
$show_count     = ! isset( $attributes['showCount'] ) || (bool) $attributes['showCount'];
$show_header    = ! isset( $attributes['showHeader'] ) || (bool) $attributes['showHeader'];
$shop_url       = wc_get_page_permalink( 'shop' );
$empty_message  = sanitize_text_field( $attributes['emptyMessage'] ?? __( 'Your wishlist is empty.', 'aggressive-apparel' ) );

// ── CSS vars (grid layout only — card design lives on inner blocks) ───────────

$css_vars = array(
	'--aa-wl-columns'        => (string) $columns,
	'--aa-wl-columns-mobile' => (string) $mobile_columns,
	'--aa-wl-columns-tablet' => (string) min( 2, $columns ),
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

?>

<div
	<?php
	echo get_block_wrapper_attributes(
		array(
			'data-wp-interactive' => 'aggressive-apparel/wishlist',
			'data-wp-context'     => wp_json_encode( array( 'loaded' => false ) ),
			'data-wp-init'        => 'callbacks.loadWishlistPage',
			'style'               => $inline_style,
		)
	);
	?>
>

	<?php if ( $show_header || $show_count ) : ?>
	<header class="aa-wishlist-page__header">
		<div class="aa-wishlist-page__intro">
			<?php if ( $show_header ) : ?>
			<p class="aa-wishlist-page__eyebrow"><?php esc_html_e( 'Saved for later', 'aggressive-apparel' ); ?></p>
			<h2 class="aa-wishlist-page__title"><?php esc_html_e( 'Your wishlist', 'aggressive-apparel' ); ?></h2>
			<p class="aa-wishlist-page__description"><?php esc_html_e( 'Your favorite pieces, all in one place.', 'aggressive-apparel' ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $show_count ) : ?>
		<p class="aa-wishlist-page__count" data-wp-bind--hidden="!state.hasWishlistItems" hidden>
			<?php
			echo aggressive_apparel_get_icon(
				'heart-outline',
				array(
					'width'       => 16,
					'height'      => 16,
					'aria-hidden' => 'true',
				)
			);
			?>
			<span data-wp-text="state.wishlistProducts.length"></span>
			<?php esc_html_e( 'saved', 'aggressive-apparel' ); ?>
		</p>
		<?php endif; ?>
	</header>
	<?php endif; ?>

	<?php /* Shown while fetch is in-flight */ ?>
	<p class="aa-wishlist-page__loading" data-wp-bind--hidden="context.loaded">
		<?php esc_html_e( 'Loading wishlist…', 'aggressive-apparel' ); ?>
	</p>

	<p class="aa-wishlist-page__loading" data-wp-bind--hidden="!state.loadFailed" hidden>
		<?php esc_html_e( 'Some wishlist items could not be loaded.', 'aggressive-apparel' ); ?>
		<button type="button" data-wp-on--click="actions.retryLoad"><?php esc_html_e( 'Try again', 'aggressive-apparel' ); ?></button>
	</p>

	<?php /* Shown when loaded and nothing in the list */ ?>
	<div class="aa-wishlist-page__empty" data-wp-bind--hidden="!state.isEmpty" hidden>
		<span class="aa-wishlist-page__empty-icon">
		<?php
		echo aggressive_apparel_get_icon(
			'heart-outline',
			array(
				'width'       => 32,
				'height'      => 32,
				'aria-hidden' => 'true',
			)
		);
		?>
		</span>
		<h3><?php echo esc_html( $empty_message ); ?></h3>
		<p><?php esc_html_e( 'Tap the heart on any product to keep it here.', 'aggressive-apparel' ); ?></p>
		<a class="aggressive-apparel-button aggressive-apparel-button--primary" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Explore the collection', 'aggressive-apparel' ); ?></a>
	</div>

	<?php /* Grid — show each healthy source as its products arrive */ ?>
	<div class="aa-wishlist-page__grid" data-wp-bind--hidden="!state.hasWishlistItems" hidden>
		<template data-wp-each="state.wishlistProducts">
			<div class="aa-wishlist-page__card">
				<?php echo aggressive_apparel_trusted_html( $content ); ?>
			</div>
		</template>
	</div>

	<a class="aa-wishlist-page__continue" data-wp-bind--hidden="state.isEmpty" href="<?php echo esc_url( $shop_url ); ?>">
		<?php esc_html_e( 'Continue shopping', 'aggressive-apparel' ); ?>
		<?php
		echo aggressive_apparel_get_icon(
			'arrow-right',
			array(
				'width'       => 18,
				'height'      => 18,
				'aria-hidden' => 'true',
			)
		);
		?>
	</a>
</div>
