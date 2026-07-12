<?php
/**
 * Card Flip Block — Server Render.
 *
 * Structure is guaranteed by the editor template (exactly one front and one
 * back face block), so there is no face-counting here — we just wrap the
 * InnerBlocks content and emit the flip control + Interactivity directives.
 *
 * The flip is a button-driven disclosure: the button toggles `context.isFlipped`,
 * CSS performs the 3D flip, and view.ts marks the away-facing side `inert` so it
 * leaves the tab order / accessibility tree. The hover variant adds a pure-CSS
 * flip on top; the button keeps it reachable by keyboard, touch and reduced-motion.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    InnerBlocks HTML (front + back face blocks).
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

$wrapper_extra = array(
	'class'                     => 'aa-card-flip aa-card-flip--' . sanitize_html_class( $flip_on ),
	'data-wp-interactive'       => 'aggressive-apparel/card-flip',
	'data-wp-context'           => (string) wp_json_encode(
		array(
			'isFlipped' => false,
			'flipOn'    => $flip_on,
		)
	),
	'data-wp-class--is-flipped' => 'context.isFlipped',
	'data-wp-watch--faces'      => 'callbacks.syncFaces',
);

if ( 'hover' === $flip_on ) {
	$wrapper_extra['data-wp-on--mouseenter'] = 'actions.pointerEnter';
	$wrapper_extra['data-wp-on--mouseleave'] = 'actions.pointerLeave';
}

$flip_icon = aggressive_apparel_get_icon(
	'returns-arrows',
	array(
		'width'       => 18,
		'height'      => 18,
		'aria-hidden' => 'true',
		'focusable'   => 'false',
	)
);
?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes( $wrapper_extra ) ); ?>>
	<button
		type="button"
		class="aa-card-flip__toggle"
		aria-pressed="false"
		aria-label="<?php esc_attr_e( 'Flip card', 'aggressive-apparel' ); ?>"
		data-wp-on--click="actions.toggle"
		data-wp-bind--aria-pressed="context.isFlipped"
	>
		<?php echo aggressive_apparel_trusted_html( $flip_icon ); ?>
	</button>
	<div class="aa-card-flip__inner">
		<?php echo aggressive_apparel_trusted_html( $content ); ?>
	</div>
</div>
