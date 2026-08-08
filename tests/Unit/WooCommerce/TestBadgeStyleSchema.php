<?php
/**
 * Badge style schema compiler tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Badge_Style_Schema;
use WP_UnitTestCase;

/** @covers \Aggressive_Apparel\WooCommerce\Badge_Style_Schema */
class TestBadgeStyleSchema extends WP_UnitTestCase {

	/** Color sanitizer retains alpha, presets, and rejects unsafe values. */
	public function test_sanitize_color(): void {
		$this->assertSame( '#aabbcc88', Badge_Style_Schema::sanitize_color( '#AbC8' ) );
		$this->assertSame( 'transparent', Badge_Style_Schema::sanitize_color( ' transparent ' ) );
		$this->assertSame( '', Badge_Style_Schema::sanitize_color( 'rgba(0,0,0,.5)' ) );
		$this->assertSame(
			'var(--wp--preset--color--accent)',
			Badge_Style_Schema::sanitize_color( 'var(--wp--preset--color--accent)' )
		);
		$this->assertSame(
			'',
			Badge_Style_Schema::sanitize_color( 'var(--wp--preset--color--vivid-purple)' )
		);
		$this->assertSame( '', Badge_Style_Schema::sanitize_color( 'var(--evil)' ) );
	}

	/** Preset fills compile through as design-system CSS variables. */
	public function test_emit_preserves_preset_color_vars(): void {
		$vars = Badge_Style_Schema::emit_css_variables(
			array(
				'bg_color'   => 'var(--wp--preset--color--accent)',
				'text_color' => 'var(--wp--preset--color--surface-elevated)',
				'fill_mode'  => 'solid',
			)
		);

		$this->assertContains( '--badge-bg:var(--wp--preset--color--accent)', $vars );
		$this->assertContains( '--badge-text:var(--wp--preset--color--surface-elevated)', $vars );
	}

	/** Layered gap=0 does not emit inset shadows (rings use outline + border). */
	public function test_layered_shadow_without_gap_is_flush(): void {
		$shadow = Badge_Style_Schema::build_box_shadow(
			array(
				'border_mode'         => 'layered',
				'inner_border_width'  => 2,
				'inner_border_color'  => '#ffffff',
				'border_gap'          => 0,
				'bg_color'            => '#000000',
				'fill_mode'           => 'solid',
				'shadow_blur'         => 0,
				'shadow_spread'       => 0,
				'shadow_color'        => '',
			)
		);

		$this->assertSame( '', $shadow );
	}

	/** Layered mode emits gap + inner vars for outline-offset spacing. */
	public function test_layered_emits_gap_and_inner_vars(): void {
		$vars = Badge_Style_Schema::emit_css_variables(
			array(
				'shape'               => 'rect',
				'border_mode'         => 'layered',
				'border_width'        => 2,
				'border_style'        => 'solid',
				'border_color'        => '#ffffff',
				'inner_border_width'  => 1,
				'inner_border_color'  => '#111111',
				'border_gap'          => 4,
				'bg_color'            => 'transparent',
				'fill_mode'           => 'solid',
				'text_color'          => '#ffffff',
				'font_weight'         => 700,
				'text_transform'      => 'uppercase',
				'letter_spacing'      => 'wide',
				'radius_tl'           => 0,
				'radius_tr'           => 0,
				'radius_br'           => 0,
				'radius_bl'           => 0,
				'padding_x'           => 8,
				'padding_y'           => 3,
			),
			'frontend'
		);

		$this->assertContains( '--badge-border-gap:4px', $vars );
		$this->assertContains( '--badge-inner-border-width:1px', $vars );
		$this->assertContains( '--badge-inner-border-color:#111111', $vars );
		$this->assertContains( '--badge-border-width:2px', $vars );
		$this->assertContains( 'aggressive-apparel-product-badge--layered', Badge_Style_Schema::emit_class_names(
			array(
				'shape'       => 'rect',
				'border_mode' => 'layered',
			)
		) );
	}

	/** Outer drop shadow still emits without layered insets. */
	public function test_outer_shadow_appends(): void {
		$shadow = Badge_Style_Schema::build_box_shadow(
			array(
				'border_mode'         => 'none',
				'inner_border_width'  => 0,
				'inner_border_color'  => '',
				'border_gap'          => 0,
				'bg_color'            => '#000000',
				'fill_mode'           => 'solid',
				'shadow_blur'         => 8,
				'shadow_spread'       => 2,
				'shadow_color'        => '#00000066',
			)
		);

		$this->assertSame( '0 2px 8px 2px #00000066', $shadow );
	}

	/** Gradient fill emits a linear-gradient background variable. */
	public function test_emit_gradient_background(): void {
		$vars = Badge_Style_Schema::emit_css_variables(
			array(
				'fill_mode'      => 'gradient',
				'gradient_from'  => '#111111',
				'gradient_to'    => '#eeeeee',
				'gradient_angle' => 90,
				'bg_color'       => '#000000',
				'text_color'     => '#ffffff',
				'font_weight'    => 700,
				'text_transform' => 'uppercase',
				'letter_spacing' => 'wide',
				'radius_tl'      => 4,
				'radius_tr'      => 4,
				'radius_br'      => 4,
				'radius_bl'      => 4,
				'padding_x'      => 8,
				'padding_y'      => 3,
				'border_mode'    => 'none',
				'border_width'   => 0,
				'border_color'   => '',
				'border_style'   => 'none',
			),
			'admin'
		);

		$this->assertContains( '--badge-bg:linear-gradient(90deg, #111111 0%, #eeeeee 100%)', $vars );
	}

	/** Custom px only applies when font_size mode is custom. */
	public function test_custom_font_size_px(): void {
		$custom = Badge_Style_Schema::resolve_font_size(
			array(
				'font_size'    => 'custom',
				'font_size_px' => 18,
			),
			'admin'
		);
		$this->assertSame( '18px', $custom );

		$preset = Badge_Style_Schema::resolve_font_size(
			array(
				'font_size'    => 'x-small',
				'font_size_px' => 18,
			),
			'admin'
		);
		$this->assertSame( '0.75rem', $preset );
	}

	/** Shape classes include shape + icon position modifiers. */
	public function test_emit_class_names_for_shaped_badge(): void {
		$classes = Badge_Style_Schema::emit_class_names(
			array(
				'shape'         => 'ticket',
				'shape_mode'    => 'mask',
				'icon_position' => 'end',
				'glass'         => 1,
				'fill_mode'     => 'solid',
			)
		);

		$this->assertContains( 'aggressive-apparel-product-badge--shape-ticket', $classes );
		$this->assertContains( 'aggressive-apparel-product-badge--shaped', $classes );
		$this->assertContains( 'aggressive-apparel-product-badge--icon-end', $classes );
		$this->assertContains( 'aggressive-apparel-product-badge--glass', $classes );
	}

	/** Glass softens opaque fills so backdrop-filter has something to show. */
	public function test_glass_frosts_opaque_background(): void {
		$this->assertSame( '#00000099', Badge_Style_Schema::frost_color_for_glass( '#000000' ) );
		$this->assertSame( '#ffffff99', Badge_Style_Schema::frost_color_for_glass( '#ffffff' ) );
		$this->assertSame( '#aabbcc88', Badge_Style_Schema::frost_color_for_glass( '#aabbcc88' ) );
		$this->assertSame(
			'color-mix(in srgb, var(--wp--preset--color--accent) 60%, transparent)',
			Badge_Style_Schema::frost_color_for_glass( 'var(--wp--preset--color--accent)' )
		);

		$this->assertSame(
			'#11111199',
			Badge_Style_Schema::resolve_background(
				array(
					'fill_mode' => 'solid',
					'bg_color'  => '#111111',
					'glass'     => 1,
				)
			)
		);

		$this->assertSame(
			'#111111',
			Badge_Style_Schema::resolve_background(
				array(
					'fill_mode' => 'solid',
					'bg_color'  => '#111111',
					'glass'     => 0,
				)
			)
		);
	}

	/** Pill is radius-driven — must not get the SVG --shaped modifier. */
	public function test_pill_is_not_shaped_modifier(): void {
		$classes = Badge_Style_Schema::emit_class_names(
			array(
				'shape'      => 'pill',
				'shape_mode' => 'mask',
			)
		);

		$this->assertContains( 'aggressive-apparel-product-badge--shape-pill', $classes );
		$this->assertNotContains( 'aggressive-apparel-product-badge--shaped', $classes );
	}

	/** Transparent fills still keep layered gap (outline-offset, not fill spacer). */
	public function test_transparent_fill_keeps_layered_gap_vars(): void {
		$vars = Badge_Style_Schema::emit_css_variables(
			array(
				'border_mode'        => 'layered',
				'border_width'       => 2,
				'border_style'       => 'solid',
				'border_color'       => '#ffffff',
				'inner_border_width' => 2,
				'inner_border_color' => '#ffffff',
				'border_gap'         => 4,
				'bg_color'           => 'transparent',
				'fill_mode'          => 'solid',
				'text_color'         => '#ffffff',
				'font_weight'        => 700,
				'text_transform'     => 'uppercase',
				'letter_spacing'     => 'wide',
				'radius_tl'          => 0,
				'radius_tr'          => 0,
				'radius_br'          => 0,
				'radius_bl'          => 0,
				'padding_x'          => 8,
				'padding_y'          => 3,
			),
			'frontend'
		);

		$this->assertContains( '--badge-border-gap:4px', $vars );
		$this->assertContains( '--badge-bg:transparent', $vars );
	}

	/** Incomplete layered inner color falls back to border/text, not fill. */
	public function test_layered_inner_falls_back_past_transparent_fill(): void {
		$inner = Badge_Style_Schema::resolve_layered_inner(
			array(
				'border_mode'        => 'layered',
				'inner_border_width' => 0,
				'inner_border_color' => '',
				'border_color'       => '#abcdef',
				'bg_color'           => 'transparent',
				'text_color'         => '#ffffff',
			)
		);

		$this->assertTrue( $inner['active'] );
		$this->assertSame( 1, $inner['width'] );
		$this->assertSame( '#abcdef', $inner['color'] );
	}

	/** Frame color falls back to border, then currentColor. */
	public function test_resolve_frame_color_fallback(): void {
		$this->assertSame(
			'#abcdef',
			Badge_Style_Schema::resolve_frame_color(
				array(
					'frame_color'  => '#abcdef',
					'border_color' => '#111111',
				)
			)
		);
		$this->assertSame(
			'#111111',
			Badge_Style_Schema::resolve_frame_color(
				array(
					'frame_color'  => '',
					'border_color' => '#111111',
				)
			)
		);
		$this->assertSame(
			'currentColor',
			Badge_Style_Schema::resolve_frame_color(
				array(
					'frame_color'  => '',
					'border_color' => '',
				)
			)
		);
	}

	/** Icon-only badges expose an accessible name. */
	public function test_compile_icon_only_aria_label(): void {
		$html = Badge_Style_Schema::compile_badge_span(
			array(
				'shape'         => 'rect',
				'icon_position' => 'only',
			),
			'<span class="icon">★</span>',
			'frontend',
			'',
			'Sale'
		);

		$this->assertStringContainsString( 'role="img"', $html );
		$this->assertStringContainsString( 'aria-label="Sale"', $html );
	}

	/** Custom shape path allowlist rejects script injection. */
	public function test_shape_path_allowlist_rejects_unsafe(): void {
		$this->assertSame( '', \Aggressive_Apparel\WooCommerce\Badge_Shapes::sanitize_path_d( 'M0 0<script>' ) );
		$this->assertSame(
			'M0 0h10v10z',
			\Aggressive_Apparel\WooCommerce\Badge_Shapes::sanitize_path_d( 'M0 0h10v10z' )
		);
	}

	/** Icon gap uses flex, not directional margin. */
	public function test_resolve_icon_gap(): void {
		$this->assertSame(
			'0.25em',
			Badge_Style_Schema::resolve_icon_gap( array( 'icon_gap' => 0 ) )
		);
		$this->assertSame(
			'8px',
			Badge_Style_Schema::resolve_icon_gap( array( 'icon_gap' => 8 ) )
		);
	}

	/** Incomplete single border state still paints with defaults. */
	public function test_resolve_outer_border_fills_incomplete_state(): void {
		$resolved = Badge_Style_Schema::resolve_outer_border(
			array(
				'border_mode'  => 'single',
				'border_width' => 0,
				'border_style' => 'none',
				'border_color' => '',
				'text_color'   => '#ff0000',
			)
		);

		$this->assertTrue( $resolved['active'] );
		$this->assertSame( 2, $resolved['width'] );
		$this->assertSame( 'solid', $resolved['style'] );
		$this->assertSame( '#ff0000', $resolved['color'] );
	}

	/** CSS double borders need at least 3px to paint two lines. */
	public function test_resolve_outer_border_bumps_double_width(): void {
		$resolved = Badge_Style_Schema::resolve_outer_border(
			array(
				'border_mode'  => 'single',
				'border_width' => 2,
				'border_style' => 'double',
				'border_color' => '#ffffff',
			)
		);

		$this->assertTrue( $resolved['active'] );
		$this->assertSame( 3, $resolved['width'] );
		$this->assertSame( 'double', $resolved['style'] );
	}

	/** Emit vars include double style after incomplete → resolved defaults. */
	public function test_emit_css_variables_paints_double_border(): void {
		$vars = Badge_Style_Schema::emit_css_variables(
			array(
				'shape'          => 'rect',
				'border_mode'    => 'single',
				'border_width'   => 2,
				'border_style'   => 'double',
				'border_color'   => '#ffffff',
				'bg_color'       => '#000000',
				'text_color'     => '#ffffff',
				'font_weight'    => 700,
				'text_transform' => 'uppercase',
				'letter_spacing' => 'wide',
				'radius_tl'      => 0,
				'radius_tr'      => 0,
				'radius_br'      => 0,
				'radius_bl'      => 0,
				'padding_x'      => 8,
				'padding_y'      => 3,
			),
			'frontend'
		);

		$this->assertContains( '--badge-border-width:3px', $vars );
		$this->assertContains( '--badge-border-style:double', $vars );
		$this->assertContains( '--badge-border-color:#ffffff', $vars );
	}

	/** Mask mode suppresses CSS border variables. */
	public function test_mask_mode_suppresses_border_vars(): void {
		$vars = Badge_Style_Schema::emit_css_variables(
			array(
				'shape'        => 'ticket',
				'shape_mode'   => 'mask',
				'border_mode'  => 'single',
				'border_width' => 2,
				'border_color' => '#ffffff',
				'border_style' => 'solid',
				'bg_color'     => '#000000',
				'text_color'   => '#ffffff',
				'font_weight'  => 700,
				'text_transform' => 'uppercase',
				'letter_spacing' => 'wide',
				'radius_tl'    => 0,
				'radius_tr'    => 0,
				'radius_br'    => 0,
				'radius_bl'    => 0,
				'padding_x'    => 8,
				'padding_y'    => 3,
			),
			'frontend'
		);

		$this->assertContains( '--badge-border-width:0', $vars );
		$this->assertContains( '--badge-border-style:none', $vars );
	}

	/** Editor mounts the React studio with a save-bridge of badge_* fields. */
	public function test_editor_renders_designer_controls(): void {
		$taxonomy = new \Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy();

		ob_start();
		$taxonomy->render_add_fields();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="aa-badge-studio-root"', $html );
		$this->assertStringContainsString( 'data-aa-badge-studio=', $html );
		// The studio has no client compiler: the mount seeds a server-rendered
		// first paint instead of shipping an icon-SVG blob for the client to
		// assemble. See Badge_Studio_Rest::compile_payload().
		$this->assertStringContainsString( 'compiled', $html );
		$this->assertStringNotContainsString( 'iconSvgs', $html );
		$this->assertStringContainsString( 'name="badge_border_gap"', $html );
		$this->assertStringContainsString( 'name="badge_shape"', $html );
		$this->assertStringContainsString( 'name="badge_fill_mode"', $html );
		$this->assertStringContainsString( 'name="badge_offset_x"', $html );
		$this->assertStringContainsString( 'name="badge_position"', $html );
		$this->assertStringContainsString( 'value="top-left"', $html );
	}
}
