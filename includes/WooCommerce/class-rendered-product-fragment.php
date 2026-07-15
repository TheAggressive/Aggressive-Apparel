<?php
/**
 * Rendered product-card fragment response value.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Keeps HTML and its required dynamic styles inseparable.
 */
final class Rendered_Product_Fragment {

	/**
	 * Create a rendered fragment and its inseparable style dependencies.
	 *
	 * @param string                $html   Rendered product-card markup.
	 * @param Dynamic_Style_Asset[] $styles Dynamic styles required by the markup.
	 */
	public function __construct(
		private string $html,
		private array $styles
	) {}

	/** Return rendered product-card markup. */
	public function html(): string {
		return $this->html;
	}

	/**
	 * Return serializable dynamic style assets.
	 *
	 * @return array<int, array{id: string, css: string, nonce?: string}>
	 */
	public function styles(): array {
		return array_map(
			static fn( Dynamic_Style_Asset $asset ): array => $asset->to_array(),
			$this->styles
		);
	}
}
