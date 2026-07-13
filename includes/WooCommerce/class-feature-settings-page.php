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
		add_filter(
			'option_page_capability_' . Feature_Settings::SETTINGS_GROUP,
			array( $this, 'get_settings_capability' )
		);
	}

	/**
	 * Capability required to save Store Enhancements options.
	 *
	 * Aligns the Settings API save gate with the Appearance menu page.
	 *
	 * @return string
	 */
	public function get_settings_capability(): string {
		return 'edit_theme_options';
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
			$control_id = 'aa-feature-' . $key;

			add_settings_field(
				'feature_' . $key,
				$feature['label'],
				array( $this->fields, 'render_toggle_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_' . $feature['section'],
				array(
					'key'         => $key,
					'description' => $feature['description'],
					'label_for'   => $control_id,
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
			$definition['label_for'] = $definition['option'];

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
	 * Sub-fields are always registered and progressive-disclosure JS shows
	 * them when their parent feature checkbox(es) are checked — so enabling
	 * a feature reveals options before save.
	 *
	 * @param string $key Parent feature key.
	 * @return void
	 */
	private function register_feature_sub_fields( string $key ): void {
		if ( 'product_filters' === $key ) {
			add_settings_field(
				'filter_layout',
				__( 'Filter Layout', 'aggressive-apparel' ),
				array( $this->fields, 'render_filter_layout_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
				array(
					'label_for' => Feature_Settings::FILTER_LAYOUT_OPTION,
					'class'     => $this->sub_field_classes( 'product_filters' ),
				),
			);
		}

		if ( 'load_more' === $key ) {
			add_settings_field(
				'load_more_mode',
				__( 'Load More Mode', 'aggressive-apparel' ),
				array( $this->fields, 'render_load_more_mode_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
				array(
					'label_for' => Feature_Settings::LOAD_MORE_MODE_OPTION,
					'class'     => $this->sub_field_classes( 'load_more' ),
				),
			);
		}

		if ( 'catalog_hover_image' === $key ) {
			$hover_class = $this->sub_field_classes( 'catalog_hover_image' );

			add_settings_field(
				'hover_image_exit_duration',
				__( 'Primary Image Exit Duration', 'aggressive-apparel' ),
				array( $this->fields, 'render_hover_image_exit_duration_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
				array(
					'label_for' => Feature_Settings::HOVER_IMAGE_EXIT_DURATION_OPTION,
					'class'     => $hover_class,
				),
			);

			add_settings_field(
				'hover_image_exit_animation',
				__( 'Primary Image Exit Animation', 'aggressive-apparel' ),
				array( $this->fields, 'render_hover_image_exit_animation_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
				array(
					'label_for' => Feature_Settings::HOVER_IMAGE_EXIT_ANIMATION_OPTION,
					'class'     => $hover_class,
				),
			);

			add_settings_field(
				'hover_image_animation',
				__( 'Hover Animation', 'aggressive-apparel' ),
				array( $this->fields, 'render_hover_image_animation_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_catalog',
				array(
					'label_for' => Feature_Settings::HOVER_IMAGE_ANIMATION_OPTION,
					'class'     => $hover_class,
				),
			);
		}

		if ( 'wishlist' === $key ) {
			add_settings_field(
				'wishlist_button_placement',
				__( 'Wishlist Button Placement', 'aggressive-apparel' ),
				array( $this->fields, 'render_wishlist_button_placement_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_engagement',
				array(
					'label_for' => Feature_Settings::WISHLIST_BUTTON_PLACEMENT_OPTION,
					'class'     => $this->sub_field_classes( 'wishlist' ),
				),
			);
		}

		if ( 'quick_view' === $key ) {
			$qv_class = $this->sub_field_classes( 'quick_view' );

			add_settings_field(
				'quick_view_trigger_style',
				__( 'Quick View Card Style', 'aggressive-apparel' ),
				array( $this->fields, 'render_quick_view_trigger_style_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_product',
				array(
					'label_for' => Feature_Settings::QUICK_VIEW_TRIGGER_STYLE_OPTION,
					'class'     => $qv_class,
				),
			);

			add_settings_field(
				'quick_view_trigger_position',
				__( 'Quick View Card Position', 'aggressive-apparel' ),
				array( $this->fields, 'render_quick_view_trigger_position_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_product',
				array(
					'label_for' => Feature_Settings::QUICK_VIEW_TRIGGER_POSITION_OPTION,
					'class'     => $qv_class,
				),
			);

			add_settings_field(
				'quick_view_media_wishlist',
				__( 'Wishlist on Product Images', 'aggressive-apparel' ),
				array( $this->fields, 'render_quick_view_media_wishlist_field' ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_product',
				array(
					'label_for' => Feature_Settings::QUICK_VIEW_MEDIA_WISHLIST_OPTION,
					'class'     => $this->sub_field_classes( 'quick_view', 'wishlist' ),
				),
			);
		}

		if ( 'social_proof' === $key ) {
			$this->register_social_proof_fields();
		}
	}

	/**
	 * Build Settings API row classes for a progressive-disclosure sub-field.
	 *
	 * @param string ...$parents Feature keys that must be enabled for the row to show.
	 * @return string
	 */
	private function sub_field_classes( string ...$parents ): string {
		$classes = array( 'aa-features-sub-field' );

		foreach ( $parents as $parent ) {
			$classes[] = 'aa-features-depends-on-' . sanitize_key( $parent );
		}

		return implode( ' ', $classes );
	}

	/**
	 * Register the Social Proof sub-fields.
	 *
	 * @return void
	 */
	private function register_social_proof_fields(): void {
		$social_proof_fields = array(
			'social_proof_sources'              => array(
				__( 'Notification Sources', 'aggressive-apparel' ),
				'render_social_proof_sources_field',
				null,
			),
			'social_proof_trust_messages'       => array(
				__( 'Trust Messages', 'aggressive-apparel' ),
				'render_social_proof_trust_messages_field',
				Feature_Settings::SOCIAL_PROOF_TRUST_MESSAGES_OPTION,
			),
			'social_proof_announcements'        => array(
				__( 'Custom Announcements', 'aggressive-apparel' ),
				'render_social_proof_announcements_field',
				Feature_Settings::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION,
			),
			'social_proof_purchase_badge_icon'  => array(
				__( 'Badge on Purchase Thumbnails', 'aggressive-apparel' ),
				'render_social_proof_purchase_badge_icon_field',
				Feature_Settings::SOCIAL_PROOF_PURCHASE_BADGE_ICON_OPTION,
			),
			'social_proof_icon_help'            => array(
				__( 'Icons: reference & customization', 'aggressive-apparel' ),
				'render_social_proof_icon_help_field',
				null,
			),
			'social_proof_engagement_min_sales' => array(
				__( 'Engagement: Minimum Lifetime Sales', 'aggressive-apparel' ),
				'render_social_proof_engagement_min_sales_field',
				Feature_Settings::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION,
			),
			'social_proof_display_mode'         => array(
				__( 'Purchase Display Mode', 'aggressive-apparel' ),
				'render_social_proof_display_mode_field',
				Feature_Settings::SOCIAL_PROOF_DISPLAY_MODE_OPTION,
			),
			'social_proof_location_granularity' => array(
				__( 'Location Granularity', 'aggressive-apparel' ),
				'render_social_proof_location_granularity_field',
				Feature_Settings::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION,
			),
			'social_proof_min_order_age'        => array(
				__( 'Minimum Order Age (Minutes)', 'aggressive-apparel' ),
				'render_social_proof_min_order_age_field',
				Feature_Settings::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION,
			),
			'social_proof_demo'                 => array(
				__( 'Demo Preview (Admin Only)', 'aggressive-apparel' ),
				'render_social_proof_demo_field',
				Feature_Settings::SOCIAL_PROOF_DEMO_OPTION,
			),
		);

		foreach ( $social_proof_fields as $id => $field ) {
			$args = array(
				'class' => $this->sub_field_classes( 'social_proof' ),
			);

			if ( ! empty( $field[2] ) ) {
				$args['label_for'] = $field[2];
			}

			add_settings_field(
				$id,
				$field[0],
				array( $this->fields, $field[1] ),
				Feature_Settings::PAGE_SLUG,
				'aggressive_apparel_features_engagement',
				$args,
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
		$page_title     = get_admin_page_title();
		if ( ! is_string( $page_title ) || '' === $page_title ) {
			$page_title = __( 'Store Enhancements', 'aggressive-apparel' );
		}

		echo '<div class="wrap aa-features-wrap">';
		echo '<h1>' . esc_html( $page_title ) . '</h1>';
		settings_errors();
		echo '<p>' . esc_html__( 'Enable or disable individual WooCommerce enhancements. Disabled features have zero performance overhead.', 'aggressive-apparel' ) . '</p>';
		echo '<form method="post" action="options.php">';

		settings_fields( Feature_Settings::SETTINGS_GROUP );

		// Tab navigation.
		echo '<div class="aa-features-tabs-wrap">';
		echo '<div class="nav-tab-wrapper aa-features-tabs" role="tablist" aria-label="' . esc_attr__( 'Store Enhancements sections', 'aggressive-apparel' ) . '">';
		foreach ( $sections as $id => $meta ) {
			$is_active  = ( $id === $first_key );
			$active     = $is_active ? ' nav-tab-active' : '';
			$counts     = $section_counts[ $id ];
			$count_html = '';
			$tab_id     = 'aa-features-tab-' . $id;
			$panel_id   = 'tab-' . $id;

			if ( $counts['total'] > 0 ) {
				$count_html = sprintf(
					' <span class="aa-features-tab-count" aria-label="%1$s">%2$d/%3$d</span>',
					esc_attr(
						sprintf(
							/* translators: 1: enabled feature count, 2: total features in section. */
							__( '%1$d of %2$d features enabled', 'aggressive-apparel' ),
							absint( $counts['enabled'] ),
							absint( $counts['total'] ),
						)
					),
					absint( $counts['enabled'] ),
					absint( $counts['total'] ),
				);
			}

			printf(
				'<button type="button" class="nav-tab%1$s" role="tab" id="%2$s" data-tab="%3$s" aria-controls="%4$s" aria-selected="%5$s" tabindex="%6$s"><span class="dashicons %7$s" aria-hidden="true"></span> <span class="aa-features-tab-label">%8$s</span>%9$s</button>',
				esc_attr( $active ),
				esc_attr( $tab_id ),
				esc_attr( $id ),
				esc_attr( $panel_id ),
				$is_active ? 'true' : 'false',
				$is_active ? '0' : '-1',
				esc_attr( $meta['icon'] ),
				esc_html( $meta['label'] ),
				wp_kses_post( $count_html )
			);
		}
		echo '</div>';
		echo '</div>';

		// Tab panels.
		foreach ( $sections as $id => $meta ) {
			$is_active  = ( $id === $first_key );
			$section_id = 'aggressive_apparel_features_' . $id;
			$tab_id     = 'aa-features-tab-' . $id;
			$panel_id   = 'tab-' . $id;

			printf(
				'<div class="aa-features-tab-panel" role="tabpanel" id="%1$s" aria-labelledby="%2$s"%3$s tabindex="0">',
				esc_attr( $panel_id ),
				esc_attr( $tab_id ),
				$is_active ? '' : ' hidden'
			);
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
