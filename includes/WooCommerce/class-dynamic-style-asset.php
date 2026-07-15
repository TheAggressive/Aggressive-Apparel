<?php
/**
 * Immutable CSS asset emitted with a dynamically rendered block fragment.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Value object for a deterministic, client-installable style asset.
 */
final class Dynamic_Style_Asset {

	/**
	 * Create an immutable dynamic style asset.
	 *
	 * @param string $id    Deterministic SHA-256 asset identifier.
	 * @param string $css   Compiled stylesheet.
	 * @param string $nonce Optional CSP nonce.
	 */
	public function __construct(
		private string $id,
		private string $css,
		private string $nonce = ''
	) {}

	/**
	 * Serialize the public REST representation.
	 *
	 * @return array{id: string, css: string, nonce?: string}
	 */
	public function to_array(): array {
		$data = array(
			'id'  => $this->id,
			'css' => $this->css,
		);

		if ( '' !== $this->nonce ) {
			$data['nonce'] = $this->nonce;
		}

		return $data;
	}
}
