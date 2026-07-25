<?php
/**
 * Custom badge taxonomy — admin field rendering.
 *
 * Extracted from Custom_Badge_Taxonomy to keep each file under the length cap.
 * Composed via `use`; all callers are unchanged.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Badge_Admin_Fields {
	/**
	 * Render fields on the "Add New Badge" form.
	 *
	 * @return void
	 */
	public function render_add_fields(): void {
		echo '<div class="form-field aa-badge-editor-wrap">';
		self::render_editor_panel( self::get_default_badge_data() );
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
		echo '<div class="aa-badge-editor-wrap aa-badge-editor-wrap--edit">';
		self::render_editor_panel( $data );
		echo '</div>';
	}

	/**
	 * Render the modern badge editor panel shared by the add and edit screens.
	 *
	 * Field `name`/`id` attributes are identical to the legacy markup so the
	 * save handler and live-preview script are unaffected.
	 *
	 * @param array<string, mixed> $d Badge data (see get_badge_data()).
	 * @return void
	 */
	private static function render_editor_panel( array $d ): void {
		$icon_source = '' !== $d['svg_icon']
			? 'svg'
			: ( '' !== $d['library_icon'] ? 'library' : ( '' !== $d['icon'] ? 'emoji' : 'none' ) );
		$is_system   = 'custom' !== $d['badge_type'];

		$border_styles = array();
		foreach ( self::BORDER_STYLES as $style ) {
			$border_styles[ $style ] = ucfirst( $style );
		}

		$positions = array();
		foreach ( self::POSITIONS as $pos ) {
			$positions[ $pos ] = ucwords( str_replace( '-', ' ', $pos ) );
		}
		?>
		<div class="aa-badge-editor">
			<div class="aa-badge-editor__preview-col">
				<div class="aa-badge-editor__preview">
					<span class="aa-badge-editor__preview-label"><?php esc_html_e( 'Live Preview', 'aggressive-apparel' ); ?></span>
					<div class="aa-badge-editor__stage">
						<?php echo aggressive_apparel_trusted_html( self::build_preview_markup( $d ) ); ?>
					</div>
				</div>
			</div>

			<div class="aa-badge-editor__fields">
				<?php if ( $is_system ) : ?>
					<div class="aa-badge-systype">
						<span class="dashicons dashicons-superhero" aria-hidden="true"></span>
						<span>
							<?php
							printf(
								/* translators: %s: system badge type name. */
								esc_html__( 'System badge: %s — applied automatically by product conditions. Restyle it freely below.', 'aggressive-apparel' ),
								wp_kses_post( '<strong>' . esc_html( ucwords( str_replace( '_', ' ', $d['badge_type'] ) ) ) . '</strong>' )
							);
							?>
						</span>
					</div>
				<?php endif; ?>

				<fieldset class="aa-badge-section">
					<legend class="aa-badge-section__title"><span class="dashicons dashicons-art" aria-hidden="true"></span><?php esc_html_e( 'Colors', 'aggressive-apparel' ); ?></legend>
					<div class="aa-badge-row">
						<?php
						self::render_color_control( 'badge_bg_color', __( 'Background', 'aggressive-apparel' ), $d['bg_color'] );
						self::render_color_control( 'badge_text_color', __( 'Text', 'aggressive-apparel' ), $d['text_color'] );
						?>
					</div>
				</fieldset>

				<fieldset class="aa-badge-section">
					<legend class="aa-badge-section__title"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Icon', 'aggressive-apparel' ); ?></legend>
					<p class="aa-badge-section__note"><?php esc_html_e( 'Choose one icon source. It appears before the badge text.', 'aggressive-apparel' ); ?></p>

					<div class="aa-badge-icon-source" role="radiogroup" aria-label="<?php esc_attr_e( 'Icon source', 'aggressive-apparel' ); ?>">
						<?php
						$sources = array(
							'none'    => __( 'None', 'aggressive-apparel' ),
							'emoji'   => __( 'Emoji', 'aggressive-apparel' ),
							'library' => __( 'Library', 'aggressive-apparel' ),
							'svg'     => __( 'Custom SVG', 'aggressive-apparel' ),
						);
						foreach ( $sources as $val => $label ) {
							printf(
								'<label><input type="radio" name="aa_badge_icon_source" value="%1$s" %2$s /><span>%3$s</span></label>',
								esc_attr( $val ),
								checked( $icon_source, $val, false ),
								esc_html( $label )
							);
						}
						?>
					</div>

					<div class="aa-badge-row">
						<div class="aa-badge-control" data-icon-source="emoji">
							<label for="badge_icon"><?php esc_html_e( 'Emoji / Character', 'aggressive-apparel' ); ?></label>
							<input type="text" name="badge_icon" id="badge_icon" value="<?php echo esc_attr( $d['icon'] ); ?>" maxlength="10" />
						</div>
						<div class="aa-badge-control" data-icon-source="library">
							<label for="badge_library_icon"><?php esc_html_e( 'Library Icon', 'aggressive-apparel' ); ?></label>
							<?php self::render_library_icon_select( $d['library_icon'] ); ?>
						</div>
					</div>

					<div class="aa-badge-control" data-icon-source="svg">
						<label for="badge_svg_icon"><?php esc_html_e( 'Custom SVG markup', 'aggressive-apparel' ); ?></label>
						<textarea name="badge_svg_icon" id="badge_svg_icon" rows="4"><?php echo esc_textarea( $d['svg_icon'] ); ?></textarea>
						<p class="aa-badge-control__help"><?php esc_html_e( 'Paste raw SVG. Sanitized on save.', 'aggressive-apparel' ); ?></p>
					</div>

					<div class="aa-badge-row" data-icon-source="shared">
						<?php
						self::render_color_control( 'badge_icon_color', __( 'Icon Color', 'aggressive-apparel' ), $d['icon_color'], __( 'Empty = inherit text color.', 'aggressive-apparel' ) );
						self::render_number_control( 'badge_icon_size', __( 'Icon Size (px)', 'aggressive-apparel' ), $d['icon_size'], 0, 64, __( '0 = auto (matches text).', 'aggressive-apparel' ) );
						self::render_number_control( 'badge_icon_gap', __( 'Icon Spacing (px)', 'aggressive-apparel' ), $d['icon_gap'], 0, 40, __( 'Gap between icon and text.', 'aggressive-apparel' ) );
						?>
					</div>
				</fieldset>

				<fieldset class="aa-badge-section">
					<legend class="aa-badge-section__title"><span class="dashicons dashicons-editor-table" aria-hidden="true"></span><?php esc_html_e( 'Border', 'aggressive-apparel' ); ?></legend>
					<div class="aa-badge-row">
						<?php
						self::render_color_control( 'badge_border_color', __( 'Color', 'aggressive-apparel' ), $d['border_color'], __( 'Empty = no border.', 'aggressive-apparel' ) );
						self::render_number_control( 'badge_border_width', __( 'Width (px)', 'aggressive-apparel' ), $d['border_width'], 0, 10 );
						self::render_select_control( 'badge_border_style', __( 'Style', 'aggressive-apparel' ), $border_styles, $d['border_style'] );
						?>
					</div>
				</fieldset>

				<fieldset class="aa-badge-section">
					<legend class="aa-badge-section__title"><span class="dashicons dashicons-editor-expand" aria-hidden="true"></span><?php esc_html_e( 'Shape & Spacing', 'aggressive-apparel' ); ?></legend>
					<div class="aa-badge-row">
						<div class="aa-badge-control">
							<label><?php esc_html_e( 'Corner Radius (px)', 'aggressive-apparel' ); ?></label>
							<div class="aa-badge-mini-grid">
								<?php
								self::render_mini_number( 'badge_radius_tl', __( 'TL', 'aggressive-apparel' ), $d['radius_tl'], 0, 100 );
								self::render_mini_number( 'badge_radius_tr', __( 'TR', 'aggressive-apparel' ), $d['radius_tr'], 0, 100 );
								self::render_mini_number( 'badge_radius_br', __( 'BR', 'aggressive-apparel' ), $d['radius_br'], 0, 100 );
								self::render_mini_number( 'badge_radius_bl', __( 'BL', 'aggressive-apparel' ), $d['radius_bl'], 0, 100 );
								?>
							</div>
						</div>
						<div class="aa-badge-control">
							<label><?php esc_html_e( 'Padding (px)', 'aggressive-apparel' ); ?></label>
							<div class="aa-badge-mini-grid">
								<?php
								self::render_mini_number( 'badge_padding_x', __( 'X', 'aggressive-apparel' ), $d['padding_x'], 0, 50 );
								self::render_mini_number( 'badge_padding_y', __( 'Y', 'aggressive-apparel' ), $d['padding_y'], 0, 50 );
								?>
							</div>
						</div>
					</div>
				</fieldset>

				<fieldset class="aa-badge-section">
					<legend class="aa-badge-section__title"><span class="dashicons dashicons-location" aria-hidden="true"></span><?php esc_html_e( 'Placement', 'aggressive-apparel' ); ?></legend>
					<div class="aa-badge-row">
						<?php
						self::render_select_control( 'badge_position', __( 'Position', 'aggressive-apparel' ), $positions, $d['position'], __( 'Corner of the product image.', 'aggressive-apparel' ) );
						self::render_number_control( 'badge_priority', __( 'Priority', 'aggressive-apparel' ), $d['priority'], 0, 100, __( 'Lower shows first.', 'aggressive-apparel' ) );
						?>
					</div>
				</fieldset>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a labeled colour-picker control.
	 *
	 * @param string $name  Field name/id.
	 * @param string $label Visible label.
	 * @param string $value Current value.
	 * @param string $help  Optional helper text.
	 * @return void
	 */
	private static function render_color_control( string $name, string $label, string $value, string $help = '' ): void {
		printf(
			'<div class="aa-badge-control"><label for="%1$s">%2$s</label><input type="text" name="%1$s" id="%1$s" class="aa-badge-color-picker" value="%3$s" />%4$s</div>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $value ),
			wp_kses_post( '' !== $help ? '<p class="aa-badge-control__help">' . esc_html( $help ) . '</p>' : '' )
		);
	}

	/**
	 * Render a labeled number control.
	 *
	 * @param string     $name  Field name/id.
	 * @param string     $label Visible label.
	 * @param int|string $value Current value.
	 * @param int        $min   Minimum.
	 * @param int        $max   Maximum.
	 * @param string     $help  Optional helper text.
	 * @return void
	 */
	private static function render_number_control( string $name, string $label, $value, int $min, int $max, string $help = '' ): void {
		printf(
			'<div class="aa-badge-control"><label for="%1$s">%2$s</label><input type="number" name="%1$s" id="%1$s" value="%3$s" min="%4$d" max="%5$d" step="1" />%6$s</div>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( (string) $value ),
			(int) $min,
			(int) $max,
			wp_kses_post( '' !== $help ? '<p class="aa-badge-control__help">' . esc_html( $help ) . '</p>' : '' )
		);
	}

	/**
	 * Render a compact number input for the radius/padding mini-grids.
	 *
	 * @param string     $name  Field name/id.
	 * @param string     $label Short label (e.g. "TL").
	 * @param int|string $value Current value.
	 * @param int        $min   Minimum.
	 * @param int        $max   Maximum.
	 * @return void
	 */
	private static function render_mini_number( string $name, string $label, $value, int $min, int $max ): void {
		printf(
			'<label class="aa-badge-mini">%2$s<input type="number" name="%1$s" id="%1$s" value="%3$s" min="%4$d" max="%5$d" step="1" /></label>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( (string) $value ),
			(int) $min,
			(int) $max
		);
	}

	/**
	 * Render a labeled select control.
	 *
	 * @param string                $name     Field name/id.
	 * @param string                $label    Visible label.
	 * @param array<string, string> $options  Value => label map.
	 * @param string                $selected Currently selected value.
	 * @param string                $help     Optional helper text.
	 * @return void
	 */
	private static function render_select_control( string $name, string $label, array $options, string $selected, string $help = '' ): void {
		$opts = '';
		foreach ( $options as $value => $text ) {
			$opts .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $value ),
				selected( $selected, (string) $value, false ),
				esc_html( $text )
			);
		}

		printf(
			'<div class="aa-badge-control"><label for="%1$s">%2$s</label><select name="%1$s" id="%1$s">%3$s</select>%4$s</div>',
			esc_attr( $name ),
			esc_html( $label ),
			wp_kses_post( $opts ),
			wp_kses_post( '' !== $help ? '<p class="aa-badge-control__help">' . esc_html( $help ) . '</p>' : '' )
		);
	}

	/**
	 * Build the live-preview badge span markup (id #aa-badge-preview-el).
	 *
	 * @param array<string, mixed> $d Badge data, optionally including 'name'.
	 * @return string Escaped badge markup.
	 */
	private static function build_preview_markup( array $d ): string {
		$name      = isset( $d['name'] ) && '' !== $d['name'] ? (string) $d['name'] : __( 'Badge Name', 'aggressive-apparel' );
		$icon_html = self::build_badge_icon_html( $d['svg_icon'], $d['library_icon'], $d['icon'], $d['icon_color'], (int) $d['icon_size'], (int) $d['icon_gap'] );

		return self::build_static_badge_span( $d, $icon_html . esc_html( $name ), 'aa-badge-preview-el' );
	}

	/**
	 * Build a self-styled badge <span> for admin contexts (no front-end CSS).
	 *
	 * Used by the editor live preview and the term list-table column. Colours,
	 * border, radius and padding are written as literal inline styles. The
	 * front-end renderer instead emits `--badge-*` custom properties (see
	 * Product_Badges::build_badge_span()) because it relies on product-badges.css.
	 *
	 * @param array<string, mixed> $d     Badge data (see get_badge_data()).
	 * @param string               $label Pre-escaped icon + text markup.
	 * @param string               $id    Optional element id.
	 * @return string Badge markup; `$label` must already be escaped.
	 */
	private static function build_static_badge_span( array $d, string $label, string $id = '' ): string {
		$border = $d['border_width'] > 0 && '' !== $d['border_color'] && 'none' !== $d['border_style']
			? sprintf( 'border:%dpx %s %s;', $d['border_width'], $d['border_style'], $d['border_color'] )
			: '';

		$style = sprintf(
			'display:inline-flex;align-items:center;gap:0.25em;padding:%1$dpx %2$dpx;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background-color:%3$s;color:%4$s;%5$sborder-radius:%6$dpx %7$dpx %8$dpx %9$dpx;',
			(int) $d['padding_y'],
			(int) $d['padding_x'],
			$d['bg_color'],
			$d['text_color'],
			$border,
			(int) $d['radius_tl'],
			(int) $d['radius_tr'],
			(int) $d['radius_br'],
			(int) $d['radius_bl']
		);

		return sprintf(
			'<span%1$s style="%2$s">%3$s</span>',
			'' !== $id ? ' id="' . esc_attr( $id ) . '"' : '',
			esc_attr( $style ),
			aggressive_apparel_trusted_html( $label )
		);
	}
}
