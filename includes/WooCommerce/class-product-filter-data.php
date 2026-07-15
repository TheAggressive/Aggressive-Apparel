<?php
/**
 * Product Filter Data Provider
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/** Loads and caches the taxonomy, attribute, and price data used by filters. */
final class Product_Filter_Data {

	private const CACHE_KEY = 'aa_pf_v2_data';
	private const CACHE_TTL = 900;

	/**
	 * Request-local data.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data = null;

	/** Return all filter source data. */
	public function get(): array {
		if ( null !== $this->data ) {
			return $this->data;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['colorTerms'], $cached['fitTerms'] ) ) {
			$this->data = $cached;
			return $cached;
		}

		$default_price = array(
			'min'            => 0,
			'max'            => 0,
			'currencyPrefix' => '$',
			'currencySuffix' => '',
			'minorUnit'      => 2,
		);

		$data = array(
			'categories'    => $this->safely( array( $this, 'categories' ) ),
			'colorTerms'    => $this->safely( array( $this, 'colors' ) ),
			'sizeTerms'     => $this->safely( array( $this, 'sizes' ) ),
			'fitTerms'      => $this->safely( array( $this, 'fits' ) ),
			'priceRange'    => $this->safely( array( $this, 'price_range' ), $default_price ),
			'stockStatuses' => $this->stock_statuses(),
		);

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		$this->data = $data;

		return $data;
	}

	/** Invalidate persistent and request-local data. */
	public function flush(): void {
		$this->data = null;
		delete_transient( self::CACHE_KEY );
	}

	/** Native managed Sales category URL, including empty archives. */
	public function sales_category_url(): string {
		$term = get_term_by( 'slug', Sale_Category::TERM_SLUG, 'product_cat' );
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		$link = get_term_link( $term );
		return is_wp_error( $link ) ? '' : esc_url_raw( $link );
	}

	/**
	 * Execute a data reader without allowing optional catalogue data to crash a page.
	 *
	 * @param array{object, string} $reader   Data reader.
	 * @param array                 $fallback Failure value.
	 * @return array
	 */
	private function safely( array $reader, array $fallback = array() ): array {
		if ( ! is_callable( $reader ) ) {
			return $fallback;
		}

		try {
			return $reader();
		} catch ( \Throwable ) {
			return $fallback;
		}
	}

	/** Product categories with native counts and canonical links. */
	private function categories(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$categories = array();
		foreach ( $terms as $term ) {
			if ( 'uncategorized' === $term->slug ) {
				continue;
			}
			$link         = get_term_link( $term );
			$categories[] = array(
				'id'     => $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'count'  => $term->count,
				'parent' => $term->parent,
				'link'   => is_wp_error( $link ) ? '' : esc_url_raw( $link ),
			);
		}

		return $categories;
	}

	/** Color attribute terms with display values. */
	private function colors(): array {
		$colors = ( new Color_Data_Manager() )->get_color_terms();
		$result = array();
		foreach ( $colors as $color ) {
			$result[] = array(
				'slug'  => $color['slug'],
				'name'  => $color['name'],
				'value' => $color['value'] ?? $color['hex'] ?? '#000000',
				'type'  => $color['type'] ?? 'solid',
				'count' => $color['count'] ?? 0,
			);
		}
		return $result;
	}

	/** Size attribute terms. */
	private function sizes(): array {
		return $this->simple_terms( 'pa_size', 'menu_order' );
	}

	/** Fit attribute terms. */
	private function fits(): array {
		return $this->simple_terms( 'pa_fit', 'name' );
	}

	/**
	 * Read simple attribute terms.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $orderby  Term ordering.
	 */
	private function simple_terms( string $taxonomy, string $orderby ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => $orderby,
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		return array_map(
			static fn( \WP_Term $term ): array => array(
				'id'    => (int) $term->term_id,
				'slug'  => $term->slug,
				'name'  => $term->name,
				'count' => $term->count,
			),
			$terms
		);
	}

	/** Indexed catalogue-wide price range. */
	private function price_range(): array {
		$default = array(
			'min'            => 0,
			'max'            => 0,
			'currencyPrefix' => '$',
			'currencySuffix' => '',
			'minorUnit'      => 2,
		);
		if ( ! function_exists( 'wc_get_price_decimals' ) ) {
			return $default;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- get() wraps this indexed aggregate in a 15-minute catalogue-data transient.
		$result = $wpdb->get_row(
			"SELECT FLOOR(MIN(product_lookup.min_price)) AS min_price,
				CEIL(MAX(product_lookup.max_price)) AS max_price
			FROM {$wpdb->wc_product_meta_lookup} product_lookup
			INNER JOIN {$wpdb->posts} posts ON product_lookup.product_id = posts.ID
			WHERE posts.post_type = 'product'
			AND posts.post_status = 'publish'
			AND product_lookup.max_price > 0"
		);

		if ( ! $result || ! $result->min_price ) {
			return $default;
		}

		return array(
			'min'            => (int) $result->min_price,
			'max'            => (int) $result->max_price,
			'currencyPrefix' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			'currencySuffix' => '',
			'minorUnit'      => wc_get_price_decimals(),
		);
	}

	/** Stock statuses exposed by the filter UI. */
	private function stock_statuses(): array {
		return array(
			array(
				'value' => 'instock',
				'label' => __( 'In Stock', 'aggressive-apparel' ),
			),
			array(
				'value' => 'outofstock',
				'label' => __( 'Out of Stock', 'aggressive-apparel' ),
			),
			array(
				'value' => 'onbackorder',
				'label' => __( 'On Backorder', 'aggressive-apparel' ),
			),
		);
	}
}
