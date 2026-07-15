<?php
/**
 * Search Index
 *
 * Maintains a compact token index for the theme's live search. Initial
 * indexing and bulk repairs are cursor-based Action Scheduler jobs, while post
 * changes are coalesced into bounded batches.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Large-catalog search index.
 */
class Search_Index {

	private const SCHEMA_VERSION        = '1.1.0';
	private const INDEX_VERSION         = '1';
	private const SCHEMA_OPTION         = 'aggressive_apparel_search_schema_version';
	private const INDEX_OPTION          = 'aggressive_apparel_search_index_version';
	private const GENERATION_OPTION     = 'aggressive_apparel_search_generation';
	private const REBUILD_CURSOR_OPTION = 'aggressive_apparel_search_rebuild_cursor';
	private const STATUS_OPTION         = 'aggressive_apparel_search_index_status';
	private const ACTION_GROUP          = 'aggressive-apparel-search';
	private const CACHE_GROUP           = 'aggressive-apparel-search-index';
	private const SYNC_HOOK             = 'aggressive_apparel_sync_search_index';
	private const REBUILD_HOOK          = 'aggressive_apparel_rebuild_search_index';
	private const BATCH_SIZE            = 250;
	private const MAX_QUERY_WORDS       = 6;
	private const MAX_DOCUMENT_TOKENS   = 200;
	private const MAX_TOKEN_LENGTH      = 100;
	private const MAX_RETRIES           = 3;
	private const STALE_AFTER           = 21600;

	/**
	 * Posts queued during this request.
	 *
	 * @var array<int, true>
	 */
	private array $pending_ids = array();

	/** Register lifecycle and worker hooks. */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_install' ), 5 );
		add_action( 'save_post', array( $this, 'queue_post' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'delete_post' ) );
		add_action( 'shutdown', array( $this, 'flush_pending' ), 8 );
		add_action( self::SYNC_HOOK, array( $this, 'process_sync_batch' ), 10, 2 );
		add_action( self::REBUILD_HOOK, array( $this, 'process_rebuild_batch' ), 10, 2 );
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
		add_action( 'switch_theme', array( $this, 'unschedule_actions' ) );
	}

	/** Install or upgrade the index table on an administrator request. */
	public function maybe_install(): void {
		if ( get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) {
			if ( ! $this->is_ready() && $this->rebuild_needs_recovery() ) {
				$this->schedule_rebuild();
			}
			return;
		}

		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = $wpdb->prepare(
			'CREATE TABLE %i (
			object_id bigint(20) unsigned NOT NULL,
			object_type varchar(20) NOT NULL,
			token varchar(100) NOT NULL,
			weight tinyint(3) unsigned NOT NULL DEFAULT 1,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (object_id, token),
			KEY token_type (token, object_type, weight, object_id),
			KEY object_type (object_type, object_id)
		)',
			$table
		) . ' ' . $charset . ';';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Do not claim the schema is ready if the host rejected table creation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time schema verification during an admin upgrade; stale cached state would be incorrect.
		$installed_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $installed_table !== $table ) {
			return;
		}

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
		delete_option( self::INDEX_OPTION );
		$this->schedule_rebuild();
	}

	/** Whether a complete index is available for frontend reads. */
	public function is_ready(): bool {
		return get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION
			&& get_option( self::INDEX_OPTION ) === self::INDEX_VERSION;
	}

	/** Generation folded into response-cache keys for constant-time invalidation. */
	public function generation(): int {
		return max( 1, (int) get_option( self::GENERATION_OPTION, 1 ) );
	}

	/**
	 * Search the index. Null means the caller should use WordPress fallback.
	 *
	 * @param string $post_type Supported post type.
	 * @param string $query     User query.
	 * @param int    $limit     Maximum IDs.
	 * @param int    $offset    Result offset for paginated hydration.
	 * @return int[]|null
	 */
	public function search( string $post_type, string $query, int $limit, int $offset = 0 ): ?array {
		if ( ! $this->is_ready() || ! in_array( $post_type, $this->supported_post_types(), true ) ) {
			return null;
		}

		$words = $this->query_words( $query );
		if ( empty( $words ) ) {
			return null;
		}

		global $wpdb;
		$table     = self::table_name();
		$limit     = min( 50, max( 1, $limit ) );
		$offset    = max( 0, $offset );
		$cache_key = 'results:' . hash(
			'sha256',
			implode(
				'|',
				array(
					(string) $this->generation(),
					$post_type,
					implode( ',', $words ),
					(string) $limit,
					(string) $offset,
				)
			)
		);
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return array_map( 'intval', $cached );
		}

		$joins = array();
		foreach ( array_slice( $words, 1 ) as $index => $word ) {
			$alias   = 'token_' . ( $index + 1 );
			$joins[] = $wpdb->prepare(
				'INNER JOIN %i %i
					ON token_0.object_id = %i.object_id
					AND token_0.object_type = %i.object_type
					AND %i.token LIKE %s',
				$table,
				$alias,
				$alias,
				$alias,
				$alias,
				$wpdb->esc_like( $word ) . '%'
			);
		}

		// Every JOIN is separately prepared above; only those prepared fragments are composed here.
		$sql      = 'SELECT token_0.object_id
			FROM %i token_0
			' . implode( "\n", $joins ) . '
			WHERE token_0.object_type = %s
			AND token_0.token LIKE %s
			GROUP BY token_0.object_id
			ORDER BY MAX(token_0.weight) DESC, token_0.object_id DESC
			LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Bounded, internally generated prepared JOIN fragments; all external values use placeholders.
		$prepared = $wpdb->prepare(
			$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The composed SQL contains only prepared joins plus static query text.
			$table,
			$post_type,
			$wpdb->esc_like( $words[0] ) . '%',
			$limit,
			$offset
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- The generation-keyed object cache immediately below covers this indexed read.
		$ids = $wpdb->get_col( $prepared );

		$ids = array_map( 'intval', $ids );
		wp_cache_set( $cache_key, $ids, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $ids;
	}

	/**
	 * Queue a supported post (or a variation's parent) for reindexing.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Saved post.
	 * @param bool     $update  Whether this updated an existing post.
	 */
	public function queue_post( int $post_id, \WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'product_variation' === $post->post_type ) {
			$post_id = (int) $post->post_parent;
		} elseif ( ! in_array( $post->post_type, $this->supported_post_types(), true ) ) {
			return;
		}

		if ( $post_id > 0 ) {
			$this->pending_ids[ $post_id ] = true;
		}
	}

	/**
	 * Remove a deleted object immediately so it cannot appear in search.
	 *
	 * @param int $post_id Deleted post ID.
	 */
	public function delete_post( int $post_id ): void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom-index mutation; bump_generation() invalidates all result-cache keys.
		$deleted = $wpdb->delete( self::table_name(), array( 'object_id' => $post_id ), array( '%d' ) );
		if ( is_int( $deleted ) && $deleted > 0 ) {
			$this->bump_generation();
		}
	}

	/** Coalesce request-local saves into one bounded background action. */
	public function flush_pending(): void {
		if ( empty( $this->pending_ids ) || get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			return;
		}

		$ids               = array_map( 'intval', array_keys( $this->pending_ids ) );
		$this->pending_ids = array();

		foreach ( array_chunk( $ids, self::BATCH_SIZE ) as $batch ) {
			if ( ! $this->enqueue_action( self::SYNC_HOOK, array( $batch, 0 ) ) ) {
				$this->log_error( 'Unable to enqueue a search-index update.', array( 'ids' => $batch ) );
			}
		}
	}

	/**
	 * Process one bounded set of changed objects.
	 *
	 * @param int[] $ids     Object IDs.
	 * @param int   $attempt Retry attempt.
	 */
	public function process_sync_batch( array $ids, int $attempt = 0 ): void {
		$ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, self::BATCH_SIZE );
		try {
			$this->sync_ids( $ids );
		} catch ( \Throwable $throwable ) {
			if ( $attempt < self::MAX_RETRIES ) {
				$delay = MINUTE_IN_SECONDS * ( 2 ** $attempt );
				if ( $this->enqueue_action( self::SYNC_HOOK, array( $ids, $attempt + 1 ), false, $delay ) ) {
					return;
				}
			}

			$this->log_error(
				'Search-index update failed.',
				array(
					'attempt' => $attempt,
					'error'   => $throwable->getMessage(),
				)
			);
		}
	}

	/**
	 * Synchronize index rows. Public for deterministic maintenance/tests.
	 *
	 * @param int[] $ids Object IDs.
	 */
	public function sync_ids( array $ids ): void {
		if ( empty( $ids ) ) {
			return;
		}

		_prime_post_caches( $ids, true, true );
		$changed = false;
		foreach ( $ids as $id ) {
			$changed = $this->sync_one( (int) $id ) || $changed;
		}

		if ( $changed ) {
			$this->bump_generation();
		}
	}

	/** Schedule a fresh cursor-based index build. */
	public function schedule_rebuild(): void {
		delete_option( self::INDEX_OPTION );
		update_option( self::REBUILD_CURSOR_OPTION, 0, false );
		$this->set_status( 'scheduled', 0 );
		if ( ! $this->enqueue_action( self::REBUILD_HOOK, array( 0, 0 ), true ) ) {
			$this->set_status( 'failed', 0, 'Unable to enqueue the initial rebuild batch.' );
		}
	}

	/**
	 * Process one rebuild page and schedule the next cursor.
	 *
	 * @param int $cursor  Last indexed object ID.
	 * @param int $attempt Retry attempt.
	 */
	public function process_rebuild_batch( int $cursor = 0, int $attempt = 0 ): void {
		$this->set_status( 'running', $cursor );

		try {
			$this->run_rebuild_batch( $cursor );
		} catch ( \Throwable $throwable ) {
			$this->handle_rebuild_failure( $cursor, $attempt, $throwable );
		}
	}

	/**
	 * Execute one rebuild page.
	 *
	 * @param int $cursor Last indexed object ID.
	 * @throws \RuntimeException When the next batch cannot be scheduled.
	 */
	private function run_rebuild_batch( int $cursor ): void {
		global $wpdb;

		if ( 0 === $cursor ) {
			// Frontend reads are disabled until completion, so clearing here cannot
			// expose a partial index.
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table_name() ) );
		}

		$types = $this->supported_post_types();
		$ids   = array_map(
			'intval',
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cursor worker intentionally reads the next uncached batch exactly once.
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM %i
					WHERE post_type IN (%s, %s, %s)
					AND post_status = 'publish'
					AND post_password = ''
					AND ID > %d
					ORDER BY ID ASC
					LIMIT %d",
					$wpdb->posts,
					$types[0],
					$types[1],
					$types[2],
					$cursor,
					self::BATCH_SIZE
				)
			)
		);

		if ( ! empty( $ids ) ) {
			$this->sync_ids( $ids );
		}

		if ( count( $ids ) === self::BATCH_SIZE ) {
			$next = (int) end( $ids );
			update_option( self::REBUILD_CURSOR_OPTION, $next, false );
			$this->set_status( 'running', $next );
			if ( ! $this->enqueue_action( self::REBUILD_HOOK, array( $next, 0 ), true ) ) {
				throw new \RuntimeException( 'Unable to enqueue the next rebuild batch.' );
			}
			return;
		}

		delete_option( self::REBUILD_CURSOR_OPTION );
		update_option( self::INDEX_OPTION, self::INDEX_VERSION, false );
		$this->bump_generation();
		$this->set_status( 'ready', (int) end( $ids ) );
	}

	/**
	 * Add search-index state to Tools > Site Health > Info.
	 *
	 * @param array<string, mixed> $info Existing debug information.
	 * @return array<string, mixed>
	 */
	public function add_debug_information( array $info ): array {
		$status                                  = $this->get_status();
		$info['aggressive_apparel_search_index'] = array(
			'label'  => __( 'Aggressive Apparel search index', 'aggressive-apparel' ),
			'fields' => array(
				'state'  => array(
					'label' => __( 'State', 'aggressive-apparel' ),
					'value' => (string) ( $status['state'] ?? ( $this->is_ready() ? 'ready' : 'not built' ) ),
				),
				'cursor' => array(
					'label' => __( 'Cursor', 'aggressive-apparel' ),
					'value' => (string) ( $status['cursor'] ?? 0 ),
				),
				'error'  => array(
					'label' => __( 'Last error', 'aggressive-apparel' ),
					'value' => (string) ( $status['error'] ?? '' ),
				),
			),
		);

		return $info;
	}

	/** Cancel pending theme-owned workers when switching themes. */
	public function unschedule_actions(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), self::ACTION_GROUP );
		}
		wp_clear_scheduled_hook( self::SYNC_HOOK );
		wp_clear_scheduled_hook( self::REBUILD_HOOK );
		delete_option( self::REBUILD_CURSOR_OPTION );
		delete_option( self::STATUS_OPTION );
	}

	/**
	 * Synchronize one object and report whether storage changed.
	 *
	 * @param int $id Object ID.
	 */
	private function sync_one( int $id ): bool {
		$post = get_post( $id );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom-index replacement; the batch bumps the cache generation after changes.
		$deleted = $wpdb->delete( self::table_name(), array( 'object_id' => $id ), array( '%d' ) );

		if ( ! $post instanceof \WP_Post || ! Search_Visibility::is_indexable( $post ) ) {
			return is_int( $deleted ) && $deleted > 0;
		}

		$tokens = $this->document_tokens( $post );
		if ( empty( $tokens ) ) {
			return is_int( $deleted ) && $deleted > 0;
		}

		$values = array();
		$params = array();
		$now    = current_time( 'mysql', true );
		foreach ( $tokens as $token => $weight ) {
			$values[] = '(%d, %s, %s, %d, %s)';
			array_push( $params, $id, $post->post_type, $token, $weight, $now );
		}

		$sql = 'INSERT INTO %i (object_id, object_type, token, weight, updated_at) VALUES ' . implode( ', ', $values );
		// The row shape is fixed, token count is capped, and every row value remains a placeholder.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Only repeated fixed placeholder tuples are composed dynamically.
		$prepared = $wpdb->prepare( $sql, ...array_merge( array( self::table_name() ), $params ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom-index batch write; sync_ids() invalidates via generation after success.
		return false !== $wpdb->query( $prepared );
	}

	/**
	 * Build a bounded weighted token map for one object.
	 *
	 * @param \WP_Post $post Source post.
	 * @return array<string, int>
	 */
	private function document_tokens( \WP_Post $post ): array {
		$tokens = array();
		foreach ( $this->tokenize( $post->post_title ) as $token ) {
			$tokens[ $token ] = 10;
		}

		foreach ( $this->tokenize( $this->document_content( $post ) ) as $token ) {
			$tokens[ $token ] = max( 4, $tokens[ $token ] ?? 0 );
			if ( count( $tokens ) >= self::MAX_DOCUMENT_TOKENS ) {
				break;
			}
		}

		return array_slice( $tokens, 0, self::MAX_DOCUMENT_TOKENS, true );
	}

	/**
	 * Build the searchable document without copying full product descriptions.
	 *
	 * @param \WP_Post $post Source post.
	 */
	private function document_content( \WP_Post $post ): string {
		$parts = array( $post->post_excerpt );

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$description = wp_trim_words(
				wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ),
				40,
				''
			);
			if ( '' !== $description ) {
				$parts[] = $description;
			}

			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$parts[] = $product->get_sku();
				if ( $product instanceof \WC_Product_Variable ) {
					global $wpdb;
					// Fetch all variation SKUs in one indexed query instead of hydrating
					// every variation object during a large rebuild.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded rebuild worker avoids hydrating every variation object; source tables own their caches.
					$variation_skus = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT product_lookup.sku
							FROM %i variations
							INNER JOIN %i product_lookup ON variations.ID = product_lookup.product_id
							WHERE variations.post_parent = %d
							AND variations.post_type = 'product_variation'
							AND product_lookup.sku <> ''",
							$wpdb->posts,
							$wpdb->wc_product_meta_lookup,
							$post->ID
						)
					);
					$parts          = array_merge( $parts, array_map( 'strval', $variation_skus ) );
				}
			}

			$taxonomies = array( 'product_cat', 'product_tag', 'product_brand' );
			if ( function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
				$taxonomies = array_merge( $taxonomies, wc_get_attribute_taxonomy_names() );
			}
			$terms = wp_get_object_terms( $post->ID, array_values( array_filter( $taxonomies, 'taxonomy_exists' ) ), array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) ) {
				$parts = array_merge( $parts, array_map( 'strval', $terms ) );
			}
		} else {
			$parts[] = $post->post_content;
		}

		return trim( wp_strip_all_tags( strip_shortcodes( implode( ' ', $parts ) ) ) );
	}

	/**
	 * Convert a user query to safe indexed prefix terms.
	 *
	 * @param string $query User query.
	 */
	private function query_words( string $query ): array {
		return array_slice( array_values( array_unique( $this->tokenize( $query ) ) ), 0, self::MAX_QUERY_WORDS );
	}

	/**
	 * Normalize text to index-safe words.
	 *
	 * @param string $text Source text.
	 * @return string[]
	 */
	private function tokenize( string $text ): array {
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$words = array_filter(
			array_map(
				static fn( string $word ): string => mb_substr( (string) preg_replace( '/[^\p{L}\p{N}]/u', '', $word ), 0, self::MAX_TOKEN_LENGTH ),
				$parts
			),
			static fn( string $word ): bool => mb_strlen( $word ) >= 2
		);

		return array_values( array_unique( $words ) );
	}

	/** Supported content types. */
	private function supported_post_types(): array {
		return Search_Visibility::supported_post_types();
	}

	/** Increment cache generation without deleting rows by wildcard. */
	private function bump_generation(): void {
		update_option( self::GENERATION_OPTION, $this->generation() + 1, false );
	}

	/** Whether an incomplete rebuild is missing or no longer making progress. */
	private function rebuild_needs_recovery(): bool {
		$cursor = get_option( self::REBUILD_CURSOR_OPTION, false );
		$status = $this->get_status();

		if ( false === $cursor || 'failed' === ( $status['state'] ?? '' ) ) {
			return true;
		}

		$updated = (int) ( $status['updated'] ?? 0 );
		return $updated < time() - self::STALE_AFTER;
	}

	/**
	 * Retry or record a failed rebuild batch.
	 *
	 * @param int        $cursor    Failed cursor.
	 * @param int        $attempt   Current retry attempt.
	 * @param \Throwable $throwable Failure.
	 */
	private function handle_rebuild_failure( int $cursor, int $attempt, \Throwable $throwable ): void {
		if ( $attempt < self::MAX_RETRIES ) {
			$delay = MINUTE_IN_SECONDS * ( 2 ** $attempt );
			$this->set_status( 'retrying', $cursor, $throwable->getMessage() );
			if ( $this->enqueue_action( self::REBUILD_HOOK, array( $cursor, $attempt + 1 ), false, $delay ) ) {
				return;
			}
		}

		$this->set_status( 'failed', $cursor, $throwable->getMessage() );
		$this->log_error(
			'Search-index rebuild failed.',
			array(
				'cursor'  => $cursor,
				'attempt' => $attempt,
				'error'   => $throwable->getMessage(),
			)
		);
	}

	/**
	 * Store compact worker health information.
	 *
	 * @param string $state  Worker state.
	 * @param int    $cursor Last processed object ID.
	 * @param string $error  Optional failure message.
	 */
	private function set_status( string $state, int $cursor, string $error = '' ): void {
		update_option(
			self::STATUS_OPTION,
			array(
				'state'   => $state,
				'cursor'  => $cursor,
				'updated' => time(),
				'error'   => $error,
			),
			false
		);
	}

	/**
	 * Read compact worker health information.
	 *
	 * @return array<string, mixed>
	 */
	private function get_status(): array {
		$status = get_option( self::STATUS_OPTION, array() );
		return is_array( $status ) ? $status : array();
	}

	/**
	 * Log an index failure without requiring WooCommerce.
	 *
	 * @param string               $message Failure message.
	 * @param array<string, mixed> $context Structured context.
	 */
	private function log_error( string $message, array $context = array() ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array_merge( array( 'source' => 'aggressive-apparel-search-index' ), $context ) );
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			aggressive_apparel_debug_log( $message, $context );
		}
	}

	/**
	 * Schedule through Action Scheduler with a WP-Cron fallback.
	 *
	 * @param string $hook   Worker hook.
	 * @param array  $args   Worker arguments.
	 * @param bool   $unique Whether an identical action should be unique.
	 * @param int    $delay  Delay in seconds.
	 * @return bool Whether the action was scheduled.
	 */
	private function enqueue_action( string $hook, array $args, bool $unique = false, int $delay = 0 ): bool {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( $unique && function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, self::ACTION_GROUP ) ) {
				return true;
			}

			$action_id = $delay > 0 && function_exists( 'as_schedule_single_action' )
				? as_schedule_single_action( time() + $delay, $hook, $args, self::ACTION_GROUP, $unique )
				: as_enqueue_async_action( $hook, $args, self::ACTION_GROUP, $unique );

			return is_int( $action_id ) && $action_id > 0;
		}

		if ( $unique && wp_next_scheduled( $hook, $args ) ) {
			return true;
		}

		$result = wp_schedule_single_event( time() + max( 1, $delay ), $hook, $args, true );
		return true === $result;
	}

	/** Fully prefixed table name. */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'aa_search_index';
	}
}
