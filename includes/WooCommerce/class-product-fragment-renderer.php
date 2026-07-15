<?php
/**
 * Product Fragment Renderer
 *
 * @package Aggressive_Apparel
 * @since 1.66.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Product Collection card fragments through WooCommerce's block pipeline.
 *
 * @since 1.66.0
 */
final class Product_Fragment_Renderer {

	/**
	 * Render one query page using the saved Product Collection block.
	 *
	 * Context-dependent block-support hashes may differ from the initial document,
	 * so the returned fragment owns the exact deterministic styles its markup uses.
	 *
	 * @param array<string, mixed> $collection_block Parsed Product Collection block.
	 * @param \WP_Query            $query            Query with this page's products.
	 * @return Rendered_Product_Fragment
	 * @throws \UnexpectedValueException When the block cannot be fingerprinted or does not render a Product Template list.
	 */
	public function render( array $collection_block, \WP_Query $query ): Rendered_Product_Fragment {
		global $wp_query;

		if ( ! isset( $collection_block['attrs'] ) || ! is_array( $collection_block['attrs'] ) ) {
			$collection_block['attrs'] = array();
		}
		if ( ! isset( $collection_block['attrs']['query'] ) || ! is_array( $collection_block['attrs']['query'] ) ) {
			$collection_block['attrs']['query'] = array();
		}
		$collection_block['attrs']['query']['inherit'] = true;

		$saved_query         = $wp_query;
		$fingerprint_payload = wp_json_encode( array( get_stylesheet(), AGGRESSIVE_APPAREL_VERSION, get_bloginfo( 'version' ), $collection_block ) );
		if ( ! is_string( $fingerprint_payload ) ) {
			throw new \UnexpectedValueException( 'Unable to encode the Product Collection style fingerprint.' );
		}
		$fingerprint = hash( 'sha256', $fingerprint_payload );

		/**
		 * CSP nonce applied to dynamic style elements installed by the client.
		 *
		 * @param string $nonce Current nonce (empty by default).
		 */
		$nonce     = (string) apply_filters( 'aggressive_apparel_dynamic_style_nonce', '' );
		$collector = new Block_Support_Style_Collector( $fingerprint, $nonce );
		$collector->start();
		// Listing-only features (quick view, wishlist, badges) use this scoped
		// signal while WooCommerce renders the appended product cards.
		add_filter( 'aggressive_apparel_is_listing_page', '__return_true' );

		// WooCommerce's inherited collection clones the current global query. The
		// scoped swap is always restored, including when block rendering throws.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Scoped swap, restored in finally.
		$wp_query = $query;

		try {
			$rendered = ( new \WP_Block( $collection_block ) )->render();
		} finally {
			$collector->stop();
			remove_filter( 'aggressive_apparel_is_listing_page', '__return_true' );
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the real global query.
			$wp_query = $saved_query;
			wp_reset_postdata();
		}

		$items = $this->extract_template_items( $rendered );
		if ( null === $items ) {
			throw new \UnexpectedValueException( 'Rendered Product Collection did not contain a Product Template list.' );
		}

		return new Rendered_Product_Fragment( $items, $collector->assets() );
	}

	/**
	 * Return the inner `<li>` cards from the Product Template list.
	 *
	 * Nested `<ul>` depth is tracked so lists inside cards are not mistaken for
	 * the template's closing tag. String parsing preserves SVG attribute casing.
	 *
	 * @param string $html Rendered Product Collection HTML.
	 * @return ?string Card markup, or null when the expected wrapper is absent.
	 */
	public function extract_template_items( string $html ): ?string {
		if ( ! preg_match( '/<ul\b[^>]*\bwc-block-product-template\b[^>]*>/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$inner_start = $match[0][1] + strlen( $match[0][0] );
		$offset      = $inner_start;
		$length      = strlen( $html );
		$depth       = 1;

		while ( $offset < $length && $depth > 0 ) {
			$open  = preg_match( '/<ul\b/', $html, $open_match, PREG_OFFSET_CAPTURE, $offset ) ? (int) $open_match[0][1] : -1;
			$close = strpos( $html, '</ul>', $offset );

			if ( false === $close ) {
				break;
			}

			if ( $open > -1 && $open < $close ) {
				++$depth;
				$offset = $open + 3;
			} else {
				--$depth;
				if ( 0 === $depth ) {
					return substr( $html, $inner_start, $close - $inner_start );
				}
				$offset = $close + 5;
			}
		}

		return null;
	}
}
