<?php
/**
 * Feature Settings Sanitizer Class
 *
 * Sanitization callbacks for the Store Enhancements settings option group.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature Settings Sanitizer
 *
 * Pure sanitization/validation logic for the Feature Settings option group.
 * Methods are registered as `sanitize_callback`s by Feature_Settings_Page.
 *
 * @since 1.18.0
 */
class Feature_Settings_Sanitizer {

	/**
	 * Sanitize the feature flags array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, bool> Sanitized flags.
	 */
	public function sanitize_features( $input ): array {
		$valid     = array_keys( Feature_Settings::get_feature_definitions() );
		$sanitized = array();

		foreach ( $valid as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize the primary image exit duration option (50–1500 ms).
	 *
	 * @param mixed $input Raw input.
	 * @return int Clamped integer 50–1500.
	 */
	public function sanitize_hover_image_exit_duration( $input ): int {
		return max( 50, min( 1500, (int) $input ) );
	}

	/**
	 * Sanitize the primary image exit animation option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized exit animation slug.
	 */
	public function sanitize_hover_image_exit_animation( $input ): string {
		return $this->sanitize_animation_slug( $input );
	}

	/**
	 * Sanitize the hover image animation option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized animation slug.
	 */
	public function sanitize_hover_image_animation( $input ): string {
		return $this->sanitize_animation_slug( $input );
	}

	/**
	 * Shared validation for hover-image animation slugs.
	 *
	 * @param mixed $input Raw input.
	 * @return string Valid animation slug or `fade` fallback.
	 */
	private function sanitize_animation_slug( $input ): string {
		$valid = array(
			'fade',
			'slide-right',
			'slide-left',
			'slide-up',
			'slide-down',
			'zoom-in',
			'zoom-out',
			'flip-h',
			'flip-v',
			'wipe-right',
			'wipe-left',
			'wipe-up',
			'blur-reveal',
			'diagonal-wipe',
			'rotate-fade',
		);
		return in_array( $input, $valid, true ) ? $input : 'fade';
	}

	/**
	 * Sanitize the filter layout option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized layout value.
	 */
	public function sanitize_filter_layout( $input ): string {
		$valid = array( 'drawer', 'sidebar', 'horizontal' );
		return in_array( $input, $valid, true ) ? $input : 'drawer';
	}

	/**
	 * Sanitize the wishlist button placement option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized placement value (`auto` or `block`).
	 */
	public function sanitize_wishlist_button_placement( $input ): string {
		$valid = array( 'auto', 'block' );
		return in_array( $input, $valid, true ) ? $input : 'auto';
	}

	/**
	 * Sanitize the Quick View media trigger style.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_quick_view_trigger_style( $input ): string {
		$valid = array( 'corner', 'bottom-bar' );
		return in_array( $input, $valid, true ) ? $input : 'corner';
	}

	/**
	 * Sanitize the Quick View media action corner position.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_quick_view_trigger_position( $input ): string {
		$valid = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left' );
		return in_array( $input, $valid, true ) ? $input : 'top-right';
	}

	/**
	 * Sanitize whether Wishlist joins the Quick View media stack.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_quick_view_media_wishlist( $input ): string {
		$valid = array( 'with_wishlist', 'quick_view_only' );
		return in_array( $input, $valid, true ) ? $input : 'with_wishlist';
	}

	/**
	 * Sanitize the load more mode option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized mode value.
	 */
	public function sanitize_load_more_mode( $input ): string {
		$valid = array( 'load_more', 'infinite_scroll' );
		return in_array( $input, $valid, true ) ? $input : 'load_more';
	}

	/**
	 * Sanitize a Store Copy text field.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_store_copy_text( $input ): string {
		if ( ! is_string( $input ) ) {
			return '';
		}

		$text = sanitize_text_field( $input );
		$text = trim( $text );

		if ( strlen( $text ) > 60 ) {
			$text = substr( $text, 0, 60 );
		}

		return $text;
	}

	/**
	 * Sanitize the source mix array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, int> Sanitized mix.
	 */
	public function sanitize_social_proof_sources( $input ): array {
		$valid_keys = array_keys( Feature_Settings::get_social_proof_source_definitions() );
		$sanitized  = array();

		foreach ( $valid_keys as $key ) {
			$weight = isset( $input[ $key ] ) ? (int) $input[ $key ] : 0;
			// Clamp to 0–10 so we can't accidentally store absurd weights.
			$sanitized[ $key ] = max( 0, min( 10, $weight ) );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a multiline messages textarea.
	 *
	 * Preserves line breaks; strips tags and per-line whitespace.
	 * Caps total length defensively.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_social_proof_messages( $input ): string {
		if ( ! is_string( $input ) ) {
			return '';
		}

		// Cap defensive max length so the option stays small.
		if ( strlen( $input ) > 8192 ) {
			$input = substr( $input, 0, 8192 );
		}

		// Normalise line endings, then sanitise each line.
		$lines = preg_split( '/\r\n|\r|\n/', $input );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		$cleaned = array();
		foreach ( $lines as $line ) {
			$line = sanitize_text_field( $line );
			// Cap per-line length for sane toast widths.
			if ( strlen( $line ) > 200 ) {
				$line = substr( $line, 0, 200 );
			}
			$cleaned[] = $line;
		}

		return implode( "\n", $cleaned );
	}

	/**
	 * Sanitize a plain boolean toggle.
	 *
	 * @param mixed $input Raw input.
	 * @return bool
	 */
	public function sanitize_bool_flag( $input ): bool {
		return (bool) $input;
	}

	/**
	 * Sanitize the minimum order age (0–1440 minutes).
	 *
	 * @param mixed $input Raw input.
	 * @return int
	 */
	public function sanitize_social_proof_min_order_age( $input ): int {
		return max( 0, min( 1440, (int) $input ) );
	}

	/**
	 * Sanitize the Engagement minimum lifetime sales gate (1–999999).
	 *
	 * @param mixed $input Raw input.
	 * @return int
	 */
	public function sanitize_social_proof_engagement_min_sales( $input ): int {
		return max( 1, min( 999999, (int) $input ) );
	}

	/**
	 * Sanitize the purchase display mode option.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_social_proof_display_mode( $input ): string {
		$valid = array( 'anonymous', 'initial', 'first_name' );
		return in_array( $input, $valid, true ) ? $input : 'anonymous';
	}

	/**
	 * Sanitize the location granularity option.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_social_proof_location_granularity( $input ): string {
		$valid = array( 'city', 'state', 'country', 'hidden' );
		return in_array( $input, $valid, true ) ? $input : 'city';
	}

	/**
	 * Sanitize the optional purchase-thumbnail badge icon slug.
	 *
	 * @param mixed $input Raw input.
	 * @return string Empty string or valid icon slug from the theme library.
	 */
	public function sanitize_social_proof_purchase_badge_icon( $input ): string {
		$key = sanitize_key( (string) $input );

		return ( '' !== $key && Icons::exists( $key ) ) ? $key : '';
	}
}
