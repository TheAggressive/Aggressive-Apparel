<?php
/**
 * Page Cache Purge
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Purges the page cache when Site Editor design changes are saved.
 *
 * Templates, template parts, global styles, navigation and synced patterns
 * render on many pages but have no public URL of their own. Breeze purges on
 * Customizer and classic-menu saves but skips these updates (and never
 * reaches Cloudflare for them), so cached pages keep the old design.
 *
 * Saves are collected and purged once per request: the Site Editor often
 * saves several entities together.
 */
final class Page_Cache_Purge {

	/** Site Editor post types whose content renders across the site. */
	private const DESIGN_POST_TYPES = array(
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'wp_block',
	);

	/**
	 * Whether a design change in this request still needs a purge.
	 *
	 * @var bool
	 */
	private bool $pending = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		foreach ( self::DESIGN_POST_TYPES as $post_type ) {
			add_action( "save_post_{$post_type}", array( $this, 'schedule' ) );
		}
		// Reverting a customized template deletes its post.
		add_action( 'deleted_post', array( $this, 'schedule_for_deleted' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'purge' ) );
	}

	/**
	 * Mark the page cache stale.
	 *
	 * @return void
	 */
	public function schedule(): void {
		$this->pending = true;
	}

	/**
	 * Mark the page cache stale when a design entity is deleted.
	 *
	 * @param int      $post_id Deleted post ID.
	 * @param \WP_Post $post    Deleted post.
	 * @return void
	 */
	public function schedule_for_deleted( $post_id, $post ): void {
		if ( $post instanceof \WP_Post && in_array( $post->post_type, self::DESIGN_POST_TYPES, true ) ) {
			$this->pending = true;
		}
	}

	/**
	 * Purge every page-cache layer, once.
	 *
	 * `breeze_clear_all_cache` is Breeze's own full purge (local files,
	 * Varnish and Cloudflare); without Breeze it has no listeners.
	 *
	 * @return void
	 */
	public function purge(): void {
		if ( ! $this->pending ) {
			return;
		}

		$this->pending = false;
		do_action( 'breeze_clear_all_cache' );
	}
}
