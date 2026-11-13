<?php
/**
 * Sales category — reconciliation run state
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
 * The bookkeeping of a full Sales-category rebuild: the expiring lock that
 * lets only one run proceed (each batch proves ownership with the run's
 * token) and the status record shown in WooCommerce → Status → Tools. The
 * batching itself is Sale_Category.
 */
final class Sale_Category_Run {

	/**
	 * Option holding the lock: the owning run's token and its expiry.
	 *
	 * @var string
	 */
	private const LOCK_OPTION = 'aggressive_apparel_sale_category_reconcile_lock';

	/**
	 * Option holding the latest run's status.
	 *
	 * @var string
	 */
	private const STATUS_OPTION = 'aggressive_apparel_sale_category_status';

	/**
	 * Lock lifetime in seconds; refreshed by every batch.
	 *
	 * @var int
	 */
	private const LOCK_TTL = 21600;

	/**
	 * Determine whether an unexpired run owns the lock (clearing a stale one).
	 *
	 * @return bool Whether a repair is active.
	 */
	public function is_active(): bool {
		$lock = get_option( self::LOCK_OPTION );
		if ( is_array( $lock ) && (int) ( $lock['expires_at'] ?? 0 ) > time() ) {
			return true;
		}

		delete_option( self::LOCK_OPTION );
		return false;
	}

	/**
	 * Take the lock for a new run and reset its status.
	 *
	 * @return string|null The run token, or null when another run won the lock.
	 */
	public function start(): ?string {
		$token = wp_generate_uuid4();
		$lock  = array(
			'token'      => $token,
			'expires_at' => time() + self::LOCK_TTL,
		);

		if ( ! add_option( self::LOCK_OPTION, $lock, '', false ) ) {
			return null;
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

		return $token;
	}

	/**
	 * Check lock ownership.
	 *
	 * @param string $token Run token.
	 * @return bool Whether this worker owns the lock.
	 */
	public function owns( string $token ): bool {
		$lock = get_option( self::LOCK_OPTION );

		return is_array( $lock )
			&& hash_equals( (string) ( $lock['token'] ?? '' ), $token )
			&& (int) ( $lock['expires_at'] ?? 0 ) > time();
	}

	/**
	 * Extend the lock for the run holding it.
	 *
	 * @param string $token Run token.
	 * @return void
	 */
	public function refresh( string $token ): void {
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
	 * Record a finished batch.
	 *
	 * @param int $cursor  Last product ID processed.
	 * @param int $checked Products checked in the batch.
	 * @param int $changed Assignments changed in the batch.
	 * @param int $failed  Products that failed in the batch.
	 * @return void
	 */
	public function record_batch( int $cursor, int $checked, int $changed, int $failed ): void {
		$status = $this->status();
		update_option(
			self::STATUS_OPTION,
			array_merge(
				$status,
				array(
					'state'      => 'running',
					'updated_at' => time(),
					'cursor'     => $cursor,
					'checked'    => (int) ( $status['checked'] ?? 0 ) + $checked,
					'changed'    => (int) ( $status['changed'] ?? 0 ) + $changed,
					'failed'     => (int) ( $status['failed'] ?? 0 ) + $failed,
				)
			),
			false
		);
	}

	/**
	 * Complete or fail a run and release its lock.
	 *
	 * @param string $token Run token.
	 * @param string $state Final state.
	 * @param string $error Optional error message.
	 * @return bool Whether this run held the lock (and so was finished).
	 */
	public function finish( string $token, string $state, string $error = '' ): bool {
		if ( ! $this->owns( $token ) ) {
			return false;
		}

		update_option(
			self::STATUS_OPTION,
			array_merge(
				$this->status(),
				array(
					'state'        => $state,
					'updated_at'   => time(),
					'completed_at' => time(),
					'last_error'   => $error,
				)
			),
			false
		);

		$this->release();
		return true;
	}

	/**
	 * Drop the lock regardless of owner (theme switch).
	 *
	 * @return void
	 */
	public function release(): void {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Human-readable health summary for WooCommerce Status > Tools.
	 *
	 * @return string Escaped status summary.
	 */
	public function health_summary(): string {
		$status = $this->status();
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
	 * Latest run status.
	 *
	 * @return array<string, mixed> Status data.
	 */
	private function status(): array {
		$status = get_option( self::STATUS_OPTION, array() );

		return is_array( $status ) ? $status : array();
	}
}
