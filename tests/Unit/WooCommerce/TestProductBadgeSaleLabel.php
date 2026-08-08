<?php
/**
 * Merchant-authored sale wording tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Sale_Pricing;
use WP_UnitTestCase;

/** Test the `{percent}` token contract shared by badges and prices. */
class TestProductBadgeSaleLabel extends WP_UnitTestCase {
	/**
	 * Templates resolve against a discount the product can supply.
	 *
	 * @dataProvider discounted_templates
	 *
	 * @param string $template Wording under test.
	 * @param string $expected Rendered result.
	 */
	public function test_formats_label_with_a_discount( string $template, string $expected ): void {
		$this->assertSame(
			$expected,
			Sale_Pricing::format_text( $template, 'On Sale', 20 ),
		);
	}

	/**
	 * Wording variants a merchant can pick from the suggestions list.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function discounted_templates(): array {
		return array(
			'legacy default'  => array( '-{percent}%', '-20%' ),
			'trailing phrase' => array( '{percent}% Off', '20% Off' ),
			'leading phrase'  => array( 'Save {percent}%', 'Save 20%' ),
			'repeated token'  => array( '{percent}% / {percent}%', '20% / 20%' ),
		);
	}

	/**
	 * Wording that never mentions a number ignores the discount entirely, so
	 * the fallback stays unused rather than overriding a deliberate phrase.
	 */
	public function test_static_wording_is_used_verbatim(): void {
		$this->assertSame( 'Now on Sale', Sale_Pricing::format_text( 'Now on Sale', 'On Sale', 20 ) );
		$this->assertSame( 'Now on Sale', Sale_Pricing::format_text( 'Now on Sale', 'On Sale', 0 ) );
	}

	/** A template wanting a number it cannot get falls back to plain wording. */
	public function test_falls_back_when_no_discount_is_available(): void {
		$this->assertSame( 'On Sale', Sale_Pricing::format_text( 'Save {percent}%', 'On Sale', 0 ) );
		$this->assertSame( 'On Sale', Sale_Pricing::format_text( '-{percent}%', 'On Sale', -5 ) );
	}

	/**
	 * An empty fallback means "render nothing", which is how the price line
	 * stays silent on a product with no single discount figure while the badge
	 * still says the product is reduced.
	 */
	public function test_empty_fallback_suppresses_output(): void {
		$this->assertSame( '', Sale_Pricing::format_text( 'Save {percent}%', '', 0 ) );
		$this->assertSame( 'Save 20%', Sale_Pricing::format_text( 'Save {percent}%', '', 20 ) );
	}

	/** The seeded defaults reproduce the pre-Store-Copy badge text. */
	public function test_default_copy_reproduces_the_historic_label(): void {
		$this->assertSame(
			'-20%',
			Sale_Pricing::format_text(
				Feature_Settings::get_sale_badge_text(),
				Feature_Settings::get_sale_badge_no_discount_text(),
				20,
			),
		);
	}

	/** The seeded defaults reproduce the pre-Store-Copy price savings note. */
	public function test_default_copy_reproduces_the_historic_savings_note(): void {
		$this->assertSame(
			'Save 20%',
			Sale_Pricing::format_text( Feature_Settings::get_price_savings_text(), '', 20 ),
		);
	}

	/** The rule reads as a plain "Sale" toggle and points at its wording. */
	public function test_rules_panel_labels_the_sale_rule(): void {
		$taxonomy = new Custom_Badge_Taxonomy();

		ob_start();
		$taxonomy->render_rules_panel();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="sale_enabled"', $html );
		$this->assertStringNotContainsString( 'Sale percentage', $html );
		$this->assertStringContainsString( 'tab=copy', $html );
	}

	/**
	 * Every rule card has the same shape: a `<div>` of labels. The link on the
	 * Sale card must not turn its card into a different kind of control.
	 */
	public function test_every_rule_card_shares_one_structure(): void {
		$taxonomy = new Custom_Badge_Taxonomy();

		ob_start();
		$taxonomy->render_rules_panel();
		$html = (string) ob_get_clean();

		$this->assertSame( 4, substr_count( $html, '<div class="aa-badge-rule">' ), 'Four rule cards, all divs' );
		$this->assertSame( 4, substr_count( $html, 'aa-badge-rule__toggle' ), 'Each card labels its own checkbox' );
		$this->assertSame( 3, substr_count( $html, 'aa-badge-rule__threshold' ), 'Each threshold labels its own number input' );
		$this->assertStringNotContainsString( '<label class="aa-badge-rule"', $html, 'The card itself is never the label' );

		// A link inside a label would toggle the checkbox on click.
		$this->assertDoesNotMatchRegularExpression( '#<label[^>]*>(?:(?!</label>).)*<a #s', $html );
	}
}
