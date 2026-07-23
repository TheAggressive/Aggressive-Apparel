<?php
/**
 * Store Notices (Toasts) Block — Server Render.
 *
 * Drop-in replacement for `woocommerce/store-notices`. Reads the current
 * WooCommerce session notice queue, normalises + sanitises it, clears it,
 * and seeds it into the Interactivity API context so `view.js` can render a
 * dismissible, auto-expiring toast stack.
 *
 * Notice messages keep their inline formatting and action links (e.g. the
 * "View cart" link WooCommerce adds after add-to-cart). They are sanitised
 * twice, defence-in-depth: `wp_kses()` here with a tight allowlist, and again
 * client-side in sanitize.ts before the string is ever assigned to innerHTML.
 *
 * Full-page-cache note (see CLAUDE.md): notices are per-session values and are
 * therefore never baked into a cacheable value we trust — this block renders
 * dynamically (render.php) and only ever appears on notice-bearing pages
 * (cart / checkout / account / post-redirect), which sit outside the page
 * cache. The `data-wp-each` list is seeded per request, not memoised.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Store_Notices;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_get_notices' ) ) {
	return;
}

/**
 * Whitelisted toast positions. Anything else falls back to top-right.
 */
$aa_notice_positions = array(
	'top-right',
	'top-center',
	'top-left',
	'bottom-right',
	'bottom-center',
	'bottom-left',
);

$aa_position = isset( $attributes['position'] ) && in_array( $attributes['position'], $aa_notice_positions, true )
	? (string) $attributes['position']
	: 'top-right';

$aa_badge_position = isset( $attributes['badgePosition'] ) && in_array( $attributes['badgePosition'], array( 'top-right', 'bottom-right' ), true )
	? (string) $attributes['badgePosition']
	: 'bottom-right';

$aa_max_visible = isset( $attributes['maxVisible'] ) ? max( 1, (int) $attributes['maxVisible'] ) : 4;

$aa_durations = array(
	'success' => isset( $attributes['successDuration'] ) ? max( 0, (int) $attributes['successDuration'] ) : 5000,
	'notice'  => isset( $attributes['noticeDuration'] ) ? max( 0, (int) $attributes['noticeDuration'] ) : 6000,
	'error'   => isset( $attributes['errorDuration'] ) ? max( 0, (int) $attributes['errorDuration'] ) : 0,
);

$aa_capture = ! empty( $attributes['captureBlockNotices'] );

/*
 * Inline formatting + links a notice message may keep. Deliberately tight:
 * no block-level tags, no images, no event attributes. This is the first of
 * two sanitisation passes (the second is client-side in sanitize.ts).
 */
$aa_allowed_html = array(
	'a'      => array(
		'href'  => array(),
		'title' => array(),
		'class' => array(),
		'rel'   => array(),
	),
	'strong' => array(),
	'em'     => array(),
	'b'      => array(),
	'i'      => array(),
	'span'   => array( 'class' => array() ),
	'br'     => array(),
	'code'   => array(),
);

/*
 * Normalise the WooCommerce notice queue.
 *
 * wc_get_notices() returns an array keyed by type ('success' | 'error' |
 * 'notice'), each an array of items shaped like array( 'notice' => string,
 * 'data' => array ). Older callers still push bare strings, so both shapes are
 * handled. Messages keep their inline HTML (sanitised via wp_kses) so action
 * links survive; a plain-text guard drops anything that sanitises to nothing.
 */
$aa_notices = array();
$aa_seq     = 0;
$aa_raw     = wc_get_notices();

foreach ( array( 'error', 'success', 'notice' ) as $aa_type ) {
	if ( empty( $aa_raw[ $aa_type ] ) || ! is_array( $aa_raw[ $aa_type ] ) ) {
		continue;
	}

	foreach ( $aa_raw[ $aa_type ] as $aa_item ) {
		$aa_html    = is_array( $aa_item ) ? ( $aa_item['notice'] ?? '' ) : (string) $aa_item;
		$aa_message = trim( wp_kses( (string) $aa_html, $aa_allowed_html ) );

		if ( '' === trim( wp_strip_all_tags( $aa_message ) ) ) {
			continue;
		}

		$aa_notices[] = array(
			'id'          => 'aa-notice-' . ( ++$aa_seq ),
			'type'        => $aa_type,
			'message'     => $aa_message,
			'leaving'     => false,
			'thumbnail'   => '',
			// Visibility flags below are plain booleans (not derived JS state) so
			// they resolve during server-side data-wp-each processing — otherwise
			// the toast paints in its default state and corrects on hydration (the
			// blue→green colour flash and the icon→thumbnail swap).
			'noThumbnail' => true,
			'isSuccess'   => 'success' === $aa_type,
			'isError'     => 'error' === $aa_type,
			'isNotice'    => 'notice' === $aa_type,
		);
	}
}

// We have consumed the queue; clear it so nothing double-renders elsewhere.
if ( ! empty( $aa_raw ) ) {
	wc_clear_notices();
}

/*
 * Pair success notices with the featured images captured at add-to-cart time
 * (Store_Notices), in order. Only consume the queue when there's a success
 * notice to attach to, so unrelated renders don't drain it.
 */
$aa_has_success = (bool) array_filter( $aa_notices, static fn( $n ) => 'success' === $n['type'] );

if ( $aa_has_success ) {
	$aa_images = Store_Notices::consume_images();
	$aa_img_i  = 0;

	foreach ( $aa_notices as &$aa_notice_ref ) {
		if ( 'success' === $aa_notice_ref['type'] && isset( $aa_images[ $aa_img_i ] ) ) {
			$aa_notice_ref['thumbnail']   = esc_url( $aa_images[ $aa_img_i ] );
			$aa_notice_ref['noThumbnail'] = false;
			++$aa_img_i;
		}
	}
	unset( $aa_notice_ref );
}

// Cap the stack so a burst can't fill the screen. Notices are ordered by
// priority (error → success → notice), so keep the FIRST N — dropping the
// lowest-priority (info) notices rather than the errors.
if ( count( $aa_notices ) > $aa_max_visible ) {
	$aa_notices = array_slice( $aa_notices, 0, $aa_max_visible );
}

$aa_context = array(
	'notices'             => array_values( $aa_notices ),
	'maxVisible'          => $aa_max_visible,
	'durations'           => $aa_durations,
	'captureBlockNotices' => $aa_capture,
	'nextId'              => $aa_seq + 1,
	'i18n'                => array(
		'dismiss' => __( 'Dismiss notification', 'aggressive-apparel' ),
	),
);

$aa_wrapper = get_block_wrapper_attributes(
	array(
		'class'               => 'aa-notices aa-notices--' . $aa_position . ' aa-notices--badge-' . $aa_badge_position,
		'data-wp-interactive' => 'aggressive-apparel/store-notices',
		'data-wp-context'     => wp_json_encode( $aa_context ),
		'data-wp-init'        => 'callbacks.init',
		'role'                => 'region',
		'aria-label'          => esc_attr__( 'Store notifications', 'aggressive-apparel' ),
	)
);
?>
<div <?php echo $aa_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes. ?>>
	<?php
	/*
	 * Persistent screen-reader announcers. Live regions must exist in the DOM
	 * before their text changes to be reliably announced, so these stay mounted
	 * and empty; view.js appends each notice's plain text into the polite or
	 * assertive region as a toast appears. Non-atomic so only the newly appended
	 * node is read (simultaneous notices don't clobber each other). The visible
	 * toasts carry no live role themselves, which avoids double announcements.
	 */
	?>
	<div class="aa-notices__sr" aria-live="polite" data-aa-live="polite"></div>
	<div class="aa-notices__sr" aria-live="assertive" data-aa-live="assertive"></div>
	<template
		data-wp-each--notice="context.notices"
		data-wp-each-key="context.notice.id"
	>
		<div
			class="aa-notices__toast"
			tabindex="-1"
			data-wp-class--aa-notices__toast--success="context.notice.isSuccess"
			data-wp-class--aa-notices__toast--error="context.notice.isError"
			data-wp-class--aa-notices__toast--notice="context.notice.isNotice"
			data-wp-class--is-leaving="context.notice.leaving"
			data-wp-init="callbacks.initToast"
			data-wp-on--mouseenter="actions.pause"
			data-wp-on--mouseleave="actions.resume"
			data-wp-on--focusin="actions.pause"
			data-wp-on--focusout="actions.resume"
			data-wp-on--keydown="actions.onKeydown"
		>
			<span class="aa-notices__icon" aria-hidden="true" data-wp-bind--hidden="context.notice.thumbnail"></span>
			<span class="aa-notices__media" aria-hidden="true" data-wp-bind--hidden="context.notice.noThumbnail">
				<img class="aa-notices__thumb" alt="" data-wp-watch="callbacks.syncThumb" />
				<span class="aa-notices__badge"></span>
			</span>
			<p class="aa-notices__message" data-wp-watch="callbacks.syncMessage"></p>
			<button
				type="button"
				class="aa-notices__close"
				data-wp-on--click="actions.dismiss"
				data-wp-bind--aria-label="state.dismissLabel"
			>&times;</button>
		</div>
	</template>
</div>
