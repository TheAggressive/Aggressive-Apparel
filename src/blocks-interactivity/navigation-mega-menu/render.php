<?php
/**
 * Navigation Mega Menu Block - Dynamic Render
 *
 * @package Aggressive Apparel
 */

defined( 'ABSPATH' ) || exit;

use Aggressive_Apparel\Core\Icons;

// Extract attributes.
$label                    = $attributes['label'] ?? '';
$columns                  = $attributes['columns'] ?? 4;
$show_featured_image      = $attributes['showFeaturedImage'] ?? false;
$featured_image           = $attributes['featuredImage'] ?? null;
$content_bg_override      = $attributes['contentBackgroundColor'] ?? '';
$content_text_override    = $attributes['contentTextColor'] ?? '';
$content_padding_override = $attributes['contentPadding'] ?? array();
$content_margin_override  = $attributes['contentMargin'] ?? array();

// Get context from parent navigation.
$open_behavior       = $block->context['aggressive-apparel/navigation/submenuOpenBehavior'] ?? 'hover';
$animation_duration  = $block->context['aggressive-apparel/navigation/animationDuration'] ?? 300;
$submenu_bg_global   = $block->context['aggressive-apparel/navigation/submenuBackgroundColor'] ?? '';
$submenu_text_global = $block->context['aggressive-apparel/navigation/submenuTextColor'] ?? '';

// Final styles logic: granular override > global context.
$final_bg   = ! empty( $content_bg_override ) ? $content_bg_override : $submenu_bg_global;
$final_text = ! empty( $content_text_override ) ? $content_text_override : $submenu_text_global;

// Generate unique ID for this mega menu.
$mega_menu_id = 'mega_' . uniqid();

// Build context for Interactivity API.
$context = array(
	'submenuId'  => $mega_menu_id,
	'isOpen'     => false,
	'expandType' => 'flyout', // Mega menus always use flyout behavior.
);

// Build CSS custom properties.
$css_vars = array(
	'--mega-columns'    => $columns,
	'--mega-transition' => $animation_duration . 'ms',
);

// Build content wrapper styles.
$content_style = '';
if ( ! empty( $final_bg ) ) {
	$content_style        .= 'background-color: ' . esc_attr( $final_bg ) . '; ';
	$css_vars['--mega-bg'] = $final_bg;
}
if ( ! empty( $final_text ) ) {
	$content_style          .= 'color: ' . esc_attr( $final_text ) . '; ';
	$css_vars['--mega-text'] = $final_text;
}

// Add padding/margin overrides if set.
if ( ! empty( $content_padding_override ) ) {
	foreach ( $content_padding_override as $side => $value ) {
		$content_style .= sprintf( 'padding-%s: %s; ', esc_attr( $side ), esc_attr( $value ) );
	}
}
if ( ! empty( $content_margin_override ) ) {
	foreach ( $content_margin_override as $side => $value ) {
		$content_style .= sprintf( 'margin-%s: %s; ', esc_attr( $side ), esc_attr( $value ) );
	}
}

$style_string = '';
foreach ( $css_vars as $property => $value ) {
	$style_string .= sprintf( '%s: %s; ', esc_attr( $property ), esc_attr( $value ) );
}

// Build classes.
$classes = array(
	'aa-navigation-mega-menu',
	'aa-navigation-mega-menu--columns-' . $columns,
);

// Get wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'           => implode( ' ', $classes ),
		'style'           => $style_string,
		'role'            => 'none',
		'data-wp-context' => wp_json_encode( $context ),
	)
);

// Hover attributes for wrapper.
$wrapper_hover_attrs = '';
if ( 'hover' === $open_behavior ) {
	$wrapper_hover_attrs = 'data-wp-on--mouseenter="actions.handleSubmenuHover" data-wp-on--mouseleave="actions.scheduleSubmenuClose"';
}

?>
<li <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo $wrapper_hover_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<button
		class="aa-navigation-mega-menu__trigger"
		type="button"
		aria-expanded="false"
		aria-haspopup="true"
		aria-controls="<?php echo esc_attr( $mega_menu_id ); ?>"
		data-wp-on--click="<?php echo 'hover' !== $open_behavior ? 'actions.openSubmenu' : ''; ?>"
		data-wp-bind--aria-expanded="context.isOpen"
		data-submenu-id="<?php echo esc_attr( $mega_menu_id ); ?>"
	>
		<span class="aa-navigation-mega-menu__label"><?php echo esc_html( $label ); ?></span>
		<span class="aa-navigation-mega-menu__icon">
		<?php
		Icons::render(
			'chevron-down',
			array(
				'width'  => 16,
				'height' => 16,
			)
		);
		?>
		</span>
	</button>

	<div
		id="<?php echo esc_attr( $mega_menu_id ); ?>"
		class="aa-navigation-mega-menu__content"
		role="menu"
		aria-label="<?php echo esc_attr( $label ); ?> mega menu"
		data-wp-class--is-open="context.isOpen"
		style="<?php echo esc_attr( $content_style ); ?>"
	>
		<div class="aa-navigation-mega-menu__inner">
			<div class="aa-navigation-mega-menu__columns">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<?php if ( $show_featured_image && ! empty( $featured_image['url'] ) ) : ?>
				<div class="aa-navigation-mega-menu__featured">
					<img
						src="<?php echo esc_url( $featured_image['url'] ); ?>"
						alt="<?php echo esc_attr( $featured_image['alt'] ?? '' ); ?>"
						loading="lazy"
					/>
				</div>
			<?php endif; ?>
		</div>
	</div>
</li>
