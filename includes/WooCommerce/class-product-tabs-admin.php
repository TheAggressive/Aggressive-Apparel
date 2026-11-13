<?php
/**
 * Product Tabs admin UI.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings and meta box UI for Product Tabs.
 *
 * @since 1.17.0
 */
class Product_Tabs_Admin {

	/**
	 * Sanitization service.
	 *
	 * @var Product_Tabs_Sanitizer
	 */
	private Product_Tabs_Sanitizer $sanitizer;

	/**
	 * Product tabs orchestrator.
	 *
	 * @var Product_Tabs
	 */
	private Product_Tabs $tabs;

	/**
	 * Custom-tabs repeater markup.
	 *
	 * @var Product_Tabs_Repeater
	 */
	private Product_Tabs_Repeater $repeater;

	/**
	 * Constructor.
	 *
	 * @param Product_Tabs_Sanitizer $sanitizer Sanitization service.
	 * @param Product_Tabs           $tabs      Product tabs orchestrator.
	 */
	public function __construct( Product_Tabs_Sanitizer $sanitizer, Product_Tabs $tabs ) {
		$this->sanitizer = $sanitizer;
		$this->tabs      = $tabs;
		$this->repeater  = new Product_Tabs_Repeater();
	}

	/**
	 * Register Product Tabs settings for the global management page.
	 *
	 * @return void
	 */
	public function register_global_settings(): void {
		register_setting(
			Product_Tabs_Config::SETTINGS_GROUP,
			Product_Tabs_Config::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->sanitizer, 'sanitize_global_tabs' ),
				'default'           => array(),
			),
		);

		add_filter( 'option_page_capability_' . Product_Tabs_Config::SETTINGS_GROUP, array( $this, 'get_settings_capability' ) );
	}

	/**
	 * Get the capability required to manage global Product Tabs.
	 *
	 * @return string Required capability.
	 */
	public function get_settings_capability(): string {
		return 'manage_woocommerce';
	}

	/**
	 * Add the global Product Tabs page under Products.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Product Tabs', 'aggressive-apparel' ),
			__( 'Product Tabs', 'aggressive-apparel' ),
			$this->get_settings_capability(),
			Product_Tabs_Config::PAGE_SLUG,
			array( $this, 'render_global_settings_page' ),
		);
	}

	/**
	 * Render the global Product Tabs settings page.
	 *
	 * @return void
	 */
	public function render_global_settings_page(): void {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return;
		}

		echo '<div class="wrap aa-product-tabs-admin-wrap">';
		echo '<h1>' . esc_html__( 'Product Tabs', 'aggressive-apparel' ) . '</h1>';
		settings_errors();
		echo '<p>' . esc_html__( 'Manage store-wide Product Details tabs and display behavior. Product-specific overrides still live on each product edit screen.', 'aggressive-apparel' ) . '</p>';
		echo '<form method="post" action="options.php">';

		settings_fields( Product_Tabs_Config::SETTINGS_GROUP );

		echo '<section class="aa-tabs-admin-panel">';
		echo '<div class="aa-tabs-admin-panel__header">';
		echo '<p class="aa-tabs-admin-panel__title">' . esc_html__( 'Display Style', 'aggressive-apparel' ) . '</p>';
		echo '<span class="aa-tabs-badge aa-tabs-badge--muted">' . esc_html__( 'Global', 'aggressive-apparel' ) . '</span>';
		echo '</div>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->render_settings_table_row(
			'aa_product_tabs_style',
			__( 'Product Info Display Style', 'aggressive-apparel' ),
			array( $this, 'render_style_setting' )
		);
		echo '</tbody></table>';
		echo '</section>';

		echo '<section class="aa-tabs-admin-panel">';
		echo '<div class="aa-tabs-admin-panel__header">';
		echo '<p class="aa-tabs-admin-panel__title">' . esc_html__( 'Default Built-In Tabs', 'aggressive-apparel' ) . '</p>';
		echo '<span class="aa-tabs-badge aa-tabs-badge--muted">' . esc_html__( 'Fallbacks', 'aggressive-apparel' ) . '</span>';
		echo '</div>';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $this->get_global_textarea_settings() as $key => $field ) {
			$this->render_settings_table_row(
				'aa_product_tabs_' . $key,
				$field['label'],
				function () use ( $key, $field ): void {
					$this->render_textarea_setting(
						array(
							'key'         => $key,
							'label_for'   => 'aa_product_tabs_' . $key,
							'description' => $field['description'],
							'rows'        => $field['rows'],
						)
					);
				}
			);
		}
		echo '</tbody></table>';
		echo '</section>';

		echo '<section class="aa-tabs-admin-panel">';
		echo '<div class="aa-tabs-admin-panel__header">';
		echo '<p class="aa-tabs-admin-panel__title">' . esc_html__( 'Global Custom Tabs', 'aggressive-apparel' ) . '</p>';
		echo '<span class="aa-tabs-badge aa-tabs-badge--success">' . esc_html__( 'No-code builder', 'aggressive-apparel' ) . '</span>';
		echo '</div>';
		$this->render_global_custom_tabs_setting();
		echo '</section>';

		submit_button( __( 'Save Product Tabs', 'aggressive-apparel' ) );

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render a settings field row for the global page.
	 *
	 * @param string   $label_for Field ID used by the table label.
	 * @param string   $label     Field label.
	 * @param callable $callback  Field renderer.
	 * @return void
	 */
	public function render_settings_table_row( string $label_for, string $label, callable $callback ): void {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $label_for ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td>';
		call_user_func( $callback );
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Get global textarea settings metadata.
	 *
	 * @return array<string, array{label: string, description: string, rows: int}> Field metadata.
	 */
	public function get_global_textarea_settings(): array {
		return array(
			'care_instructions' => array(
				'label'       => __( 'Default Care Instructions', 'aggressive-apparel' ),
				'description' => __( 'Shown when a product does not have its own Care Instructions value.', 'aggressive-apparel' ),
				'rows'        => 5,
			),
			'shipping_returns'  => array(
				'label'       => __( 'Shipping & Returns Tab', 'aggressive-apparel' ),
				'description' => __( 'Leave empty to hide this tab from Product Details.', 'aggressive-apparel' ),
				'rows'        => 6,
			),
			'sustainability'    => array(
				'label'       => __( 'Sustainability Tab', 'aggressive-apparel' ),
				'description' => __( 'Leave empty to hide this tab from Product Details.', 'aggressive-apparel' ),
				'rows'        => 6,
			),
		);
	}

	/**
	 * Render the display style setting.
	 *
	 * @return void
	 */
	public function render_style_setting(): void {
		$style = $this->tabs->get_display_style();

		$styles = array(
			'accordion'   => __( 'Accordion — collapsible sections (recommended)', 'aggressive-apparel' ),
			'inline'      => __( 'Inline — all sections visible, no interaction', 'aggressive-apparel' ),
			'modern-tabs' => __( 'Modern Tabs — pill-style tab navigation', 'aggressive-apparel' ),
			'scrollspy'   => __( 'Scrollspy — sticky sidebar with scroll tracking', 'aggressive-apparel' ),
		);

		echo '<select id="aa_product_tabs_style" name="' . esc_attr( Product_Tabs_Config::OPTION_KEY ) . '[display_style]">';
		foreach ( $styles as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $style, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose how product information is displayed on single product pages. Requires the Product Tabs Manager toggle to be enabled.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render a global tab textarea setting.
	 *
	 * @param array $args Settings field args.
	 * @return void
	 */
	public function render_textarea_setting( array $args ): void {
		$options = get_option( Product_Tabs_Config::OPTION_KEY, array() );
		$key     = $args['key'];
		$value   = is_array( $options ) ? (string) ( $options[ $key ] ?? '' ) : '';
		$rows    = max( 3, (int) $args['rows'] );

		printf(
			'<textarea id="%1$s" name="%2$s[%3$s]" rows="%4$s" class="large-text">%5$s</textarea>',
			esc_attr( $args['label_for'] ),
			esc_attr( Product_Tabs_Config::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( (string) $rows ),
			esc_textarea( $value ),
		);

		echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
	}

	/**
	 * Render the global custom tabs repeater.
	 *
	 * @return void
	 */
	public function render_global_custom_tabs_setting(): void {
		$options = get_option( Product_Tabs_Config::OPTION_KEY, array() );
		$tabs    = is_array( $options ) && isset( $options['custom_tabs'] ) && is_array( $options['custom_tabs'] )
			? $this->sanitizer->sanitize_custom_tabs( $options['custom_tabs'] )
			: array();

		$this->repeater->render_custom_tabs_repeater(
			$tabs,
			Product_Tabs_Config::OPTION_KEY . '[custom_tabs]',
			'aa_global_custom_tabs',
			__( 'Add store-wide tabs that appear in Product Details for every product. Product-specific custom tabs can be added on each product edit screen.', 'aggressive-apparel' )
		);
	}

	/**
	 * Add the product-level tabs meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'aggressive_apparel_product_tabs',
			__( 'Extra Product Tabs', 'aggressive-apparel' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'default',
		);
	}

	/**
	 * Render the product-level tabs meta box.
	 *
	 * @param \WP_Post $post Product post.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'aggressive_apparel_tabs_nonce', '_aa_tabs_nonce' );

		$care = (string) get_post_meta( $post->ID, '_aggressive_apparel_care_instructions', true );
		$tabs = get_post_meta( $post->ID, Product_Tabs_Config::PRODUCT_CUSTOM_TABS_META_KEY, true );
		$tabs = $this->sanitizer->sanitize_custom_tabs( $tabs );

		echo '<input type="hidden" name="aa_product_tabs_submitted" value="1">';
		echo '<section class="aa-tabs-admin-panel">';
		echo '<div class="aa-tabs-admin-panel__header">';
		echo '<p class="aa-tabs-admin-panel__title">' . esc_html__( 'Product Content Defaults', 'aggressive-apparel' ) . '</p>';
		echo '<span class="aa-tabs-badge aa-tabs-badge--muted">' . esc_html__( 'Product level', 'aggressive-apparel' ) . '</span>';
		echo '</div>';
		echo '<p><label for="aa_care_instructions"><strong>' . esc_html__( 'Care Instructions', 'aggressive-apparel' ) . '</strong></label></p>';
		echo '<textarea id="aa_care_instructions" name="aa_care_instructions" rows="4" class="widefat">' . esc_textarea( $care ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Leave empty to use the global default from Products → Product Tabs.', 'aggressive-apparel' ) . '</p>';
		echo '</section>';

		$this->render_global_tab_overrides( $post->ID );

		echo '<section class="aa-tabs-admin-panel">';
		echo '<div class="aa-tabs-admin-panel__header">';
		echo '<p class="aa-tabs-admin-panel__title">' . esc_html__( 'Product-Specific Custom Tabs', 'aggressive-apparel' ) . '</p>';
		echo '<span class="aa-tabs-badge aa-tabs-badge--success">' . esc_html(
			sprintf(
				/* translators: %d: number of saved product-specific tabs. */
				_n( '%d saved product-specific tab.', '%d saved product-specific tabs.', count( $tabs ), 'aggressive-apparel' ),
				count( $tabs )
			)
		) . '</span>';
		echo '</div>';
		$this->repeater->render_custom_tabs_repeater(
			$tabs,
			'aa_custom_tabs',
			'aa_product_custom_tabs_' . (string) $post->ID,
			__( 'These tabs appear only on this product and are added to the Product Details block.', 'aggressive-apparel' ),
			(int) $post->ID
		);
		echo '</section>';
	}

	/**
	 * Save product-level tab meta.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function save_meta( int $post_id ): void {
		$has_tabs_nonce = isset( $_POST['_aa_tabs_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aa_tabs_nonce'] ) ), 'aggressive_apparel_tabs_nonce' );

		$has_wc_nonce = isset( $_POST['woocommerce_meta_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' );

		if ( ! $has_tabs_nonce && ! $has_wc_nonce ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$has_tabs_fields = isset( $_POST['aa_product_tabs_submitted'] )
			|| isset( $_POST['aa_care_instructions'] )
			|| isset( $_POST['aa_tab_overrides'] )
			|| isset( $_POST['aa_custom_tabs'] );

		if ( ! $has_tabs_fields ) {
			return;
		}

		if ( isset( $_POST['aa_care_instructions'] ) ) {
			$care = wp_kses_post( wp_unslash( $_POST['aa_care_instructions'] ) );

			update_post_meta( $post_id, '_aggressive_apparel_care_instructions', $care );
		}

		if ( isset( $_POST['aa_tab_overrides'] ) ) {
			$overrides = aggressive_apparel_sanitize_tab_overrides(
				wp_unslash( $_POST['aa_tab_overrides'] ),
				$this->sanitizer
			);

			if ( empty( $overrides ) ) {
				delete_post_meta( $post_id, Product_Tabs_Config::PRODUCT_TAB_OVERRIDES_META_KEY );
			} else {
				update_post_meta( $post_id, Product_Tabs_Config::PRODUCT_TAB_OVERRIDES_META_KEY, $overrides );
			}
		}

		if ( isset( $_POST['aa_custom_tabs'] ) ) {
			$custom_tabs = aggressive_apparel_sanitize_custom_tabs(
				wp_unslash( $_POST['aa_custom_tabs'] ),
				$this->sanitizer
			);

			if ( empty( $custom_tabs ) ) {
				delete_post_meta( $post_id, Product_Tabs_Config::PRODUCT_CUSTOM_TABS_META_KEY );
			} else {
				update_post_meta( $post_id, Product_Tabs_Config::PRODUCT_CUSTOM_TABS_META_KEY, $custom_tabs );
			}
		}
	}

	/**
	 * Render per-product controls for global tab overrides.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function render_global_tab_overrides( int $post_id ): void {
		$options     = get_option( Product_Tabs_Config::OPTION_KEY, array() );
		$base_tabs   = $this->get_overridable_base_tabs( is_array( $options ) ? $options : array() );
		$global_tabs = is_array( $options ) && isset( $options['custom_tabs'] ) && is_array( $options['custom_tabs'] )
			? $this->sanitizer->sanitize_custom_tabs( $options['custom_tabs'] )
			: array();
		$tabs        = array_merge( $base_tabs, $global_tabs );

		echo '<section class="aa-tabs-admin-panel">';
		echo '<div class="aa-tabs-admin-panel__header">';
		echo '<p class="aa-tabs-admin-panel__title">' . esc_html__( 'Global Tab Overrides', 'aggressive-apparel' ) . '</p>';
		echo '<span class="aa-tabs-badge aa-tabs-badge--muted">' . esc_html__( 'Optional', 'aggressive-apparel' ) . '</span>';
		echo '</div>';

		if ( empty( $tabs ) ) {
			echo '<p class="description">' . esc_html__( 'No global custom tabs are configured yet. Add them in Products → Product Tabs.', 'aggressive-apparel' ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<p class="description">' . esc_html__( 'Customize, append to, or hide global tabs for this product only.', 'aggressive-apparel' ) . '</p>';

		$overrides = $this->sanitizer->get_product_tab_overrides( $post_id );
		$modes     = array(
			'inherit'  => __( 'Use global behavior', 'aggressive-apparel' ),
			'override' => __( 'Override content', 'aggressive-apparel' ),
			'append'   => __( 'Append product content', 'aggressive-apparel' ),
			'hide'     => __( 'Hide on this product', 'aggressive-apparel' ),
		);

		foreach ( $tabs as $tab ) {
			$key      = (string) $tab['key'];
			$override = $overrides[ $key ] ?? array();
			$mode     = (string) ( $override['mode'] ?? 'inherit' );
			$content  = (string) ( $override['content'] ?? '' );

			echo '<div class="aa-tab-override" data-mode="' . esc_attr( $mode ) . '">';
			echo '<div class="aa-tab-override__header">';
			echo '<p class="aa-tab-override__title">' . esc_html( (string) $tab['title'] ) . '</p>';
			echo '<span class="aa-tabs-badge aa-tabs-badge--mode">' . esc_html( $modes[ $mode ] ?? $modes['inherit'] ) . '</span>';
			echo '</div>';
			echo '<div class="aa-tab-override__body">';
			echo '<p><label>' . esc_html__( 'Product behavior', 'aggressive-apparel' ) . '<br><select class="aa-tab-override__mode" name="' . esc_attr( 'aa_tab_overrides[' . $key . '][mode]' ) . '">';
			foreach ( $modes as $value => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $value ),
					selected( $mode, $value, false ),
					esc_html( $label )
				);
			}
			echo '</select></label></p>';
			echo '<p class="aa-tab-override__content"><label>' . esc_html__( 'Override / Append Content', 'aggressive-apparel' ) . '<br><textarea name="' . esc_attr( 'aa_tab_overrides[' . $key . '][content]' ) . '" rows="4" class="large-text">' . esc_textarea( $content ) . '</textarea></label><span class="description">' . esc_html__( 'Shown only when this product overrides or appends to the global tab.', 'aggressive-apparel' ) . '</span></p>';
			echo '</div>';
			echo '</div>';
		}

		echo '</section>';
	}

	/**
	 * Get built-in global tabs that can be overridden per product.
	 *
	 * @param array $options Global product tab settings.
	 * @return array<int, array<string, string>> Override-ready tab rows.
	 */
	public function get_overridable_base_tabs( array $options ): array {
		$tabs = array();

		if ( ! empty( $options['care_instructions'] ) ) {
			$tabs[] = array(
				'key'   => 'care_instructions',
				'title' => __( 'Care Instructions', 'aggressive-apparel' ),
			);
		}

		if ( ! empty( $options['shipping_returns'] ) ) {
			$tabs[] = array(
				'key'   => 'shipping_returns',
				'title' => __( 'Shipping & Returns', 'aggressive-apparel' ),
			);
		}

		if ( ! empty( $options['sustainability'] ) ) {
			$tabs[] = array(
				'key'   => 'sustainability',
				'title' => __( 'Sustainability', 'aggressive-apparel' ),
			);
		}

		return $tabs;
	}

	/**
	 * Enqueue Product Tabs admin assets on relevant screens.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ( 'product' !== $screen->post_type && false === strpos( (string) $screen->id, 'aggressive-apparel' ) ) ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/admin/product-tabs-admin.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-product-tabs-admin',
				AGGRESSIVE_APPAREL_URI . '/build/styles/admin/product-tabs-admin.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		$enqueued = \Aggressive_Apparel\Assets\Asset_Loader::enqueue_admin_script(
			'aggressive-apparel-product-tabs-admin',
			'build/scripts/admin/product-tabs-repeater',
			array()
		);

		if ( ! $enqueued ) {
			return;
		}

		wp_localize_script(
			'aggressive-apparel-product-tabs-admin',
			'aggressiveApparelProductTabs',
			$this->get_script_localization_data()
		);
	}

	/**
	 * Get localized strings and labels for the admin repeater script.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_localization_data(): array {
		return array(
			'layoutHelp' => Product_Tabs_Config::get_tab_layout_help(),
			'layouts'    => Product_Tabs_Config::get_tab_layout_labels(),
			'sources'    => Product_Tabs_Config::get_tab_source_labels(),
			'templates'  => Product_Tabs_Config::get_tab_templates(),
			'strings'    => array(
				'productDetails'       => __( 'Product Details', 'aggressive-apparel' ),
				'previewEmpty'         => __( 'Add content or layout items to preview the frontend output.', 'aggressive-apparel' ),
				'untitledTab'          => __( 'Untitled tab', 'aggressive-apparel' ),
				'enabled'              => __( 'Enabled', 'aggressive-apparel' ),
				'disabled'             => __( 'Disabled', 'aggressive-apparel' ),
				'ready'                => __( 'Ready', 'aggressive-apparel' ),
				'needsAttention'       => __( 'Needs Attention', 'aggressive-apparel' ),
				'addTabTitle'          => __( 'Add a tab title before saving.', 'aggressive-apparel' ),
				'addMetaKey'           => __( 'Add a product meta key or switch the source to Manual.', 'aggressive-apparel' ),
				'addAttribute'         => __( 'Add a product attribute slug or switch the source to Manual.', 'aggressive-apparel' ),
				'addContent'           => __( 'This tab will not render until it has content or layout items.', 'aggressive-apparel' ),
				'needsIntro'           => __( 'This layout needs intro/fallback content to render.', 'aggressive-apparel' ),
				'icon'                 => __( 'Icon', 'aggressive-apparel' ),
				'label'                => __( 'Label / Question / Step', 'aggressive-apparel' ),
				'detail'               => __( 'Detail / Eyebrow', 'aggressive-apparel' ),
				'removeItem'           => __( 'Remove Item', 'aggressive-apparel' ),
				'text'                 => __( 'Text / Answer / Value', 'aggressive-apparel' ),
				'enabledLabel'         => __( 'Enabled', 'aggressive-apparel' ),
				'priority'             => __( 'Priority', 'aggressive-apparel' ),
				'remove'               => __( 'Remove Tab', 'aggressive-apparel' ),
				'tabTitle'             => __( 'Tab Title', 'aggressive-apparel' ),
				'tabTitlePlaceholder'  => __( 'Size & Fit', 'aggressive-apparel' ),
				'contentLayout'        => __( 'Content Layout', 'aggressive-apparel' ),
				'contentSource'        => __( 'Content Source', 'aggressive-apparel' ),
				'productMetaField'     => __( 'Product Meta Field', 'aggressive-apparel' ),
				'productMetaHelp'      => __( 'Used only when Content Source is Product meta field.', 'aggressive-apparel' ),
				'productAttribute'     => __( 'Product Attribute', 'aggressive-apparel' ),
				'productAttributeHelp' => __( 'Used only when Content Source is Product attribute.', 'aggressive-apparel' ),
				'selectAttribute'      => __( 'Select attribute…', 'aggressive-apparel' ),
				'introContent'         => __( 'Intro / Fallback Content', 'aggressive-apparel' ),
				'introHelp'            => __( 'For structured layouts, this appears above the generated layout. For rich text, this is the full tab content.', 'aggressive-apparel' ),
				'layoutItemsHeader'    => __( 'No-Code Layout Items', 'aggressive-apparel' ),
				'layoutItemsHelp'      => __( 'Add the rows this layout will render. The theme handles the frontend markup and styling.', 'aggressive-apparel' ),
				'addLayoutItem'        => __( 'Add Layout Item', 'aggressive-apparel' ),
				'layoutItemsFooter'    => __( 'Use these rows for cards, specs tables, FAQs, timelines, care grids, and icon lists. Leave them empty for rich text or custom HTML tabs.', 'aggressive-apparel' ),
				'previewLabel'         => __( 'Frontend Preview', 'aggressive-apparel' ),
				'previewHelp'          => __( 'Updates as you edit. Custom HTML is shown as plain text in the preview for admin safety.', 'aggressive-apparel' ),
			),
		);
	}
}
