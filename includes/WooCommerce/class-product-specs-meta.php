<?php
/**
 * Product Specs Meta
 *
 * Product meta for how specs present on one product: hidden items and
 * wording overrides. Spec values themselves are WooCommerce attributes
 * (Product_Specs_Taxonomy), so they show in the REST API and CSV
 * import/export as ordinary attributes. The `_aa_spec_{key}` keys here are
 * where values lived before; they are read until Product_Specs_Migration
 * moves them.
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
 * Product Specs meta registration and value parsing.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 */
final class Product_Specs_Meta {

	/**
	 * Per-item value meta key prefix.
	 *
	 * @var string
	 */
	public const PREFIX = '_aa_spec_';

	/**
	 * Item keys hidden on one product.
	 *
	 * @var string
	 */
	public const HIDDEN = '_aa_spec_hidden';

	/**
	 * Per-product wording overrides, keyed by item key.
	 *
	 * @var string
	 */
	public const WORDING = '_aa_spec_wording';

	/**
	 * Longest stored text value.
	 *
	 * @var int
	 */
	private const VALUE_MAX = 200;

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * Meta key for an item's value.
	 *
	 * @param string $item_key Item key.
	 */
	public static function key( string $item_key ): string {
		return self::PREFIX . $item_key;
	}

	/**
	 * Register product meta.
	 */
	public function register(): void {
		register_post_meta(
			'product',
			self::HIDDEN,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_keys' ),
				'auth_callback'     => array( self::class, 'can_edit_post' ),
				'show_in_rest'      => false,
			)
		);
		register_post_meta(
			'product',
			self::WORDING,
			array(
				'type'              => 'object',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( Product_Specs::class, 'sanitize_wording' ),
				'auth_callback'     => array( self::class, 'can_edit_post' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Normalize a stored item value (step index, number, or text).
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_value( $value ): string {
		return is_scalar( $value ) ? mb_substr( trim( sanitize_text_field( (string) $value ) ), 0, self::VALUE_MAX ) : '';
	}

	/**
	 * Keep only safe item keys.
	 *
	 * @param mixed $value Raw list.
	 * @return list<string>
	 */
	public static function sanitize_keys( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$keys = array();
		foreach ( $value as $key ) {
			$key = is_scalar( $key ) ? sanitize_key( (string) $key ) : '';
			if ( '' !== $key ) {
				$keys[ $key ] = $key;
			}
		}

		return array_values( $keys );
	}

	/**
	 * Picked step (0-based) from a stored value, or -1.
	 *
	 * @param mixed $value Stored value.
	 * @param array $item  Item.
	 * @phpstan-param Item $item
	 */
	public static function step( $value, array $item ): int {
		$value = self::sanitize_value( $value );
		if ( 1 !== preg_match( '/^\d+$/', $value ) ) {
			return -1;
		}

		$step = (int) $value;

		return $step < count( $item['steps'] ) ? $step : -1;
	}

	/**
	 * Positive number from a stored value, or 0.
	 *
	 * @param mixed $value Stored value.
	 */
	public static function number( $value ): float {
		$value = self::sanitize_value( $value );

		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : 0.0;
	}

	/**
	 * Meta auth callback: the user may edit the post.
	 *
	 * @param bool   $allowed  Whether allowed so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 */
	public static function can_edit_post( $allowed, $meta_key, $post_id ): bool {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_post', (int) $post_id );
	}
}
