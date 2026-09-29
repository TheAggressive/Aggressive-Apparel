<?php
/**
 * Back in Stock Confirmation Email Template (HTML)
 *
 * @package Aggressive_Apparel
 *
 * @var \WC_Product $product       Product object.
 * @var string      $email_heading Email heading.
 * @var string      $confirm_url   Signup confirmation URL.
 * @var \WC_Email   $email         Email object.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	printf(
		/* translators: %s: product name. */
		esc_html__( 'You asked us to email you when %s is back in stock. Please confirm this was you:', 'aggressive-apparel' ),
		'<strong>' . esc_html( $product->get_name() ) . '</strong>'
	);
	?>
</p>

<p style="margin:24px 0;">
	<a
		href="<?php echo esc_url( $confirm_url ); ?>"
		style="display:inline-block; padding:10px 24px; background:#000; color:#fff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:700; text-transform:uppercase;"
	>
		<?php esc_html_e( 'Confirm my alert', 'aggressive-apparel' ); ?>
	</a>
</p>

<p style="font-size:12px; color:#999; margin-top:24px;">
	<?php esc_html_e( 'If you did not sign up, ignore this email and you will not hear from us about this product.', 'aggressive-apparel' ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
