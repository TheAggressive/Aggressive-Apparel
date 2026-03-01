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
		add_action( $tax . '_edit_form_fields', array( $this, 'render_edit_fields' ), 10, 1 );
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
		?>
		<div class="form-field">
			<label for="badge_bg_color"><?php esc_html_e( 'Background Color', 'aggressive-apparel' ); ?></label>
			<input type="text" name="badge_bg_color" id="badge_bg_color" class="aa-badge-color-picker" value="#000000" />
		</div>

		<div class="form-field">
			<label for="badge_text_color"><?php esc_html_e( 'Text Color', 'aggressive-apparel' ); ?></label>
			<input type="text" name="badge_text_color" id="badge_text_color" class="aa-badge-color-picker" value="#ffffff" />
		</div>

		<div class="form-field">
			<label for="badge_icon"><?php esc_html_e( 'Emoji Icon', 'aggressive-apparel' ); ?></label>
			<input type="text" name="badge_icon" id="badge_icon" value="" maxlength="10" />
			<p><?php esc_html_e( 'Optional. Emoji or character shown before badge text.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label for="badge_library_icon"><?php esc_html_e( 'Library Icon', 'aggressive-apparel' ); ?></label>
			<?php self::render_library_icon_select( '' ); ?>
			<p><?php esc_html_e( 'Pick from the built-in icon library. Overrides emoji.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label for="badge_svg_icon"><?php esc_html_e( 'Custom SVG', 'aggressive-apparel' ); ?></label>
			<textarea name="badge_svg_icon" id="badge_svg_icon" rows="4" style="font-family:monospace;font-size:12px;"></textarea>
			<p><?php esc_html_e( 'Paste SVG markup. Overrides library icon and emoji.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label for="badge_icon_color"><?php esc_html_e( 'Icon Color', 'aggressive-apparel' ); ?></label>
			<input type="text" name="badge_icon_color" id="badge_icon_color" class="aa-badge-color-picker" value="" />
			<p><?php esc_html_e( 'Optional. Leave empty to inherit text color.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label for="badge_icon_size"><?php esc_html_e( 'Icon Size (px)', 'aggressive-apparel' ); ?></label>
			<input type="number" name="badge_icon_size" id="badge_icon_size" value="0" min="0" max="64" step="1" />
			<p><?php esc_html_e( 'Optional. 0 = auto (inherits font size). Default: 0.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label for="badge_border_color"><?php esc_html_e( 'Border Color', 'aggressive-apparel' ); ?></label>
			<input type="text" name="badge_border_color" id="badge_border_color" class="aa-badge-color-picker" value="" />
			<p><?php esc_html_e( 'Optional. Leave empty for no border.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label for="badge_border_width"><?php esc_html_e( 'Border Width (px)', 'aggressive-apparel' ); ?></label>
			<input type="number" name="badge_border_width" id="badge_border_width" value="0" min="0" max="10" step="1" />
		</div>

		<div class="form-field">
			<label for="badge_border_style"><?php esc_html_e( 'Border Style', 'aggressive-apparel' ); ?></label>
			<select name="badge_border_style" id="badge_border_style">
				<?php foreach ( self::BORDER_STYLES as $style ) : ?>
					<option value="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( ucfirst( $style ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="form-field">
			<label><?php esc_html_e( 'Border Radius (px)', 'aggressive-apparel' ); ?></label>
			<div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
				<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
					<?php esc_html_e( 'TL', 'aggressive-apparel' ); ?>
					<input type="number" name="badge_radius_tl" id="badge_radius_tl" value="4" min="0" max="100" step="1" style="width:60px;" />
				</label>
				<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
					<?php esc_html_e( 'TR', 'aggressive-apparel' ); ?>
					<input type="number" name="badge_radius_tr" id="badge_radius_tr" value="4" min="0" max="100" step="1" style="width:60px;" />
				</label>
				<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
					<?php esc_html_e( 'BR', 'aggressive-apparel' ); ?>
					<input type="number" name="badge_radius_br" id="badge_radius_br" value="4" min="0" max="100" step="1" style="width:60px;" />
				</label>
				<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
					<?php esc_html_e( 'BL', 'aggressive-apparel' ); ?>
					<input type="number" name="badge_radius_bl" id="badge_radius_bl" value="4" min="0" max="100" step="1" style="width:60px;" />
				</label>
			</div>
			<p><?php esc_html_e( 'Top-left, top-right, bottom-right, bottom-left. Default: 4px each.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label><?php esc_html_e( 'Padding (px)', 'aggressive-apparel' ); ?></label>
			<div style="display:flex;gap:0.5rem;align-items:center;">
				<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
					<?php esc_html_e( 'X', 'aggressive-apparel' ); ?>
					<input type="number" name="badge_padding_x" id="badge_padding_x" value="8" min="0" max="50" step="1" style="width:60px;" />
				</label>
				<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
					<?php esc_html_e( 'Y', 'aggressive-apparel' ); ?>
					<input type="number" name="badge_padding_y" id="badge_padding_y" value="3" min="0" max="50" step="1" style="width:60px;" />
				</label>
			</div>
			<p><?php esc_html_e( 'Horizontal (X) and vertical (Y). Default: 8px / 3px.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label for="badge_position"><?php esc_html_e( 'Position', 'aggressive-apparel' ); ?></label>
			<select name="badge_position" id="badge_position">
				<?php foreach ( self::POSITIONS as $pos ) : ?>
					<option value="<?php echo esc_attr( $pos ); ?>"><?php echo esc_html( ucwords( str_replace( '-', ' ', $pos ) ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p><?php esc_html_e( 'Corner of the product image where this badge appears.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label for="badge_priority"><?php esc_html_e( 'Priority', 'aggressive-apparel' ); ?></label>
			<input type="number" name="badge_priority" id="badge_priority" value="10" min="0" max="100" step="1" />
			<p><?php esc_html_e( 'Lower number = displayed first. Default: 10.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="form-field">
			<label><?php esc_html_e( 'Preview', 'aggressive-apparel' ); ?></label>
			<div style="margin-top: 0.5rem;">
				<span id="aa-badge-preview-el" style="display:inline-flex;align-items:center;gap:0.25em;padding:3px 8px;border-radius:0.25rem;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background-color:#000;color:#fff;">
					<?php esc_html_e( 'Badge Name', 'aggressive-apparel' ); ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render fields on the "Edit Badge" form.
	 *
	 * @param \WP_Term $term Current term object.
	 * @return void
	 */
	public function render_edit_fields( \WP_Term $term ): void {
		$data           = self::get_badge_data( $term->term_id );
		$bg_color       = $data['bg_color'];
		$text_color     = $data['text_color'];
		$icon           = $data['icon'];
		$library_icon   = $data['library_icon'];
		$svg_icon       = $data['svg_icon'];
		$priority       = $data['priority'];
		$border_color   = $data['border_color'];
		$border_width   = $data['border_width'];
		$border_style   = $data['border_style'];
		$radius_tl      = $data['radius_tl'];
		$radius_tr      = $data['radius_tr'];
		$radius_br      = $data['radius_br'];
		$radius_bl      = $data['radius_bl'];
		$padding_x      = $data['padding_x'];
		$padding_y      = $data['padding_y'];
		$icon_color     = $data['icon_color'];
		$icon_size      = $data['icon_size'];
		$icon_html      = self::build_badge_icon_html( $svg_icon, $library_icon, $icon, $icon_color, $icon_size );
		$label          = $icon_html . esc_html( $term->name );
		$preview_border = $border_width > 0 && '' !== $border_color && 'none' !== $border_style
			? sprintf( 'border:%dpx %s %s;', $border_width, $border_style, $border_color )
			: '';
		$preview_radius = sprintf( 'border-radius:%dpx %dpx %dpx %dpx;', $radius_tl, $radius_tr, $radius_br, $radius_bl );
		$badge_type     = $data['badge_type'];
		?>
		<?php if ( 'custom' !== $badge_type ) : ?>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Badge Type', 'aggressive-apparel' ); ?></label></th>
			<td>
				<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $badge_type ) ) ); ?></strong>
				<p class="description"><?php esc_html_e( 'System badge. Automatically applied based on product conditions.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>
		<tr class="form-field">
			<th scope="row"><label for="badge_bg_color"><?php esc_html_e( 'Background Color', 'aggressive-apparel' ); ?></label></th>
			<td><input type="text" name="badge_bg_color" id="badge_bg_color" class="aa-badge-color-picker" value="<?php echo esc_attr( $bg_color ); ?>" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_text_color"><?php esc_html_e( 'Text Color', 'aggressive-apparel' ); ?></label></th>
			<td><input type="text" name="badge_text_color" id="badge_text_color" class="aa-badge-color-picker" value="<?php echo esc_attr( $text_color ); ?>" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_icon"><?php esc_html_e( 'Emoji Icon', 'aggressive-apparel' ); ?></label></th>
			<td>
				<input type="text" name="badge_icon" id="badge_icon" value="<?php echo esc_attr( $icon ); ?>" maxlength="10" />
				<p class="description"><?php esc_html_e( 'Optional. Emoji or character shown before badge text.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_library_icon"><?php esc_html_e( 'Library Icon', 'aggressive-apparel' ); ?></label></th>
			<td>
				<?php self::render_library_icon_select( $library_icon ); ?>
				<p class="description"><?php esc_html_e( 'Pick from the built-in icon library. Overrides emoji.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_svg_icon"><?php esc_html_e( 'Custom SVG', 'aggressive-apparel' ); ?></label></th>
			<td>
				<textarea name="badge_svg_icon" id="badge_svg_icon" rows="4" style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $svg_icon ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Paste SVG markup. Overrides library icon and emoji.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_icon_color"><?php esc_html_e( 'Icon Color', 'aggressive-apparel' ); ?></label></th>
			<td>
				<input type="text" name="badge_icon_color" id="badge_icon_color" class="aa-badge-color-picker" value="<?php echo esc_attr( $icon_color ); ?>" />
				<p class="description"><?php esc_html_e( 'Optional. Leave empty to inherit text color.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_icon_size"><?php esc_html_e( 'Icon Size (px)', 'aggressive-apparel' ); ?></label></th>
			<td>
				<input type="number" name="badge_icon_size" id="badge_icon_size" value="<?php echo esc_attr( (string) $icon_size ); ?>" min="0" max="64" step="1" />
				<p class="description"><?php esc_html_e( 'Optional. 0 = auto (inherits font size). Default: 0.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_border_color"><?php esc_html_e( 'Border Color', 'aggressive-apparel' ); ?></label></th>
			<td>
				<input type="text" name="badge_border_color" id="badge_border_color" class="aa-badge-color-picker" value="<?php echo esc_attr( $border_color ); ?>" />
				<p class="description"><?php esc_html_e( 'Optional. Leave empty for no border.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_border_width"><?php esc_html_e( 'Border Width (px)', 'aggressive-apparel' ); ?></label></th>
			<td>
				<input type="number" name="badge_border_width" id="badge_border_width" value="<?php echo esc_attr( (string) $border_width ); ?>" min="0" max="10" step="1" />
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_border_style"><?php esc_html_e( 'Border Style', 'aggressive-apparel' ); ?></label></th>
			<td>
				<select name="badge_border_style" id="badge_border_style">
					<?php foreach ( self::BORDER_STYLES as $style ) : ?>
						<option value="<?php echo esc_attr( $style ); ?>" <?php selected( $border_style, $style ); ?>><?php echo esc_html( ucfirst( $style ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Border Radius (px)', 'aggressive-apparel' ); ?></label></th>
			<td>
				<div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
					<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
						<?php esc_html_e( 'TL', 'aggressive-apparel' ); ?>
						<input type="number" name="badge_radius_tl" id="badge_radius_tl" value="<?php echo esc_attr( (string) $radius_tl ); ?>" min="0" max="100" step="1" style="width:60px;" />
					</label>
					<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
						<?php esc_html_e( 'TR', 'aggressive-apparel' ); ?>
						<input type="number" name="badge_radius_tr" id="badge_radius_tr" value="<?php echo esc_attr( (string) $radius_tr ); ?>" min="0" max="100" step="1" style="width:60px;" />
					</label>
					<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
						<?php esc_html_e( 'BR', 'aggressive-apparel' ); ?>
						<input type="number" name="badge_radius_br" id="badge_radius_br" value="<?php echo esc_attr( (string) $radius_br ); ?>" min="0" max="100" step="1" style="width:60px;" />
					</label>
					<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
						<?php esc_html_e( 'BL', 'aggressive-apparel' ); ?>
						<input type="number" name="badge_radius_bl" id="badge_radius_bl" value="<?php echo esc_attr( (string) $radius_bl ); ?>" min="0" max="100" step="1" style="width:60px;" />
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Top-left, top-right, bottom-right, bottom-left. Default: 4px each.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Padding (px)', 'aggressive-apparel' ); ?></label></th>
			<td>
				<div style="display:flex;gap:0.5rem;align-items:center;">
					<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
						<?php esc_html_e( 'X', 'aggressive-apparel' ); ?>
						<input type="number" name="badge_padding_x" id="badge_padding_x" value="<?php echo esc_attr( (string) $padding_x ); ?>" min="0" max="50" step="1" style="width:60px;" />
					</label>
					<label style="display:flex;flex-direction:column;font-weight:400;font-size:12px;">
						<?php esc_html_e( 'Y', 'aggressive-apparel' ); ?>
						<input type="number" name="badge_padding_y" id="badge_padding_y" value="<?php echo esc_attr( (string) $padding_y ); ?>" min="0" max="50" step="1" style="width:60px;" />
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Horizontal (X) and vertical (Y). Default: 8px / 3px.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_position"><?php esc_html_e( 'Position', 'aggressive-apparel' ); ?></label></th>
			<td>
				<select name="badge_position" id="badge_position">
					<?php foreach ( self::POSITIONS as $pos ) : ?>
						<option value="<?php echo esc_attr( $pos ); ?>" <?php selected( $data['position'], $pos ); ?>><?php echo esc_html( ucwords( str_replace( '-', ' ', $pos ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Corner of the product image where this badge appears.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="badge_priority"><?php esc_html_e( 'Priority', 'aggressive-apparel' ); ?></label></th>
			<td>
				<input type="number" name="badge_priority" id="badge_priority" value="<?php echo esc_attr( (string) $priority ); ?>" min="0" max="100" step="1" />
				<p class="description"><?php esc_html_e( 'Lower number = displayed first. Default: 10.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Preview', 'aggressive-apparel' ); ?></label></th>
			<td>
				<span id="aa-badge-preview-el" style="display:inline-flex;align-items:center;gap:0.25em;padding:<?php echo esc_attr( $padding_y . 'px ' . $padding_x . 'px' ); ?>;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background-color:<?php echo esc_attr( $bg_color ); ?>;color:<?php echo esc_attr( $text_color ); ?>;<?php echo esc_attr( $preview_border ); ?><?php echo esc_attr( $preview_radius ); ?>">
					<?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
				</span>
			</td>
		</tr>
		<?php
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

		$icon_html = self::build_badge_icon_html( $data['svg_icon'], $data['library_icon'], $data['icon'], $data['icon_color'], $data['icon_size'] );
		$label     = $icon_html . esc_html( $term->name );

		$border_css  = $data['border_width'] > 0 && '' !== $data['border_color'] && 'none' !== $data['border_style']
			? sprintf( 'border:%dpx %s %s;', $data['border_width'], $data['border_style'], $data['border_color'] )
			: '';
		$radius_css  = sprintf( 'border-radius:%dpx %dpx %dpx %dpx;', $data['radius_tl'], $data['radius_tr'], $data['radius_br'], $data['radius_bl'] );
		$padding_css = sprintf( 'padding:%dpx %dpx;', $data['padding_y'], $data['padding_x'] );

		printf(
			'<span style="display:inline-flex;align-items:center;gap:0.25em;%sfont-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background-color:%s;color:%s;%s%s">%s</span>',
			esc_attr( $padding_css ),
			esc_attr( $data['bg_color'] ),
			esc_attr( $data['text_color'] ),
			esc_attr( $border_css ),
			esc_attr( $radius_css ),
			$label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		);
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
		wp_enqueue_script( 'wp-color-picker' );

		wp_add_inline_script( 'wp-color-picker', self::get_inline_script() );
	}

	/**
	 * Get custom badges assigned to a product, sorted by priority.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array{name: string, bg_color: string, text_color: string, icon: string, library_icon: string, svg_icon: string, icon_color: string, icon_size: int, priority: int, border_color: string, border_width: int, border_style: string, radius_tl: int, radius_tr: int, radius_br: int, radius_bl: int, padding_x: int, padding_y: int, position: string, badge_type: string}>
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
	 * @return array{bg_color: string, text_color: string, icon: string, library_icon: string, svg_icon: string, icon_color: string, icon_size: int, priority: int, border_color: string, border_width: int, border_style: string, radius_tl: int, radius_tr: int, radius_br: int, radius_bl: int, padding_x: int, padding_y: int, position: string, badge_type: string}
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
	 * @return string Icon HTML with wrapper span, or empty string.
	 */
	public static function build_badge_icon_html( string $svg_icon, string $library_icon, string $emoji, string $icon_color = '', int $icon_size = 0 ): string {
		$style_parts = array();
		if ( '' !== $icon_color ) {
			$style_parts[] = 'color:' . $icon_color;
		}
		if ( $icon_size > 0 ) {
			$style_parts[] = 'font-size:' . $icon_size . 'px';
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
	 * Get inline JS for color picker initialization and live preview.
	 *
	 * @return string JavaScript code.
	 */
	private static function get_inline_script(): string {
		return <<<'JS'
jQuery(document).ready(function($){
	function updatePreview(){
		var bg=$('#badge_bg_color').val()||'#000000';
		var text=$('#badge_text_color').val()||'#ffffff';
		var emoji=$('#badge_icon').val()||'';
		var libIcon=$('#badge_library_icon').val()||'';
		var svgRaw=$('#badge_svg_icon').val()||'';
		var name=$('#tag-name').val()||$('input[name="name"]').val()||'Badge Name';
		var iconColor=$('#badge_icon_color').val()||'';
		var iconSize=parseInt($('#badge_icon_size').val(),10)||0;
		var iconStyle='display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;';
		if(iconColor)iconStyle+='color:'+iconColor+';';
		if(iconSize>0)iconStyle+='font-size:'+iconSize+'px;';
		var iconHtml='';
		if(svgRaw){
			iconHtml='<span class="aggressive-apparel-product-badge__icon" aria-hidden="true" style="'+iconStyle+'">'+svgRaw+'</span>';
		}else if(libIcon){
			var libSvg=$('#badge_library_icon option:selected').data('svg')||'';
			iconHtml='<span class="aggressive-apparel-product-badge__icon" aria-hidden="true" style="'+iconStyle+'">'+libSvg+'</span>';
		}else if(emoji){
			iconHtml='<span class="aggressive-apparel-product-badge__icon" aria-hidden="true" style="'+iconStyle+'">'+$('<span>').text(emoji).html()+'</span>';
		}
		var borderColor=$('#badge_border_color').val()||'';
		var borderWidth=parseInt($('#badge_border_width').val(),10)||0;
		var borderStyle=$('#badge_border_style').val()||'none';
		var rTL=parseInt($('#badge_radius_tl').val(),10)||0;
		var rTR=parseInt($('#badge_radius_tr').val(),10)||0;
		var rBR=parseInt($('#badge_radius_br').val(),10)||0;
		var rBL=parseInt($('#badge_radius_bl').val(),10)||0;
		var css={'display':'inline-flex','align-items':'center','gap':'0.25em','background-color':bg,'color':text};
		if(borderWidth>0&&borderColor&&borderStyle!=='none'){
			css['border']=borderWidth+'px '+borderStyle+' '+borderColor;
		}else{
			css['border']='none';
		}
		css['border-radius']=rTL+'px '+rTR+'px '+rBR+'px '+rBL+'px';
		var pX=parseInt($('#badge_padding_x').val(),10);
		var pY=parseInt($('#badge_padding_y').val(),10);
		if(isNaN(pX))pX=8;
		if(isNaN(pY))pY=3;
		css['padding']=pY+'px '+pX+'px';
		$('#aa-badge-preview-el').css(css).html(iconHtml+$('<span>').text(name).html());
	}
	$('.aa-badge-color-picker').wpColorPicker({change:function(){setTimeout(updatePreview,50);}});
	$('#badge_icon,#badge_library_icon,#badge_svg_icon,#tag-name,input[name="name"],#badge_border_width,#badge_border_style,#badge_radius_tl,#badge_radius_tr,#badge_radius_br,#badge_radius_bl,#badge_padding_x,#badge_padding_y,#badge_icon_size').on('input change',updatePreview);
	updatePreview();
});
JS;
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
