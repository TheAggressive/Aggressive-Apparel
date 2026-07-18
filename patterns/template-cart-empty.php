<?php
/**
 * Title: Empty Cart Message
 * Description: Empty-cart messaging and browse CTA for the cart template.
 * Slug: aggressive-apparel/template-cart-empty
 * Categories: aggressive, aggressive-apparel, aggressive-shop
 * Keywords: cart, empty, browse
 * Inserter: no
 *
 * @package Aggressive_Apparel
 */

?>
<!-- wp:heading {"textAlign":"center","className":"with-empty-cart-icon wc-block-cart__empty-cart__title"} -->
<h2 class="wp-block-heading has-text-align-center with-empty-cart-icon wc-block-cart__empty-cart__title"><?php echo esc_html__( 'Your cart is currently empty!', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Browse store', 'aggressive-apparel' ); ?></a></p>
<!-- /wp:paragraph -->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'New in store', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->
