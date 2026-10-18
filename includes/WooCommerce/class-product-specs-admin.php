<?php
/**
 * Product Specs Admin
 *
 * The per-product editing surface: a Specs section inside the product's Size
 * Guide tab (or its own tab when Size Guide is off). Each field shows where
 * its value comes from, and a product can override, hide, or reword any spec.
 *
 * Everything shared lives on Products → Size Guides (Product_Specs_Settings):
 * size guides, spec definitions with their defaults, and per-category
 * boundaries.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Specs per-product admin.
 */
final class Product_Specs_Admin {

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'aa_product_specs_save';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	private const NONCE_NAME = 'aa_product_specs_nonce';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_tabs' ), 20 );
		add_action( 'aggressive_apparel_size_guide_product_panel', array( $this, 'render_product_fields' ) );
		// After WooCommerce saves its own Attributes tab (priority 10).
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_product_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_data' ) );
	}

	/**
	 * Rename the Size Guide tab to cover its specs.
	 *
	 * @param array<string, array<string, mixed>> $tabs Product data tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function product_tabs( array $tabs ): array {
		if ( isset( $tabs['aa_size_guide'] ) ) {
			$tabs['aa_size_guide']['label'] = __( 'Size & specs', 'aggressive-apparel' );
		}

		return $tabs;
	}

	/**
	 * Product Specs fields: one row per applicable item, a preview, wording.
	 *
	 * @param \WP_Post $post Product.
	 */
	public function render_product_fields( \WP_Post $post ): void {
		$data   = Product_Specs::for_product( $post->ID );
		$manage = Product_Specs_Settings::url( 'specs' );
		$links  = array( 'a' => array( 'href' => array() ) );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME, false );
		echo Product_Specs_Admin_Fields::styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
		echo '<input type="hidden" name="' . esc_attr( Product_Specs_Admin_Fields::FIELD ) . '[form]" value="1">';

		echo '<div class="options_group aa-specs">';
		echo '<h3 class="aa-specs__title">' . esc_html__( 'Product specs', 'aggressive-apparel' ) . '</h3>';

		if ( array() === $data['items'] ) {
			echo '<p class="aa-specs-help">' . wp_kses(
				sprintf(
					/* translators: %s: link to the Specs tab of Products → Size Guides. */
					__( 'No specs apply to this product’s categories. Choose where each spec shows on %s.', 'aggressive-apparel' ),
					'<a href="' . esc_url( $manage ) . '">' . esc_html__( 'Products → Size Guides → Specs', 'aggressive-apparel' ) . '</a>'
				),
				$links
			) . '</p></div>';

			return;
		}

		echo '<p class="aa-specs-help">' . wp_kses(
			sprintf(
				/* translators: %s: link to the Specs tab of Products → Size Guides. */
				__( 'Values save as this product’s WooCommerce attributes. Empty fields use the product’s size guide, then each spec’s default from %s.', 'aggressive-apparel' ),
				'<a href="' . esc_url( $manage ) . '">' . esc_html__( 'Products → Size Guides → Specs', 'aggressive-apparel' ) . '</a>'
			),
			$links
		) . '</p>';
		$product = wc_get_product( $post->ID );
		foreach ( $data['items'] as $item ) {
			$locked = $product instanceof \WC_Product && Product_Specs_Product_Writer::drives_variations( $product, $item['taxonomy'] );
			echo Product_Specs_Admin_Fields::product_row( $item, $locked ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in builder.
			// Saving only rewrites the hidden flag and wording of items shown here.
			echo '<input type="hidden" name="' . esc_attr( Product_Specs_Admin_Fields::FIELD ) . '[shown][]" value="' . esc_attr( $item['key'] ) . '">';
		}

		$preview = Product_Specs_Renderer::render(
			$post->ID,
			array(
				'showNudge'   => true,
				'showDetails' => true,
			)
		);
		echo '<div class="aa-specs-preview"><p class="aa-specs-preview__label">' . esc_html__( 'Product Specs block preview (updates when you save)', 'aggressive-apparel' ) . '</p>';
		if ( '' !== $preview ) {
			echo '<div class="aa-product-specs is-layout-full">' . aggressive_apparel_trusted_html( $preview ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Product_Specs_Renderer.
		} else {
			echo '<p>' . esc_html__( 'Nothing to show yet. Set a value here, as a product attribute, or as the spec’s default.', 'aggressive-apparel' ) . '</p>';
		}
		echo '</div>';

		$wording = Product_Specs_Admin_Fields::wording(
			array_map( array( Product_Specs_Admin_Fields::class, 'set_from_resolved' ), $data['items'] ),
			Product_Specs::sanitize_wording( get_post_meta( $post->ID, Product_Specs_Meta::WORDING, true ) ),
			__( 'Wording for this product only. Empty fields use the wording from Products → Size Guides → Specs, shown in grey.', 'aggressive-apparel' )
		);
		echo $wording; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in builder.
		echo '</div>';
	}

	/**
	 * Save product values, hidden items, and wording.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product( int $post_id ): void {
		if ( ! $this->verified() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = $this->submitted();
		if ( empty( $raw['form'] ) ) {
			return;
		}

		// Only fields changed here, so an edit to the same attribute in
		// WooCommerce's Attributes tab is never undone by an untouched field.
		$values  = isset( $raw['values'] ) && is_array( $raw['values'] ) ? $raw['values'] : array();
		$orig    = isset( $raw['orig'] ) && is_array( $raw['orig'] ) ? $raw['orig'] : array();
		$changed = array_filter(
			$values,
			static fn( $value, $key ): bool => is_scalar( $value ) && 0 !== strcmp( (string) $value, (string) ( is_scalar( $orig[ $key ] ?? null ) ? $orig[ $key ] : '' ) ),
			ARRAY_FILTER_USE_BOTH
		);
		$product = wc_get_product( $post_id );
		if ( array() !== $changed && $product instanceof \WC_Product ) {
			$failed = Product_Specs_Product_Writer::save( $product, $changed )['failed'];
			if ( array() !== $failed && class_exists( '\WC_Admin_Meta_Boxes' ) ) {
				\WC_Admin_Meta_Boxes::add_error(
					sprintf(
						/* translators: %s: comma-separated spec keys. */
						__( 'Some spec values could not be saved (%s). Check the spec on Products → Size Guides → Specs.', 'aggressive-apparel' ),
						implode( ', ', $failed )
					)
				);
			}
		}

		// The form only lists items that apply to the product right now, so
		// overrides for the others (another category, a spec scoped elsewhere)
		// are kept, ready for when they apply again.
		$shown = Product_Specs_Meta::sanitize_keys( $raw['shown'] ?? array() );

		$kept   = array_diff( Product_Specs_Meta::sanitize_keys( get_post_meta( $post_id, Product_Specs_Meta::HIDDEN, true ) ), $shown );
		$picked = array_intersect( Product_Specs_Meta::sanitize_keys( $raw['hidden'] ?? array() ), $shown );
		$hidden = array_values( array_unique( array_merge( $kept, $picked ) ) );
		if ( array() === $hidden ) {
			delete_post_meta( $post_id, Product_Specs_Meta::HIDDEN );
		} else {
			update_post_meta( $post_id, Product_Specs_Meta::HIDDEN, $hidden );
		}

		$wording = array_diff_key( Product_Specs::sanitize_wording( get_post_meta( $post_id, Product_Specs_Meta::WORDING, true ) ), array_flip( $shown ) )
			+ array_intersect_key( Product_Specs::sanitize_wording( $raw['wording'] ?? null ), array_flip( $shown ) );
		if ( array() === $wording ) {
			delete_post_meta( $post_id, Product_Specs_Meta::WORDING );
		} else {
			update_post_meta( $post_id, Product_Specs_Meta::WORDING, $wording );
		}
	}

	/**
	 * Load the block stylesheet on the product editor for the preview.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue_product_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || 'product' !== get_post_type() ) {
			return;
		}

		wp_enqueue_style( generate_block_asset_handle( 'aggressive-apparel/product-specs', 'style' ) );
	}

	/**
	 * Hand the spec list to the Product Specs block's editor script.
	 */
	public function enqueue_block_data(): void {
		$items = array_map(
			static fn( array $item ): array => array(
				'key'      => $item['key'],
				'type'     => $item['type'],
				'name'     => $item['name'],
				'steps'    => $item['steps'],
				'centered' => $item['centered'],
			) + Product_Specs_Registry::edge_labels( $item['steps'], $item['start'], $item['mid'], $item['end'] ),
			Product_Specs_Registry::items()
		);

		wp_add_inline_script(
			generate_block_asset_handle( 'aggressive-apparel/product-specs', 'editorScript' ),
			'window.aaProductSpecs = ' . wp_json_encode(
				array(
					'items'     => $items,
					'manageUrl' => Product_Specs_Settings::url(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Whether the request carries a valid Specs nonce.
	 */
	private function verified(): bool {
		return isset( $_POST[ self::NONCE_NAME ] )
			&& false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION );
	}

	/**
	 * Submitted Specs fields, unslashed. Sanitized by the callers.
	 *
	 * @return array<mixed>
	 */
	private function submitted(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in verified(); each value sanitized by its caller.
		$raw = isset( $_POST[ Product_Specs_Admin_Fields::FIELD ] ) ? wp_unslash( $_POST[ Product_Specs_Admin_Fields::FIELD ] ) : array();

		return is_array( $raw ) ? $raw : array();
	}
}
