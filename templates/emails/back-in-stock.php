<?php
/**
 * Back in Stock Email Template (HTML)
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 *
 * @var \WC_Product $product           Product object.
 * @var string      $email_heading     Email heading.
 * @var string      $unsubscribe_token Unsubscribe token.
 * @var \WC_Email   $email             Email object.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$product_name  = $product->get_name();
$product_url   = $product->get_permalink();
$product_price = wp_strip_all_tags( $product->get_price_html() );
$image_id      = $product->get_image_id();
$image_url     = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
$unsubscribe   = add_query_arg( 'aa_unsubscribe', $unsubscribe_token, home_url() );
?>

<p><?php esc_html_e( 'Great news! A product you were waiting for is back in stock:', 'aggressive-apparel' ); ?></p>

<table cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:16px 0; border:1px solid #e5e5e5; border-radius:4px;">
	<tr>
		<?php if ( $image_url ) : ?>
		<td style="padding:16px; width:120px; vertical-align:top;">
			<a href="<?php echo esc_url( $product_url ); ?>">
				<img
					src="<?php echo esc_url( $image_url ); ?>"
					alt="<?php echo esc_attr( $product_name ); ?>"
					width="100"
					height="100"
					style="display:block; border-radius:4px; object-fit:cover;"
				/>
			</a>
		</td>
		<?php endif; ?>
		<td style="padding:16px; vertical-align:top;">
			<h3 style="margin:0 0 8px; font-size:16px;">
				<a href="<?php echo esc_url( $product_url ); ?>" style="color:inherit; text-decoration:none;">
					<?php echo esc_html( $product_name ); ?>
				</a>
			</h3>
			<p style="margin:0 0 16px; font-size:14px; color:#666;">
				<?php echo esc_html( $product_price ); ?>
			</p>
			<a
				href="<?php echo esc_url( $product_url ); ?>"
				style="display:inline-block; padding:10px 24px; background:#000; color:#fff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:700; text-transform:uppercase;"
			>
				<?php esc_html_e( 'Shop Now', 'aggressive-apparel' ); ?>
			</a>
		</td>
	</tr>
</table>

<p style="font-size:12px; color:#999; margin-top:24px;">
	<?php
	printf(
		/* translators: %s: unsubscribe link. */
		esc_html__( 'You received this email because you asked to be notified. %s', 'aggressive-apparel' ),
		'<a href="' . esc_url( $unsubscribe ) . '" style="color:#999;">' . esc_html__( 'Unsubscribe', 'aggressive-apparel' ) . '</a>'
	);
	?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
