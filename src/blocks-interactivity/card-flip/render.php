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

defined( 'ABSPATH' ) || exit;

$flip_on      = ! empty( $attributes['flipOn'] ) ? $attributes['flipOn'] : 'hover';
$aspect_ratio = ! empty( $attributes['aspectRatio'] ) ? $attributes['aspectRatio'] : '3/4';

$is_click = 'click' === $flip_on;

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'aa-card-flip aa-card-flip--' . sanitize_html_class( $flip_on ),
		'style'               => sprintf( 'aspect-ratio: %s;', esc_attr( $aspect_ratio ) ),
		'data-wp-interactive' => 'aggressive-apparel/card-flip',
		'data-wp-context'     => wp_json_encode(
			array(
				'isFlipped' => false,
				'flipOn'    => $flip_on,
			)
		),
	) + ( $is_click ? array(
		'role'                       => 'button',
		'tabindex'                   => '0',
		'aria-pressed'               => 'false',
		'data-wp-on--click'          => 'actions.toggle',
		'data-wp-on--keydown'        => 'actions.onKeydown',
		'data-wp-bind--aria-pressed' => 'context.isFlipped',
		'data-wp-class--is-flipped'  => 'context.isFlipped',
	) : array() )
);
?>
<div <?php echo wp_kses_post( $wrapper_attrs ); ?>>
	<div class="aa-card-flip__inner">
		<?php echo wp_kses_post( $content ); ?>
	</div>
</div>
