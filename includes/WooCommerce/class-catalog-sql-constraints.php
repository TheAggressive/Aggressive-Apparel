<?php
/**
 * Catalog SQL Constraints
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/** Builds prepared, indexed SQL joins shared by catalogue endpoints. */
final class Catalog_SQL_Constraints {

	/**
	 * Prepared JOIN fragments.
	 *
	 * @var string[]
	 */
	private array $joins = array();

	/**
	 * JOIN placeholder values.
	 *
	 * @var array<int, int|float|string>
	 */
	private array $join_params = array();

	/**
	 * Prepared WHERE fragments.
	 *
	 * @var string[]
	 */
	private array $where = array();

	/**
	 * WHERE placeholder values.
	 *
	 * @var array<int, float|string>
	 */
	private array $where_params = array();

	/**
	 * Generated-alias sequence.
	 *
	 * @var int
	 */
	private int $join_index = 0;

	/**
	 * Product table alias.
	 *
	 * @var string
	 */
	private string $product_alias;

	/**
	 * Prefix used for generated JOIN aliases.
	 *
	 * @var string
	 */
	private string $alias_prefix;

	/**
	 * Configure generated aliases.
	 *
	 * @param string $product_alias Product table alias.
	 * @param string $alias_prefix  Generated JOIN alias prefix.
	 */
	public function __construct( string $product_alias = 'p', string $alias_prefix = 'aa_filter' ) {
		$this->product_alias = $product_alias;
		$this->alias_prefix  = $alias_prefix;
	}

	/**
	 * Add an indexed WordPress taxonomy constraint.
	 *
	 * @param string   $taxonomy Taxonomy name.
	 * @param string[] $slugs    Selected term slugs.
	 */
	public function add_taxonomy( string $taxonomy, array $slugs ): void {
		$slugs = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_title', $slugs ) ) ) ), 0, 20 );
		if ( empty( $slugs ) ) {
			return;
		}

		global $wpdb;
		++$this->join_index;
		$tr    = $this->alias_prefix . '_tr_' . $this->join_index;
		$tt    = $this->alias_prefix . '_tt_' . $this->join_index;
		$t     = $this->alias_prefix . '_t_' . $this->join_index;
		$marks = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

		$this->joins[]       = "INNER JOIN {$wpdb->term_relationships} {$tr} ON {$this->product_alias}.ID = {$tr}.object_id";
		$this->joins[]       = "INNER JOIN {$wpdb->term_taxonomy} {$tt} ON {$tr}.term_taxonomy_id = {$tt}.term_taxonomy_id AND {$tt}.taxonomy = %s";
		$this->joins[]       = "INNER JOIN {$wpdb->terms} {$t} ON {$tt}.term_id = {$t}.term_id AND {$t}.slug IN ({$marks})";
		$this->join_params[] = $taxonomy;
		array_push( $this->join_params, ...$slugs );
	}

	/**
	 * Add a WooCommerce product-attribute lookup constraint.
	 *
	 * @param string   $taxonomy    Attribute taxonomy.
	 * @param string[] $slugs       Selected term slugs.
	 * @param string   $lookup_table WooCommerce attribute lookup table.
	 */
	public function add_attribute( string $taxonomy, array $slugs, string $lookup_table ): void {
		$slugs = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_title', $slugs ) ) ) ), 0, 20 );
		if ( empty( $slugs ) ) {
			return;
		}

		global $wpdb;
		++$this->join_index;
		$lookup = $this->alias_prefix . '_attr_' . $this->join_index;
		$term   = $this->alias_prefix . '_attr_t_' . $this->join_index;
		$marks  = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

		$this->joins[]       = "INNER JOIN {$lookup_table} {$lookup} ON {$this->product_alias}.ID = {$lookup}.product_or_parent_id AND {$lookup}.taxonomy = %s";
		$this->joins[]       = "INNER JOIN {$wpdb->terms} {$term} ON {$lookup}.term_id = {$term}.term_id AND {$term}.slug IN ({$marks})";
		$this->join_params[] = $taxonomy;
		array_push( $this->join_params, ...$slugs );
	}

	/**
	 * Add price and stock predicates backed by wc_product_meta_lookup.
	 *
	 * @param Catalog_Filter_Set $filters      Normalized filters.
	 * @param string             $lookup_alias Product lookup alias.
	 */
	public function add_lookup_filters( Catalog_Filter_Set $filters, string $lookup_alias ): void {
		if ( $filters->min_price() > 0 ) {
			$this->where[]        = "{$lookup_alias}.max_price >= %f";
			$this->where_params[] = $filters->min_price();
		}
		if ( $filters->max_price() > 0 ) {
			$this->where[]        = "{$lookup_alias}.min_price <= %f";
			$this->where_params[] = $filters->max_price();
		}
		if ( '' !== $filters->stock() ) {
			$this->where[]        = "{$lookup_alias}.stock_status = %s";
			$this->where_params[] = $filters->stock();
		}
	}

	/** Prepared JOIN SQL. */
	public function joins(): string {
		return implode( "\n", $this->joins );
	}

	/**
	 * Prepared WHERE clauses.
	 *
	 * @return string[]
	 */
	public function where(): array {
		return $this->where;
	}

	/**
	 * JOIN placeholder values.
	 *
	 * @return array<int, int|float|string>
	 */
	public function join_params(): array {
		return $this->join_params;
	}

	/**
	 * WHERE placeholder values.
	 *
	 * @return array<int, float|string>
	 */
	public function where_params(): array {
		return $this->where_params;
	}
}
