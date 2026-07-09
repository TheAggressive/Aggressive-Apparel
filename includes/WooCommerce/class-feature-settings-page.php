<?php
/**
 * Feature Settings Page Class
 *
 * Owns the Store Enhancements admin page lifecycle: menu registration,
 * asset loading, WordPress Settings API wiring, and page chrome rendering.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature Settings Page
 *
 * Controller for the Appearance → Store Enhancements settings screen.
 * Delegates sanitization to Feature_Settings_Sanitizer and field rendering
 * to Feature_Settings_Fields, keeping each concern in its own class.
 *
 * @since 1.18.0
 */
class Feature_Settings_Page {

	/**
	 * Sanitization callbacks for the option group.
	 *
	 * @var Feature_Settings_Sanitizer
	 */
	private Feature_Settings_Sanitizer $sanitizer;

	/**
	 * Field renderers for the settings screen.
	 *
	 * @var Feature_Settings_Fields
	 */
	private Feature_Settings_Fields $fields;

	/**
	 * Constructor.
	 *
	 * @param Feature_Settings_Sanitizer|null $sanitizer Optional sanitizer (injectable for tests).
	 * @param Feature_Settings_Fields|null    $fields    Optional field renderer (injectable for tests).
	 */
	public function __construct( ?Feature_Settings_Sanitizer $sanitizer = null, ?Feature_Settings_Fields $fields = null ) {
		$this->sanitizer = $sanitizer ?? new Feature_Settings_Sanitizer();
		$this->fields    = $fields ?? new Feature_Settings_Fields();
	}

	/**
	 * Initialize settings hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page under Appearance.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		$hook = add_theme_page(
			__( 'Store Enhancements', 'aggressive-apparel' ),
			__( 'Store Enhancements', 'aggressive-apparel' ),
			'edit_theme_options',
			Feature_Settings::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
		);

		if ( $hook ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Enqueue admin assets for the settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix = '' ): void {
		if ( 'appearance_page_' . Feature_Settings::PAGE_SLUG !== $hook_suffix ) {
			return;
		}
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/admin/store-enhancements-admin.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-store-enhancements-admin',
				AGGRESSIVE_APPAREL_URI . '/build/styles/admin/store-enhancements-admin.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		\Aggressive_Apparel\Assets\Asset_Loader::enqueue_admin_script(
			'aggressive-apparel-store-enhancements-admin',
			'build/scripts/admin/store-enhancements-admin',
			array()
		);
	}

	/**
	 * Register the single option and settings sections.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			Feature_Settings::SETTINGS_GROUP,
			Feature_Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->sanitizer, 'sanitize_features' ),
			)
		);

		foreach ( Feature_Settings::get_sections() as $id => $meta ) {
			add_settings_section(
				'aggressive_apparel_features_' . $id,
				$meta['label'],
				'__return_false',
				Feature_Settings::PAGE_SLUG,
			);
		}

		$this->register_sub_settings();
		$this->register_feature_fields();
		$this->register_store_copy_fields();
	}

	/**
	 * Register sub-setting options (always, so saved values persist).
	 *
	 * Store Copy texts come from their definitions list; every other
	 * sub-setting is driven by the shared option schema so registration
	 * defaults and read fallbacks can never drift apart.
	 *
	 * @return void
	 */
	private function register_sub_settings(): void {
		$group = Feature_Settings::SETTINGS_GROUP;

		foreach ( Feature_Settings::get_store_copy_definitions() as $definition ) {
			register_setting(
				$group,
				$definition['option'],
				array(
					'type'              => 'string',
					'default'           => $definition['default'],
					'sanitize_callback' => array( $this->sanitizer, 'sanitize_store_copy_text' ),
				)
			);
		}

		foreach ( Feature_Settings::get_option_schema() as $option => $schema ) {
			$sanitize_callback = array( $this->sanitizer, $schema['sanitize'] );

			// Guards schema typos; also proves callability to PHPStan.
			if ( ! is_callable( $sanitize_callback ) ) {
				continue;
			}

			register_setting(
				$group,
				$option,
				array(
					'type'              => $schema['type'],
					'default'           => $schema['default'],
					'sanitize_callback' => $sanitize_callback,
				)
			);
		}
	}

	/**
	 * Register the per-feature toggle fields plus their conditional sub-fields.
	 *
	 * @return void
	 */
	private function register_feature_fields(): void {
		foreach ( Feature_Settings::get_feature_definitions() as $key => $feature ) {
			add_settings_field(
				'feature_' . $key,
				$feature['label'],
				array( $this->fields, 'render_toggle_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_' . $feature['section'],
				array(
					'key'         => $key,
					'description' => $feature['description'],
				),
			);

			$this->register_feature_sub_fields( $key );
		}
	}

	/**
	 * Register Store Copy fields.
	 *
	 * @return void
	 */
	private function register_store_copy_fields(): void {
		foreach ( Feature_Settings::get_store_copy_definitions() as $id => $definition ) {
			add_settings_field(
				'store_copy_' . $id,
				$definition['label'],
				array( $this->fields, 'render_store_copy_text_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_copy',
				$definition,
			);
		}
	}

	/**
	 * Register sub-fields rendered immediately after a parent toggle.
	 *
	 * Only emitted when the parent feature is enabled.
	 *
	 * @param string $key Parent feature key.
	 * @return void
	 */
	private function register_feature_sub_fields( string $key ): void {
		if ( 'product_filters' === $key && Feature_Settings::is_enabled( 'product_filters' ) ) {
			add_settings_field(
				'filter_layout',
				__( 'Filter Layout', 'aggressive-apparel' ),
				array( $this->fields, 'render_filter_layout_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
			);

		}

		if ( 'load_more' === $key && Feature_Settings::is_enabled( 'load_more' ) ) {
			add_settings_field(
				'load_more_mode',
				__( 'Load More Mode', 'aggressive-apparel' ),
				array( $this->fields, 'render_load_more_mode_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
			);
		}

		if ( 'catalog_hover_image' === $key && Feature_Settings::is_enabled( 'catalog_hover_image' ) ) {
			add_settings_field(
				'hover_image_exit_duration',
				__( 'Primary Image Exit Duration', 'aggressive-apparel' ),
				array( $this->fields, 'render_hover_image_exit_duration_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
			);

			add_settings_field(
				'hover_image_exit_animation',
				__( 'Primary Image Exit Animation', 'aggressive-apparel' ),
				array( $this->fields, 'render_hover_image_exit_animation_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
			);

			add_settings_field(
				'hover_image_animation',
				__( 'Hover Animation', 'aggressive-apparel' ),
				array( $this->fields, 'render_hover_image_animation_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
			);
		}

		if ( 'wishlist' === $key && Feature_Settings::is_enabled( 'wishlist' ) ) {
			add_settings_field(
				'wishlist_button_placement',
				__( 'Wishlist Button Placement', 'aggressive-apparel' ),
				array( $this->fields, 'render_wishlist_button_placement_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_engagement',
			);
		}

		if ( 'social_proof' === $key && Feature_Settings::is_enabled( 'social_proof' ) ) {
			$this->register_social_proof_fields();
		}
	}

	/**
	 * Register the Social Proof sub-fields.
	 *
	 * @return void
	 */
	private function register_social_proof_fields(): void {
		$social_proof_fields = array(
			'social_proof_sources'              => array( __( 'Notification Sources', 'aggressive-apparel' ), 'render_social_proof_sources_field' ),
			'social_proof_trust_messages'       => array( __( 'Trust Messages', 'aggressive-apparel' ), 'render_social_proof_trust_messages_field' ),
			'social_proof_announcements'        => array( __( 'Custom Announcements', 'aggressive-apparel' ), 'render_social_proof_announcements_field' ),
			'social_proof_purchase_badge_icon'  => array( __( 'Badge on Purchase Thumbnails', 'aggressive-apparel' ), 'render_social_proof_purchase_badge_icon_field' ),
			'social_proof_icon_help'            => array( __( 'Icons: reference & customization', 'aggressive-apparel' ), 'render_social_proof_icon_help_field' ),
			'social_proof_engagement_min_sales' => array( __( 'Engagement: Minimum Lifetime Sales', 'aggressive-apparel' ), 'render_social_proof_engagement_min_sales_field' ),
			'social_proof_display_mode'         => array( __( 'Purchase Display Mode', 'aggressive-apparel' ), 'render_social_proof_display_mode_field' ),
			'social_proof_location_granularity' => array( __( 'Location Granularity', 'aggressive-apparel' ), 'render_social_proof_location_granularity_field' ),
			'social_proof_min_order_age'        => array( __( 'Minimum Order Age (Minutes)', 'aggressive-apparel' ), 'render_social_proof_min_order_age_field' ),
			'social_proof_demo'                 => array( __( 'Demo Preview (Admin Only)', 'aggressive-apparel' ), 'render_social_proof_demo_field' ),
		);

		foreach ( $social_proof_fields as $id => $field ) {
			add_settings_field(
				$id,
				$field[0],
				array( $this->fields, $field[1] ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_engagement',
			);
		}
	}

	/**
	 * Render the settings page with tabbed sections.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$sections       = Feature_Settings::get_sections();
		$section_counts = $this->get_section_counts();
		$first_key      = array_key_first( $sections );

		echo '<div class="wrap aa-features-wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		settings_errors();
		echo '<p>' . esc_html__( 'Enable or disable individual WooCommerce enhancements. Disabled features have zero performance overhead.', 'aggressive-apparel' ) . '</p>';
		echo '<form method="post" action="options.php">';

		settings_fields( Feature_Settings::SETTINGS_GROUP );

		// Tab navigation.
		echo '<nav class="nav-tab-wrapper aa-features-tabs">';
		foreach ( $sections as $id => $meta ) {
			$active     = ( $id === $first_key ) ? ' nav-tab-active' : '';
			$counts     = $section_counts[ $id ];
			$count_html = '';

			if ( $counts['total'] > 0 ) {
				$count_html = sprintf(
					' <span class="aa-features-tab-count">%d/%d</span>',
					absint( $counts['enabled'] ),
					absint( $counts['total'] ),
				);
			}

			printf(
				'<a href="#" class="nav-tab%s" data-tab="%s"><span class="dashicons %s"></span> %s%s</a>',
				esc_attr( $active ),
				esc_attr( $id ),
				esc_attr( $meta['icon'] ),
				esc_html( $meta['label'] ),
				wp_kses_post( $count_html )
			);
		}
		echo '</nav>';

		// Tab panels.
		foreach ( $sections as $id => $meta ) {
			$hidden     = ( $id !== $first_key ) ? ' hidden' : '';
			$section_id = 'aggressive_apparel_features_' . $id;

			printf( '<div class="aa-features-tab-panel" id="tab-%s"%s>', esc_attr( $id ), esc_attr( $hidden ) );
			echo '<table class="form-table" role="presentation">';
			do_settings_fields( Feature_Settings::PAGE_SLUG, $section_id );
			echo '</table>';
			echo '</div>';
		}

		submit_button( __( 'Save Changes', 'aggressive-apparel' ) );

		echo '</form>';

		echo '</div>';
	}

	/**
	 * Get enabled/total feature counts per section.
	 *
	 * @return array<string, array{enabled: int, total: int}>
	 */
	private function get_section_counts(): array {
		$counts = array();
		foreach ( Feature_Settings::get_sections() as $id => $meta ) {
			$counts[ $id ] = array(
				'enabled' => 0,
				'total'   => 0,
			);
		}

		foreach ( Feature_Settings::get_feature_definitions() as $key => $feature ) {
			$section = $feature['section'];
			if ( ! isset( $counts[ $section ] ) ) {
				continue;
			}

			++$counts[ $section ]['total'];
			if ( Feature_Settings::is_enabled( $key ) ) {
				++$counts[ $section ]['enabled'];
			}
		}

		return $counts;
	}
}
