<?php
/**
 * Card Flip Block — Server Render.
 *
 * Wraps the InnerBlocks content in a perspective container.
 * The IA store handles click-to-flip; hover variant is pure CSS.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    InnerBlocks HTML (front + back Group blocks).
 * @var WP_Block $block      Block instance.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed_flip_on = array( 'hover', 'click' );
$flip_on         = isset( $attributes['flipOn'] ) ? (string) $attributes['flipOn'] : 'hover';
$flip_on         = in_array( $flip_on, $allowed_flip_on, true ) ? $flip_on : 'hover';

$allowed_ratios = array( '3/4', '1/1', '4/3', '16/9' );
$aspect_ratio   = isset( $attributes['aspectRatio'] ) ? (string) $attributes['aspectRatio'] : '3/4';
$aspect_ratio   = in_array( $aspect_ratio, $allowed_ratios, true ) ? $aspect_ratio : '3/4';

$is_click = 'click' === $flip_on;

$wrapper_extra = array(
	'class'               => 'aa-card-flip aa-card-flip--' . sanitize_html_class( $flip_on ),
	'style'               => 'aspect-ratio: ' . $aspect_ratio . ';',
	'data-wp-interactive' => 'aggressive-apparel/card-flip',
	'data-wp-context'     => wp_json_encode(
		array(
			'isFlipped' => false,
			'flipOn'    => $flip_on,
		)
	),
);

if ( $is_click ) {
	$wrapper_extra = array_merge(
		$wrapper_extra,
		array(
			'role'                       => 'button',
			'tabindex'                   => '0',
			'aria-pressed'               => 'false',
			'data-wp-on--click'          => 'actions.toggle',
			'data-wp-on--keydown'        => 'actions.onKeydown',
			'data-wp-bind--aria-pressed' => 'context.isFlipped',
			'data-wp-class--is-flipped'  => 'context.isFlipped',
		)
	);
}
?>
<div <?php echo get_block_wrapper_attributes( $wrapper_extra ); ?>>
	<div class="aa-card-flip__inner">
		<?php echo aggressive_apparel_trusted_html( $content ); ?>
	</div>
</div>
