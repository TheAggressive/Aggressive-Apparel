<?php
/**
 * Product Specs Admin
 *
 * The per-product editing surface: a Specs section inside the product's Size
 * Guide tab (or its own tab when Size Guide is off), built from the same
 * pieces as a size guide's specs. Each row says where its value comes from,
 * and a product can set its own value, hide a spec, or reword it.
 *
 * Everything shared lives on Products → Size & Specs (Product_Specs_Settings):
 * size guides, spec definitions with their defaults, and per-category
 * boundaries.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Specs per-product admin.
 */
final class Product_Specs_Admin {

	/**
	 * Script and style handle of the Size & specs panel.
	 *
	 * @var string
	 */
	private const PANEL_HANDLE = 'aggressive-apparel-product-specs-panel';

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
	 * The Specs section: the same rows, switch, wording panel and live
	 * preview as a size guide's specs, drawn by the Size & specs panel
	 * script (src/scripts/admin/product-specs-panel.tsx). It writes this
	 * form's own fields, so saving the product saves it (save_product).
	 *
	 * @param \WP_Post $post Product.
	 */
	public function render_product_fields( \WP_Post $post ): void {
		$data = self::panel_data( $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME, false );
		echo '<div class="aa-ps aa-ps-product">';
		echo '<h3 class="aa-ps-product__title">' . esc_html__( 'Product specs', 'aggressive-apparel' ) . '</h3>';
		printf(
			'<p class="aa-ps-help">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html(
				'' !== $data['guide']
					/* translators: %s: size guide name. */
					? sprintf( __( 'Values save as this product’s WooCommerce attributes. An empty value uses the “%s” size guide’s, then the spec’s default.', 'aggressive-apparel' ), $data['guide'] )
					: __( 'Values save as this product’s WooCommerce attributes. An empty value uses the spec’s default.', 'aggressive-apparel' )
			),
			esc_url( Product_Specs_Settings::url( 'specs' ) ),
			esc_html__( 'Manage specs', 'aggressive-apparel' )
		);
		// The rows, drawn by the panel script from this data (text only).
		printf(
			'<div id="aa-product-specs-panel" class="aa-ps-product__specs" data-aa-product-specs-panel="%s"></div>',
			esc_attr( (string) wp_json_encode( $data ) )
		);
		echo '</div>';
	}

	/**
	 * What the panel shows: each spec that applies, with the product's own
	 * value, what it falls back to and from where, its switch and wording;
	 * and the specs the product's size guide hides.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private static function panel_data( int $product_id ): array {
		$view    = Product_Specs::admin_view( $product_id );
		$product = wc_get_product( $product_id );
		$wording = Product_Specs::sanitize_wording( get_post_meta( $product_id, Product_Specs_Meta::WORDING, true ) );
		$items   = array();
		foreach ( Product_Specs::for_product( $product_id )['items'] as $item ) {
			$key = $item['key'];
			// As the guide words it; the panel lays the product's wording over.
			$base    = $view['base'][ $key ];
			$items[] = array(
				'key'         => $key,
				'type'        => $item['type'],
				'multiple'    => $item['multiple'],
				'unit'        => $item['unit'],
				'centered'    => $item['centered'],
				'thresholds'  => $item['thresholds'],
				'name'        => $base['title'],
				'steps'       => $base['steps'],
				'start'       => $base['start'],
				'mid'         => $base['mid'],
				'end'         => $base['end'],
				'advice'      => $base['advice'],
				'own'         => $item['own'],
				'default'     => self::raw( $item['type'], $item['inherited'] ),
				'source'      => $item['inherited']['source'],
				'sourceLabel' => $item['inherited']['label'],
				'hidden'      => $item['hidden'],
				'locked'      => $product instanceof \WC_Product && Product_Specs_Product_Writer::drives_variations( $product, $item['taxonomy'] ),
				'terms'       => Product_Specs_Registry::TYPE_TEXT === $item['type'] ? Product_Specs_Taxonomy::usage( $item['taxonomy'] ) : array(),
				'wording'     => (object) ( $wording[ $key ] ?? array() ),
			);
		}

		return array(
			'field'         => Product_Specs_Admin_Fields::FIELD,
			'items'         => $items,
			'guide'         => $view['guide'],
			'hiddenByGuide' => $view['hidden_by_guide'],
			'canCreate'     => current_user_can( 'manage_product_terms' ),
		);
	}

	/**
	 * A resolved value as the editor stores it: a step index, a number, or
	 * text ('' when unset).
	 *
	 * @param string $type  Item type.
	 * @param array  $value Resolved value.
	 * @phpstan-param array{step: int, number: float, text: string} $value
	 */
	private static function raw( string $type, array $value ): string {
		if ( Product_Specs_Registry::TYPE_TEXT === $type ) {
			return $value['text'];
		}
		if ( Product_Specs_Registry::TYPE_NUMBER === $type ) {
			return 0 < $value['number'] ? (string) $value['number'] : '';
		}

		return 0 <= $value['step'] ? (string) $value['step'] : '';
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
		// Only specs this form showed (those that apply to the product).
		$values = isset( $raw['values'] ) && is_array( $raw['values'] ) ? $raw['values'] : array();
		$values = array_intersect_key( $values, array_flip( Product_Specs_Meta::sanitize_keys( $raw['shown'] ?? array() ) ) );
		$orig   = isset( $raw['orig'] ) && is_array( $raw['orig'] ) ? $raw['orig'] : array();
		// Several picked values arrive as a list; store them as one value.
		$values  = array_map( array( self::class, 'joined' ), $values );
		$changed = array_filter(
			$values,
			static fn( string $value, $key ): bool => 0 !== strcmp( $value, self::joined( $orig[ $key ] ?? '' ) ),
			ARRAY_FILTER_USE_BOTH
		);
		$product = wc_get_product( $post_id );
		if ( array() !== $changed && $product instanceof \WC_Product ) {
			// New values become catalog terms, so they need term management.
			$create = current_user_can( 'manage_product_terms' );
			$failed = Product_Specs_Product_Writer::save( $product, $changed, false, $create )['failed'];
			if ( array() !== $failed && class_exists( '\WC_Admin_Meta_Boxes' ) ) {
				\WC_Admin_Meta_Boxes::add_error(
					sprintf(
						$create
							/* translators: %s: comma-separated spec keys. */
							? __( 'Some spec values could not be saved (%s). Check the spec on Products → Size & Specs → Specs.', 'aggressive-apparel' )
							/* translators: %s: comma-separated spec keys. */
							: __( 'Some spec values could not be saved (%s). You can pick existing values only; ask a store manager to add new ones.', 'aggressive-apparel' ),
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
	 * A submitted value as one string: several values joined like
	 * WooCommerce attribute values ("Mens | Womens"), spacing normalized so
	 * an untouched field compares equal.
	 *
	 * @param mixed $value Submitted value.
	 */
	private static function joined( $value ): string {
		$list = is_array( $value ) ? array_filter( $value, 'is_scalar' ) : ( is_scalar( $value ) ? array( $value ) : array() );
		$all  = array();
		foreach ( $list as $part ) {
			$all = array_merge( $all, Product_Specs_Taxonomy::split( sanitize_text_field( (string) $part ) ) );
		}

		return implode( ' ' . Product_Specs_Taxonomy::DELIMITER . ' ', array_unique( $all ) );
	}

	/**
	 * Load the Size & specs panel on the product editor.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue_product_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || 'product' !== get_post_type() ) {
			return;
		}

		Asset_Loader::enqueue_script(
			self::PANEL_HANDLE,
			'build/scripts/admin/product-specs-panel',
			array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-dom-ready' )
		);
		Asset_Loader::enqueue_style( self::PANEL_HANDLE, 'build/scripts/admin/product-specs-panel', array( 'wp-components' ) );
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
