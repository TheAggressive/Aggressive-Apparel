<?php
/**
 * Free-shipping message wording tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Feature_Settings_Page;
use Aggressive_Apparel\WooCommerce\Feature_Settings_Sanitizer;
use Aggressive_Apparel\WooCommerce\Free_Shipping_Message;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Free_Shipping_Message
 */
class TestFreeShippingMessage extends WP_UnitTestCase {

	/**
	 * USD formatting fields, independent of the store's settings.
	 *
	 * @var array<string, mixed>
	 */
	private const USD = array(
		'currencyMinorUnit'         => 2,
		'currencyPrefix'            => '$',
		'currencySuffix'            => '',
		'currencyDecimalSeparator'  => '.',
		'currencyThousandSeparator' => ',',
	);

	/**
	 * Reset wording options between tests.
	 */
	public function tearDown(): void {
		delete_option( Free_Shipping_Message::PROGRESS_WORDING_OPTION );
		delete_option( Free_Shipping_Message::UNLOCKED_WORDING_OPTION );
		parent::tearDown();
	}

	/**
	 * Default copy renders as plain text in both states.
	 */
	public function test_format_uses_default_copy(): void {
		$this->assertStringContainsString( 'Away from FREE Shipping!', Free_Shipping_Message::format( 150.0, '', false ) );
		$this->assertSame( 'FREE Shipping UNLOCKED!', Free_Shipping_Message::format( 0.0, '', true ) );
		$this->assertSame( 'FREE Express UNLOCKED!', Free_Shipping_Message::format( 0.0, 'FREE Express', true ) );
	}

	/**
	 * Segments split the amount and highlighted phrase out of the sentence.
	 */
	public function test_segments_mark_amount_and_emphasis(): void {
		$segments = Free_Shipping_Message::segments(
			array_merge(
				array(
					'remaining'    => 49.5,
					'complete'     => false,
					'emphasisText' => 'FREE Shipping',
					'i18n'         => Free_Shipping_Message::get_default_templates(),
				),
				self::USD
			)
		);

		$this->assertSame( array( '$49.50', ' Away from ', 'FREE Shipping', '!' ), array_column( $segments, 'text' ) );
		$this->assertSame( array( true, false, false, false ), array_column( $segments, 'amount' ) );
		$this->assertSame( array( false, false, true, false ), array_column( $segments, 'emphasis' ) );
		$this->assertSame( array( '0-amount', '1-text', '2-emphasis', '3-text' ), array_column( $segments, 'key' ) );
	}

	/**
	 * Translated word order is followed and whole amounts drop zero decimals.
	 */
	public function test_segments_follow_translated_order(): void {
		$segments = Free_Shipping_Message::segments(
			array(
				'remaining'                 => 200.0,
				'complete'                  => false,
				'emphasisText'              => 'Express',
				'i18n'                      => array( 'progressCustom' => '%2$s: noch %1$s' ),
				'currencyMinorUnit'         => 2,
				'currencyPrefix'            => '',
				'currencySuffix'            => ' €',
				'currencyDecimalSeparator'  => ',',
				'currencyThousandSeparator' => '.',
			)
		);

		$this->assertSame( array( 'Express', ': noch ', '200 €' ), array_column( $segments, 'text' ) );
	}

	/**
	 * Broken translations degrade instead of throwing a sprintf error.
	 */
	public function test_segments_tolerate_broken_templates(): void {
		$segments = Free_Shipping_Message::segments(
			array(
				'remaining'    => 10.0,
				'complete'     => false,
				'emphasisText' => '',
				'i18n'         => array( 'progressDefault' => '{em_end}100%% %2$s {em}free' ),
			)
		);

		$this->assertSame( array( '100%  ', 'free' ), array_column( $segments, 'text' ) );
		$this->assertSame( array( false, true ), array_column( $segments, 'emphasis' ) );
	}

	/**
	 * Merchant wording becomes a template: highlights, {amount}, escaped %.
	 */
	public function test_wording_to_template(): void {
		$this->assertSame(
			'{em}%1$s{em_end} left for {em}free{em_end} delivery & 100%% fun*',
			Free_Shipping_Message::wording_to_template( '*{amount}* left for *free* delivery & 100% <em>fun</em>*', true )
		);
		$this->assertSame(
			'{em}Free shipping{em_end} is yours!',
			Free_Shipping_Message::wording_to_template( '*Free shipping* is yours{amount}!', false )
		);
		$this->assertSame( '', Free_Shipping_Message::wording_to_template( "  \n ", true ) );
	}

	/**
	 * Default wording round-trips to the translator template.
	 */
	public function test_default_wording_round_trips(): void {
		$templates = Free_Shipping_Message::get_default_templates();

		$this->assertSame( '{amount} Away from *FREE Shipping*!', Free_Shipping_Message::default_wording( 'progress' ) );
		$this->assertSame( '*FREE Shipping* UNLOCKED!', Free_Shipping_Message::default_wording( 'unlocked' ) );
		$this->assertSame(
			str_replace( '%s', '%1$s', $templates['progressDefault'] ),
			Free_Shipping_Message::wording_to_template( Free_Shipping_Message::default_wording( 'progress' ), true )
		);
	}

	/**
	 * Layers: block override > legacy phrase > site wording > theme default.
	 */
	public function test_wording_layers(): void {
		$context = static fn( array $attributes ): array => array_merge(
			array(
				'remaining' => 12.0,
				'complete'  => false,
			),
			Free_Shipping_Message::block_context( $attributes ),
			self::USD
		);
		$text    = static fn( array $attributes ): string => implode(
			'',
			array_column( Free_Shipping_Message::segments( $context( $attributes ) ), 'text' )
		);

		$this->assertSame( '$12 Away from FREE Shipping!', $text( array() ) );

		update_option( Free_Shipping_Message::PROGRESS_WORDING_OPTION, 'Just {amount} to *free shipping*' );
		$this->assertSame( 'Just $12 to free shipping', $text( array() ) );

		// A legacy phrase is block-level, so it outranks site wording.
		$this->assertSame( '$12 Away from FREE Express!', $text( array( 'emphasisText' => 'FREE Express' ) ) );

		// Block wording applies only while its toggle is on.
		$override = array(
			'progressText' => 'Only {amount} left',
			'emphasisText' => 'FREE Express',
		);
		$this->assertSame( '$12 Away from FREE Express!', $text( $override ) );
		$this->assertSame( 'Only $12 left', $text( array( 'useCustomWording' => true ) + $override ) );

		// Blank block wording inherits the site wording.
		$this->assertSame( 'Just $12 to free shipping', $text( array( 'useCustomWording' => true ) ) );
	}

	/**
	 * Store Copy stores untouched wording blank so translations keep applying.
	 */
	public function test_store_copy_inherits_translated_default(): void {
		$definition = Feature_Settings::get_store_copy_definitions()['free_shipping_progress_text'];
		$sanitizer  = new Feature_Settings_Sanitizer();

		$this->assertSame( '', $sanitizer->sanitize_store_copy_text( $definition['default'], $definition ) );
		$this->assertSame( 'Only {amount} to go', $sanitizer->sanitize_store_copy_text( 'Only {amount} to go', $definition ) );
		$this->assertSame( 120, mb_strlen( $sanitizer->sanitize_store_copy_text( str_repeat( 'a', 200 ), $definition ) ) );

		// A typo is rejected, keeping the previous value.
		update_option( Free_Shipping_Message::PROGRESS_WORDING_OPTION, 'Only {amount} to go' );
		$this->assertSame( 'Only {amount} to go', $sanitizer->sanitize_store_copy_text( 'Only {amout} to go', $definition ) );

		// The unlocked message cannot show an amount.
		$unlocked = Feature_Settings::get_store_copy_definitions()['free_shipping_unlocked_text'];
		$this->assertSame( '', $sanitizer->sanitize_store_copy_text( 'Unlocked {amount}', $unlocked ) );
	}

	/**
	 * The editor edits the site wording over /wp/v2/settings.
	 */
	public function test_site_wording_is_exposed_to_rest(): void {
		( new Feature_Settings_Page() )->register_rest_settings();

		$settings = get_registered_settings();

		$this->assertTrue( $settings[ Free_Shipping_Message::PROGRESS_WORDING_OPTION ]['show_in_rest'] );
		$this->assertTrue( $settings[ Free_Shipping_Message::UNLOCKED_WORDING_OPTION ]['show_in_rest'] );
		$this->assertSame( '', $settings[ Free_Shipping_Message::PROGRESS_WORDING_OPTION ]['default'] );
	}
}
