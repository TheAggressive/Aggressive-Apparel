<?php
/**
 * Managed WooCommerce sale category.
 *
 * @package Aggressive_Apparel
 * @since 1.128.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a native product category in sync with WooCommerce sale status.
 *
 * Product changes are coalesced into small Action Scheduler jobs. A resumable,
 * cursor-based repair pass catches direct database imports and interrupted
 * requests without loading the complete catalogue into memory.
 *
 * @since 1.128.0
 */
class Sale_Category {

	/** Managed product-category slug. */
	public const TERM_SLUG = 'sales';

	/** Action Scheduler group. */
	private const ACTION_GROUP = 'aggressive-apparel-sales';

	/** Product-update batch action. */
	private const PRODUCT_BATCH_HOOK = 'aggressive_apparel_sync_sale_products';

	/** Full reconciliation batch action. */
	private const RECONCILE_BATCH_HOOK = 'aggressive_apparel_reconcile_sale_products';

	/** Stored managed term ID. */
	private const TERM_OPTION = 'aggressive_apparel_sale_category_term_id';

	/** One-time reconciliation version. */
	private const SYNC_VERSION_OPTION = 'aggressive_apparel_sale_category_sync_version';

	/** Reconciliation lock state. */
	private const LOCK_OPTION = 'aggressive_apparel_sale_category_reconcile_lock';

	/** Reconciliation progress and health state. */
	private const STATUS_OPTION = 'aggressive_apparel_sale_category_status';

	/** Current reconciliation version. */
	private const SYNC_VERSION = '2';

	/** Products per reconciliation action. */
	private const RECONCILE_BATCH_SIZE = 250;

	/** Product IDs per update action. */
	private const PRODUCT_BATCH_SIZE = 100;

	/** Maximum worker retry count. */
	private const MAX_RETRIES = 3;

	/** Reconciliation lock lifetime (six hours). */
	private const LOCK_TTL = 21600;

	/**
	 * Product IDs queued during the current request.
	 *
	 * @var array<int, int>
	 */
	private array $pending_product_ids = array();

	/**
	 * Prevent re-entry while term relationships are being changed.
	 *
	 * @var bool
	 */
	private bool $syncing = false;

	/**
	 * Managed term ID cached for the current request.
	 *
	 * @var int|null
	 */
	private ?int $term_id = null;

	/**
	 * Register lifecycle hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_install' ), 20 );

		// WooCommerce CRUD hooks cover product edits, REST updates, and imports.
		add_action( 'woocommerce_new_product', array( $this, 'queue_product_sync' ), 30 );
		add_action( 'woocommerce_update_product', array( $this, 'queue_product_sync' ), 30 );
		add_action( 'woocommerce_new_product_variation', array( $this, 'queue_product_sync' ), 30 );
		add_action( 'woocommerce_update_product_variation', array( $this, 'queue_product_sync' ), 30 );
		add_action( 'transition_post_status', array( $this, 'queue_status_transition' ), 30, 3 );
		add_action( 'shutdown', array( $this, 'flush_pending_syncs' ), 20 );

		// Background workers.
		add_action( self::PRODUCT_BATCH_HOOK, array( $this, 'process_product_batch' ), 10, 2 );
		add_action( self::RECONCILE_BATCH_HOOK, array( $this, 'process_reconciliation_batch' ), 10, 3 );

		// WooCommerce runs this after scheduled sale prices start/end. The repair
		// itself is queued so the scheduler request stays bounded.
		add_action( 'woocommerce_scheduled_sales', array( $this, 'scheduled_reconcile' ), 20 );

		add_filter( 'woocommerce_debug_tools', array( $this, 'register_debug_tool' ) );
		add_action( 'switch_theme', array( $this, 'unschedule_actions' ) );
	}

	/**
	 * Provision the category and schedule the first repair pass.
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		if ( ! $this->ensure_category() ) {
			return;
		}

		if ( self::SYNC_VERSION !== get_option( self::SYNC_VERSION_OPTION ) ) {
			$this->schedule_reconciliation();
		}
	}

	/**
	 * Queue the daily repair after WooCommerce processes scheduled prices.
	 *
	 * @return void
	 */
	public function scheduled_reconcile(): void {
		$this->schedule_reconciliation();
	}

	/**
	 * Ensure the native WooCommerce product category exists.
	 *
	 * @return int Managed term ID, or 0 when provisioning is unavailable.
	 */
	public function ensure_category(): int {
		if ( null !== $this->term_id ) {
			return $this->term_id;
		}

		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return 0;
		}

		$term_id = absint( get_option( self::TERM_OPTION, 0 ) );
		$term    = $term_id ? get_term( $term_id, 'product_cat' ) : null;

		if ( ! $term instanceof \WP_Term ) {
			$existing = term_exists( self::TERM_SLUG, 'product_cat' );
			$term_id  = is_array( $existing )
				? absint( $existing['term_id'] )
				: absint( $existing );

			if ( ! $term_id ) {
				$created = wp_insert_term(
					__( 'Sales', 'aggressive-apparel' ),
					'product_cat',
					array( 'slug' => self::TERM_SLUG )
				);

				if ( is_wp_error( $created ) ) {
					$this->log_error( 'Unable to create the managed Sales category.', array( 'error' => $created->get_error_message() ) );
					return 0;
				}

				$term_id = absint( $created['term_id'] );
			}
		}

		if ( ! $term_id ) {
			return 0;
		}

		$term = get_term( $term_id, 'product_cat' );
		if ( $term instanceof \WP_Term && self::TERM_SLUG !== $term->slug ) {
			$updated = wp_update_term( $term_id, 'product_cat', array( 'slug' => self::TERM_SLUG ) );
			if ( is_wp_error( $updated ) ) {
				$this->log_error( 'Unable to restore the managed Sales category slug.', array( 'error' => $updated->get_error_message() ) );
				return 0;
			}
		}

		update_option( self::TERM_OPTION, $term_id, false );
		$this->term_id = $term_id;

		return $term_id;
	}

	/**
	 * Queue a product or variation for synchronization.
	 *
	 * IDs are deduplicated per request and flushed in bounded chunks during very
	 * large imports rather than accumulating until shutdown.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return void
	 */
	public function queue_product_sync( int $product_id ): void {
		if ( $this->syncing ) {
			return;
		}

		$product_id = $this->normalize_product_id( $product_id );
		if ( ! $product_id ) {
			return;
		}

		$this->pending_product_ids[ $product_id ] = $product_id;

		if ( count( $this->pending_product_ids ) >= self::PRODUCT_BATCH_SIZE ) {
			$this->flush_pending_syncs();
		}
	}

	/**
	 * Queue products when publication state changes outside WooCommerce CRUD.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       Post being transitioned.
	 * @return void
	 */
	public function queue_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			$this->queue_product_sync( (int) $post->ID );
		}
	}

	/**
	 * Dispatch queued product IDs to the background worker.
	 *
	 * @return void
	 */
	public function flush_pending_syncs(): void {
		if ( empty( $this->pending_product_ids ) || $this->syncing ) {
			return;
		}

		$product_ids               = array_values( $this->pending_product_ids );
		$this->pending_product_ids = array();

		foreach ( array_chunk( $product_ids, self::PRODUCT_BATCH_SIZE ) as $batch ) {
			$args = array( $batch, 0 );
			if ( ! $this->enqueue_action( self::PRODUCT_BATCH_HOOK, $args, 0, true ) ) {
				// Action Scheduler should always be available with WooCommerce. Fail
				// safely by completing this small bounded batch inline if it is not.
				$this->process_product_batch( $batch );
			}
		}
	}

	/**
	 * Process a bounded product-update batch.
	 *
	 * @param int[] $product_ids Parent or variation product IDs.
	 * @param int   $attempt     Retry attempt.
	 * @return void
	 */
	public function process_product_batch( array $product_ids, int $attempt = 0 ): void {
		$result = $this->synchronize_ids( wp_parse_id_list( $product_ids ) );

		if ( $result['changed'] > 0 ) {
			do_action( 'aggressive_apparel_sale_category_updated' );
		}

		if ( ! empty( $result['failed_ids'] ) ) {
			if ( $attempt < self::MAX_RETRIES ) {
				$delay = MINUTE_IN_SECONDS * ( 2 ** $attempt );
				if ( $this->enqueue_action( self::PRODUCT_BATCH_HOOK, array( $result['failed_ids'], $attempt + 1 ), $delay, true ) ) {
					return;
				}
			}

			$this->log_error(
				'Sales category product synchronization exhausted its retries or could not enqueue a retry.',
				array( 'product_ids' => $result['failed_ids'] )
			);
		}
	}

	/**
	 * Synchronize one product immediately.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool Whether a term relationship changed.
	 */
	public function sync_product( int $product_id ): bool {
		return 'changed' === $this->sync_product_result( $product_id );
	}

	/**
	 * Schedule a resumable full-catalogue reconciliation.
	 *
	 * @return bool Whether a new repair run was scheduled.
	 */
	public function schedule_reconciliation(): bool {
		if ( ! $this->ensure_category() || $this->has_active_lock() ) {
			return false;
		}

		$token = wp_generate_uuid4();
		$lock  = array(
			'token'      => $token,
			'expires_at' => time() + self::LOCK_TTL,
		);

		if ( ! add_option( self::LOCK_OPTION, $lock, '', false ) ) {
			return false;
		}

		update_option(
			self::STATUS_OPTION,
			array(
				'state'      => 'scheduled',
				'token'      => $token,
				'started_at' => time(),
				'updated_at' => time(),
				'cursor'     => 0,
				'checked'    => 0,
				'changed'    => 0,
				'failed'     => 0,
				'last_error' => '',
			),
			false
		);

		if ( $this->enqueue_action( self::RECONCILE_BATCH_HOOK, array( 0, $token, 0 ), 0, true ) ) {
			return true;
		}

		$this->finish_reconciliation( $token, 'failed', 'Unable to enqueue the first reconciliation batch.' );
		return false;
	}

	/**
	 * Process one cursor-based reconciliation batch.
	 *
	 * @param int    $cursor  Last processed product ID.
	 * @param string $token   Reconciliation run token.
	 * @param int    $attempt Retry attempt.
	 * @throws \RuntimeException When the next batch cannot be enqueued.
	 * @return void
	 */
	public function process_reconciliation_batch( int $cursor, string $token, int $attempt = 0 ): void {
		if ( ! $this->owns_lock( $token ) ) {
			return;
		}

		$this->refresh_lock( $token );

		try {
			$product_ids = $this->get_product_batch_after( $cursor );
			$result      = $this->synchronize_ids( $product_ids );
			$next_cursor = empty( $product_ids ) ? $cursor : max( $product_ids );

			if ( $result['changed'] > 0 ) {
				do_action( 'aggressive_apparel_sale_category_updated' );
			}

			if ( ! empty( $result['failed_ids'] ) ) {
				throw new \RuntimeException(
					'Synchronization failed for product IDs: ' . implode( ',', $result['failed_ids'] )
				);
			}

			$status = $this->get_status();
			update_option(
				self::STATUS_OPTION,
				array_merge(
					$status,
					array(
						'state'      => 'running',
						'updated_at' => time(),
						'cursor'     => $next_cursor,
						'checked'    => (int) ( $status['checked'] ?? 0 ) + count( $product_ids ),
						'changed'    => (int) ( $status['changed'] ?? 0 ) + $result['changed'],
						'failed'     => (int) ( $status['failed'] ?? 0 ) + count( $result['failed_ids'] ),
					)
				),
				false
			);

			if ( count( $product_ids ) < self::RECONCILE_BATCH_SIZE ) {
				$this->finish_reconciliation( $token, 'complete' );
				return;
			}

			if ( ! $this->enqueue_action( self::RECONCILE_BATCH_HOOK, array( $next_cursor, $token, 0 ), 0, true ) ) {
				throw new \RuntimeException( 'Unable to enqueue the next reconciliation batch.' );
			}
		} catch ( \Throwable $throwable ) {
			$this->handle_reconciliation_failure( $cursor, $token, $attempt, $throwable );
		}
	}

	/**
	 * Run a bounded-memory reconciliation synchronously.
	 *
	 * Production lifecycle hooks use schedule_reconciliation(); this method is
	 * retained for tests and emergency programmatic diagnostics.
	 *
	 * @return int Number of changed term relationships.
	 */
	public function reconcile(): int {
		$cursor  = 0;
		$changed = 0;

		do {
			$product_ids = $this->get_product_batch_after( $cursor );
			$batch_count = count( $product_ids );
			$result      = $this->synchronize_ids( $product_ids );
			$changed    += $result['changed'];
			if ( ! empty( $product_ids ) ) {
				$cursor = max( $product_ids );
			}
		} while ( self::RECONCILE_BATCH_SIZE === $batch_count );

		if ( $changed > 0 ) {
			do_action( 'aggressive_apparel_sale_category_updated' );
		}

		return $changed;
	}

	/**
	 * Register the manual repair tool and expose worker health.
	 *
	 * @param array<string, array<string, mixed>> $tools Existing tools.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_debug_tool( array $tools ): array {
		$tools['aggressive_apparel_reconcile_sale_category'] = array(
			'name'     => __( 'Sales category membership', 'aggressive-apparel' ),
			'button'   => __( 'Schedule rebuild', 'aggressive-apparel' ),
			'desc'     => __( 'Schedules a batched rebuild of the managed Sales category.', 'aggressive-apparel' ) . ' ' . $this->get_health_summary(),
			'callback' => array( $this, 'run_debug_tool' ),
		);

		return $tools;
	}

	/**
	 * Schedule the manual WooCommerce status tool.
	 *
	 * @return string Completion message.
	 */
	public function run_debug_tool(): string {
		if ( $this->schedule_reconciliation() ) {
			return __( 'Sales category rebuild scheduled in the background.', 'aggressive-apparel' );
		}

		return __( 'A Sales category rebuild is already running or could not be scheduled. Check WooCommerce logs for details.', 'aggressive-apparel' );
	}

	/**
	 * Remove pending theme-owned actions when switching themes.
	 *
	 * @return void
	 */
	public function unschedule_actions(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), self::ACTION_GROUP );
		}

		wp_clear_scheduled_hook( self::PRODUCT_BATCH_HOOK );
		wp_clear_scheduled_hook( self::RECONCILE_BATCH_HOOK );
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Synchronize IDs with deferred term counting.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return array{changed: int, failed_ids: int[]}
	 */
	private function synchronize_ids( array $product_ids ): array {
		$changed      = 0;
		$failed_ids   = array();
		$was_deferred = wp_defer_term_counting();

		// Prime product meta and term relationships once for the whole batch so
		// wc_get_product() and has_term() do not degenerate into N+1 queries.
		if ( ! empty( $product_ids ) ) {
			update_meta_cache( 'post', $product_ids );
			update_object_term_cache( $product_ids, 'product' );
		}

		wp_defer_term_counting( true );

		try {
			foreach ( $product_ids as $product_id ) {
				$result = $this->sync_product_result( (int) $product_id );
				if ( 'changed' === $result ) {
					++$changed;
				} elseif ( 'failed' === $result ) {
					$failed_ids[] = (int) $product_id;
				}
			}
		} finally {
			wp_defer_term_counting( $was_deferred );
		}

		if ( $changed > 0 && $this->term_id ) {
			clean_term_cache( $this->term_id, 'product_cat' );
		}

		return array(
			'changed'    => $changed,
			'failed_ids' => $failed_ids,
		);
	}

	/**
	 * Synchronize one product and report a detailed outcome.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return 'changed'|'unchanged'|'failed'
	 */
	private function sync_product_result( int $product_id ): string {
		$product_id = $this->normalize_product_id( $product_id );
		$term_id    = $this->ensure_category();

		if ( ! $product_id ) {
			return 'unchanged';
		}

		if ( ! $term_id || $this->syncing || ! function_exists( 'wc_get_product' ) ) {
			return 'failed';
		}

		try {
			$product  = \wc_get_product( $product_id );
			$on_sale  = is_object( $product )
				&& method_exists( $product, 'is_on_sale' )
				&& 'publish' === get_post_status( $product_id )
				&& $product->is_on_sale();
			$assigned = has_term( $term_id, 'product_cat', $product_id );

			if ( $on_sale === $assigned ) {
				return 'unchanged';
			}

			$this->syncing = true;
			try {
				$result = $on_sale
					? wp_set_object_terms( $product_id, array( $term_id ), 'product_cat', true )
					: wp_remove_object_terms( $product_id, array( $term_id ), 'product_cat' );
			} finally {
				$this->syncing = false;
			}

			if ( is_wp_error( $result ) ) {
				$this->log_error(
					'Unable to update a Sales category relationship.',
					array(
						'product_id' => $product_id,
						'error'      => $result->get_error_message(),
					)
				);
				return 'failed';
			}

			return 'changed';
		} catch ( \Throwable $throwable ) {
			$this->syncing = false;
			$this->log_error(
				'Sales category product synchronization failed.',
				array(
					'product_id' => $product_id,
					'error'      => $throwable->getMessage(),
				)
			);
			return 'failed';
		}
	}

	/**
	 * Fetch the next catalogue batch using an indexed ID cursor.
	 *
	 * @param int $cursor Last processed product ID.
	 * @return int[] Product IDs.
	 */
	private function get_product_batch_after( int $cursor ): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND ID > %d ORDER BY ID ASC LIMIT %d",
				$cursor,
				self::RECONCILE_BATCH_SIZE
			)
		);

		return wp_parse_id_list( $ids );
	}

	/**
	 * Enqueue an Action Scheduler job with a WP-Cron fallback.
	 *
	 * @param string $hook   Action hook.
	 * @param array  $args   Positional callback arguments.
	 * @param int    $delay  Delay in seconds.
	 * @param bool   $unique Whether an equivalent pending action is sufficient.
	 * @return bool Whether the action is queued.
	 */
	private function enqueue_action( string $hook, array $args, int $delay = 0, bool $unique = false ): bool {
		if ( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_schedule_single_action' ) ) {
			$action_id = $delay > 0
				? as_schedule_single_action( time() + $delay, $hook, $args, self::ACTION_GROUP, $unique )
				: as_enqueue_async_action( $hook, $args, self::ACTION_GROUP, $unique );

			if ( $action_id > 0 ) {
				return true;
			}

			if ( $unique && function_exists( 'as_has_scheduled_action' ) ) {
				return as_has_scheduled_action( $hook, $args, self::ACTION_GROUP );
			}
		}

		if ( false !== wp_next_scheduled( $hook, $args ) ) {
			return true;
		}

		return true === wp_schedule_single_event( time() + max( 1, $delay ), $hook, $args );
	}

	/**
	 * Determine whether an unexpired reconciliation owns the lock.
	 *
	 * @return bool Whether a repair is active.
	 */
	private function has_active_lock(): bool {
		$lock = get_option( self::LOCK_OPTION );
		if ( is_array( $lock ) && (int) ( $lock['expires_at'] ?? 0 ) > time() ) {
			return true;
		}

		delete_option( self::LOCK_OPTION );
		return false;
	}

	/**
	 * Check lock ownership.
	 *
	 * @param string $token Run token.
	 * @return bool Whether this worker owns the lock.
	 */
	private function owns_lock( string $token ): bool {
		$lock = get_option( self::LOCK_OPTION );

		return is_array( $lock )
			&& hash_equals( (string) ( $lock['token'] ?? '' ), $token )
			&& (int) ( $lock['expires_at'] ?? 0 ) > time();
	}

	/**
	 * Refresh the reconciliation lock.
	 *
	 * @param string $token Run token.
	 * @return void
	 */
	private function refresh_lock( string $token ): void {
		update_option(
			self::LOCK_OPTION,
			array(
				'token'      => $token,
				'expires_at' => time() + self::LOCK_TTL,
			),
			false
		);
	}

	/**
	 * Handle a failed reconciliation batch with exponential retry.
	 *
	 * @param int        $cursor    Last successful cursor.
	 * @param string     $token     Run token.
	 * @param int        $attempt   Current attempt.
	 * @param \Throwable $throwable Failure.
	 * @return void
	 */
	private function handle_reconciliation_failure( int $cursor, string $token, int $attempt, \Throwable $throwable ): void {
		$this->log_error(
			'Sales category reconciliation batch failed.',
			array(
				'cursor'  => $cursor,
				'attempt' => $attempt,
				'error'   => $throwable->getMessage(),
			)
		);

		if ( $attempt < self::MAX_RETRIES ) {
			$delay = MINUTE_IN_SECONDS * ( 2 ** $attempt );
			if ( $this->enqueue_action( self::RECONCILE_BATCH_HOOK, array( $cursor, $token, $attempt + 1 ), $delay, true ) ) {
				return;
			}
		}

		$this->finish_reconciliation( $token, 'failed', $throwable->getMessage() );
	}

	/**
	 * Complete or fail a reconciliation and release its lock.
	 *
	 * @param string $token Run token.
	 * @param string $state Final state.
	 * @param string $error Optional error message.
	 * @return void
	 */
	private function finish_reconciliation( string $token, string $state, string $error = '' ): void {
		if ( ! $this->owns_lock( $token ) ) {
			return;
		}

		$status = $this->get_status();
		update_option(
			self::STATUS_OPTION,
			array_merge(
				$status,
				array(
					'state'        => $state,
					'updated_at'   => time(),
					'completed_at' => time(),
					'last_error'   => $error,
				)
			),
			false
		);

		delete_option( self::LOCK_OPTION );

		if ( 'complete' === $state ) {
			update_option( self::SYNC_VERSION_OPTION, self::SYNC_VERSION, false );
		}
	}

	/**
	 * Get reconciliation status.
	 *
	 * @return array<string, mixed> Status data.
	 */
	private function get_status(): array {
		$status = get_option( self::STATUS_OPTION, array() );

		return is_array( $status ) ? $status : array();
	}

	/**
	 * Human-readable health summary for WooCommerce Status > Tools.
	 *
	 * @return string Escaped status summary.
	 */
	private function get_health_summary(): string {
		$status = $this->get_status();
		if ( empty( $status ) ) {
			return esc_html__( 'No rebuild has completed yet.', 'aggressive-apparel' );
		}

		$state   = sanitize_text_field( (string) ( $status['state'] ?? 'unknown' ) );
		$checked = absint( $status['checked'] ?? 0 );
		$changed = absint( $status['changed'] ?? 0 );

		/* translators: 1: worker state, 2: products checked, 3: assignments changed. */
		return esc_html( sprintf( __( 'Last state: %1$s. Checked: %2$d; changed: %3$d.', 'aggressive-apparel' ), $state, $checked, $changed ) );
	}

	/**
	 * Write an error to WooCommerce logs.
	 *
	 * @param string               $message Error message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	private function log_error( string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		\wc_get_logger()->error(
			$message,
			array_merge( array( 'source' => 'aggressive-apparel-sales-category' ), $context )
		);
	}

	/**
	 * Resolve variations to their parent catalogue product.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return int Parent product ID, or 0 for an unrelated post.
	 */
	private function normalize_product_id( int $product_id ): int {
		$product_id = absint( $product_id );
		$post_type  = get_post_type( $product_id );

		if ( 'product_variation' === $post_type ) {
			$product_id = (int) wp_get_post_parent_id( $product_id );
			$post_type  = get_post_type( $product_id );
		}

		return 'product' === $post_type ? $product_id : 0;
	}
}
