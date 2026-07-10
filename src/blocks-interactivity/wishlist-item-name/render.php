<?php
/**
 * Wishlist Item Name — Server Render
 *
 * Link text is filled client-side via data-wp-text once the wishlist
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
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'aa-wl-item-name' ) ); ?>>
	<a
		class="aa-wl-item-name__link"
		data-wp-bind--href="context.item.permalink"
		data-wp-text="context.item.name"
	></a>
</div>
