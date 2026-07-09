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

?>
<div
	<?php
	echo get_block_wrapper_attributes(
		array( 'class' => 'aa-wl-item-price' )
	);
	?>
>
	<span class="aa-wl-item-price__text" data-wp-text="context.item.price"></span>
</div>
