<?php
/**
 * Wishlist Item Price — Server Render
 *
 * @var array $attributes
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_attrs = get_block_wrapper_attributes(
	array( 'class' => 'aa-wl-item-price' )
);
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<span class="aa-wl-item-price__text" data-wp-text="context.item.price"></span>
</div>
