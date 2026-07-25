<?php
/**
 * Social Proof settings + resolution.
 *
 * Extracted from Feature_Settings to keep each file under the length cap.
 * Composed into Feature_Settings via `use`; all Feature_Settings::method()
 * callers are unchanged.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Social_Proof_Settings {
	/**
	 * Available social proof sources with their human-readable labels
	 * and descriptions. Demo is intentionally NOT a "source" here — it
	 * lives in its own toggle because of the admin-only visibility gate.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_social_proof_source_definitions(): array {
		return array(
			'trust'         => array(
				'label'       => __( 'Trust Messages', 'aggressive-apparel' ),
				'description' => __( 'Always-on brand trust signals from your Trust Messages list. Works at zero orders.', 'aggressive-apparel' ),
			),
			'purchases'     => array(
				'label'       => __( 'Real Purchases', 'aggressive-apparel' ),
				'description' => __( 'Anonymized recent orders. Skipped silently when you have no eligible orders.', 'aggressive-apparel' ),
			),
			'announcements' => array(
				'label'       => __( 'Custom Announcements', 'aggressive-apparel' ),
				'description' => __( 'Short-term promos / seasonal copy from your Announcements list.', 'aggressive-apparel' ),
			),
			'engagement'    => array(
				'label'       => __( 'Engagement (sales signal)', 'aggressive-apparel' ),
				'description' => __( 'Shows top-selling catalogue products backed by WooCommerce total sales counts (honest bestseller cues). Quieter than live purchase notices.', 'aggressive-apparel' ),
			),
		);
	}

	/**
	 * Default source mix for new installs.
	 *
	 * Real purchases dominate by default — they are the strongest social
	 * proof signal, so they lead the rotation as soon as eligible orders
	 * exist. Trust messages stay enabled at lower weight so brand-new
	 * stores with zero orders still see useful content immediately
	 * (purchases are skipped silently when there are none).
	 *
	 * @return array<string, int>
	 */
	private static function get_default_social_proof_sources(): array {
		return array(
			'trust'         => 3,
			'purchases'     => 8,
			'announcements' => 0,
			'engagement'    => 2,
		);
	}

	/**
	 * Public accessor: trust messages with defaults applied.
	 *
	 * An empty saved row (e.g. the page was once saved with a blank
	 * textarea) also falls back to the defaults (via the schema's
	 * `empty_means_default`) so the textarea always shows the copy that
	 * would actually render. Disabling the source is done via the Trust
	 * weight (0), not by emptying the box.
	 *
	 * @return string Raw textarea contents (newline-separated).
	 */
	public static function get_social_proof_trust_messages(): string {
		return (string) self::get_setting( self::SOCIAL_PROOF_TRUST_MESSAGES_OPTION );
	}

	/**
	 * Public accessor: custom announcements with defaults applied.
	 *
	 * @return string Raw textarea contents (newline-separated).
	 */
	public static function get_social_proof_announcements(): string {
		return (string) self::get_setting( self::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION );
	}

	/**
	 * Minimum WooCommerce lifetime sales gate for Engagement toasts.
	 *
	 * Clamped on read as well as on save so legacy rows stored before
	 * the sanitizer existed can never yield a zero/negative gate.
	 *
	 * @return int
	 */
	public static function get_social_proof_engagement_min_sales(): int {
		return max( 1, min( 999999, (int) self::get_setting( self::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION ) ) );
	}

	/**
	 * Whether the admin-only demo preview toggle is on.
	 *
	 * Note: visibility additionally requires `edit_theme_options` — see
	 * Social_Proof::should_show_demo().
	 *
	 * @return bool
	 */
	public static function is_social_proof_demo_enabled(): bool {
		return (bool) self::get_setting( self::SOCIAL_PROOF_DEMO_OPTION );
	}

	/**
	 * Minimum order age (minutes) before a purchase may surface.
	 *
	 * @return int
	 */
	public static function get_social_proof_min_order_age(): int {
		return max( 0, (int) self::get_setting( self::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION ) );
	}

	/**
	 * Resolve the thumbnail badge slug (unset DB row uses the bundled fallback icon).
	 *
	 * Stored empty string hides the thumbnail badge deliberately.
	 *
	 * @return string Sanitized theme icon slug — empty when suppressed.
	 */
	public static function resolve_social_proof_purchase_badge_icon_slug(): string {
		$stored = get_option( self::SOCIAL_PROOF_PURCHASE_BADGE_ICON_OPTION, null );

		if ( null === $stored || false === $stored ) {
			return self::SOCIAL_PROOF_PURCHASE_BADGE_FALLBACK_SLUG;
		}

		return sanitize_key( (string) $stored );
	}

	/**
	 * Public accessor: resolved source mix (defaults applied).
	 *
	 * @return array<string, int>
	 */
	public static function get_social_proof_sources(): array {
		$saved    = get_option( self::SOCIAL_PROOF_SOURCES_OPTION, array() );
		$defaults = self::get_default_social_proof_sources();

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $defaults;
		}

		$resolved = array();
		foreach ( $defaults as $key => $weight ) {
			$resolved[ $key ] = isset( $saved[ $key ] ) ? max( 0, min( 10, (int) $saved[ $key ] ) ) : $weight;
		}

		return $resolved;
	}

	/**
	 * Social proof display mode (e.g. 'anonymous', 'named').
	 *
	 * @return string
	 */
	public static function get_social_proof_display_mode(): string {
		return (string) self::get_setting( self::SOCIAL_PROOF_DISPLAY_MODE_OPTION );
	}

	/**
	 * Social proof location granularity (e.g. 'city', 'region', 'country').
	 *
	 * @return string
	 */
	public static function get_social_proof_location_granularity(): string {
		return (string) self::get_setting( self::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION );
	}
}
