<?php
/**
 * Legacy Collection URL Redirect
 *
 * The catalogue's audience filter used to read `?fit=mens|womens` (backed by
 * the `pa_fit` attribute). It now reads `?collection=` (backed by
 * `pa_collection`), and Fit means garment silhouette (Product Specs).
 *
 * Header/footer navigation saved in the Site Editor and external links still
 * carry the old parameter, so this redirects exactly those audience values to
 * the canonical `collection` parameter. Any other `fit` value is left alone —
 * `fit` is not an alias for `collection`.
 *
 * Temporary (302), not permanent: a cached 301 would outlive a theme rollback
 * and keep sending old links to a parameter the old theme does not read.
 *
 * Remove once stored navigation is migrated and the old links have aged out.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/** Redirects legacy `?fit=<audience>` catalogue links to `?collection=`. */
final class Collection_Legacy_Url {

	/** Register hooks. */
	public function init(): void {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
	}

	/** Redirect a legacy audience URL on catalogue archives. */
	public function maybe_redirect(): void {
		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public URL routing.
		$target = self::target( wp_unslash( $_GET ), $request_uri );
		if ( null === $target ) {
			return;
		}

		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * Canonical URL for a legacy audience request, or null when the request
	 * is not one.
	 *
	 * @param array<mixed> $query       Request query vars.
	 * @param string       $request_uri Request URI (path + query).
	 */
	public static function target( array $query, string $request_uri ): ?string {
		if ( ! isset( $query['fit'] ) || ! is_string( $query['fit'] ) || isset( $query['collection'] ) ) {
			return null;
		}

		// The same positively identified audience slugs the migration moves,
		// mapped to their Collection term (`men` → `mens`).
		$collections = array();
		foreach ( array_filter( array_map( 'sanitize_title', explode( ',', $query['fit'] ) ) ) as $slug ) {
			if ( ! isset( Collection_Attribute_Migration::AUDIENCE_SLUGS[ $slug ] ) ) {
				return null;
			}
			$collections[] = Collection_Attribute_Migration::AUDIENCE_SLUGS[ $slug ];
		}
		if ( array() === $collections ) {
			return null;
		}

		return add_query_arg( 'collection', implode( ',', array_unique( $collections ) ), remove_query_arg( 'fit', $request_uri ) );
	}
}
