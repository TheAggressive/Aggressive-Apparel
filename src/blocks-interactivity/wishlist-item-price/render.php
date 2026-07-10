<?php
/**
 * Wishlist Item Price — Server Render
 *
 * Price text is filled client-side via data-wp-text once the wishlist
 * Store API payload hydrates each card.
 *
 * @var array $attributes
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'aa-wl-item-price' ) ); ?>>
	<span
		class="aa-wl-item-price__text"
		data-wp-text="context.item.price"
		data-wp-bind--hidden="!context.item.price"
		hidden
	></span>
</div>
