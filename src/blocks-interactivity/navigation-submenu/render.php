<?php
/**
 * Navigation Submenu Block - Dynamic Render
 *
 * @package Aggressive Apparel
 */

defined( 'ABSPATH' ) || exit;

use Aggressive_Apparel\Core\Icons;

// Extract attributes.
$label     = $attributes['label'] ?? '';
$url       = $attributes['url'] ?? '';
$alignment = $attributes['alignment'] ?? 'left';
$width     = $attributes['width'] ?? 'auto';
$list_bg   = $attributes['listBackgroundColor'] ?? '';
$list_text = $attributes['listTextColor'] ?? '';
$list_pad  = $attributes['listPadding'] ?? array();
$list_marg = $attributes['listMargin'] ?? array();

// Get context from parent navigation.
$expand_type        = $block->context['aggressive-apparel/navigation/submenuExpandType'] ?? 'flyout';
$open_behavior      = $block->context['aggressive-apparel/navigation/submenuOpenBehavior'] ?? 'hover';
$animation_duration = $block->context['aggressive-apparel/navigation/animationDuration'] ?? 300;
$submenu_bg         = $block->context['aggressive-apparel/navigation/submenuBackgroundColor'] ?? '';
$submenu_text       = $block->context['aggressive-apparel/navigation/submenuTextColor'] ?? '';
$submenu_hover_text = $block->context['aggressive-apparel/navigation/submenuHoverTextColor'] ?? '';
$submenu_hover_bg   = $block->context['aggressive-apparel/navigation/submenuHoverBackgroundColor'] ?? '';

// Generate unique ID for this submenu.
$submenu_id = 'submenu_' . uniqid();

// Build context for Interactivity API.
$context = array(
	'submenuId'   => $submenu_id,
	'isOpen'      => false,
	'expandType'  => $expand_type,
	'hasChildren' => ! empty( $content ),
);

// Build CSS custom properties.
$css_vars = array(
	'--submenu-width'      => 'full' === $width ? '100%' : $width,
	'--submenu-transition' => $animation_duration . 'ms',
);

if ( ! empty( $submenu_bg ) ) {
	$css_vars['--submenu-bg'] = $submenu_bg;
}
if ( ! empty( $submenu_text ) ) {
	$css_vars['--submenu-text'] = $submenu_text;
}
if ( ! empty( $submenu_hover_text ) ) {
	$css_vars['--submenu-hover-text'] = $submenu_hover_text;
}
if ( ! empty( $submenu_hover_bg ) ) {
	$css_vars['--submenu-hover-bg'] = $submenu_hover_bg;
}

// Build content wrapper styles (inline to support editor control without default CSS).
$content_style = '';
if ( ! empty( $submenu_bg ) ) {
	$content_style           .= 'background-color: ' . esc_attr( $submenu_bg ) . '; ';
	$css_vars['--submenu-bg'] = $submenu_bg; // Keep for legacy/other references.
}
if ( ! empty( $submenu_text ) ) {
	$content_style             .= 'color: ' . esc_attr( $submenu_text ) . '; ';
	$css_vars['--submenu-text'] = $submenu_text;
}

$style_string = '';
foreach ( $css_vars as $property => $value ) {
	$style_string .= sprintf( '%s: %s; ', esc_attr( $property ), esc_attr( $value ) );
}

// Build classes.
$classes = array(
	'aa-navigation-submenu',
	'aa-navigation-submenu--expand-' . $expand_type,
	'aa-navigation-submenu--align-' . $alignment,
);

// Build trigger attributes based on expand type.
$trigger_attrs = array(
	'class'         => 'aa-navigation-submenu__trigger',
	'aria-expanded' => 'false',
	'aria-haspopup' => 'true',
	'aria-controls' => $submenu_id,
);

// Event handlers based on behavior and expand type.
if ( 'hover' === $open_behavior && 'flyout' === $expand_type ) {
	$trigger_attrs['data-wp-on--mouseenter'] = 'actions.handleSubmenuHover';
	$trigger_attrs['data-wp-on--mouseleave'] = 'actions.scheduleSubmenuClose';
} elseif ( 'accordion' === $expand_type ) {
	$trigger_attrs['data-wp-on--click'] = 'actions.toggleAccordion';
} elseif ( 'drill-down' === $expand_type ) {
	$trigger_attrs['data-wp-on--click'] = 'actions.navigateDrillDown';
} else {
	$trigger_attrs['data-wp-on--click'] = 'actions.openSubmenu';
}

$trigger_attrs['data-wp-bind--aria-expanded'] = 'context.isOpen';
$trigger_attrs['data-submenu-id']             = $submenu_id;

// Build trigger attributes string.
$trigger_attrs_string = '';
foreach ( $trigger_attrs as $attr => $value ) {
	$trigger_attrs_string .= sprintf( ' %s="%s"', $attr, esc_attr( $value ) );
}

// Choose icon based on expand type.
$icon_name = 'drill-down' === $expand_type ? 'arrow' : 'chevron';

// Get wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'           => implode( ' ', $classes ),
		'style'           => $style_string,
		'role'            => 'none',
		'data-wp-context' => wp_json_encode( $context ),
	)
);

// Hover attributes for wrapper (flyout mode).
$wrapper_hover_attrs = '';
if ( 'hover' === $open_behavior && 'flyout' === $expand_type ) {
	$wrapper_hover_attrs = 'data-wp-on--mouseenter="actions.handleSubmenuHover" data-wp-on--mouseleave="actions.scheduleSubmenuClose"';
}

?>
<li <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo $wrapper_hover_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! empty( $url ) ) : ?>
		<!-- Parent has URL - split button -->
		<div class="aa-navigation-submenu__trigger-wrapper">
			<a href="<?php echo esc_url( $url ); ?>" class="aa-navigation-submenu__link">
				<?php echo esc_html( $label ); ?>
			</a>
			<button <?php echo $trigger_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<span class="aa-navigation-submenu__icon">
				<?php
				Icons::render(
					$icon_name,
					array(
						'width'  => 16,
						'height' => 16,
					)
				);
				?>
															</span>
				<span class="screen-reader-text"><?php esc_html_e( 'Show submenu', 'aggressive-apparel' ); ?></span>
			</button>
		</div>
	<?php else : ?>
		<!-- No URL - single trigger -->
		<button <?php echo $trigger_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<span class="aa-navigation-submenu__label"><?php echo esc_html( $label ); ?></span>
			<span class="aa-navigation-submenu__icon">
			<?php
			Icons::render(
				$icon_name,
				array(
					'width'  => 16,
					'height' => 16,
				)
			);
			?>
			</span>
		</button>
	<?php endif; ?>

	<!-- Submenu Content -->
	<div
		id="<?php echo esc_attr( $submenu_id ); ?>"
		class="aa-navigation-submenu__content"
		role="menu"
		aria-label="<?php echo esc_attr( $label ); ?> submenu"
		aria-label="<?php echo esc_attr( $label ); ?> submenu"
		data-wp-class--is-open="context.isOpen"
		style="<?php echo esc_attr( $content_style ); ?>"
	>
		<?php
		// Build CSS for the inner list.
		$list_style = '';
		if ( ! empty( $list_bg ) ) {
			$list_style .= 'background-color: ' . esc_attr( $list_bg ) . '; ';
		}
		if ( ! empty( $list_text ) ) {
			$list_style .= 'color: ' . esc_attr( $list_text ) . '; ';
		}

		// List Padding.
		if ( ! empty( $list_pad ) ) {
			if ( isset( $list_pad['top'] ) ) {
				$list_style .= 'padding-top: ' . esc_attr( $list_pad['top'] ) . '; ';
			}
			if ( isset( $list_pad['right'] ) ) {
				$list_style .= 'padding-right: ' . esc_attr( $list_pad['right'] ) . '; ';
			}
			if ( isset( $list_pad['bottom'] ) ) {
				$list_style .= 'padding-bottom: ' . esc_attr( $list_pad['bottom'] ) . '; ';
			}
			if ( isset( $list_pad['left'] ) ) {
				$list_style .= 'padding-left: ' . esc_attr( $list_pad['left'] ) . '; ';
			}
		}

		// List Margin.
		if ( ! empty( $list_marg ) ) {
			if ( isset( $list_marg['top'] ) ) {
				$list_style .= 'margin-top: ' . esc_attr( $list_marg['top'] ) . '; ';
			}
			if ( isset( $list_marg['right'] ) ) {
				$list_style .= 'margin-right: ' . esc_attr( $list_marg['right'] ) . '; ';
			}
			if ( isset( $list_marg['bottom'] ) ) {
				$list_style .= 'margin-bottom: ' . esc_attr( $list_marg['bottom'] ) . '; ';
			}
			if ( isset( $list_marg['left'] ) ) {
				$list_style .= 'margin-left: ' . esc_attr( $list_marg['left'] ) . '; ';
			}
		}
		?>
		<ul class="aa-navigation-submenu__items" style="<?php echo esc_attr( $list_style ); ?>">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</ul>
	</div>
</li>
