<?php
/**
 * Wishlist Item Name — Server Render
 *
 * @var array $attributes
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_attrs = get_block_wrapper_attributes(
	array( 'class' => 'aa-wl-item-name' )
);
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<a
		class="aa-wl-item-name__link"
		data-wp-bind--href="context.item.permalink"
		data-wp-text="context.item.name"
	></a>
</div>
