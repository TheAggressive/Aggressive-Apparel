<?php
/**
 * Custom Badge Taxonomy
 *
 * Registers a custom taxonomy for product badges so store owners can
 * create reusable, styled badges and assign them to products.
 *
 * Each badge term stores visual properties (colors, icon, priority)
 * as term meta. Rendering is handled by the Product_Badges class.
 *
 * @package Aggressive_Apparel
 * @since 1.54.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Badge Taxonomy
 *
 * @since 1.54.0
 */
class Custom_Badge_Taxonomy {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	public const TAXONOMY = 'aa_product_badge';

	/**
	 * Term meta keys.
	 */
	private const META_BG_COLOR     = 'badge_bg_color';
	private const META_TEXT_COLOR   = 'badge_text_color';
	private const META_ICON         = 'badge_icon';
	private const META_PRIORITY     = 'badge_priority';
	private const META_BORDER_COLOR = 'badge_border_color';
	private const META_BORDER_WIDTH = 'badge_border_width';
	private const META_BORDER_STYLE = 'badge_border_style';
	private const META_RADIUS_TL    = 'badge_radius_tl';
	private const META_RADIUS_TR    = 'badge_radius_tr';
	private const META_RADIUS_BR    = 'badge_radius_br';
	private const META_RADIUS_BL    = 'badge_radius_bl';
	private const META_PADDING_X    = 'badge_padding_x';
	private const META_PADDING_Y    = 'badge_padding_y';
	private const META_LIBRARY_ICON = 'badge_library_icon';
	private const META_SVG_ICON     = 'badge_svg_icon';
	private const META_ICON_COLOR   = 'badge_icon_color';
	private const META_ICON_SIZE    = 'badge_icon_size';
	private const META_ICON_GAP     = 'badge_icon_gap';
	private const META_POSITION     = 'badge_position';
	private const META_BADGE_TYPE   = 'badge_type';

	/**
	 * Allowed border styles.
	 *
	 * @var string[]
	 */
	private const BORDER_STYLES = array( 'none', 'solid', 'dashed', 'dotted', 'double' );

	/**
	 * Allowed badge positions.
	 *
	 * @var string[]
	 */
	private const POSITIONS = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );

	/**
	 * Allowed badge types.
	 *
	 * @var string[]
	 */
	private const BADGE_TYPES = array( 'custom', 'sale', 'new', 'low_stock', 'bestseller' );

	/**
	 * Option key for tracking system badge seed version.
	 *
	 * @var string
	 */
	private const SEED_VERSION_OPTION = 'aggressive_apparel_system_badges_version';

	/**
	 * Current seed version. Bump to re-seed new system badge types.
	 *
	 * @var string
	 */
	private const SEED_VERSION = '1.0.0';

	/**
	 * Cached system badge data, keyed by badge_type.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $system_badges_cache = null;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_term_meta_hooks' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}
	}

	/**
	 * Register the aa_product_badge taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( 'product' ),
			array(
				'labels'            => self::get_labels(),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'show_tagcloud'     => false,
				'rewrite'           => false,
			),
		);

		self::register_term_meta();
	}

	/**
	 * Register term meta fields with types and sanitize callbacks.
	 *
	 * Ensures meta is visible in the REST API and properly sanitized
	 * on write regardless of how it's saved (admin form or REST).
	 *
	 * @return void
	 */
	private static function register_term_meta(): void {
		$color_meta = array(
			self::META_BG_COLOR,
			self::META_TEXT_COLOR,
			self::META_BORDER_COLOR,
			self::META_ICON_COLOR,
		);

		foreach ( $color_meta as $key ) {
			register_meta(
				'term',
				$key,
				array(
					'object_subtype'    => self::TAXONOMY,
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_hex_color',
				),
			);
		}

		$integer_meta = array(
			self::META_PRIORITY     => array(
				'default' => 10,
				'max'     => 100,
			),
			self::META_BORDER_WIDTH => array(
				'default' => 0,
				'max'     => 10,
			),
			self::META_RADIUS_TL    => array(
				'default' => 4,
				'max'     => 100,
			),
			self::META_RADIUS_TR    => array(
				'default' => 4,
				'max'     => 100,
			),
			self::META_RADIUS_BR    => array(
				'default' => 4,
				'max'     => 100,
			),
			self::META_RADIUS_BL    => array(
				'default' => 4,
				'max'     => 100,
			),
			self::META_PADDING_X    => array(
				'default' => 8,
				'max'     => 50,
			),
			self::META_PADDING_Y    => array(
				'default' => 3,
				'max'     => 50,
			),
			self::META_ICON_SIZE    => array(
				'default' => 0,
				'max'     => 64,
			),
			self::META_ICON_GAP     => array(
				'default' => 0,
				'max'     => 40,
			),
		);

		foreach ( $integer_meta as $key => $opts ) {
			$max = $opts['max'];
			register_meta(
				'term',
				$key,
				array(
					'object_subtype'    => self::TAXONOMY,
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'default'           => $opts['default'],
					'sanitize_callback' => static fn( $value ) => min( absint( $value ), $max ),
				),
			);
		}

		// Emoji icon — text field.
		register_meta(
			'term',
			self::META_ICON,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => static fn( $value ) => mb_substr( sanitize_text_field( $value ), 0, 10 ),
			),
		);

		// Library icon — validated against Icons::list().
		register_meta(
			'term',
			self::META_LIBRARY_ICON,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => static function ( $value ): string {
					$value = sanitize_text_field( $value );
					return '' !== $value && Icons::exists( $value ) ? $value : '';
				},
			),
		);

		// Custom SVG — wp_kses sanitized.
		register_meta(
			'term',
			self::META_SVG_ICON,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize_svg' ),
			),
		);

		// Border style — whitelist.
		register_meta(
			'term',
			self::META_BORDER_STYLE,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 'none',
				'sanitize_callback' => static function ( $value ): string {
					$value = sanitize_text_field( $value );
					return in_array( $value, self::BORDER_STYLES, true ) ? $value : 'none';
				},
			),
		);

		// Badge position — whitelist.
		register_meta(
			'term',
			self::META_POSITION,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 'top-left',
				'sanitize_callback' => static function ( $value ): string {
					$value = sanitize_text_field( $value );
					return in_array( $value, self::POSITIONS, true ) ? $value : 'top-left';
				},
			),
		);

		// Badge type — whitelist (custom, sale, new, low_stock, bestseller).
		register_meta(
			'term',
			self::META_BADGE_TYPE,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 'custom',
				'sanitize_callback' => static function ( $value ): string {
					$value = sanitize_text_field( $value );
					return in_array( $value, self::BADGE_TYPES, true ) ? $value : 'custom';
				},
			),
		);
	}

	/**
	 * Register term meta form hooks.
	 *
	 * @return void
	 */
	public function register_term_meta_hooks(): void {
		$tax = self::TAXONOMY;

		add_action( $tax . '_add_form_fields', array( $this, 'render_add_fields' ) );
		// `_edit_form` (not `_edit_form_fields`) renders after the core form-table
		// at full content width — the panel needs the room for its two-pane
		// layout, and a container query on a table-cell collapses its width.
		add_action( $tax . '_edit_form', array( $this, 'render_edit_fields' ), 10, 1 );
		add_action( 'created_' . $tax, array( $this, 'save_fields' ), 10, 1 );
		add_action( 'edited_' . $tax, array( $this, 'save_fields' ), 10, 1 );

		add_filter( 'manage_edit-' . $tax . '_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . $tax . '_custom_column', array( $this, 'render_column' ), 10, 3 );
	}

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
						<?php echo self::build_preview_markup( $d ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
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
			$label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller passes escaped markup.
		);
	}

	/**
	 * Default badge data for the "Add New Badge" screen.
	 *
	 * Mirrors the fallbacks used in get_badge_data().
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_badge_data(): array {
		return array(
			'bg_color'     => '#000000',
			'text_color'   => '#ffffff',
			'icon'         => '',
			'library_icon' => '',
			'svg_icon'     => '',
			'icon_color'   => '',
			'icon_size'    => 0,
			'icon_gap'     => 0,
			'priority'     => 10,
			'border_color' => '',
			'border_width' => 0,
			'border_style' => 'none',
			'radius_tl'    => 4,
			'radius_tr'    => 4,
			'radius_br'    => 4,
			'radius_bl'    => 4,
			'padding_x'    => 8,
			'padding_y'    => 3,
			'position'     => 'top-left',
			'badge_type'   => 'custom',
		);
	}

	/**
	 * Save term meta fields.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_fields( int $term_id ): void {
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'add-tag' ) && ! wp_verify_nonce( $nonce, 'update-tag_' . $term_id ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Background color.
		$bg_color = isset( $_POST['badge_bg_color'] )
			? sanitize_hex_color( wp_unslash( $_POST['badge_bg_color'] ) )
			: '';
		update_term_meta( $term_id, self::META_BG_COLOR, '' !== $bg_color ? $bg_color : '#000000' );

		// Text color.
		$text_color = isset( $_POST['badge_text_color'] )
			? sanitize_hex_color( wp_unslash( $_POST['badge_text_color'] ) )
			: '';
		update_term_meta( $term_id, self::META_TEXT_COLOR, '' !== $text_color ? $text_color : '#ffffff' );

		// Icon (emoji).
		$icon = isset( $_POST['badge_icon'] )
			? sanitize_text_field( wp_unslash( $_POST['badge_icon'] ) )
			: '';
		update_term_meta( $term_id, self::META_ICON, mb_substr( $icon, 0, 10 ) );

		// Library icon.
		$library_icon = isset( $_POST['badge_library_icon'] )
			? sanitize_text_field( wp_unslash( $_POST['badge_library_icon'] ) )
			: '';
		if ( '' !== $library_icon && ! Icons::exists( $library_icon ) ) {
			$library_icon = '';
		}
		update_term_meta( $term_id, self::META_LIBRARY_ICON, $library_icon );

		// Custom SVG icon.
		$svg_icon = isset( $_POST['badge_svg_icon'] )
			? self::sanitize_svg( wp_unslash( $_POST['badge_svg_icon'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in method.
			: '';
		update_term_meta( $term_id, self::META_SVG_ICON, $svg_icon );

		// Icon color.
		$icon_color = isset( $_POST['badge_icon_color'] )
			? sanitize_hex_color( wp_unslash( $_POST['badge_icon_color'] ) )
			: '';
		update_term_meta( $term_id, self::META_ICON_COLOR, '' !== $icon_color ? $icon_color : '' );

		// Icon size.
		$icon_size = isset( $_POST['badge_icon_size'] ) ? absint( $_POST['badge_icon_size'] ) : 0;
		update_term_meta( $term_id, self::META_ICON_SIZE, min( $icon_size, 64 ) );

		// Icon spacing (gap between icon and text).
		$icon_gap = isset( $_POST['badge_icon_gap'] ) ? absint( $_POST['badge_icon_gap'] ) : 0;
		update_term_meta( $term_id, self::META_ICON_GAP, min( $icon_gap, 40 ) );

		// Border color.
		$border_color = isset( $_POST['badge_border_color'] )
			? sanitize_hex_color( wp_unslash( $_POST['badge_border_color'] ) )
			: '';
		update_term_meta( $term_id, self::META_BORDER_COLOR, '' !== $border_color ? $border_color : '' );

		// Border width.
		$border_width = isset( $_POST['badge_border_width'] )
			? absint( $_POST['badge_border_width'] )
			: 0;
		update_term_meta( $term_id, self::META_BORDER_WIDTH, min( $border_width, 10 ) );

		// Border style.
		$border_style = isset( $_POST['badge_border_style'] )
			? sanitize_text_field( wp_unslash( $_POST['badge_border_style'] ) )
			: 'none';
		if ( ! in_array( $border_style, self::BORDER_STYLES, true ) ) {
			$border_style = 'none';
		}
		update_term_meta( $term_id, self::META_BORDER_STYLE, $border_style );

		// Border radius (per corner).
		foreach ( array( 'tl', 'tr', 'br', 'bl' ) as $corner ) {
			$key   = 'badge_radius_' . $corner;
			$value = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 4;
			update_term_meta( $term_id, $key, min( $value, 100 ) );
		}

		// Padding.
		$padding_x = isset( $_POST['badge_padding_x'] ) ? absint( $_POST['badge_padding_x'] ) : 8;
		$padding_y = isset( $_POST['badge_padding_y'] ) ? absint( $_POST['badge_padding_y'] ) : 3;
		update_term_meta( $term_id, self::META_PADDING_X, min( $padding_x, 50 ) );
		update_term_meta( $term_id, self::META_PADDING_Y, min( $padding_y, 50 ) );

		// Priority.
		$priority = isset( $_POST['badge_priority'] )
			? absint( $_POST['badge_priority'] )
			: 10;
		update_term_meta( $term_id, self::META_PRIORITY, min( $priority, 100 ) );

		// Position.
		$position = isset( $_POST['badge_position'] )
			? sanitize_text_field( wp_unslash( $_POST['badge_position'] ) )
			: 'top-left';
		if ( ! in_array( $position, self::POSITIONS, true ) ) {
			$position = 'top-left';
		}
		update_term_meta( $term_id, self::META_POSITION, $position );
	}

	/**
	 * Add a "Preview" column to the badge list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;
			if ( 'name' === $key ) {
				$new['badge_preview'] = __( 'Preview', 'aggressive-apparel' );
				$new['badge_type']    = __( 'Type', 'aggressive-apparel' );
			}
		}
		return $new;
	}

	/**
	 * Render the badge preview column content.
	 *
	 * @param string $value      Column value (unused for custom columns).
	 * @param string $column_name Column name.
	 * @param string $term_id    Term ID as string.
	 * @return void
	 */
	public function render_column( string $value, string $column_name, string $term_id ): void {
		if ( 'badge_preview' !== $column_name && 'badge_type' !== $column_name ) {
			return;
		}

		$tid  = (int) $term_id;
		$term = get_term( $tid, self::TAXONOMY );

		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$data = self::get_badge_data( $tid );

		if ( 'badge_type' === $column_name ) {
			$type_label = 'custom' !== $data['badge_type']
				? ucwords( str_replace( '_', ' ', $data['badge_type'] ) )
				: __( 'Custom', 'aggressive-apparel' );
			printf(
				'<span style="display:inline-flex;align-items:center;gap:0.25em;font-size:0.8em;color:%s;">%s%s</span>',
				'custom' !== $data['badge_type'] ? '#6366f1' : '#6b7280',
				'custom' !== $data['badge_type'] ? '<span class="dashicons dashicons-admin-generic" style="font-size:14px;width:14px;height:14px;"></span>' : '',
				esc_html( $type_label ),
			);
			return;
		}

		$icon_html = self::build_badge_icon_html( $data['svg_icon'], $data['library_icon'], $data['icon'], $data['icon_color'], $data['icon_size'], $data['icon_gap'] );

		echo self::build_static_badge_span( $data, $icon_html . esc_html( $term->name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
	}

	/**
	 * Enqueue color picker scripts on badge taxonomy admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}

		$current_taxonomy = isset( $_GET['taxonomy'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		if ( self::TAXONOMY !== $current_taxonomy ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

		// Modern badge-editor styles (also carries the icon-sizing rule the
		// front-end bundle isn't loaded for in wp-admin).
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/admin/badge-admin.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-badge-admin',
				AGGRESSIVE_APPAREL_URI . '/build/styles/admin/badge-admin.css',
				array( 'wp-color-picker', 'dashicons' ),
				(string) filemtime( $css_file )
			);
		}

		// Enqueue the compiled badge preview admin script following the theme's
		// admin asset convention (filemtime cache-busting, build/ output).
		\Aggressive_Apparel\Assets\Asset_Loader::enqueue_admin_script(
			'aggressive-apparel-badge-preview-admin',
			'build/scripts/admin/woocommerce/badge-preview-admin',
			array( 'wp-color-picker' )
		);
	}

	/**
	 * Get custom badges assigned to a product, sorted by priority.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array{name: string, bg_color: string, text_color: string, icon: string, library_icon: string, svg_icon: string, icon_color: string, icon_size: int, icon_gap: int, priority: int, border_color: string, border_width: int, border_style: string, radius_tl: int, radius_tr: int, radius_br: int, radius_bl: int, padding_x: int, padding_y: int, position: string, badge_type: string}>
	 */
	public static function get_product_badges( int $product_id ): array {
		$terms = get_the_terms( $product_id, self::TAXONOMY );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$badges = array();
		foreach ( $terms as $term ) {
			$badge         = self::get_badge_data( $term->term_id );
			$badge['name'] = $term->name;
			$badges[]      = $badge;
		}

		usort( $badges, fn( array $a, array $b ): int => $a['priority'] <=> $b['priority'] );

		return $badges;
	}

	/**
	 * Get term meta with a default fallback.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private static function get_meta( int $term_id, string $key, string $fallback ): string {
		$value = get_term_meta( $term_id, $key, true );

		return is_string( $value ) && '' !== $value ? $value : $fallback;
	}

	/**
	 * Get all badge visual data for a term.
	 *
	 * Centralises the 17 get_meta() calls used by render_edit_fields(),
	 * render_column(), and get_product_badges().
	 *
	 * @param int $term_id Term ID.
	 * @return array{bg_color: string, text_color: string, icon: string, library_icon: string, svg_icon: string, icon_color: string, icon_size: int, icon_gap: int, priority: int, border_color: string, border_width: int, border_style: string, radius_tl: int, radius_tr: int, radius_br: int, radius_bl: int, padding_x: int, padding_y: int, position: string, badge_type: string}
	 */
	private static function get_badge_data( int $term_id ): array {
		return array(
			'bg_color'     => self::get_meta( $term_id, self::META_BG_COLOR, '#000000' ),
			'text_color'   => self::get_meta( $term_id, self::META_TEXT_COLOR, '#ffffff' ),
			'icon'         => self::get_meta( $term_id, self::META_ICON, '' ),
			'library_icon' => self::get_meta( $term_id, self::META_LIBRARY_ICON, '' ),
			'svg_icon'     => self::get_meta( $term_id, self::META_SVG_ICON, '' ),
			'icon_color'   => self::get_meta( $term_id, self::META_ICON_COLOR, '' ),
			'icon_size'    => (int) self::get_meta( $term_id, self::META_ICON_SIZE, '0' ),
			'icon_gap'     => (int) self::get_meta( $term_id, self::META_ICON_GAP, '0' ),
			'priority'     => (int) self::get_meta( $term_id, self::META_PRIORITY, '10' ),
			'border_color' => self::get_meta( $term_id, self::META_BORDER_COLOR, '' ),
			'border_width' => (int) self::get_meta( $term_id, self::META_BORDER_WIDTH, '0' ),
			'border_style' => self::get_meta( $term_id, self::META_BORDER_STYLE, 'none' ),
			'radius_tl'    => (int) self::get_meta( $term_id, self::META_RADIUS_TL, '4' ),
			'radius_tr'    => (int) self::get_meta( $term_id, self::META_RADIUS_TR, '4' ),
			'radius_br'    => (int) self::get_meta( $term_id, self::META_RADIUS_BR, '4' ),
			'radius_bl'    => (int) self::get_meta( $term_id, self::META_RADIUS_BL, '4' ),
			'padding_x'    => (int) self::get_meta( $term_id, self::META_PADDING_X, '8' ),
			'padding_y'    => (int) self::get_meta( $term_id, self::META_PADDING_Y, '3' ),
			'position'     => self::get_meta( $term_id, self::META_POSITION, 'top-left' ),
			'badge_type'   => self::get_meta( $term_id, self::META_BADGE_TYPE, 'custom' ),
		);
	}

	/**
	 * Sanitize SVG markup using wp_kses with allowed SVG elements.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string Sanitized SVG.
	 */
	public static function sanitize_svg( string $svg ): string {
		$allowed = array(
			'svg'      => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'class'       => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
			),
			'path'     => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'fill-rule'       => true,
				'clip-rule'       => true,
			),
			'circle'   => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'rect'     => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'line'     => array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polyline' => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polygon'  => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'g'        => array(
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
			),
			'defs'     => array(),
			'title'    => array(),
		);

		return wp_kses( trim( $svg ), $allowed );
	}

	/**
	 * Build the icon HTML for a badge (priority: custom SVG > library > emoji).
	 *
	 * @param string $svg_icon     Custom SVG markup.
	 * @param string $library_icon Library icon name.
	 * @param string $emoji        Emoji/text icon.
	 * @param string $icon_color   Optional hex color for the icon.
	 * @param int    $icon_size    Optional size in px (0 = auto).
	 * @param int    $icon_gap     Optional spacing in px between icon and text (0 = default).
	 * @return string Icon HTML with wrapper span, or empty string.
	 */
	public static function build_badge_icon_html( string $svg_icon, string $library_icon, string $emoji, string $icon_color = '', int $icon_size = 0, int $icon_gap = 0 ): string {
		$style_parts = array();
		if ( '' !== $icon_color ) {
			$style_parts[] = 'color:' . $icon_color;
		}
		if ( $icon_size > 0 ) {
			// font-size sizes emoji glyphs; --badge-icon-size sizes SVGs (1:1,
			// not 1.25x) via the CSS rule. Both kept so "size" means size for
			// every icon type, on the front end and the admin preview.
			$style_parts[] = 'font-size:' . $icon_size . 'px';
			$style_parts[] = '--badge-icon-size:' . $icon_size . 'px';
		}
		if ( $icon_gap > 0 ) {
			// Extra space pushing the icon away from the badge text, on top of
			// the badge's base flex gap.
			$style_parts[] = 'margin-right:' . $icon_gap . 'px';
		}
		$style_attr = ! empty( $style_parts ) ? ' style="' . esc_attr( implode( ';', $style_parts ) ) . '"' : '';

		if ( '' !== $svg_icon ) {
			return '<span class="aggressive-apparel-product-badge__icon" aria-hidden="true"' . $style_attr . '>' . $svg_icon . '</span>';
		}

		if ( '' !== $library_icon && Icons::exists( $library_icon ) ) {
			$svg = Icons::get(
				$library_icon,
				array(
					'width'       => 16,
					'height'      => 16,
					'aria-hidden' => 'true',
				),
			);
			return '<span class="aggressive-apparel-product-badge__icon" aria-hidden="true"' . $style_attr . '>' . $svg . '</span>';
		}

		if ( '' !== $emoji ) {
			return '<span class="aggressive-apparel-product-badge__icon" aria-hidden="true"' . $style_attr . '>' . esc_html( $emoji ) . '</span>';
		}

		return '';
	}

	/**
	 * Render the library icon <select> with data-svg attributes for live preview.
	 *
	 * @param string $selected Currently selected icon name.
	 * @return void
	 */
	private static function render_library_icon_select( string $selected ): void {
		$icon_attrs = array(
			'width'       => 16,
			'height'      => 16,
			'aria-hidden' => 'true',
		);
		?>
		<select name="badge_library_icon" id="badge_library_icon">
			<option value=""><?php esc_html_e( 'None', 'aggressive-apparel' ); ?></option>
			<?php foreach ( Icons::list() as $icon_name ) : ?>
				<option
					value="<?php echo esc_attr( $icon_name ); ?>"
					data-svg="<?php echo esc_attr( Icons::get( $icon_name, $icon_attrs ) ); ?>"
					<?php selected( $selected, $icon_name ); ?>
				><?php echo esc_html( ucwords( str_replace( '-', ' ', $icon_name ) ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Get taxonomy labels.
	 *
	 * @return array<string, string>
	 */
	private static function get_labels(): array {
		return array(
			'name'          => __( 'Product Badges', 'aggressive-apparel' ),
			'singular_name' => __( 'Badge', 'aggressive-apparel' ),
			'menu_name'     => __( 'Badges', 'aggressive-apparel' ),
			'add_new_item'  => __( 'Add New Badge', 'aggressive-apparel' ),
			'edit_item'     => __( 'Edit Badge', 'aggressive-apparel' ),
			'new_item_name' => __( 'New Badge Name', 'aggressive-apparel' ),
			'search_items'  => __( 'Search Badges', 'aggressive-apparel' ),
			'not_found'     => __( 'No badges found.', 'aggressive-apparel' ),
			'all_items'     => __( 'All Badges', 'aggressive-apparel' ),
			'back_to_items' => __( 'Back to Badges', 'aggressive-apparel' ),
		);
	}

	/**
	 * Seed system badge terms if not already done.
	 *
	 * Creates the 4 automatic badge types (sale, new, low_stock, bestseller)
	 * as taxonomy terms with default visual properties matching the previously
	 * hardcoded styles. Safe to call multiple times — uses a version option guard.
	 *
	 * @return void
	 */
	public static function maybe_seed_system_badges(): void {
		if ( get_option( self::SEED_VERSION_OPTION ) === self::SEED_VERSION ) {
			return;
		}

		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		$system_badges = self::get_system_badge_defaults();

		foreach ( $system_badges as $badge_type => $config ) {
			// Skip if a term with this badge_type already exists.
			$existing = get_terms(
				array(
					'taxonomy'   => self::TAXONOMY,
					'hide_empty' => false,
					'meta_key'   => self::META_BADGE_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $badge_type, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'number'     => 1,
				),
			);

			if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
				continue;
			}

			$result = wp_insert_term( $config['name'], self::TAXONOMY );
			if ( is_wp_error( $result ) ) {
				continue;
			}

			$term_id = $result['term_id'];

			update_term_meta( $term_id, self::META_BADGE_TYPE, $badge_type );

			foreach ( $config['meta'] as $key => $value ) {
				update_term_meta( $term_id, $key, $value );
			}
		}

		update_option( self::SEED_VERSION_OPTION, self::SEED_VERSION );
	}

	/**
	 * Default configurations for the 4 system badges.
	 *
	 * Colors match the hex fallback values from the previously hardcoded CSS.
	 *
	 * @return array<string, array{name: string, meta: array<string, string|int>}>
	 */
	private static function get_system_badge_defaults(): array {
		$shared_meta = array(
			self::META_POSITION     => 'top-left',
			self::META_RADIUS_TL    => 4,
			self::META_RADIUS_TR    => 4,
			self::META_RADIUS_BR    => 4,
			self::META_RADIUS_BL    => 4,
			self::META_PADDING_X    => 8,
			self::META_PADDING_Y    => 3,
			self::META_BORDER_WIDTH => 0,
			self::META_BORDER_STYLE => 'none',
			self::META_BORDER_COLOR => '',
			self::META_ICON         => '',
			self::META_LIBRARY_ICON => '',
			self::META_SVG_ICON     => '',
			self::META_ICON_COLOR   => '',
			self::META_ICON_SIZE    => 0,
			self::META_ICON_GAP     => 0,
		);

		return array(
			'sale'       => array(
				'name' => __( 'Sale', 'aggressive-apparel' ),
				'meta' => array_merge(
					$shared_meta,
					array(
						self::META_BG_COLOR   => '#dc2626',
						self::META_TEXT_COLOR => '#ffffff',
						self::META_PRIORITY   => 1,
					),
				),
			),
			'new'        => array(
				'name' => __( 'New', 'aggressive-apparel' ),
				'meta' => array_merge(
					$shared_meta,
					array(
						self::META_BG_COLOR   => '#000000',
						self::META_TEXT_COLOR => '#ffffff',
						self::META_PRIORITY   => 2,
					),
				),
			),
			'low_stock'  => array(
				'name' => __( 'Low Stock', 'aggressive-apparel' ),
				'meta' => array_merge(
					$shared_meta,
					array(
						self::META_BG_COLOR   => '#f59e0b',
						self::META_TEXT_COLOR => '#000000',
						self::META_PRIORITY   => 3,
					),
				),
			),
			'bestseller' => array(
				'name' => __( 'Bestseller', 'aggressive-apparel' ),
				'meta' => array_merge(
					$shared_meta,
					array(
						self::META_BG_COLOR     => '#ffffff',
						self::META_TEXT_COLOR   => '#000000',
						self::META_PRIORITY     => 4,
						self::META_BORDER_WIDTH => 1,
						self::META_BORDER_STYLE => 'solid',
						self::META_BORDER_COLOR => '#000000',
					),
				),
			),
		);
	}

	/**
	 * Get visual data for all system badge terms, keyed by badge_type.
	 *
	 * Performs a single WP_Term_Query per request and caches the result.
	 * Returns an associative array: 'sale' => [...data...], 'new' => [...], etc.
	 * If a system badge term has been deleted, its key is absent.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_system_badges(): array {
		if ( null !== self::$system_badges_cache ) {
			return self::$system_badges_cache;
		}

		self::$system_badges_cache = array();

		$terms = get_terms(
			array(
				'taxonomy'     => self::TAXONOMY,
				'hide_empty'   => false,
				'meta_key'     => self::META_BADGE_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'   => array( 'sale', 'new', 'low_stock', 'bestseller' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => 'IN',
			),
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return self::$system_badges_cache;
		}

		foreach ( $terms as $term ) {
			$data         = self::get_badge_data( $term->term_id );
			$data['name'] = $term->name;

			self::$system_badges_cache[ $data['badge_type'] ] = $data;
		}

		return self::$system_badges_cache;
	}
}
