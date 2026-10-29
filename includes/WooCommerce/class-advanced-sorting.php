<?php
/**
 * Advanced Sorting Class
 *
 * Adds additional product sorting options to the WooCommerce catalog.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Sorting
 *
 * Extends the WooCommerce sort dropdown with Featured, Biggest Savings and
 * alphabetical options, and orders the archive query server-side for them.
 * Featured and Savings apply to any query flagged with
 * `aa_catalog_custom_sort` — Catalog_Archive_Filters flags the archive's main
 * query or main collection when the URL requests one.
 *
 * @since 1.51.0
 */
class Advanced_Sorting {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_catalog_orderby', array( $this, 'modify_orderby_options' ) );
		add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'handle_custom_ordering' ), 10, 3 );
		add_filter( 'posts_clauses', array( $this, 'apply_ssr_ordering' ), 20, 2 );
	}

	/**
	 * Modify the catalog orderby dropdown options.
	 *
	 * @param array<string, string> $options Existing options.
	 * @return array<string, string> Modified options.
	 */
	public function modify_orderby_options( array $options ): array {
		// Relabel "popularity" to "Best Selling".
		if ( isset( $options['popularity'] ) ) {
			$options['popularity'] = __( 'Best Selling', 'aggressive-apparel' );
		}

		// Add new options.
		$options['featured']   = __( 'Featured', 'aggressive-apparel' );
		$options['savings']    = __( 'Biggest Savings', 'aggressive-apparel' );
		$options['title-asc']  = __( 'Name: A-Z', 'aggressive-apparel' );
		$options['title-desc'] = __( 'Name: Z-A', 'aggressive-apparel' );

		return $options;
	}

	/**
	 * Handle custom ordering args for the main archive query.
	 *
	 * @param array<string, mixed> $args     Current ordering args.
	 * @param string               $orderby  The orderby value.
	 * @param string               $order    The order direction.
	 * @return array<string, mixed> Modified args.
	 */
	public function handle_custom_ordering( array $args, string $orderby, string $order ): array {
		switch ( $orderby ) {
			// Paginated sorts end on the ID so pages never overlap or skip.
			case 'title-asc':
				$args['orderby'] = array(
					'title' => 'ASC',
					'ID'    => 'ASC',
				);
				$args['order']   = 'ASC';
				break;

			case 'title-desc':
				$args['orderby'] = array(
					'title' => 'DESC',
					'ID'    => 'DESC',
				);
				$args['order']   = 'DESC';
				break;

			// Ordered in SQL by apply_ssr_ordering(); date is the base order.
			case 'featured':
			case 'savings':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
		}

		return $args;
	}

	/**
	 * Apply a custom sort in SQL to a query flagged `aa_catalog_custom_sort`.
	 *
	 * @param array<string, string> $clauses Query clauses.
	 * @param \WP_Query             $query   Query object.
	 * @return array<string, string>
	 */
	public function apply_ssr_ordering( array $clauses, \WP_Query $query ): array {
		$sort = (string) $query->get( 'aa_catalog_custom_sort' );
		if ( 'featured' !== $sort && 'savings' !== $sort ) {
			return $clauses;
		}

		global $wpdb;
		if ( 'featured' === $sort ) {
			$clauses['orderby'] = "EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} aa_featured_tr
				INNER JOIN {$wpdb->term_taxonomy} aa_featured_tt ON aa_featured_tr.term_taxonomy_id = aa_featured_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} aa_featured_t ON aa_featured_tt.term_id = aa_featured_t.term_id
				WHERE aa_featured_tr.object_id = {$wpdb->posts}.ID
				AND aa_featured_tt.taxonomy = 'product_visibility'
				AND aa_featured_t.slug = 'featured'
			) DESC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		} else {
			// Savings = (regular - active price) / regular, from the meta
			// WooCommerce keeps: `_price` is the price being charged now, so a
			// scheduled sale counts only once it starts. Variable products keep
			// prices on their variations, so a product ranks by its best one.
			$clauses['join']   .= " LEFT JOIN (
				SELECT CASE WHEN aa_item.post_type = 'product_variation' THEN aa_item.post_parent ELSE aa_item.ID END AS product_id,
					MAX( ( CAST(aa_regular.meta_value AS DECIMAL(20,6)) - CAST(aa_price.meta_value AS DECIMAL(20,6)) ) / CAST(aa_regular.meta_value AS DECIMAL(20,6)) ) AS savings
				FROM {$wpdb->posts} aa_item
				INNER JOIN {$wpdb->postmeta} aa_regular ON aa_regular.post_id = aa_item.ID AND aa_regular.meta_key = '_regular_price'
				INNER JOIN {$wpdb->postmeta} aa_price ON aa_price.post_id = aa_item.ID AND aa_price.meta_key = '_price'
				WHERE aa_item.post_type IN ('product', 'product_variation')
				AND aa_item.post_status = 'publish'
				AND aa_price.meta_value <> ''
				AND CAST(aa_regular.meta_value AS DECIMAL(20,6)) > 0
				GROUP BY product_id
			) aa_savings ON aa_savings.product_id = {$wpdb->posts}.ID";
			$clauses['orderby'] = "COALESCE(aa_savings.savings, -1) DESC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		}

		return $clauses;
	}
}
