<?php
/**
 * Badge studio server-compile tests.
 *
 * The studio has no client-side compiler: `Badge_Studio_Rest::compile_payload()`
 * renders both the first paint and every live edit, so these assertions cover
 * the admin preview and the storefront at once. They also carry the SVG and
 * shape sanitizer cases that used to live in the deleted client mirror — that
 * markup is injected into the studio canvas, so "an admin typed it" is not a
 * safety argument.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Badge_Shapes;
use Aggressive_Apparel\WooCommerce\Badge_Studio_Rest;
use Aggressive_Apparel\WooCommerce\Badge_Style_Schema;
use Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Badge_Studio_Rest
 * @covers \Aggressive_Apparel\WooCommerce\Badge_Shapes
 */
class TestBadgeStudioCompile extends WP_UnitTestCase {

	/**
	 * Legitimate SVG survives the sanitizer intact.
	 *
	 * `wp_kses` lower-cases attribute names, so `viewBox` comes back as
	 * `viewbox`. That is safe here and only here: the payload is parsed as
	 * HTML (the canvas sets it as markup), and the HTML parser's foreign-content
	 * adjustment maps `viewbox` back to the case-sensitive SVG `viewBox`. Do not
	 * reuse this output anywhere it would be parsed as XML.
	 */
	public function test_sanitize_svg_keeps_legitimate_markup(): void {
		$out = Custom_Badge_Taxonomy::sanitize_svg(
			'<svg viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>'
		);

		$this->assertStringContainsString( '<svg', $out );
		$this->assertStringContainsString( 'viewbox="0 0 24 24"', $out );
		$this->assertStringContainsString( 'd="M0 0h24v24H0z"', $out );
	}

	/** Script elements are removed from authored SVG. */
	public function test_sanitize_svg_removes_script_elements(): void {
		$out = Custom_Badge_Taxonomy::sanitize_svg(
			'<svg><script>alert(1)</script><circle r="5"/></svg>'
		);

		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringContainsString( '<circle', $out );
	}

	/** Inline event handlers never survive onto any element. */
	public function test_sanitize_svg_strips_event_handlers(): void {
		$out = Custom_Badge_Taxonomy::sanitize_svg(
			'<svg onload="alert(1)"><circle r="5" onclick="alert(2)"/></svg>'
		);

		$this->assertStringNotContainsString( 'onload', $out );
		$this->assertStringNotContainsString( 'onclick', $out );
		$this->assertStringContainsString( '<circle', $out );
	}

	/** foreignObject can smuggle arbitrary HTML, so it is not allowlisted. */
	public function test_sanitize_svg_removes_foreign_object_and_html(): void {
		$out = Custom_Badge_Taxonomy::sanitize_svg(
			'<svg><foreignObject><body><img src=x onerror="alert(1)"></body></foreignObject></svg>'
		);

		$this->assertStringNotContainsString( 'foreignObject', $out );
		$this->assertStringNotContainsString( '<img', $out );
		$this->assertStringNotContainsString( 'onerror', $out );
	}

	/** Anchors and javascript: URLs are not in the allowlist. */
	public function test_sanitize_svg_drops_anchors(): void {
		$out = Custom_Badge_Taxonomy::sanitize_svg(
			'<svg><a href="javascript:alert(1)"><circle r="5"/></a></svg>'
		);

		$this->assertStringNotContainsString( 'javascript:', $out );
		$this->assertStringNotContainsString( '<a ', $out );
	}

	/** Rect and pill are radius geometry, not SVG silhouettes. */
	public function test_shape_resolve_skips_rect_and_pill(): void {
		$this->assertNull( Badge_Shapes::resolve( array( 'shape' => 'rect' ) ) );
		$this->assertNull( Badge_Shapes::resolve( array( 'shape' => 'pill' ) ) );
	}

	/** Curated shapes resolve to a viewBox + path pair. */
	public function test_shape_resolve_returns_curated_definition(): void {
		$def = Badge_Shapes::resolve( array( 'shape' => 'hex' ) );

		$this->assertIsArray( $def );
		$this->assertSame( '0 0 100 40', $def['viewBox'] );
		$this->assertNotSame( '', $def['path'] );
	}

	/** Custom shapes extract an allowlisted path from sanitized SVG. */
	public function test_shape_resolve_extracts_custom_path(): void {
		$def = Badge_Shapes::resolve(
			array(
				'shape'     => 'custom',
				'shape_svg' => '<svg viewBox="0 0 50 20"><path d="M0 0h50v20z"/></svg>',
			)
		);

		$this->assertIsArray( $def );
		$this->assertSame( '0 0 50 20', $def['viewBox'] );
		$this->assertSame( 'M0 0h50v20z', $def['path'] );
	}

	/** A custom shape whose path fails the allowlist resolves to nothing. */
	public function test_shape_resolve_rejects_unsafe_custom_path(): void {
		$this->assertNull(
			Badge_Shapes::resolve(
				array(
					'shape'     => 'custom',
					'shape_svg' => '<svg><path d="M0 0 url(javascript:alert(1))"/></svg>',
				)
			)
		);
	}

	/** viewBox allowlist falls back rather than emitting authored text. */
	public function test_sanitize_viewbox_falls_back(): void {
		$this->assertSame( '0 0 24 24', Badge_Shapes::sanitize_viewbox( ' 0 0 24 24 ' ) );
		$this->assertSame( '0 0 100 40', Badge_Shapes::sanitize_viewbox( '0 0 24 24"><script>' ) );
	}

	/** Mask mode emits a data URI; frame mode emits none. */
	public function test_mask_image_css_respects_shape_mode(): void {
		$mask = Badge_Shapes::mask_image_css(
			array(
				'shape'      => 'hex',
				'shape_mode' => 'mask',
			)
		);
		$this->assertStringStartsWith( 'url("data:image/svg+xml,', $mask );

		$this->assertSame(
			'',
			Badge_Shapes::mask_image_css(
				array(
					'shape'      => 'hex',
					'shape_mode' => 'frame',
				)
			)
		);
	}

	/** Frame mode renders a stroked silhouette; mask mode renders no SVG. */
	public function test_render_frame_svg_only_in_frame_mode(): void {
		$frame = Badge_Shapes::render_frame_svg(
			array(
				'shape'      => 'shield',
				'shape_mode' => 'frame',
			)
		);
		$this->assertStringContainsString( 'aggressive-apparel-product-badge__shape', $frame );

		$this->assertSame(
			'',
			Badge_Shapes::render_frame_svg(
				array(
					'shape'      => 'shield',
					'shape_mode' => 'mask',
				)
			)
		);
	}

	/**
	 * The badge screens load nothing from a third-party host.
	 *
	 * An external stylesheet hangs an offline admin behind a DNS timeout and
	 * discloses the viewer's request to a third party on every page load. The
	 * theme bundles no fonts and loads none remotely; this pins that.
	 */
	public function test_admin_assets_load_no_external_hosts(): void {
		$_GET['taxonomy'] = Custom_Badge_Taxonomy::TAXONOMY;
		set_current_screen( 'edit-tags' );

		$taxonomy = new Custom_Badge_Taxonomy();
		$taxonomy->enqueue_admin_scripts( 'edit-tags.php' );

		$styles = wp_styles();
		$local  = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( $styles->queue as $handle ) {
			$src = $styles->registered[ $handle ]->src ?? '';
			if ( ! is_string( $src ) || '' === $src ) {
				continue;
			}

			$host = wp_parse_url( $src, PHP_URL_HOST );
			if ( null === $host ) {
				// Relative src — same origin by definition.
				continue;
			}

			$this->assertSame(
				$local,
				$host,
				sprintf( 'Style "%s" loads from an external host: %s', $handle, $src )
			);
		}

		unset( $_GET['taxonomy'] );
	}

	/**
	 * The studio stylesheet must not depend on a conditionally-registered handle.
	 *
	 * WordPress drops a stylesheet whose dependency was never registered, so
	 * naming the storefront bundle unconditionally would blank the entire studio
	 * UI whenever that build artifact is missing.
	 */
	public function test_studio_style_does_not_hard_depend_on_storefront_bundle(): void {
		$_GET['taxonomy'] = Custom_Badge_Taxonomy::TAXONOMY;
		set_current_screen( 'edit-tags' );

		$taxonomy = new Custom_Badge_Taxonomy();
		$taxonomy->enqueue_admin_scripts( 'edit-tags.php' );

		$studio = wp_styles()->registered['aggressive-apparel-badge-studio'] ?? null;
		if ( null === $studio ) {
			$this->markTestSkipped( 'Studio CSS build artifact is absent.' );
		}

		foreach ( $studio->deps as $dep ) {
			$this->assertArrayHasKey(
				$dep,
				wp_styles()->registered,
				sprintf( 'Studio CSS depends on unregistered handle "%s".', $dep )
			);
		}

		unset( $_GET['taxonomy'] );
	}

	/** Frame width 0 means "no frame" in the markup and the CSS var alike. */
	public function test_frame_width_zero_paints_nothing(): void {
		$badge = array(
			'shape'       => 'shield',
			'shape_mode'  => 'frame',
			'frame_width' => 0,
		);

		$this->assertSame( '', Badge_Shapes::render_frame_svg( $badge ) );
		$this->assertContains(
			'--badge-frame-width:0px',
			Badge_Style_Schema::emit_css_variables( $badge )
		);
	}

	/** Absent frame width is missing data, not a choice — it defaults to 2. */
	public function test_frame_width_defaults_when_absent(): void {
		$badge = array(
			'shape'      => 'shield',
			'shape_mode' => 'frame',
		);

		$this->assertStringContainsString(
			'stroke-width="2"',
			Badge_Shapes::render_frame_svg( $badge )
		);
		$this->assertContains(
			'--badge-frame-width:2px',
			Badge_Style_Schema::emit_css_variables( $badge )
		);
	}

	/** The compile payload carries everything the canvas renders. */
	public function test_compile_payload_shape(): void {
		$payload = Badge_Studio_Rest::compile_payload(
			array( 'badge_bg_color' => '#123456' ),
			'Sale'
		);

		$this->assertArrayHasKey( 'html', $payload );
		$this->assertArrayHasKey( 'classes', $payload );
		$this->assertArrayHasKey( 'style', $payload );
		$this->assertArrayHasKey( 'position', $payload );
		$this->assertStringContainsString( 'id="aa-badge-preview-el"', $payload['html'] );
		$this->assertStringContainsString( 'Sale', $payload['html'] );
		$this->assertStringContainsString( '--badge-bg:#123456', $payload['style'] );
	}

	/** An empty label never renders a blank badge. */
	public function test_compile_payload_defaults_label(): void {
		$payload = Badge_Studio_Rest::compile_payload( array(), '' );

		$this->assertStringContainsString( 'Badge', $payload['html'] );
	}

	/** Position is enum-checked before the client builds a class name from it. */
	public function test_compile_payload_clamps_position(): void {
		$payload = Badge_Studio_Rest::compile_payload(
			array( 'badge_position' => 'top-left" onload="alert(1)' ),
			'Sale'
		);

		$this->assertSame( 'top-left', $payload['position'] );
	}

	/** Icon-only badges get an accessible name instead of losing their label. */
	public function test_compile_payload_icon_only_sets_aria_label(): void {
		$payload = Badge_Studio_Rest::compile_payload(
			array(
				'badge_icon'          => '★',
				'badge_icon_position' => 'only',
			),
			'Clearance'
		);

		$this->assertStringContainsString( 'role="img"', $payload['html'] );
		$this->assertStringContainsString( 'aria-label="Clearance"', $payload['html'] );
	}

	/** Icon-only with no icon configured falls back to the label, never blank. */
	public function test_compile_payload_icon_only_without_icon_keeps_label(): void {
		$payload = Badge_Studio_Rest::compile_payload(
			array( 'badge_icon_position' => 'only' ),
			'Clearance'
		);

		$this->assertStringContainsString( 'Clearance', $payload['html'] );
		$this->assertStringNotContainsString( 'role="img"', $payload['html'] );
	}

	/** Trailing icons render after the label. */
	public function test_compile_payload_icon_end_order(): void {
		$payload = Badge_Studio_Rest::compile_payload(
			array(
				'badge_icon'          => '★',
				'badge_icon_position' => 'end',
			),
			'Sale'
		);

		$label_at = strpos( $payload['html'], 'Sale' );
		$icon_at  = strpos( $payload['html'], '★' );

		$this->assertIsInt( $label_at );
		$this->assertIsInt( $icon_at );
		$this->assertLessThan( $icon_at, $label_at );
	}

	/** Authored label text is escaped before it reaches the canvas. */
	public function test_compile_payload_escapes_label(): void {
		$payload = Badge_Studio_Rest::compile_payload(
			array(),
			'<script>alert(1)</script>'
		);

		$this->assertStringNotContainsString( '<script', $payload['html'] );
	}
}
