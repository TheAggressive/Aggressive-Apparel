<?php
/**
 * Theme-level feature toggles (Appearance → Theme Features).
 *
 * Home for Core appearance features that must work without WooCommerce.
 * Add new toggles to get_feature_definitions() — Adaptive Colors is the
 * first; more can join without new admin pages.
 *
 * @package Aggressive_Apparel
 * @since 1.143.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme Features settings registry and admin screen.
 */
class Theme_Features {

	/**
	 * Option key for theme feature flags (map of feature => 'yes'|'no').
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'aggressive_apparel_theme_features';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'aggressive-apparel-theme-features';

	/**
	 * Settings API group.
	 *
	 * @var string
	 */
	public const SETTINGS_GROUP = 'aggressive_apparel_theme_features_group';

	/**
	 * Feature keys that default ON when missing from the option bag.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_ON = array( 'adaptive_colors' );

	/**
	 * Hook the admin UI.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter(
			'option_page_capability_' . self::SETTINGS_GROUP,
			array( $this, 'get_settings_capability' )
		);
	}

	/**
	 * Capability required to save Theme Features options.
	 *
	 * @return string
	 */
	public function get_settings_capability(): string {
		return 'edit_theme_options';
	}

	/**
	 * Feature definitions for the admin screen.
	 *
	 * Add new Core toggles here. Use DEFAULT_ON above when a feature should
	 * stay enabled until an admin explicitly saves it off.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_feature_definitions(): array {
		return array(
			'adaptive_colors' => array(
				'label'       => __( 'Adaptive Colors', 'aggressive-apparel' ),
				'description' => __( 'Per-block light/dark color overrides and auto-generated adaptive palette using CSS light-dark().', 'aggressive-apparel' ),
			),
		);
	}

	/**
	 * Whether a theme feature is enabled.
	 *
	 * Values are stored as 'yes' / 'no' so WordPress does not drop falsey
	 * option entries. Features in DEFAULT_ON stay on until explicitly saved off.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function is_enabled( string $feature ): bool {
		if ( ! array_key_exists( $feature, self::get_feature_definitions() ) ) {
			return false;
		}

		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( ! array_key_exists( $feature, $options ) ) {
			return in_array( $feature, self::DEFAULT_ON, true );
		}

		return 'yes' === $options[ $feature ];
	}

	/**
	 * Add Appearance → Theme Features.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_theme_page(
			__( 'Theme Features', 'aggressive-apparel' ),
			__( 'Theme Features', 'aggressive-apparel' ),
			'edit_theme_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register the option and toggle fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_features' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'aggressive_apparel_theme_features_main',
			'',
			static function (): void {
				echo '<p>' . esc_html__( 'Appearance features that work with or without WooCommerce. More toggles can be added here over time.', 'aggressive-apparel' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		foreach ( self::get_feature_definitions() as $key => $feature ) {
			$control_id = 'aa-theme-feature-' . $key;

			add_settings_field(
				'theme_feature_' . $key,
				$feature['label'],
				array( $this, 'render_toggle_field' ),
				self::PAGE_SLUG,
				'aggressive_apparel_theme_features_main',
				array(
					'key'         => $key,
					'description' => $feature['description'],
					'label_for'   => $control_id,
				)
			);
		}
	}

	/**
	 * Sanitize the theme features option bag to explicit yes/no values.
	 *
	 * @param mixed $input Raw form input.
	 * @return array<string, string>
	 */
	public function sanitize_features( $input ): array {
		$sanitized = array();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		foreach ( array_keys( self::get_feature_definitions() ) as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] ) ? 'yes' : 'no';
		}

		return $sanitized;
	}

	/**
	 * Render a single checkbox toggle.
	 *
	 * @param array{key?: string, description?: string, label_for?: string} $args Field args.
	 * @return void
	 */
	public function render_toggle_field( array $args ): void {
		$key         = isset( $args['key'] ) ? (string) $args['key'] : '';
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		$control_id  = isset( $args['label_for'] ) ? (string) $args['label_for'] : 'aa-theme-feature-' . $key;

		if ( '' === $key ) {
			return;
		}

		$enabled = self::is_enabled( $key );
		$desc_id = $control_id . '-desc';

		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s%5$s /> %6$s</label>',
			esc_attr( $control_id ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			checked( $enabled, true, false ),
			'' !== $description ? ' aria-describedby="' . esc_attr( $desc_id ) . '"' : '',
			esc_html__( 'Enabled', 'aggressive-apparel' )
		);

		if ( '' !== $description ) {
			printf(
				'<p id="%1$s" class="description">%2$s</p>',
				esc_attr( $desc_id ),
				esc_html( $description )
			);
		}
	}

	/**
	 * Render the settings page chrome.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Theme Features', 'aggressive-apparel' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::SETTINGS_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';
		echo '</div>';
	}
}
