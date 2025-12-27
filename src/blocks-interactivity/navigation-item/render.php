<?php
/**
 * Navigation Item Block - Dynamic Render
 *
 * @package Aggressive Apparel
 */

defined( 'ABSPATH' ) || exit;

// Extract attributes.
$label            = $attributes['label'] ?? '';
$url              = $attributes['url'] ?? '';
$opens_in_new_tab = $attributes['opensInNewTab'] ?? false;
$rel              = $attributes['rel'] ?? '';
$title_attr       = $attributes['title'] ?? '';
$description      = $attributes['description'] ?? '';
$is_current_page  = $attributes['isCurrentPage'] ?? false;

// Get context from parent navigation.
$hover_text_color = $block->context['aggressive-apparel/navigation/hoverTextColor'] ?? '';
$hover_bg_color   = $block->context['aggressive-apparel/navigation/hoverBackgroundColor'] ?? '';

// Check if we're inside a mega menu context.
// Also check parent blocks in the hierarchy for mega menu context.
$is_in_mega_menu = false;

if ( ! empty( $block->context['aggressive-apparel/navigation-mega-menu'] ) ) {
	$is_in_mega_menu = true;
} elseif ( ! empty( $block->parent_block ) ) {
	// Check parent block context.
	$parent_context = $block->parent_block->context ?? array();
	if ( ! empty( $parent_context['aggressive-apparel/navigation-mega-menu'] ) ) {
		$is_in_mega_menu = true;
	} elseif ( ! empty( $block->parent_block->parent_block ) ) {
		// Check grandparent block context.
		$grandparent_context = $block->parent_block->parent_block->context ?? array();
		if ( ! empty( $grandparent_context['aggressive-apparel/navigation-mega-menu'] ) ) {
			$is_in_mega_menu = true;
		}
	}
}

// Auto-detect current page.
if ( ! $is_current_page && ! empty( $url ) ) {
	$current_url     = home_url( add_query_arg( array() ) );
	$is_current_page = trailingslashit( $url ) === trailingslashit( $current_url );
}

// Build CSS variables.
$css_vars = array();
if ( ! empty( $hover_text_color ) ) {
	$css_vars['--item-hover-text'] = $hover_text_color;
}
if ( ! empty( $hover_bg_color ) ) {
	$css_vars['--item-hover-bg'] = $hover_bg_color;
}

$style_string = '';
foreach ( $css_vars as $property => $value ) {
	$style_string .= sprintf( '%s: %s; ', esc_attr( $property ), esc_attr( $value ) );
}

// Build classes.
$classes = array( 'aa-navigation-item' );
if ( $is_current_page ) {
	$classes[] = 'aa-navigation-item--current';
}

// When inside a mega menu, use different classes.
if ( $is_in_mega_menu ) {
	$classes = array( 'aa-navigation-mega-menu__item' );
	if ( $is_current_page ) {
		$classes[] = 'aa-navigation-mega-menu__item--current';
	}
}

// Build link attributes.
$link_attrs = array(
	'href'  => esc_url( $url ),
	'class' => $is_in_mega_menu ? 'aa-navigation-mega-menu__link' : 'aa-navigation-item__link',
);

if ( $opens_in_new_tab ) {
	$link_attrs['target'] = '_blank';
	// Add noopener for security.
	$rel = trim( $rel . ' noopener' );
}

if ( ! empty( $rel ) ) {
	$link_attrs['rel'] = esc_attr( $rel );
}

if ( ! empty( $title_attr ) ) {
	$link_attrs['title'] = esc_attr( $title_attr );
}

if ( $is_current_page ) {
	$link_attrs['aria-current'] = 'page';
}

// Build link attributes string.
$link_attrs_string = '';
foreach ( $link_attrs as $attr => $value ) {
	$link_attrs_string .= sprintf( ' %s="%s"', $attr, $value );
}

if ( $is_in_mega_menu ) {
	// When inside a mega menu, render as just a link.
	?>
	<a <?php echo $link_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> style="<?php echo esc_attr( $style_string ); ?>">
		<span class="<?php echo $is_in_mega_menu ? 'aa-navigation-mega-menu__label' : 'aa-navigation-item__label'; ?>"><?php echo esc_html( $label ); ?></span>
		<?php if ( ! empty( $description ) ) : ?>
			<span class="<?php echo $is_in_mega_menu ? 'aa-navigation-mega-menu__description' : 'aa-navigation-item__description'; ?>"><?php echo esc_html( $description ); ?></span>
		<?php endif; ?>
	</a>
	<?php
} else {
	// Normal navigation item rendering.
	// Get wrapper attributes.
	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class' => implode( ' ', $classes ),
			'style' => $style_string,
			'role'  => 'none',
		)
	);

	?>
	<li <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<a <?php echo $link_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="menuitem">
			<span class="aa-navigation-item__label"><?php echo esc_html( $label ); ?></span>
			<?php if ( ! empty( $description ) ) : ?>
				<span class="aa-navigation-item__description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</a>
	</li>
	<?php
}
