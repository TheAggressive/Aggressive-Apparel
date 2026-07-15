<?php
/**
 * Essential Helper Functions
 *
 * Only the most commonly used helper functions
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get asset URI
 *
 * @param string $path Asset path relative to theme root.
 * @return string Full asset URI.
 */
function aggressive_apparel_asset_uri( $path ) {
	return AGGRESSIVE_APPAREL_URI . '/' . ltrim( $path, '/' );
}

/**
 * Get asset path
 *
 * @param string $path Asset path relative to theme root.
 * @return string Full asset path.
 */
function aggressive_apparel_asset_path( $path ) {
	return AGGRESSIVE_APPAREL_DIR . '/' . ltrim( $path, '/' );
}

/**
 * Get trusted theme SVG icon markup.
 *
 * Thin wrapper around Icons::get() so PHPCS can treat the return value as
 * auto-escaped (class methods cannot be registered in customAutoEscapedFunctions).
 *
 * @param string               $icon  Icon name.
 * @param array<string, mixed> $attrs Optional SVG attributes.
 * @return string SVG markup or empty string if icon not found.
 */
function aggressive_apparel_get_icon( string $icon, array $attrs = array() ): string {
	return \Aggressive_Apparel\Core\Icons::get( $icon, $attrs );
}

/**
 * Echo a trusted theme SVG icon.
 *
 * @param string               $icon  Icon name.
 * @param array<string, mixed> $attrs Optional SVG attributes.
 */
function aggressive_apparel_render_icon( string $icon, array $attrs = array() ): void {
	echo aggressive_apparel_get_icon( $icon, $attrs );
}

/**
 * Mark HTML as trusted for escaped output (PHPCS EscapeOutput).
 *
 * Use only when the markup is already safe:
 * - InnerBlocks `$content` from the block editor
 * - Strings built with esc_html / esc_attr / esc_url in the same scope
 * - Theme SVG from Icons / Icon_Block
 * - Static theme chrome (critical CSS, announcer shells)
 *
 * Registered in phpcs.xml.dist as customAutoEscapedFunctions so call sites
 * do not need phpcs:ignore. Never pass unsanitized request/user input.
 *
 * @param string $html Already-escaped or otherwise trusted HTML.
 * @return string Same HTML, safe to echo/printf under WPCS EscapeOutput.
 */
function aggressive_apparel_trusted_html( string $html ): string {
	return $html;
}

/**
 * Product rating marks markup (brand icon fill).
 *
 * Thin wrapper so PHPCS can treat Rating::stars() as auto-escaped.
 *
 * @param float $rating Average rating 0–5.
 * @return string Accessible rating HTML.
 */
function aggressive_apparel_rating_stars( float $rating ): string {
	return \Aggressive_Apparel\WooCommerce\Rating::stars( $rating );
}

/**
 * Brand / library icon SVG for the icon block.
 *
 * @param string    $slug Icon slug.
 * @param int|float $size Pixel size.
 * @return string SVG markup or empty string.
 */
function aggressive_apparel_icon_block_svg( string $slug, int|float $size = 48 ): string {
	return \Aggressive_Apparel\Blocks\Icon_Block::render_svg( $slug, $size );
}

/**
 * Auto-detect the lowest free-shipping threshold from WooCommerce shipping zones.
 *
 * @return float Threshold in store currency, or 0 when none configured.
 */
function aggressive_apparel_free_shipping_threshold(): float {
	return \Aggressive_Apparel\WooCommerce\Free_Shipping::get_threshold();
}

/**
 * Write a theme diagnostic through WordPress's native error pipeline.
 *
 * Callers decide when to invoke (e.g. WP_DEBUG). Do not pass secrets.
 *
 * @param string               $message Log message.
 * @param array<string, mixed> $context Optional structured context.
 */
function aggressive_apparel_debug_log( string $message, array $context = array() ): void {
	$line = '[Aggressive Apparel] ' . $message;
	if ( array() !== $context ) {
		$encoded = wp_json_encode( $context );
		if ( is_string( $encoded ) ) {
			$line .= ' ' . $encoded;
		}
	}

	wp_trigger_error( __FUNCTION__, $line, E_USER_NOTICE );
}

/**
 * Sanitize a custom product-badge SVG payload.
 *
 * @param mixed $value Raw SVG payload.
 */
function aggressive_apparel_sanitize_badge_svg( mixed $value ): string {
	return is_string( $value )
		? \Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy::sanitize_svg( $value )
		: '';
}

/**
 * Sanitize structured per-product tab overrides.
 *
 * @param mixed                                                  $value     Raw override rows.
 * @param \Aggressive_Apparel\WooCommerce\Product_Tabs_Sanitizer $sanitizer Product-tabs sanitizer.
 * @return array<string, array<string, string>>
 */
function aggressive_apparel_sanitize_tab_overrides(
	mixed $value,
	\Aggressive_Apparel\WooCommerce\Product_Tabs_Sanitizer $sanitizer
): array {
	return $sanitizer->sanitize_tab_overrides( $value );
}

/**
 * Sanitize structured per-product custom tabs.
 *
 * @param mixed                                                  $value     Raw custom-tab rows.
 * @param \Aggressive_Apparel\WooCommerce\Product_Tabs_Sanitizer $sanitizer Product-tabs sanitizer.
 * @return array<int, array<string, mixed>>
 */
function aggressive_apparel_sanitize_custom_tabs(
	mixed $value,
	\Aggressive_Apparel\WooCommerce\Product_Tabs_Sanitizer $sanitizer
): array {
	return $sanitizer->sanitize_custom_tabs( $value );
}

/**
 * Whether the current visitor may see block debug tooling.
 *
 * Debug Mode on the parallax / animate-on-scroll blocks is a saved block
 * attribute, so without this gate a page saved with it enabled would ship
 * the debug overlays (and download the debug script chunk) to every
 * visitor. Requires an editing capability rather than a mere login so
 * logged-in customers never see it either.
 *
 * @return bool True when debug tooling may render for this request.
 */
function aggressive_apparel_can_view_block_debug(): bool {
	/**
	 * Filters who may see front-end block debug tooling.
	 *
	 * @param bool $can_view Defaults to current_user_can( 'edit_posts' ).
	 */
	return (bool) apply_filters(
		'aggressive_apparel_can_view_block_debug',
		current_user_can( 'edit_posts' )
	);
}

/**
 * Translated strings for the front-end block debug tooling.
 *
 * The debug UI lives in code-split view-module chunks where
 * `@wordpress/i18n` is unavailable, so translation happens here and the
 * result is printed as the `#aa-dbg-i18n` JSON blob (see
 * aggressive_apparel_enqueue_block_debug_assets()). Keys MUST mirror
 * `DEFAULT_STRINGS` in src/blocks-interactivity/debug-shared/i18n.ts;
 * missing keys fall back to English in the module.
 *
 * @return array<string,string> Translated debug UI strings.
 */
function aggressive_apparel_block_debug_strings(): array {
	return array(
		'titleParallax'      => __( 'Parallax Debug', 'aggressive-apparel' ),
		'titleAos'           => __( 'Animate On Scroll Debug', 'aggressive-apparel' ),
		'panelCollapse'      => __( 'Collapse debug panel', 'aggressive-apparel' ),
		'panelExpand'        => __( 'Expand debug panel', 'aggressive-apparel' ),
		'sectionLive'        => __( 'Live state', 'aggressive-apparel' ),
		'sectionDetails'     => __( 'Details', 'aggressive-apparel' ),
		'legend'             => __( 'Legend', 'aggressive-apparel' ),
		'rowState'           => __( 'State', 'aggressive-apparel' ),
		'rowVisibility'      => __( 'Visibility', 'aggressive-apparel' ),
		'rowProgress'        => __( 'Progress', 'aggressive-apparel' ),
		'rowDirection'       => __( 'Scroll direction', 'aggressive-apparel' ),
		'rowThreshold'       => __( 'Threshold', 'aggressive-apparel' ),
		'rowFramerate'       => __( 'Frame rate', 'aggressive-apparel' ),
		'rowSize'            => __( 'Element size', 'aggressive-apparel' ),
		'rowBoundary'        => __( 'Boundary', 'aggressive-apparel' ),
		'rowObserver'        => __( 'Observer', 'aggressive-apparel' ),
		'phaseWaiting'       => __( 'Waiting', 'aggressive-apparel' ),
		'phaseApproaching'   => __( 'Approaching', 'aggressive-apparel' ),
		'phaseActive'        => __( 'Active', 'aggressive-apparel' ),
		'engineLabel'        => __( 'Engine', 'aggressive-apparel' ),
		'engineActive'       => __( 'Active', 'aggressive-apparel' ),
		'engineIdle'         => __( 'Idle', 'aggressive-apparel' ),
		'animationLabel'     => __( 'Animation', 'aggressive-apparel' ),
		'animationShown'     => __( 'Shown', 'aggressive-apparel' ),
		'animationHidden'    => __( 'Hidden', 'aggressive-apparel' ),
		'reverseLabel'       => __( 'Reverse on scroll back', 'aggressive-apparel' ),
		'yes'                => __( 'Yes', 'aggressive-apparel' ),
		'no'                 => __( 'No', 'aggressive-apparel' ),
		'directionDown'      => __( '↓ Down', 'aggressive-apparel' ),
		'directionUp'        => __( '↑ Up', 'aggressive-apparel' ),
		'measuring'          => __( '— measuring…', 'aggressive-apparel' ),
		/* translators: {pct} is replaced with a percentage number. */
		'thresholdEntry'     => __( '{pct}% entry', 'aggressive-apparel' ),
		/* translators: {entry} and {exit} are replaced with percentage numbers. */
		'thresholdEntryExit' => __( '{entry}% entry · {exit}% exit', 'aggressive-apparel' ),
		'boundaryConfigured' => __( 'Detection boundary', 'aggressive-apparel' ),
		'boundaryEffective'  => __( 'Observer boundary (incl. engine buffer)', 'aggressive-apparel' ),
		'boundaryExtends'    => __( '· extends beyond viewport', 'aggressive-apparel' ),
		/* translators: {pct} is replaced with a percentage number. */
		'lineEntryBottom'    => __( 'Entry (bottom) {pct}%', 'aggressive-apparel' ),
		/* translators: {pct} is replaced with a percentage number. */
		'lineEntryTop'       => __( 'Entry (top) {pct}%', 'aggressive-apparel' ),
		/* translators: {pct} is replaced with a percentage number. */
		'lineExit'           => __( 'Exit ≤ {pct}%', 'aggressive-apparel' ),
		'legendBoundary'     => __( 'Detection boundary — area the observer watches (viewport ± your margins)', 'aggressive-apparel' ),
		'legendEffective'    => __( 'Observer boundary — detection boundary plus the engine’s pre-activation buffer', 'aggressive-apparel' ),
		'legendElement'      => __( 'This block’s element — outlined even while its content is hidden', 'aggressive-apparel' ),
		/* translators: {pct} is replaced with a percentage number. */
		'legendEntry'        => __( 'Entry line — triggers at {pct}% visible when scrolling down', 'aggressive-apparel' ),
		/* translators: {pct} is replaced with a percentage number. */
		'legendEntryTop'     => __( 'Entry line for scrolling up (same {pct}%, measured from the bottom)', 'aggressive-apparel' ),
		/* translators: {pct} is replaced with a percentage number. */
		'legendExit'         => __( 'Exit line — reverses once visibility falls below {pct}%', 'aggressive-apparel' ),
		'legendZone'         => __( 'Entry zone — tinted band the boundary edge must reach to trigger', 'aggressive-apparel' ),
		/* translators: Four brace-delimited placeholders are replaced with measurements. */
		'warnUnreachable'    => __( 'Entry threshold {pct}% is unreachable: the element ({elem}px) is taller than the detection area ({root}px). Max visibility ≈ {max}%.', 'aggressive-apparel' ),
	);
}

/**
 * Enqueue everything the front-end block debug tooling needs.
 *
 * Called from a block's render.php only after
 * aggressive_apparel_can_view_block_debug() has passed: loads the
 * debug-only stylesheet and prints the translated-strings JSON blob
 * once in the footer. Production visitors never reach this.
 *
 * @return void
 */
function aggressive_apparel_enqueue_block_debug_assets(): void {
	\Aggressive_Apparel\Assets\Asset_Loader::enqueue_feature_style(
		'aggressive-apparel-debug-overlays',
		'build/styles/components/debug-overlays'
	);

	static $strings_hooked = false;
	if ( $strings_hooked ) {
		return;
	}
	$strings_hooked = true;

	add_action(
		'wp_footer',
		static function (): void {
			printf(
				'<script type="application/json" id="aa-dbg-i18n">%s</script>',
				wp_json_encode(
					aggressive_apparel_block_debug_strings(),
					JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
				)
			);
		}
	);
}
