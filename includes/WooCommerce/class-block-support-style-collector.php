<?php
/**
 * Dynamic block-support style collector.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Collects element-support rules against classes present in rendered markup.
 */
final class Block_Support_Style_Collector {

	private const CACHE_PREFIX = 'aa_dynamic_css_';
	private const CACHE_TTL    = WEEK_IN_SECONDS;

	/**
	 * Assets collected during the current render, keyed by immutable ID.
	 *
	 * @var array<string, Dynamic_Style_Asset>
	 */
	private array $assets = array();

	/**
	 * Scoped render_block filter callback.
	 *
	 * @var ?callable
	 */
	private $filter = null;

	/**
	 * Create a scoped collector.
	 *
	 * @param string $render_fingerprint Template/runtime cache fingerprint.
	 * @param string $nonce              Optional CSP nonce.
	 */
	public function __construct(
		private string $render_fingerprint,
		private string $nonce = ''
	) {}

	/** Start collecting for the current render scope. */
	public function start(): void {
		if ( null !== $this->filter ) {
			return;
		}

		$this->filter = function ( string $content, array $block ): string {
			$this->collect_block( $content, $block );
			return $content;
		};
		add_filter( 'render_block', $this->filter, PHP_INT_MAX, 2 );
	}

	/** Always stop collecting after the scoped render. */
	public function stop(): void {
		if ( null === $this->filter ) {
			return;
		}

		remove_filter( 'render_block', $this->filter, PHP_INT_MAX );
		$this->filter = null;
	}

	/**
	 * Return styles collected during the render.
	 *
	 * @return Dynamic_Style_Asset[]
	 */
	public function assets(): array {
		return array_values( $this->assets );
	}

	/**
	 * Capture CSS for the exact wp-elements class applied to a block.
	 *
	 * @param string               $content Rendered block markup.
	 * @param array<string, mixed> $block   Processed block data.
	 */
	private function collect_block( string $content, array $block ): void {
		$elements = $block['attrs']['style']['elements'] ?? null;
		if ( ! is_array( $elements ) || ! preg_match( '/\b(wp-elements-[a-f0-9]+)\b/', $content, $match ) ) {
			return;
		}

		$signature = array(
			'contract'    => 1,
			'fingerprint' => $this->render_fingerprint,
			'block'       => $block['blockName'] ?? '',
			'class'       => $match[1],
			'elements'    => $elements,
		);
		$id        = hash( 'sha256', (string) wp_json_encode( $signature ) );

		if ( isset( $this->assets[ $id ] ) ) {
			return;
		}

		$cache_key = self::CACHE_PREFIX . $id;
		$css       = get_transient( $cache_key );
		if ( ! is_string( $css ) ) {
			$css = $this->compile_element_styles( $match[1], $elements );
			if ( '' !== $css ) {
				$ttl = (int) apply_filters( 'aggressive_apparel_dynamic_style_cache_ttl', self::CACHE_TTL );
				set_transient( $cache_key, $css, max( MINUTE_IN_SECONDS, $ttl ) );
			}
		}

		if ( '' !== $css ) {
			$this->assets[ $id ] = new Dynamic_Style_Asset( $id, $css, $this->nonce );
		}
	}

	/**
	 * Mirror WordPress element-support selectors while retaining the rendered hash.
	 *
	 * @param string               $class_name Rendered wp-elements class.
	 * @param array<string, mixed> $elements   Block style.elements object.
	 * @return string Compiled CSS.
	 */
	private function compile_element_styles( string $class_name, array $elements ): string {
		$class = '.' . $class_name;
		$rules = array(
			'button'  => array( 'selector' => "$class .wp-element-button, $class .wp-block-button__link" ),
			'link'    => array(
				'selector'       => "$class a:where(:not(.wp-element-button))",
				'hover_selector' => "$class a:where(:not(.wp-element-button)):hover",
			),
			'heading' => array( 'selector' => "$class h1, $class h2, $class h3, $class h4, $class h5, $class h6" ),
			'h1'      => array( 'selector' => "$class h1" ),
			'h2'      => array( 'selector' => "$class h2" ),
			'h3'      => array( 'selector' => "$class h3" ),
			'h4'      => array( 'selector' => "$class h4" ),
			'h5'      => array( 'selector' => "$class h5" ),
			'h6'      => array( 'selector' => "$class h6" ),
		);

		$css = '';
		foreach ( $rules as $element => $selectors ) {
			$style = $elements[ $element ] ?? null;
			if ( ! is_array( $style ) ) {
				continue;
			}

			$compiled = wp_style_engine_get_styles( $style, array( 'selector' => $selectors['selector'] ) );
			$css     .= $compiled['css'];

			if ( isset( $selectors['hover_selector'] ) && is_array( $style[':hover'] ?? null ) ) {
				$hover = wp_style_engine_get_styles( $style[':hover'], array( 'selector' => $selectors['hover_selector'] ) );
				$css  .= $hover['css'];
			}
		}

		return $css;
	}
}
