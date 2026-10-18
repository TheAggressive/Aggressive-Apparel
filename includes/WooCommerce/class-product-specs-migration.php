<?php
/**
 * Product Specs Migration
 *
 * Moves specs onto WooCommerce attributes. Specs used to keep their values
 * in theme meta (`_aa_spec_{key}` on products and size guides); now each spec
 * is a global attribute and every value a term (Product_Specs_Taxonomy).
 *
 * Two steps, both idempotent:
 *
 * 1. Link: a store that saved its own spec list gets each spec connected to
 *    a compatible attribute, or a new one (Product_Specs_Taxonomy::find), and
 *    each default turned into its term. A store still on the built-in specs
 *    is left alone until it first sets a value.
 * 2. Values: product values become product attributes, only where the
 *    product has no value for that attribute yet (never overwriting, never
 *    touching variation attributes); guide values become term IDs. Old meta
 *    is deleted only for values that moved. A value that can't move (the
 *    product already has a different value for the attribute, the attribute
 *    drives variations, or the value isn't usable) stays where it is, and
 *    Product_Specs keeps showing it ahead of the attribute, exactly as
 *    before; re-saving it on the product moves it. Runs in Action Scheduler
 *    batches, a cursor keeping each batch moving forward.
 *
 * Until it finishes, Product_Specs still reads the old meta, so nothing
 * disappears from the storefront in between. Runs once from the admin, or
 * by hand with `wp aggressive-apparel specs-migrate` (a dry run by default).
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
 * Theme-meta specs → WooCommerce attributes.
 *
 * @phpstan-type Link array{spec: string, attribute: string, status: string}
 * @phpstan-type Report array{links: list<Link>, posts: int, left: int, errors: list<string>}
 */
final class Product_Specs_Migration {

	/**
	 * Option recording the finished migration version.
	 *
	 * @var string
	 */
	public const OPTION = 'aggressive_apparel_specs_attributes';

	/**
	 * Current migration version.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * Action Scheduler hook for one batch of values.
	 *
	 * @var string
	 */
	public const ACTION = 'aggressive_apparel_specs_move_values';

	/**
	 * Option holding the last post ID a batch reached.
	 *
	 * @var string
	 */
	private const CURSOR = 'aggressive_apparel_specs_attributes_cursor';

	/**
	 * Posts moved per batch.
	 *
	 * @var int
	 */
	private const BATCH = 50;

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_run' ) );
		add_action( self::ACTION, array( self::class, 'scheduled_batch' ) );
	}

	/**
	 * Run once, from an admin page a store manager opens.
	 */
	public function maybe_run(): void {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION || ! current_user_can( 'manage_woocommerce' ) || wp_doing_ajax() ) {
			return;
		}
		// Two admin tabs opening at once must not both create attributes.
		if ( false !== get_transient( self::OPTION . '_lock' ) ) {
			return;
		}
		set_transient( self::OPTION . '_lock', 1, 5 * MINUTE_IN_SECONDS );

		$report = self::run( false, false );
		if ( array() !== $report['errors'] ) {
			// Left unfinished; the next admin visit tries again.
			delete_transient( self::OPTION . '_lock' );
		}
	}

	/**
	 * Link specs and move values.
	 *
	 * @param bool $dry_run Report only; change nothing.
	 * @param bool $inline  Move every value now instead of in background batches.
	 * @return Report
	 */
	public static function run( bool $dry_run, bool $inline ): array {
		$report = array(
			'links'  => array(),
			'posts'  => count( self::legacy_posts( PHP_INT_MAX, 0 ) ),
			'left'   => 0,
			'errors' => array(),
		);

		$stored = is_array( get_option( Product_Specs_Registry::OPTION_KEY, null ) );
		$taken  = array_values( array_filter( array_column( Product_Specs_Registry::get()['items'], 'taxonomy' ) ) );
		foreach ( Product_Specs_Registry::get()['items'] as $item ) {
			if ( '' !== $item['taxonomy'] ) {
				$report['links'][] = self::link( $item['name'], $item['taxonomy'], 'linked' );
				continue;
			}
			$found             = Product_Specs_Taxonomy::find( $item, $taken );
			$report['links'][] = self::link( $item['name'], $found ?? Product_Specs_Taxonomy::new_taxonomy( $item ), null !== $found ? 'reuse' : 'create' );
		}

		if ( $dry_run ) {
			return $report;
		}

		if ( $stored || $report['posts'] > 0 ) {
			$report['errors'] = Product_Specs_Registry::ensure_connected();
			if ( array() !== $report['errors'] ) {
				return $report;
			}
		}

		if ( $inline || ! function_exists( 'as_enqueue_async_action' ) ) {
			do {
				$moved = self::move_batch( false );
			} while ( self::BATCH === $moved );
			$report['left'] = count( self::legacy_posts( PHP_INT_MAX, 0 ) );
		} else {
			as_enqueue_async_action( self::ACTION, array(), 'aggressive-apparel' );
		}

		return $report;
	}

	/**
	 * The Action Scheduler callback.
	 */
	public static function scheduled_batch(): void {
		self::move_batch();
	}

	/**
	 * Move one batch of old meta values; queues the next batch while more
	 * remain, and records the migration as done once none do.
	 *
	 * @param bool $queue_next Queue the next batch through Action Scheduler.
	 * @return int Posts moved.
	 */
	public static function move_batch( bool $queue_next = true ): int {
		$ids   = self::legacy_posts( self::BATCH, (int) get_option( self::CURSOR, 0 ) );
		$items = Product_Specs_Registry::get()['items'];

		foreach ( $ids as $post_id ) {
			$values = array();
			foreach ( $items as $item ) {
				$value = Product_Specs_Values::clean( get_post_meta( $post_id, Product_Specs_Meta::key( $item['key'] ), true ), $item );
				if ( '' !== $value ) {
					$values[ $item['key'] ] = $value;
				}
			}

			// Each removes the old meta of the values it moved, and only those.
			$type = get_post_type( $post_id );
			if ( Size_Guide_Post_Type::POST_TYPE === $type ) {
				Product_Specs_Values::save_guide( $post_id, $values );
			} elseif ( 'product' === $type && array() !== $values ) {
				$product = wc_get_product( $post_id );
				if ( $product instanceof \WC_Product ) {
					Product_Specs_Product_Writer::save( $product, $values, true );
				}
			}
			update_option( self::CURSOR, $post_id, false );
		}

		if ( count( $ids ) < self::BATCH ) {
			update_option( self::OPTION, self::VERSION, false );
			delete_option( self::CURSOR );
			delete_transient( self::OPTION . '_lock' );
		} elseif ( $queue_next && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::ACTION, array(), 'aggressive-apparel' );
		}

		return count( $ids );
	}

	/**
	 * Posts that still hold old meta values, after a post ID.
	 *
	 * @param int $limit Most to return.
	 * @param int $after Only posts with a higher ID.
	 * @return list<int>
	 */
	private static function legacy_posts( int $limit, int $after ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration scan; meta_key prefix search has no API.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_key NOT IN (%s, %s) AND post_id > %d ORDER BY post_id LIMIT %d",
				$wpdb->esc_like( Product_Specs_Meta::PREFIX ) . '%',
				Product_Specs_Meta::HIDDEN,
				Product_Specs_Meta::WORDING,
				$after,
				$limit
			)
		);

		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * A report row.
	 *
	 * @param string $spec      Spec name.
	 * @param string $attribute Attribute taxonomy.
	 * @param string $status    linked, reuse, or create.
	 * @return Link
	 */
	private static function link( string $spec, string $attribute, string $status ): array {
		return array(
			'spec'      => $spec,
			'attribute' => $attribute,
			'status'    => $status,
		);
	}
}
