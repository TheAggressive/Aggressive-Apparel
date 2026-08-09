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
 * @phpstan-type BadgeData array{bg_color: string, text_color: string, icon: string, library_icon: string, svg_icon: string, icon_color: string, icon_size: int, icon_gap: int, priority: int, border_color: string, border_width: int, border_style: string, radius_tl: int, radius_tr: int, radius_br: int, radius_bl: int, padding_x: int, padding_y: int, position: string, badge_type: string, border_mode: string, inner_border_color: string, inner_border_width: int, font_size: string, font_weight: int, text_transform: string, letter_spacing: string}
 */
class Custom_Badge_Taxonomy {
	use Badge_Admin_Fields;
	use Badge_Icon_Markup;
	use Badge_Advanced_Styles;
	use Badge_Rules_Admin;
	use Badge_System_Defaults;


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
	private const BORDER_STYLES = Badge_Style_Schema::BORDER_STYLES;

	/**
	 * Allowed badge positions.
	 *
	 * @var string[]
	 */
	private const POSITIONS = Badge_Style_Schema::POSITIONS;

	/**
	 * Allowed badge types.
	 *
	 * @var string[]
	 */
	private const BADGE_TYPES = Badge_Style_Schema::BADGE_TYPES;

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
		add_action( 'rest_api_init', array( new Badge_Studio_Rest(), 'register_routes' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_term_meta_hooks' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
			add_action( 'admin_post_aggressive_apparel_save_badge_rules', array( $this, 'save_badge_rules' ) );
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
					'sanitize_callback' => array( self::class, 'sanitize_badge_color' ),
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

		self::register_advanced_style_meta();
	}

	/**
	 * Register term meta form hooks.
	 *
	 * @return void
	 */
	public function register_term_meta_hooks(): void {
		$tax = self::TAXONOMY;

		add_action( $tax . '_add_form_fields', array( $this, 'render_add_fields' ) );
		add_action( $tax . '_pre_add_form', array( $this, 'render_rules_panel' ) );
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
	 * Default badge data for the "Add New Badge" screen.
	 *
	 * Mirrors the fallbacks used in get_badge_data().
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_badge_data(): array {
		return array_merge(
			array(
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
			),
			self::get_advanced_style_defaults(),
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

		self::save_registry_fields( $term_id );
	}

	/**
	 * Persist every registry-declared field from the posted form.
	 *
	 * One loop replaces the two hand-written save paths that previously split
	 * these fields between this class and the advanced-styles trait, each with
	 * its own clamping idiom. Bounds now come from the same table the schema and
	 * the studio read, so a field cannot be saved to a range the compiler will
	 * not honour.
	 *
	 * The caller has already verified the taxonomy nonce and capability.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	private static function save_registry_fields( int $term_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by save_fields() before delegation.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized by Badge_Field_Registry::sanitize().
		$values = array();

		foreach ( Badge_Field_Registry::fields() as $key => $spec ) {
			if ( ! $spec['save'] ) {
				continue;
			}

			$field = (string) $spec['field'];
			$raw   = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : null;

			// A field the form did not post keeps its declared default rather than
			// being coerced from null.
			$values[ $key ] = null === $raw
				? $spec['default']
				: Badge_Field_Registry::sanitize( $key, $raw );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Cross-field rule: CSS `double` needs at least 3px to render two lines,
		// so a thinner width saved alongside it would silently paint as solid.
		if ( 'double' === $values['border_style'] && (int) $values['border_width'] > 0 ) {
			$values['border_width'] = max( 3, (int) $values['border_width'] );
		}

		$keys = Badge_Field_Registry::field_keys();
		foreach ( $values as $key => $value ) {
			update_term_meta( $term_id, $keys[ $key ], $value );
		}
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
			$is_system  = 'custom' !== $data['badge_type'];
			$type_label = $is_system
				? ucwords( str_replace( '_', ' ', $data['badge_type'] ) )
				: __( 'Custom', 'aggressive-apparel' );
			printf(
				'<span class="aa-badge-type aa-badge-type--%1$s">%2$s</span>',
				$is_system ? 'automatic' : 'custom',
				esc_html( $type_label ),
			);
			return;
		}

		$icon_position = Badge_Style_Schema::sanitize_enum(
			$data['icon_position'] ?? 'start',
			Badge_Style_Schema::ICON_POSITIONS,
			'start'
		);
		$icon_html     = self::build_badge_icon_html(
			$data['svg_icon'],
			$data['library_icon'],
			$data['icon'],
			$data['icon_color'],
			$data['icon_size']
		);
		$aria_label    = '';

		if ( 'only' === $icon_position ) {
			$inner = '' !== $icon_html ? $icon_html : esc_html( $term->name );
			if ( '' !== $icon_html ) {
				$aria_label = $term->name;
			}
		} elseif ( 'end' === $icon_position ) {
			$inner = esc_html( $term->name ) . $icon_html;
		} else {
			$inner = $icon_html . esc_html( $term->name );
		}

		echo aggressive_apparel_trusted_html(
			Badge_Style_Schema::compile_badge_span( $data, $inner, 'admin', '', $aria_label )
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing used only to conditionally enqueue assets.
		$current_taxonomy = isset( $_GET['taxonomy'] )
			? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
			: '';

		if ( self::TAXONOMY !== $current_taxonomy ) {
			return;
		}

		// Modern badge-editor styles (also carries the icon-sizing rule the
		// front-end bundle isn't loaded for in wp-admin).
		$style_deps     = array();
		$storefront_css = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/product-badges.css';
		if ( file_exists( $storefront_css ) ) {
			wp_enqueue_style(
				'aggressive-apparel-product-badges',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/product-badges.css',
				array(),
				(string) filemtime( $storefront_css )
			);
			// Only depend on a handle that was actually registered: WordPress
			// drops a stylesheet whose dependency is missing, so listing this
			// unconditionally would take the whole studio UI down with it when
			// the storefront bundle is absent.
			$style_deps[] = 'aggressive-apparel-product-badges';
		}

		// Load package CSS before our studio shell (do not list script handles as
		// style deps — WordPress silently drops the stylesheet).
		wp_enqueue_style( 'wp-components' );

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/admin/badge-studio.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-badge-studio',
				AGGRESSIVE_APPAREL_URI . '/build/styles/admin/badge-studio.css',
				$style_deps,
				(string) filemtime( $css_file )
			);

			$preset_css = Badge_Palette::root_css();
			if ( '' !== $preset_css ) {
				wp_add_inline_style( 'aggressive-apparel-badge-studio', $preset_css );
			}
		}

		\Aggressive_Apparel\Assets\Asset_Loader::enqueue_admin_script(
			'aggressive-apparel-badge-studio',
			'build/scripts/admin/badge-studio/index',
			array(
				'react-jsx-runtime',
				'wp-element',
				'wp-components',
				'wp-i18n',
				'wp-api-fetch',
				'wp-dom-ready',
			)
		);
	}

	/**
	 * Get custom badges assigned to a product, sorted by priority.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array{name: string, bg_color: string, text_color: string, icon: string, library_icon: string, svg_icon: string, icon_color: string, icon_size: int, icon_gap: int, priority: int, border_color: string, border_width: int, border_style: string, radius_tl: int, radius_tr: int, radius_br: int, radius_bl: int, padding_x: int, padding_y: int, position: string, badge_type: string, border_mode: string, inner_border_color: string, inner_border_width: int, font_size: string, font_weight: int, text_transform: string, letter_spacing: string}>
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
	 * @return BadgeData
	 */
	private static function get_badge_data( int $term_id ): array {
		$legacy = array(
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
			'border_style' => self::validated_meta( $term_id, self::META_BORDER_STYLE, self::BORDER_STYLES, 'none' ),
			'radius_tl'    => (int) self::get_meta( $term_id, self::META_RADIUS_TL, '4' ),
			'radius_tr'    => (int) self::get_meta( $term_id, self::META_RADIUS_TR, '4' ),
			'radius_br'    => (int) self::get_meta( $term_id, self::META_RADIUS_BR, '4' ),
			'radius_bl'    => (int) self::get_meta( $term_id, self::META_RADIUS_BL, '4' ),
			'padding_x'    => (int) self::get_meta( $term_id, self::META_PADDING_X, '8' ),
			'padding_y'    => (int) self::get_meta( $term_id, self::META_PADDING_Y, '3' ),
			'position'     => self::validated_meta( $term_id, self::META_POSITION, self::POSITIONS, 'top-left' ),
			'badge_type'   => self::validated_meta( $term_id, self::META_BADGE_TYPE, self::BADGE_TYPES, 'custom' ),
		);

		/**
		 * Merged data retains the complete, validated badge-data shape.
		 *
		 * @var BadgeData $data
		 */
		$data = array_merge( $legacy, self::get_advanced_style_data( $term_id, $legacy ) );

		return $data;
	}
}
