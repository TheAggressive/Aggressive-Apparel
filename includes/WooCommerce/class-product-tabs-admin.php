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
	 * Constructor.
	 *
	 * @param Product_Tabs_Sanitizer $sanitizer Sanitization service.
	 * @param Product_Tabs           $tabs      Product tabs orchestrator.
	 */
	public function __construct( Product_Tabs_Sanitizer $sanitizer, Product_Tabs $tabs ) {
		$this->sanitizer = $sanitizer;
		$this->tabs      = $tabs;
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

		$this->render_custom_tabs_repeater(
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
		$this->render_custom_tabs_repeater(
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
	 * Render a custom tabs repeater UI.
	 *
	 * @param array  $tabs        Sanitized custom tabs.
	 * @param string $name_prefix Form field prefix.
	 * @param string $id          Repeater element ID.
	 * @param string $description Repeater help text.
	 * @param int    $product_id  Product post ID for attribute options.
	 * @return void
	 */
	public function render_custom_tabs_repeater( array $tabs, string $name_prefix, string $id, string $description, int $product_id = 0 ): void {
		$rows = ! empty( $tabs )
			? array_values( $tabs )
			: array(
				array(
					'enabled'  => true,
					'key'      => '',
					'title'    => '',
					'priority' => 40,
					'source'   => 'manual',
					'layout'   => 'rich_text',
					'items'    => array(),
					'content'  => '',
				),
			);

		$attribute_options = $product_id > 0 ? $this->get_product_attribute_options( $product_id ) : array();

		$attribute_options_json = wp_json_encode( $attribute_options );
		if ( false === $attribute_options_json ) {
			$attribute_options_json = '{}';
		}

		echo '<div class="aa-custom-tabs-repeater" id="' . esc_attr( $id ) . '" data-name-prefix="' . esc_attr( $name_prefix ) . '" data-next-index="' . esc_attr( (string) count( $rows ) ) . '" data-attribute-options="' . esc_attr( $attribute_options_json ) . '">';
		echo '<div class="aa-custom-tabs-repeater__header">';
		echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '<div class="aa-custom-tabs-repeater__actions">';
		echo '<button type="button" class="button button-primary aa-custom-tabs-repeater__add" data-template="blank">' . esc_html__( 'Add Blank Tab', 'aggressive-apparel' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '<div class="aa-custom-tabs-template-bar" aria-label="' . esc_attr__( 'Tab templates', 'aggressive-apparel' ) . '">';
		echo '<span class="aa-custom-tabs-template-bar__label">' . esc_html__( 'Quick templates', 'aggressive-apparel' ) . '</span>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="size_fit">' . esc_html__( 'Size & Fit', 'aggressive-apparel' ) . '</button>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="shipping_returns">' . esc_html__( 'Shipping & Returns', 'aggressive-apparel' ) . '</button>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="faq">' . esc_html__( 'FAQ', 'aggressive-apparel' ) . '</button>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="specs">' . esc_html__( 'Specs Table', 'aggressive-apparel' ) . '</button>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="care">' . esc_html__( 'Care Grid', 'aggressive-apparel' ) . '</button>';
		echo '</div>';
		echo '<div class="aa-custom-tabs-manager">';
		echo '<aside class="aa-custom-tabs-manager__sidebar">';
		echo '<div class="aa-custom-tabs-manager__sidebar-header">';
		echo '<p class="aa-custom-tabs-manager__sidebar-title">' . esc_html__( 'Tabs', 'aggressive-apparel' ) . '</p>';
		echo '<span class="aa-custom-tabs-manager__count"></span>';
		echo '</div>';
		echo '<div class="aa-custom-tabs-manager__list" role="listbox" aria-label="' . esc_attr__( 'Custom tabs', 'aggressive-apparel' ) . '"></div>';
		echo '<p class="description">' . esc_html__( 'Select a tab to edit it. Drag or use arrows to reorder.', 'aggressive-apparel' ) . '</p>';
		echo '</aside>';
		echo '<div class="aa-custom-tabs-repeater__rows">';

		foreach ( $rows as $index => $tab ) {
			$this->render_custom_tab_row( $name_prefix, $index, $tab, $attribute_options );
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render one custom tab repeater row.
	 *
	 * @param string $name_prefix       Form field prefix.
	 * @param int    $index             Row index.
	 * @param array  $tab               Sanitized custom tab row.
	 * @param array  $attribute_options Product attribute options.
	 * @return void
	 */
	public function render_custom_tab_row( string $name_prefix, int $index, array $tab, array $attribute_options = array() ): void {
		$enabled_name  = sprintf( '%s[%d][enabled]', $name_prefix, $index );
		$key_name      = sprintf( '%s[%d][key]', $name_prefix, $index );
		$title_name    = sprintf( '%s[%d][title]', $name_prefix, $index );
		$priority_name = sprintf( '%s[%d][priority]', $name_prefix, $index );
		$source_name   = sprintf( '%s[%d][source]', $name_prefix, $index );
		$layout_name   = sprintf( '%s[%d][layout]', $name_prefix, $index );
		$meta_key_name = sprintf( '%s[%d][metaField]', $name_prefix, $index );
		$attr_name     = sprintf( '%s[%d][attribute]', $name_prefix, $index );
		$content_name  = sprintf( '%s[%d][content]', $name_prefix, $index );
		$source        = (string) ( $tab['source'] ?? 'manual' );
		$layout        = (string) ( $tab['layout'] ?? 'rich_text' );
		$sources       = Product_Tabs_Config::get_tab_source_labels();
		$layouts       = Product_Tabs_Config::get_tab_layout_labels();
		$title         = '' !== (string) ( $tab['title'] ?? '' ) ? (string) $tab['title'] : __( 'Untitled tab', 'aggressive-apparel' );
		$is_enabled    = ! empty( $tab['enabled'] );

			echo '<div class="aa-custom-tabs-repeater__row aa-custom-tabs-card" data-tab-index="' . esc_attr( (string) $index ) . '" data-layout="' . esc_attr( $layout ) . '" data-source="' . esc_attr( $source ) . '">';
			echo '<div class="aa-custom-tabs-card__header">';
			echo '<div>';
			echo '<p class="aa-custom-tabs-card__eyebrow">' . esc_html__( 'Selected tab', 'aggressive-apparel' ) . '</p>';
			echo '<h3 class="aa-custom-tabs-card__title">' . esc_html( $title ) . '</h3>';
			echo '</div>';
			echo '<span class="aa-custom-tabs-card__meta">';
			echo '<span class="aa-tabs-badge aa-tabs-badge--enabled">' . esc_html( $is_enabled ? __( 'Enabled', 'aggressive-apparel' ) : __( 'Disabled', 'aggressive-apparel' ) ) . '</span>';
			echo '<span class="aa-tabs-badge aa-tabs-badge--layout">' . esc_html( $layouts[ $layout ] ?? $layouts['rich_text'] ) . '</span>';
			echo '<span class="aa-tabs-badge aa-tabs-badge--render">' . esc_html__( 'Ready', 'aggressive-apparel' ) . '</span>';
			echo '</span>';
			echo '</div>';
			echo '<div class="aa-custom-tabs-card__body">';
			echo '<input type="hidden" name="' . esc_attr( $key_name ) . '" value="' . esc_attr( (string) ( $tab['key'] ?? '' ) ) . '">';
			echo '<p class="aa-custom-tabs-repeater__toolbar">';
			echo '<label><input type="hidden" name="' . esc_attr( $enabled_name ) . '" value="0"><input type="checkbox" name="' . esc_attr( $enabled_name ) . '" value="1"' . checked( ! empty( $tab['enabled'] ), true, false ) . '> ' . esc_html__( 'Enabled', 'aggressive-apparel' ) . '</label>';
				echo '<label>' . esc_html__( 'Priority', 'aggressive-apparel' ) . ' <input type="number" min="1" max="999" step="1" name="' . esc_attr( $priority_name ) . '" value="' . esc_attr( (string) $tab['priority'] ) . '" class="aa-custom-tabs-repeater__priority-input"></label>';
			echo '<button type="button" class="button button-secondary aa-custom-tabs-repeater__remove">' . esc_html__( 'Remove Tab', 'aggressive-apparel' ) . '</button>';
			echo '</p>';
			echo '<div class="aa-custom-tabs-repeater__grid">';
				echo '<p><label>' . esc_html__( 'Tab Title', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $title_name ) . '" value="' . esc_attr( $tab['title'] ) . '" class="regular-text" placeholder="' . esc_attr__( 'Size & Fit', 'aggressive-apparel' ) . '"></label></p>';
			echo '<p><label>' . esc_html__( 'Content Layout', 'aggressive-apparel' ) . '<br><select class="aa-custom-tabs-layout-select" name="' . esc_attr( $layout_name ) . '">';
		foreach ( $layouts as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $layout, $value, false ),
				esc_html( $label )
			);
		}
			echo '</select></label><span class="description aa-custom-tabs-layout-help" aria-live="polite"></span></p>';
			echo '<p><label>' . esc_html__( 'Content Source', 'aggressive-apparel' ) . '<br><select class="aa-custom-tabs-source-select" name="' . esc_attr( $source_name ) . '">';
		foreach ( $sources as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $source, $value, false ),
				esc_html( $label )
			);
		}
			echo '</select></label></p>';
			echo '</div>';
			echo '<div class="aa-custom-tabs-source-fields">';
				echo '<p data-source-field="meta"><label>' . esc_html__( 'Product Meta Field', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $meta_key_name ) . '" value="' . esc_attr( (string) ( $tab['metaField'] ?? $tab['meta_key'] ?? '' ) ) . '" class="regular-text" placeholder="_my_meta_key"></label><span class="description">' . esc_html__( 'Used only when Content Source is Product meta field.', 'aggressive-apparel' ) . '</span></p>';
				echo '<p data-source-field="attribute"><label>' . esc_html__( 'Product Attribute', 'aggressive-apparel' ) . '<br>';
				$this->render_attribute_field( $attr_name, (string) ( $tab['attribute'] ?? '' ), $attribute_options );
				echo '</label><span class="description">' . esc_html__( 'Used only when Content Source is Product attribute.', 'aggressive-apparel' ) . '</span></p>';
			echo '</div>';
			echo '<p><label class="aa-custom-tabs-content-label">' . esc_html__( 'Intro / Fallback Content', 'aggressive-apparel' ) . '<br><textarea name="' . esc_attr( $content_name ) . '" rows="5" class="large-text">' . esc_textarea( (string) $tab['content'] ) . '</textarea></label><span class="description aa-custom-tabs-content-help">' . esc_html__( 'For structured layouts, this appears above the generated layout. For rich text, this is the full tab content.', 'aggressive-apparel' ) . '</span></p>';
				echo '<p class="aa-custom-tabs-validation" aria-live="polite"></p>';
				$this->render_custom_tab_items( $name_prefix, $index, isset( $tab['items'] ) && is_array( $tab['items'] ) ? $tab['items'] : array() );
				$this->render_custom_tab_preview();
				echo '</div>';
				echo '</div>';
	}

	/**
	 * Get attribute slug options for a product edit screen.
	 *
	 * @param int $product_id Product post ID.
	 * @return array<string, string> Attribute slug => label.
	 */
	public function get_product_attribute_options( int $product_id ): array {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$options = array();

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute ) {
				continue;
			}

			$name = $attribute->get_name();
			if ( '' === $name ) {
				continue;
			}

			$label            = wc_attribute_label( $name, $product );
			$options[ $name ] = is_string( $label ) && '' !== $label ? $label : $name;
		}

		return $options;
	}

	/**
	 * Render the attribute source field as a select or text input.
	 *
	 * @param string               $name              Input name.
	 * @param string               $selected          Selected attribute slug.
	 * @param array<string,string> $attribute_options Available attributes.
	 * @return void
	 */
	public function render_attribute_field( string $name, string $selected, array $attribute_options ): void {
		if ( empty( $attribute_options ) ) {
			printf(
				'<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="pa_material">',
				esc_attr( $name ),
				esc_attr( $selected )
			);
			return;
		}

		echo '<select name="' . esc_attr( $name ) . '" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Select attribute…', 'aggressive-apparel' ) . '</option>';

		foreach ( $attribute_options as $slug => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $slug ),
				selected( $selected, (string) $slug, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Render no-code layout item rows for a custom tab.
	 *
	 * @param string $name_prefix Form field prefix.
	 * @param int    $tab_index   Parent tab row index.
	 * @param array  $items       Sanitized layout item rows.
	 * @return void
	 */
	public function render_custom_tab_items( string $name_prefix, int $tab_index, array $items ): void {
		$rows = ! empty( $items )
			? array_values( $items )
			: array(
				array(
					'icon'  => '',
					'title' => '',
					'text'  => '',
					'meta'  => '',
				),
			);

		echo '<div class="aa-custom-tab-items" data-next-item-index="' . esc_attr( (string) count( $rows ) ) . '">';
		echo '<p class="aa-custom-tab-items__header"><strong>' . esc_html__( 'No-Code Layout Items', 'aggressive-apparel' ) . '</strong><br><span class="description">' . esc_html__( 'Add the rows this layout will render. The theme handles the frontend markup and styling.', 'aggressive-apparel' ) . '</span></p>';
		echo '<div class="aa-custom-tab-items__rows">';

		foreach ( $rows as $item_index => $item ) {
			$this->render_custom_tab_item_row( $name_prefix, $tab_index, $item_index, $item );
		}

		echo '</div>';
		echo '<button type="button" class="button aa-custom-tab-items__add">' . esc_html__( 'Add Layout Item', 'aggressive-apparel' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Use these rows for cards, specs tables, FAQs, timelines, care grids, and icon lists. Leave them empty for rich text or custom HTML tabs.', 'aggressive-apparel' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render one no-code layout item row.
	 *
	 * @param string $name_prefix Form field prefix.
	 * @param int    $tab_index   Parent tab row index.
	 * @param int    $item_index  Item row index.
	 * @param array  $item        Sanitized item row.
	 * @return void
	 */
	public function render_custom_tab_item_row( string $name_prefix, int $tab_index, int $item_index, array $item ): void {
		$base = sprintf( '%s[%d][items][%d]', $name_prefix, $tab_index, $item_index );

		echo '<div class="aa-custom-tab-items__row">';
		echo '<p class="aa-custom-tab-items__row-fields">';
		echo '<label>' . esc_html__( 'Icon', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[icon]' ) . '" value="' . esc_attr( (string) ( $item['icon'] ?? '' ) ) . '" class="aa-custom-tab-items__icon-input" placeholder="✓"></label>';
		echo '<label>' . esc_html__( 'Label / Question / Step', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[title]' ) . '" value="' . esc_attr( (string) ( $item['title'] ?? '' ) ) . '" class="regular-text"></label>';
		echo '<label>' . esc_html__( 'Detail / Eyebrow', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[meta]' ) . '" value="' . esc_attr( (string) ( $item['meta'] ?? '' ) ) . '" class="regular-text"></label>';
		echo '<button type="button" class="button button-small aa-custom-tab-items__remove">' . esc_html__( 'Remove Item', 'aggressive-apparel' ) . '</button>';
		echo '</p>';
		echo '<p><label>' . esc_html__( 'Text / Answer / Value', 'aggressive-apparel' ) . '<br><textarea name="' . esc_attr( $base . '[text]' ) . '" rows="3" class="large-text">' . esc_textarea( (string) ( $item['text'] ?? '' ) ) . '</textarea></label></p>';
		echo '</div>';
	}

	/**
	 * Render the live frontend-style preview shell.
	 *
	 * @return void
	 */
	public function render_custom_tab_preview(): void {
		echo '<div class="aa-custom-tabs-preview">';
		echo '<p class="aa-custom-tabs-preview__label"><strong>' . esc_html__( 'Frontend Preview', 'aggressive-apparel' ) . '</strong><br><span class="description">' . esc_html__( 'Updates as you edit. Custom HTML is shown as plain text in the preview for admin safety.', 'aggressive-apparel' ) . '</span></p>';
		echo '<div class="aa-custom-tabs-preview__surface">';
		echo '<h4 class="aa-custom-tabs-preview__title"></h4>';
		echo '<div class="aa-product-info__content aa-custom-tabs-preview__content"></div>';
		echo '</div>';
		echo '</div>';
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
