<?php
/**
 * Order progress tracker: Placed → In production → Shipped.
 *
 * @package Aggressive_Apparel
 * @since 1.186.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Maps a WooCommerce order status onto three customer-facing steps.
 *
 * Printful prints on demand: "processing" means the garment is being made,
 * and Printful completes the order when it ships. On-hold (payment awaiting
 * confirmation) counts as placed. Unpaid (pending, failed), cancelled,
 * refunded and draft orders get no tracker: nothing is moving, and the status
 * chip plus the Pay action already say what to do.
 */
class Order_Progress {

	/**
	 * Furthest step reached per status (0 placed, 1 in production, 2 shipped).
	 *
	 * @var array<string, int>
	 */
	private const REACHED = array(
		'on-hold'    => 0,
		'processing' => 1,
		'completed'  => 2,
	);

	/**
	 * Step labels, in order.
	 *
	 * @return array<int, string>
	 */
	private static function labels(): array {
		return array(
			__( 'Placed', 'aggressive-apparel' ),
			__( 'In production', 'aggressive-apparel' ),
			__( 'Shipped', 'aggressive-apparel' ),
		);
	}

	/**
	 * Whether a status has a tracker.
	 *
	 * @param string $status Status slug, without the `wc-` prefix.
	 * @return bool
	 */
	public static function applies( string $status ): bool {
		return isset( self::REACHED[ $status ] );
	}

	/**
	 * Per-step state: 'done', 'current' or 'upcoming'.
	 *
	 * @param string $status Status slug, without the `wc-` prefix.
	 * @return array<int, string> Empty when the status has no tracker.
	 */
	public static function states( string $status ): array {
		if ( ! self::applies( $status ) ) {
			return array();
		}

		$reached  = self::REACHED[ $status ];
		$finished = 'completed' === $status;
		$states   = array();

		foreach ( array_keys( self::labels() ) as $i ) {
			if ( $i < $reached || ( $i === $reached && $finished ) ) {
				$states[ $i ] = 'done';
			} elseif ( $i === $reached ) {
				$states[ $i ] = 'current';
			} else {
				$states[ $i ] = 'upcoming';
			}
		}

		return $states;
	}

	/**
	 * Print the tracker, or nothing for statuses without one.
	 *
	 * @param \WC_Order $order The order.
	 * @return void
	 */
	public static function render( \WC_Order $order ): void {
		$states = self::states( $order->get_status() );

		if ( array() === $states ) {
			return;
		}

		$labels = self::labels();

		printf( '<ol class="aa-order-progress" aria-label="%s">', esc_attr__( 'Order progress', 'aggressive-apparel' ) );

		foreach ( $states as $i => $state ) {
			printf(
				'<li class="aa-order-progress__step is-%1$s"%2$s><span class="aa-order-progress__label">%3$s</span>%4$s</li>',
				esc_attr( $state ),
				'current' === $state ? ' aria-current="step"' : '',
				esc_html( $labels[ $i ] ),
				'done' === $state ? '<span class="screen-reader-text"> ' . esc_html__( '(complete)', 'aggressive-apparel' ) . '</span>' : ''
			);
		}

		echo '</ol>';
	}
}
