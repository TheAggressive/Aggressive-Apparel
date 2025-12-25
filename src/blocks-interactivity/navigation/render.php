<?php
/**
 * Ultimate Navigation Block - Dynamic Render
 *
 * Frontend rendering with Interactivity API integration.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive Apparel
 */

defined( 'ABSPATH' ) || exit;

use Aggressive_Apparel\Core\Icons;

// Extract attributes with defaults.
$mobile_breakpoint     = $attributes['mobileBreakpoint'] ?? 1024;
$sticky_behavior       = $attributes['stickyBehavior'] ?? 'none';
$sticky_offset         = $attributes['stickyOffset'] ?? 0;
$mobile_menu_type      = $attributes['mobileMenuType'] ?? 'drawer';
$submenu_open_behavior = $attributes['submenuOpenBehavior'] ?? 'hover';
$submenu_expand_type   = $attributes['submenuExpandType'] ?? 'flyout';
$animation_duration    = $attributes['animationDuration'] ?? 300;
$show_search           = $attributes['showSearch'] ?? false;
$show_cart             = $attributes['showCart'] ?? false;
$overlay_opacity       = $attributes['overlayOpacity'] ?? 0.5;

// Color attributes.
$hover_text_color   = $attributes['hoverTextColor'] ?? '';
$hover_bg_color     = $attributes['hoverBackgroundColor'] ?? '';
$active_text_color  = $attributes['activeTextColor'] ?? '';
$active_bg_color    = $attributes['activeBackgroundColor'] ?? '';
$submenu_bg_color   = $attributes['submenuBackgroundColor'] ?? '';
$submenu_text_color = $attributes['submenuTextColor'] ?? '';
$submenu_hover_text = $attributes['submenuHoverTextColor'] ?? '';
$submenu_hover_bg   = $attributes['submenuHoverBackgroundColor'] ?? '';
$mobile_bg_color    = $attributes['mobileMenuBackgroundColor'] ?? '';
$mobile_text_color  = $attributes['mobileMenuTextColor'] ?? '';

// Generate unique instance ID.
$instance_id = 'nav_' . uniqid();

// Build context for Interactivity API.
$context = array(
	// Configuration.
	'mobileBreakpoint'    => $mobile_breakpoint,
	'stickyBehavior'      => $sticky_behavior,
	'stickyOffset'        => $sticky_offset,
	'mobileMenuType'      => $mobile_menu_type,
	'submenuOpenBehavior' => $submenu_open_behavior,
	'submenuExpandType'   => $submenu_expand_type,
	'animationDuration'   => $animation_duration,
	'overlayOpacity'      => $overlay_opacity,

	// Runtime state.
	'isMobileMenuOpen'    => false,
	'activeSubmenuStack'  => array(),
	'isMobile'            => false,
	'isSticky'            => false,
	'lastScrollY'         => 0,
	'scrollDirection'     => 'down',
	'instanceId'          => $instance_id,
);

// Build CSS custom properties.
$css_vars = array(
	'--nav-transition'      => $animation_duration . 'ms',
	'--nav-overlay-opacity' => $overlay_opacity,
	'--mobile-breakpoint'   => $mobile_breakpoint . 'px',
);

// Apply custom color attributes.
if ( ! empty( $hover_text_color ) ) {
	$css_vars['--nav-hover-text'] = $hover_text_color;
}
if ( ! empty( $hover_bg_color ) ) {
	$css_vars['--nav-hover-bg'] = $hover_bg_color;
}
if ( ! empty( $active_text_color ) ) {
	$css_vars['--nav-active-text'] = $active_text_color;
}
if ( ! empty( $active_bg_color ) ) {
	$css_vars['--nav-active-bg'] = $active_bg_color;
}
if ( ! empty( $submenu_bg_color ) ) {
	$css_vars['--submenu-bg'] = $submenu_bg_color;
}
if ( ! empty( $submenu_text_color ) ) {
	$css_vars['--submenu-text'] = $submenu_text_color;
}
if ( ! empty( $submenu_hover_text ) ) {
	$css_vars['--submenu-hover-text'] = $submenu_hover_text;
}
if ( ! empty( $submenu_hover_bg ) ) {
	$css_vars['--submenu-hover-bg'] = $submenu_hover_bg;
}
if ( ! empty( $mobile_bg_color ) ) {
	$css_vars['--mobile-bg'] = $mobile_bg_color;
}
if ( ! empty( $mobile_text_color ) ) {
	$css_vars['--mobile-text'] = $mobile_text_color;
}

$style_string = '';
foreach ( $css_vars as $property => $value ) {
	$style_string .= sprintf( '%s: %s; ', esc_attr( $property ), esc_attr( $value ) );
}

// Build wrapper classes.
$classes = array(
	'aa-navigation',
	'aa-navigation--sticky-' . $sticky_behavior,
	'aa-navigation--mobile-' . $mobile_menu_type,
	'aa-navigation--expand-' . $submenu_expand_type,
);

// Get wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                      => implode( ' ', $classes ),
		'style'                      => $style_string,
		'data-wp-interactive'        => 'aggressive-apparel/navigation',
		'data-wp-context'            => wp_json_encode( $context ),
		'data-wp-init'               => 'callbacks.init',
		'data-wp-on-window--resize'  => 'callbacks.handleResize',
		'data-wp-on-window--scroll'  => 'callbacks.handleScroll',
		'data-wp-on-window--keydown' => 'actions.handleEscapeKey',
		'data-instance-id'           => $instance_id,
	)
);

?>
<nav <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="aa-navigation__container">

		<!-- Mobile Toggle Button -->
		<button
			class="aa-navigation__toggle"
			type="button"
			aria-label="<?php esc_attr_e( 'Toggle menu', 'aggressive-apparel' ); ?>"
			aria-expanded="false"
			aria-controls="<?php echo esc_attr( $instance_id ); ?>-menu"
			data-wp-on--click="actions.toggleMobileMenu"
			data-wp-bind--aria-expanded="context.isMobileMenuOpen"
			data-wp-class--is-active="context.isMobileMenuOpen"
		>
			<span class="aa-navigation__toggle-icon aa-navigation__toggle-icon--open">
				<?php Icons::render( 'hamburger' ); ?>
			</span>
			<span class="aa-navigation__toggle-icon aa-navigation__toggle-icon--close">
				<?php Icons::render( 'close' ); ?>
			</span>
		</button>

		<!-- Main Menu Wrapper -->
		<div
			id="<?php echo esc_attr( $instance_id ); ?>-menu"
			class="aa-navigation__menu"
			data-wp-class--is-open="context.isMobileMenuOpen"
			role="navigation"
			aria-label="<?php esc_attr_e( 'Main navigation', 'aggressive-apparel' ); ?>"
		>
			<?php if ( 'drill-down' === $submenu_expand_type ) : ?>
				<!-- Drill-down Back Button -->
				<button
					class="aa-navigation__back"
					type="button"
					aria-label="<?php esc_attr_e( 'Go back', 'aggressive-apparel' ); ?>"
					data-wp-on--click="actions.navigateDrillUp"
					data-wp-class--is-visible="context.activeSubmenuStack.length"
				>
					<?php Icons::render( 'arrow-left' ); ?>
					<span><?php esc_html_e( 'Back', 'aggressive-apparel' ); ?></span>
				</button>
			<?php endif; ?>

			<!-- Navigation Items (Inner Blocks) -->
			<ul class="aa-navigation__items" role="menubar">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</ul>
		</div>

		<!-- Action Buttons (Search, Cart) -->
		<div class="aa-navigation__actions">
			<?php if ( $show_search ) : ?>
				<button
					class="aa-navigation__search-toggle"
					type="button"
					aria-label="<?php esc_attr_e( 'Search', 'aggressive-apparel' ); ?>"
					data-wp-on--click="actions.toggleSearch"
				>
					<?php Icons::render( 'search' ); ?>
				</button>
			<?php endif; ?>

			<?php if ( $show_cart ) : ?>
				<?php
				$cart_url = '/cart';
				if ( function_exists( 'wc_get_cart_url' ) ) {
					$cart_url = \WC()->cart->get_cart_url();
				}
				?>
				<a
					class="aa-navigation__cart-toggle"
					href="<?php echo esc_url( $cart_url ); ?>"
					aria-label="<?php esc_attr_e( 'View cart', 'aggressive-apparel' ); ?>"
				>
					<?php Icons::render( 'cart' ); ?>
					<span
						class="aa-navigation__cart-count"
						data-wp-text="state.cartCount"
						data-wp-class--has-items="state.cartCount"
					></span>
				</a>
			<?php endif; ?>
		</div>

		<!-- Mobile Overlay -->
		<div
			class="aa-navigation__overlay"
			data-wp-on--click="actions.closeMobileMenu"
			data-wp-class--is-visible="context.isMobileMenuOpen"
			aria-hidden="true"
		></div>
	</div>

	<!-- Sticky Header Classes -->
	<template
		data-wp-class--is-sticky="context.isSticky"
		data-wp-class--is-mobile="context.isMobile"
		data-wp-class--menu-open="context.isMobileMenuOpen"
	></template>
</nav>
