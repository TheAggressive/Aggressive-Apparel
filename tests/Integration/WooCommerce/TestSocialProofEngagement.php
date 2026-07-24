<?php
/**
 * Social Proof Engagement source integration tests.
 *
 * Validates catalog engagement notifications against WooCommerce total sales.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Social_Proof;
use ReflectionClassConstant;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Social Proof Engagement tests (requires WooCommerce).
 */
final class TestSocialProofEngagement extends WP_UnitTestCase {

	/**
	 * IDs of WC products created in a test method.
	 *
	 * @var array<int>
	 */
	private array $created_product_ids = array();

	/**
	 * Prior option value for engagement min sales restoration.
	 *
	 * @var mixed
	 */
	private $previous_min_sales_option;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wc_get_products' ) ) {
			$this->markTestSkipped( 'WooCommerce must be loaded for engagement tests.' );
		}

		$this->previous_min_sales_option = get_option(
			Feature_Settings::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION,
			null
		);
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		foreach ( $this->created_product_ids as $product_id ) {
			if ( function_exists( 'wc_delete_product' ) ) {
				wc_delete_product( absint( $product_id ), true );
			}
		}
		$this->created_product_ids = array();

		if ( null !== $this->previous_min_sales_option && false !== $this->previous_min_sales_option ) {
			update_option(
				Feature_Settings::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION,
				$this->previous_min_sales_option
			);
		} else {
			delete_option( Feature_Settings::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION );
		}

		parent::tearDown();
	}

	/**
	 * Products below the min sales threshold must not yield engagement rows.
	 *
	 * @return void
	 */
	public function test_engagement_notifications_exclude_below_minimum_sales(): void {
		update_option(
			Feature_Settings::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION,
			999
		);

		$low_id = $this->create_simple_product_with_sales( 'AA-SP-Engagement-Low', 3 );
		$items  = $this->invoke_engagement_notifications();
		$mine   = $this->matching_engagement_rows( $items, array( $low_id ) );

		$this->assertCount(
			0,
			$mine,
			'Low-sales product must not produce an engagement toast when gate is higher.'
		);
	}

	/**
	 * Products meeting the threshold yield engagement payloads with catalog links.
	 *
	 * @return void
	 */
	public function test_engagement_notifications_include_at_minimum_sales_threshold(): void {
		update_option(
			Feature_Settings::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION,
			7
		);

		$high_id = $this->create_simple_product_with_sales( 'AA-SP-Engagement-High', 12 );
		$items   = $this->invoke_engagement_notifications();
		$mine    = $this->matching_engagement_rows( $items, array( $high_id ) );

		$this->assertCount(
			1,
			$mine,
			'Qualifying sales must produce exactly one engagement row for that product.'
		);

		$this->assertSame( 'engagement', $mine[0]['kind'] );
		$this->assertSame( '', $mine[0]['time'] ?? '' );

		$product = wc_get_product( $high_id );
		$this->assertNotFalse( $product );
		if ( false !== $product ) {
			$expected_url = esc_url_raw( (string) $product->get_permalink() );

			$this->assertSame(
				self::normalize_url_for_compare( $expected_url ),
				self::normalize_url_for_compare( (string) ( $mine[0]['url'] ?? '' ) ),
				'Toast URL matches storefront product permalink.'
			);

			$this->assertStringContainsString(
				(string) $product->get_name(),
				(string) $mine[0]['message'],
				'Message includes the WooCommerce title.'
			);
		}
	}

	/**
	 * Mixed catalog: only products at or above the gate appear among matches.
	 *
	 * @return void
	 */
	public function test_engagement_notifications_filter_mixed_sales_counts(): void {
		update_option(
			Feature_Settings::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION,
			6
		);

		$low_id  = $this->create_simple_product_with_sales( 'AA-SP-Engagement-Mixed-Low', 2 );
		$high_id = $this->create_simple_product_with_sales( 'AA-SP-Engagement-Mixed-High', 20 );
		$items   = $this->invoke_engagement_notifications();
		$low_ok  = $this->matching_engagement_rows( $items, array( $low_id ) );
		$high_ok = $this->matching_engagement_rows( $items, array( $high_id ) );

		$this->assertCount( 0, $low_ok, 'Below-threshold SKU must stay out.' );
		$this->assertCount(
			1,
			$high_ok,
			'Above-threshold SKU must be included.'
		);
		$this->assertStringContainsString(
			'AA-SP-Engagement-Mixed-High',
			(string) $high_ok[0]['message'],
			'Message carries the WooCommerce product name.'
		);
	}

	/**
	 * @return int Product ID.
	 */
	private function create_simple_product_with_sales( string $title, int $total_sales ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( $title );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_regular_price( '10.00' );
		$product->set_total_sales( $total_sales );

		$saved_id = $product->save();
		if ( is_wp_error( $saved_id ) ) {
			$this->fail( 'WooCommerce fixture product failed to save.' );
		}

		$id = (int) $saved_id;
		$this->assertGreaterThan( 0, $id, 'WooCommerce fixture product must persist with a positive ID.' );

		$this->created_product_ids[] = $id;

		return $id;
	}

	/**
	 * The rendered toast is a decorative marketing surface: hidden from the
	 * accessibility tree (so a ~20s cycle doesn't spam polite announcements) with
	 * its interactive children removed from the tab order (no focusable
	 * descendants inside aria-hidden). This locks that contract so a future edit
	 * can't silently re-introduce the live region or a keyboard-focusable,
	 * pointer-catching "ghost" toast.
	 *
	 * @return void
	 */
	public function test_toast_container_markup_is_decorative_not_a_live_region(): void {
		// Single-product context so Social_Proof::should_show() passes.
		$product_id = $this->create_simple_product_with_sales( 'AA-SP-Markup-Contract', 0 );
		$this->go_to( (string) get_permalink( $product_id ) );

		// Seed the cached pool so a toast renders without needing order history
		// or admin demo mode. The transient key is private, so read it reflectively.
		$transient_key = (string) ( new ReflectionClassConstant( Social_Proof::class, 'TRANSIENT_KEY' ) )->getValue();
		set_transient(
			$transient_key,
			array(
				array(
					'message'    => 'Someone just grabbed this',
					'time'       => '2 minutes ago',
					'url'        => (string) get_permalink( $product_id ),
					'thumbnail'  => '',
					'decor_html' => '',
					'badge_html' => '',
					'kind'       => 'purchases',
				),
			),
			MINUTE_IN_SECONDS
		);

		ob_start();
		( new Social_Proof() )->render_toast_container();
		$html = (string) ob_get_clean();

		delete_transient( $transient_key );

		$this->assertNotSame( '', $html, 'A toast should render on a single product when notifications exist.' );

		// Decorative, not a cycling live region.
		$this->assertStringContainsString( 'aria-hidden="true"', $html, 'Container must be hidden from the accessibility tree.' );
		$this->assertStringNotContainsString( 'aria-live', $html, 'The decorative toast must not be a live region.' );
		$this->assertStringNotContainsString( 'role="status"', $html, 'The decorative toast must not expose role=status.' );

		// No focusable descendants inside aria-hidden.
		$this->assertMatchesRegularExpression(
			'/social-proof__link[^>]*tabindex="-1"/',
			$html,
			'The link must be out of the tab order inside aria-hidden.'
		);
		$this->assertMatchesRegularExpression(
			'/social-proof__close[^>]*tabindex="-1"/',
			$html,
			'The dismiss button must be out of the tab order inside aria-hidden.'
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function invoke_engagement_notifications(): array {
		$subject = new Social_Proof();

		$method = new ReflectionMethod( Social_Proof::class, 'build_engagement_notifications' );
		$method->setAccessible( true );

		return $method->invoke( $subject );
	}

	/**
	 * Engagement rows targeting one or more catalog products (matched by canonical URL).
	 *
	 * @param array<int, array<string, mixed>> $items Rows from the builder.
	 * @param array<int>                       $product_ids Product post IDs created in-test.
	 * @return array<int, array<string, mixed>>
	 */
	private function matching_engagement_rows( array $items, array $product_ids ): array {
		$url_set = array();
		foreach ( $product_ids as $pid ) {
			$url = $this->storefront_product_url_normalized( absint( $pid ) );
			if ( '' !== $url ) {
				$url_set[ $url ] = true;
			}
		}

		return array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $url_set ) {
					return is_array( $item )
						&& isset( $item['kind'], $item['url'] )
						&& 'engagement' === $item['kind']
						&& isset( $url_set[ self::normalize_url_for_compare( (string) $item['url'] ) ] );
				},
			)
		);
	}

	private static function normalize_url_for_compare( string $url ): string {
		$url = untrailingslashit( strtolower( trim( $url ) ) );

		return $url;
	}

	private function storefront_product_url_normalized( int $product_id ): string {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return '';
		}

		return self::normalize_url_for_compare(
			esc_url_raw( (string) $product->get_permalink() )
		);
	}
}
