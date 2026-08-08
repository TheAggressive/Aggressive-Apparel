<?php
/**
 * Custom badge taxonomy — React studio mount + save-bridge fields.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the badge studio root and hidden POST fields for taxonomy save.
 */
trait Badge_Admin_Fields {
	/**
	 * Render fields on the "Add New Badge" form.
	 *
	 * @return void
	 */
	public function render_add_fields(): void {
		echo '<div class="aa-badge-studio-shell aa-badge-studio-shell--add">';
		self::render_studio_mount( self::get_default_badge_data(), 'add' );
		echo '</div>';
	}

	/**
	 * Render fields on the "Edit Badge" form.
	 *
	 * @param \WP_Term $term Current term object.
	 * @return void
	 */
	public function render_edit_fields( \WP_Term $term ): void {
		$data         = self::get_badge_data( $term->term_id );
		$data['name'] = $term->name;
		echo '<div class="aa-badge-studio-shell aa-badge-studio-shell--edit">';
		self::render_studio_mount( $data, 'edit' );
		echo '</div>';
	}

	/**
	 * Mount node + hidden inputs the React studio syncs into.
	 *
	 * @param array<string, mixed> $d      Badge data (see get_badge_data()).
	 * @param string               $screen `add` or `edit`.
	 * @return void
	 */
	private static function render_studio_mount( array $d, string $screen = 'edit' ): void {
		$screen      = 'add' === $screen ? 'add' : 'edit';
		$fields      = self::badge_data_to_studio_fields( $d );
		$label       = isset( $d['name'] ) ? (string) $d['name'] : '';
		$sale_sample = 'sale' === ( $d['badge_type'] ?? '' )
			? Sale_Pricing::format_text(
				Feature_Settings::get_sale_badge_text(),
				Feature_Settings::get_sale_badge_no_discount_text(),
				25
			)
			: '';

		$config = array(
			'fields'     => $fields,
			'label'      => $label,
			'badgeType'  => (string) ( $d['badge_type'] ?? 'custom' ),
			'screen'     => $screen,
			'saleSample' => $sale_sample,
			'shapes'     => Badge_Shapes::labels(),
			'icons'      => Icons::list(),
			'presets'    => self::studio_preset_labels(),
			'palette'    => Badge_Palette::swatches(),
			'restUrl'    => esc_url_raw( rest_url( 'aggressive-apparel/v1/badge-studio/compile' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			// First paint comes from the same compiler the REST route uses, so
			// the preview is correct before the first request resolves.
			'compiled'   => Badge_Studio_Rest::compile_payload(
				$fields,
				'' !== $sale_sample ? $sale_sample : $label
			),
			'i18n'       => self::studio_i18n(),
		);

		printf(
			'<div id="aa-badge-studio-root" class="aa-badge-studio" data-aa-badge-studio="%s"></div>',
			esc_attr( (string) wp_json_encode( $config ) )
		);

		echo '<div id="aa-badge-studio-fields" class="aa-badge-studio__fields" hidden>';
		foreach ( $fields as $name => $value ) {
			if ( 'badge_shape_svg' === $name || 'badge_svg_icon' === $name ) {
				printf(
					'<textarea name="%1$s" id="%1$s">%2$s</textarea>',
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				continue;
			}

			printf(
				'<input type="hidden" name="%1$s" id="%1$s" value="%2$s" />',
				esc_attr( $name ),
				esc_attr( (string) $value )
			);
		}
		echo '</div>';
	}

	/**
	 * Flat `badge_*` field map for the studio + taxonomy POST save.
	 *
	 * @param array<string, mixed> $d Badge data.
	 * @return array<string, string>
	 */
	private static function badge_data_to_studio_fields( array $d ): array {
		$d = Badge_Style_Schema::with_defaults( $d );

		return array(
			'badge_bg_color'           => (string) $d['bg_color'],
			'badge_text_color'         => (string) $d['text_color'],
			'badge_icon'               => (string) $d['icon'],
			'badge_library_icon'       => (string) $d['library_icon'],
			'badge_svg_icon'           => (string) $d['svg_icon'],
			'badge_icon_color'         => (string) $d['icon_color'],
			'badge_icon_size'          => (string) (int) $d['icon_size'],
			'badge_icon_gap'           => (string) (int) $d['icon_gap'],
			'badge_priority'           => (string) (int) $d['priority'],
			'badge_border_color'       => (string) $d['border_color'],
			'badge_border_width'       => (string) (int) $d['border_width'],
			'badge_border_style'       => (string) $d['border_style'],
			'badge_radius_tl'          => (string) (int) $d['radius_tl'],
			'badge_radius_tr'          => (string) (int) $d['radius_tr'],
			'badge_radius_br'          => (string) (int) $d['radius_br'],
			'badge_radius_bl'          => (string) (int) $d['radius_bl'],
			'badge_padding_x'          => (string) (int) $d['padding_x'],
			'badge_padding_y'          => (string) (int) $d['padding_y'],
			'badge_position'           => (string) $d['position'],
			'badge_type'               => (string) $d['badge_type'],
			'badge_border_mode'        => (string) $d['border_mode'],
			'badge_inner_border_color' => (string) $d['inner_border_color'],
			'badge_inner_border_width' => (string) (int) $d['inner_border_width'],
			'badge_border_gap'         => (string) (int) $d['border_gap'],
			'badge_font_size'          => (string) $d['font_size'],
			'badge_font_size_px'       => (string) (int) $d['font_size_px'],
			'badge_font_weight'        => (string) (int) $d['font_weight'],
			'badge_text_transform'     => (string) $d['text_transform'],
			'badge_letter_spacing'     => (string) $d['letter_spacing'],
			'badge_line_height'        => (string) $d['line_height'],
			'badge_icon_position'      => (string) $d['icon_position'],
			'badge_offset_x'           => (string) (int) $d['offset_x'],
			'badge_offset_y'           => (string) (int) $d['offset_y'],
			'badge_rotation'           => (string) (int) $d['rotation'],
			'badge_shadow_blur'        => (string) (int) $d['shadow_blur'],
			'badge_shadow_spread'      => (string) (int) $d['shadow_spread'],
			'badge_shadow_color'       => (string) $d['shadow_color'],
			'badge_glass'              => (int) $d['glass'] ? '1' : '0',
			'badge_fill_mode'          => (string) $d['fill_mode'],
			'badge_gradient_angle'     => (string) (int) $d['gradient_angle'],
			'badge_gradient_from'      => (string) $d['gradient_from'],
			'badge_gradient_to'        => (string) $d['gradient_to'],
			'badge_shape'              => (string) $d['shape'],
			'badge_shape_mode'         => (string) $d['shape_mode'],
			'badge_shape_svg'          => (string) $d['shape_svg'],
			'badge_frame_color'        => (string) $d['frame_color'],
			'badge_frame_width'        => (string) (int) $d['frame_width'],
		);
	}

	/**
	 * Preset chip labels for the studio library.
	 *
	 * @return array<string, string>
	 */
	private static function studio_preset_labels(): array {
		return array(
			'solid'    => __( 'Solid', 'aggressive-apparel' ),
			'outline'  => __( 'Outline', 'aggressive-apparel' ),
			'layered'  => __( 'Layered', 'aggressive-apparel' ),
			'pill'     => __( 'Pill', 'aggressive-apparel' ),
			'minimal'  => __( 'Minimal', 'aggressive-apparel' ),
			'glass'    => __( 'Glass', 'aggressive-apparel' ),
			'shadow'   => __( 'Soft shadow', 'aggressive-apparel' ),
			'gradient' => __( 'Gradient blaze', 'aggressive-apparel' ),
			'ticket'   => __( 'Ticket stub', 'aggressive-apparel' ),
			'ribbon'   => __( 'Ribbon corner', 'aggressive-apparel' ),
			'stamp'    => __( 'Stamp', 'aggressive-apparel' ),
			'neon'     => __( 'Neon outline', 'aggressive-apparel' ),
		);
	}

	/**
	 * Client-facing strings.
	 *
	 * @return array<string, string>
	 */
	private static function studio_i18n(): array {
		return array(
			'title'         => __( 'Badge Studio', 'aggressive-apparel' ),
			'styles'        => __( 'Styles', 'aggressive-apparel' ),
			'shapes'        => __( 'Shapes', 'aggressive-apparel' ),
			'templates'     => __( 'Templates', 'aggressive-apparel' ),
			'searchLibrary' => __( 'Search styles, shapes…', 'aggressive-apparel' ),
			'inspector'     => __( 'Properties', 'aggressive-apparel' ),
			'canvas'        => __( 'Badge Preview', 'aggressive-apparel' ),
			'fill'          => __( 'Fill', 'aggressive-apparel' ),
			'border'        => __( 'Border', 'aggressive-apparel' ),
			'type'          => __( 'Type', 'aggressive-apparel' ),
			'icon'          => __( 'Icon', 'aggressive-apparel' ),
			'layout'        => __( 'Layout', 'aggressive-apparel' ),
			'light'         => __( 'Light', 'aggressive-apparel' ),
			'dark'          => __( 'Dark', 'aggressive-apparel' ),
			'appearance'    => __( 'Preview backdrop', 'aggressive-apparel' ),
			'solidFill'     => __( 'Solid', 'aggressive-apparel' ),
			'gradientFill'  => __( 'Gradient', 'aggressive-apparel' ),
			'glassEffect'   => __( 'Glass blur', 'aggressive-apparel' ),
			'customSvg'     => __( 'Custom SVG', 'aggressive-apparel' ),
			'maskMode'      => __( 'Filled silhouette', 'aggressive-apparel' ),
			'frameMode'     => __( 'Outline frame', 'aggressive-apparel' ),
			'systemLocked'  => __( 'System badge — style only. Label comes from store rules.', 'aggressive-apparel' ),
			/* translators: %s: text/background contrast ratio, e.g. 4.7. */
			'contrastPass'  => __( 'Contrast %s:1 — passes AA for small text.', 'aggressive-apparel' ),
			/* translators: %s: text/background contrast ratio, e.g. 2.1. */
			'contrastFail'  => __( 'Contrast %s:1 — increase contrast.', 'aggressive-apparel' ),
			'contrastAlpha' => __( 'Transparency enabled — final contrast depends on the product image.', 'aggressive-apparel' ),
			'none'          => __( 'None', 'aggressive-apparel' ),
			'transparent'   => __( 'Transparent', 'aggressive-apparel' ),
			'chooseColor'   => __( 'choose color', 'aggressive-apparel' ),
			'emoji'         => __( 'Emoji', 'aggressive-apparel' ),
			'library'       => __( 'Library', 'aggressive-apparel' ),
			'svg'           => __( 'SVG', 'aggressive-apparel' ),
			'saved'         => __( 'Saved', 'aggressive-apparel' ),
			'unsaved'       => __( 'Unsaved', 'aggressive-apparel' ),
			'preview'       => __( 'Preview', 'aggressive-apparel' ),
			'update'        => __( 'Update', 'aggressive-apparel' ),
			'addNew'        => __( 'Add New Badge', 'aggressive-apparel' ),
			'undo'          => __( 'Undo', 'aggressive-apparel' ),
			'redo'          => __( 'Redo', 'aggressive-apparel' ),
			'zoomIn'        => __( 'Zoom in', 'aggressive-apparel' ),
			'zoomOut'       => __( 'Zoom out', 'aggressive-apparel' ),
		);
	}
}
