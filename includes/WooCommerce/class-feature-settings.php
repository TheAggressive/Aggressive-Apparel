<?php
/**
 * Feature Settings Class
 *
 * Manages WooCommerce enhancement feature toggles.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature Settings Class
 *
 * Provides a settings page and helper to toggle individual WooCommerce
 * enhancements on or off. Stores all flags in a single option row.
 *
 * @since 1.17.0
 */
class Feature_Settings {

	/**
	 * Option key for all feature flags.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'aggressive_apparel_wc_features';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'aggressive-apparel-features';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	private const SETTINGS_GROUP = 'aggressive_apparel_features_group';

	/**
	 * Option key for the product filter layout.
	 *
	 * @var string
	 */
	public const FILTER_LAYOUT_OPTION = 'aggressive_apparel_filter_layout';

	/**
	 * Option key for the load more mode.
	 *
	 * @var string
	 */
	public const LOAD_MORE_MODE_OPTION = 'aggressive_apparel_load_more_mode';

	/**
	 * Settings page sections with tab metadata.
	 *
	 * @var array<string, array{label: string, icon: string}>
	 */
	private const SECTIONS = array(
		'catalog'    => array(
			'label' => 'Catalog & Browsing',
			'icon'  => 'dashicons-store',
		),
		'product'    => array(
			'label' => 'Product Page',
			'icon'  => 'dashicons-products',
		),
		'cart'       => array(
			'label' => 'Cart & Mini Cart',
			'icon'  => 'dashicons-cart',
		),
		'engagement' => array(
			'label' => 'Customer Engagement',
			'icon'  => 'dashicons-groups',
		),
		'ui'         => array(
			'label' => 'Mobile & UI',
			'icon'  => 'dashicons-smartphone',
		),
	);

	/**
	 * Feature definitions with metadata.
	 *
	 * @return array<string, array{label: string, description: string, section: string}>
	 */
	public static function get_feature_definitions(): array {
		return array(
			// ── Catalog & Browsing ──────────────────────────────.
			'product_badges'             => array(
				'label'       => __( 'Product Badges', 'aggressive-apparel' ),
				'description' => __( 'Show sale percentage, "New", "Low Stock", and "Bestseller" badges on product cards.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'price_display'              => array(
				'label'       => __( 'Smart Price Display', 'aggressive-apparel' ),
				'description' => __( 'Show "From $X" on archives, "Save X%" on sale items.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'advanced_sorting'           => array(
				'label'       => __( 'Advanced Sorting Options', 'aggressive-apparel' ),
				'description' => __( 'Add Featured, Biggest Savings, and A-Z/Z-A sorting to the product catalog.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'grid_list_toggle'           => array(
				'label'       => __( 'Grid/List View Toggle', 'aggressive-apparel' ),
				'description' => __( 'Toggle between grid and list view on shop archive pages.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'product_filters'            => array(
				'label'       => __( 'Product Filters', 'aggressive-apparel' ),
				'description' => __( 'AJAX product filters with categories, color swatches, sizes, price range, and stock status.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'load_more'                  => array(
				'label'       => __( 'Load More / Infinite Scroll', 'aggressive-apparel' ),
				'description' => __( 'Replace pagination with a Load More button or automatic infinite scroll.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'predictive_search'          => array(
				'label'       => __( 'Predictive Search', 'aggressive-apparel' ),
				'description' => __( 'Show live product search results with thumbnails and prices as users type.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'page_transitions'           => array(
				'label'       => __( 'Page Transitions', 'aggressive-apparel' ),
				'description' => __( 'Smooth crossfade between pages with product image morphing (Chrome/Safari).', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),

			// ── Product Page ────────────────────────────────────.
			'product_tabs'               => array(
				'label'       => __( 'Product Tabs Manager', 'aggressive-apparel' ),
				'description' => __( 'Replace default WooCommerce tabs with 4 display styles (accordion, inline, modern tabs, scrollspy) and add custom tabs.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'size_guide'                 => array(
				'label'       => __( 'Size Guide', 'aggressive-apparel' ),
				'description' => __( 'Manage reusable size guides and assign them to products or categories.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'countdown_timer'            => array(
				'label'       => __( 'Sale Countdown Timer', 'aggressive-apparel' ),
				'description' => __( 'Live countdown for products with scheduled sale end dates.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'sticky_add_to_cart'         => array(
				'label'       => __( 'Sticky Add to Cart', 'aggressive-apparel' ),
				'description' => __( 'Fixed bar with product info and add-to-cart when main button scrolls out of view.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'stock_status'               => array(
				'label'       => __( 'Stock Status', 'aggressive-apparel' ),
				'description' => __( 'Show stock availability indicator (In Stock, Low Stock, Out of Stock) in Quick View.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'quick_view'                 => array(
				'label'       => __( 'Quick View', 'aggressive-apparel' ),
				'description' => __( 'Preview products in a modal overlay from shop pages.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'frequently_bought_together' => array(
				'label'       => __( 'Frequently Bought Together', 'aggressive-apparel' ),
				'description' => __( 'Show recommended products with checkboxes and combined add-to-cart on product pages.', 'aggressive-apparel' ),
				'section'     => 'product',
			),

			// ── Cart & Mini Cart ────────────────────────────────.
			'free_shipping_bar'          => array(
				'label'       => __( 'Free Shipping Progress Bar', 'aggressive-apparel' ),
				'description' => __( 'Show progress toward free shipping threshold in the cart.', 'aggressive-apparel' ),
				'section'     => 'cart',
			),
			'mini_cart_styling'          => array(
				'label'       => __( 'Mini Cart Styling', 'aggressive-apparel' ),
				'description' => __( 'Style the native WooCommerce mini-cart to match the theme design.', 'aggressive-apparel' ),
				'section'     => 'cart',
			),

			// ── Customer Engagement ─────────────────────────────.
			'recently_viewed'            => array(
				'label'       => __( 'Recently Viewed Products', 'aggressive-apparel' ),
				'description' => __( 'Show customers their recently viewed products using browser storage.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'wishlist'                   => array(
				'label'       => __( 'Wishlist', 'aggressive-apparel' ),
				'description' => __( 'Save-for-later with heart icon toggle and dedicated wishlist page.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'social_proof'               => array(
				'label'       => __( 'Social Proof Notifications', 'aggressive-apparel' ),
				'description' => __( 'Show recent purchase toast notifications to build urgency.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'back_in_stock'              => array(
				'label'       => __( 'Back in Stock Notifications', 'aggressive-apparel' ),
				'description' => __( 'Let customers subscribe to out-of-stock products and get notified when restocked.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'exit_intent'                => array(
				'label'       => __( 'Exit Intent Email Capture', 'aggressive-apparel' ),
				'description' => __( 'Show an email signup popup when visitors are about to leave. Configurable text and re-show interval.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),

			// ── Mobile & UI ─────────────────────────────────────.
			'swatch_tooltips'            => array(
				'label'       => __( 'Swatch Tooltips', 'aggressive-apparel' ),
				'description' => __( 'Show fabric name and composition on color swatch hover.', 'aggressive-apparel' ),
				'section'     => 'ui',
			),
			'mobile_bottom_nav'          => array(
				'label'       => __( 'Mobile Bottom Navigation', 'aggressive-apparel' ),
				'description' => __( 'Fixed bottom bar on mobile with Home, Search, Cart, and Account.', 'aggressive-apparel' ),
				'section'     => 'ui',
			),
		);
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
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
		);

		if ( $hook ) {
			add_action( 'admin_print_styles-' . $hook, array( $this, 'enqueue_admin_styles' ) );
		}
	}

	/**
	 * Enqueue admin styles for the settings page.
	 *
	 * @return void
	 */
	public function enqueue_admin_styles(): void {
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/admin/store-enhancements-admin.css';
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		wp_enqueue_style(
			'aggressive-apparel-store-enhancements-admin',
			AGGRESSIVE_APPAREL_URI . '/build/styles/admin/store-enhancements-admin.css',
			array(),
			(string) filemtime( $css_file ),
		);
	}

	/**
	 * Register the single option and settings sections.
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
			)
		);

		foreach ( self::SECTIONS as $id => $meta ) {
			add_settings_section(
				'aggressive_apparel_features_' . $id,
				$meta['label'],
				'__return_false',
				self::PAGE_SLUG,
			);
		}

		// Register sub-setting options (always, so saved values persist).
		register_setting(
			self::SETTINGS_GROUP,
			self::FILTER_LAYOUT_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'drawer',
				'sanitize_callback' => array( $this, 'sanitize_filter_layout' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::LOAD_MORE_MODE_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'load_more',
				'sanitize_callback' => array( $this, 'sanitize_load_more_mode' ),
			)
		);

		foreach ( self::get_feature_definitions() as $key => $feature ) {
			add_settings_field(
				'feature_' . $key,
				$feature['label'],
				array( $this, 'render_toggle_field' ),
				self::PAGE_SLUG,
				'aggressive_apparel_features_' . $feature['section'],
				array(
					'key'         => $key,
					'description' => $feature['description'],
				),
			);

			// Sub-settings rendered immediately after their parent toggle.
			if ( 'product_filters' === $key && self::is_enabled( 'product_filters' ) ) {
				add_settings_field(
					'filter_layout',
					__( 'Filter Layout', 'aggressive-apparel' ),
					array( $this, 'render_filter_layout_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_catalog',
				);
			}

			if ( 'load_more' === $key && self::is_enabled( 'load_more' ) ) {
				add_settings_field(
					'load_more_mode',
					__( 'Load More Mode', 'aggressive-apparel' ),
					array( $this, 'render_load_more_mode_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_catalog',
				);
			}
		}
	}

	/**
	 * Sanitize the feature flags array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, bool> Sanitized flags.
	 */
	public function sanitize_features( $input ): array {
		$valid     = array_keys( self::get_feature_definitions() );
		$sanitized = array();

		foreach ( $valid as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Render a single toggle checkbox field.
	 *
	 * @param array $args Field arguments containing key and description.
	 * @return void
	 */
	public function render_toggle_field( array $args ): void {
		$key     = $args['key'];
		$enabled = self::is_enabled( $key );

		printf(
			'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			checked( $enabled, true, false ),
			esc_html( $args['description'] )
		);
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

		$section_counts = $this->get_section_counts();
		$first_key      = array_key_first( self::SECTIONS );

		echo '<div class="wrap aa-features-wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		settings_errors();
		echo '<p>' . esc_html__( 'Enable or disable individual WooCommerce enhancements. Disabled features have zero performance overhead.', 'aggressive-apparel' ) . '</p>';
		echo '<form method="post" action="options.php">';

		settings_fields( self::SETTINGS_GROUP );

		// Tab navigation.
		echo '<nav class="nav-tab-wrapper aa-features-tabs">';
		foreach ( self::SECTIONS as $id => $meta ) {
			$active = ( $id === $first_key ) ? ' nav-tab-active' : '';
			$counts = $section_counts[ $id ];

			printf(
				'<a href="#" class="nav-tab%s" data-tab="%s"><span class="dashicons %s"></span> %s <span class="aa-features-tab-count">%d/%d</span></a>',
				esc_attr( $active ),
				esc_attr( $id ),
				esc_attr( $meta['icon'] ),
				esc_html( $meta['label'] ),
				absint( $counts['enabled'] ),
				absint( $counts['total'] ),
			);
		}
		echo '</nav>';

		// Tab panels.
		foreach ( self::SECTIONS as $id => $meta ) {
			$hidden     = ( $id !== $first_key ) ? ' hidden' : '';
			$section_id = 'aggressive_apparel_features_' . $id;

			printf( '<div class="aa-features-tab-panel" id="tab-%s"%s>', esc_attr( $id ), esc_attr( $hidden ) );
			echo '<table class="form-table" role="presentation">';
			do_settings_fields( self::PAGE_SLUG, $section_id );
			echo '</table>';
			echo '</div>';
		}

		submit_button( __( 'Save Changes', 'aggressive-apparel' ) );

		echo '</form>';

		// Inline tab-switching script.
		$this->render_tab_script();

		echo '</div>';
	}

	/**
	 * Render inline JavaScript for tab switching.
	 *
	 * @return void
	 */
	private function render_tab_script(): void {
		?>
		<script>
		(function() {
			var KEY = 'aa_features_tab';
			var tabs = document.querySelectorAll('.aa-features-tabs .nav-tab');
			var panels = document.querySelectorAll('.aa-features-tab-panel');

			function activate(id) {
				tabs.forEach(function(t) {
					t.classList.toggle('nav-tab-active', t.dataset.tab === id);
				});
				panels.forEach(function(p) {
					p.hidden = p.id !== 'tab-' + id;
				});
				try { localStorage.setItem(KEY, id); } catch(e) {}
			}

			tabs.forEach(function(t) {
				t.addEventListener('click', function(e) {
					e.preventDefault();
					activate(t.dataset.tab);
				});
			});

			try {
				var saved = localStorage.getItem(KEY);
				if (saved && document.getElementById('tab-' + saved)) {
					activate(saved);
				}
			} catch(e) {}
		})();
		</script>
		<?php
	}

	/**
	 * Sanitize the filter layout option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized layout value.
	 */
	public function sanitize_filter_layout( $input ): string {
		$valid = array( 'drawer', 'sidebar', 'horizontal' );
		return in_array( $input, $valid, true ) ? $input : 'drawer';
	}

	/**
	 * Render the filter layout select field.
	 *
	 * @return void
	 */
	public function render_filter_layout_field(): void {
		$layout  = get_option( self::FILTER_LAYOUT_OPTION, 'drawer' );
		$options = array(
			'drawer'     => __( 'Drawer (slide-out panel)', 'aggressive-apparel' ),
			'sidebar'    => __( 'Sidebar (persistent column)', 'aggressive-apparel' ),
			'horizontal' => __( 'Horizontal Bar (dropdown filters)', 'aggressive-apparel' ),
		);

		printf( '<select name="%s">', esc_attr( self::FILTER_LAYOUT_OPTION ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $layout, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose how filters are displayed on shop pages. Sidebar and Horizontal Bar fall back to Drawer on mobile.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Sanitize the load more mode option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized mode value.
	 */
	public function sanitize_load_more_mode( $input ): string {
		$valid = array( 'load_more', 'infinite_scroll' );
		return in_array( $input, $valid, true ) ? $input : 'load_more';
	}

	/**
	 * Render the load more mode select field.
	 *
	 * @return void
	 */
	public function render_load_more_mode_field(): void {
		$mode    = get_option( self::LOAD_MORE_MODE_OPTION, 'load_more' );
		$options = array(
			'load_more'       => __( 'Load More Button', 'aggressive-apparel' ),
			'infinite_scroll' => __( 'Infinite Scroll', 'aggressive-apparel' ),
		);

		printf( '<select name="%s">', esc_attr( self::LOAD_MORE_MODE_OPTION ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $mode, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Load More shows a button; Infinite Scroll loads automatically as users scroll down.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Get enabled/total feature counts per section.
	 *
	 * @return array<string, array{enabled: int, total: int}>
	 */
	private function get_section_counts(): array {
		$counts = array();
		foreach ( self::SECTIONS as $id => $meta ) {
			$counts[ $id ] = array(
				'enabled' => 0,
				'total'   => 0,
			);
		}

		foreach ( self::get_feature_definitions() as $key => $feature ) {
			$section = $feature['section'];
			if ( ! isset( $counts[ $section ] ) ) {
				continue;
			}

			++$counts[ $section ]['total'];
			if ( self::is_enabled( $key ) ) {
				++$counts[ $section ]['enabled'];
			}
		}

		return $counts;
	}

	/**
	 * Check whether a specific feature is enabled.
	 *
	 * All features default to OFF. The admin must explicitly enable
	 * each feature via Appearance → Store Enhancements.
	 *
	 * @param string $feature Feature key.
	 * @return bool True if enabled.
	 */
	public static function is_enabled( string $feature ): bool {
		$options = get_option( self::OPTION_KEY, array() );

		return ! empty( $options[ $feature ] );
	}
}
