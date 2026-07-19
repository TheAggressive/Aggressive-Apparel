<?php
/**
 * Unit tests for the Aggressive Apparel Modal block.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);


namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Test modal block render output.
 */
class Modal_Block_Test extends WP_UnitTestCase {

	/**
	 * Register theme blocks.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Blocks::init();
	}

	/**
	 * Render the modal block with the supplied attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	private function render_modal( array $attributes ): string {
		return render_block(
			array(
				'blockName'    => 'aggressive-apparel/modal',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Dialog exposes a resolvable accessible name and trigger ARIA.
	 *
	 * @return void
	 */
	public function test_dialog_accessible_name_and_trigger_aria(): void {
		$html = $this->render_modal(
			array(
				'modalId'      => 'a11y-modal',
				'triggerLabel' => 'View details',
			)
		);

		$this->assertStringContainsString( 'aria-labelledby="a11y-modal-label"', $html );
		$this->assertStringContainsString( 'id="a11y-modal-label"', $html );
		$this->assertStringContainsString( 'View details', $html );
		$this->assertStringContainsString( 'aria-haspopup="dialog"', $html );
		$this->assertStringContainsString( 'aria-controls="a11y-modal"', $html );
		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringNotContainsString( 'data-wp-bind--aria-expanded', $html );
		$this->assertStringNotContainsString( 'aria-label="View details"', $html );
	}

	/**
	 * Icon-only close uses aria-label; visible close label does not duplicate it.
	 *
	 * @return void
	 */
	public function test_close_button_aria_label_only_when_icon_only(): void {
		$icon_only = $this->render_modal( array() );
		$this->assertStringContainsString( 'aria-label="Close modal"', $icon_only );
		$this->assertStringNotContainsString(
			'wp-block-aggressive-apparel-modal__close-label',
			$icon_only
		);

		$labeled = $this->render_modal(
			array(
				'closeButtonLabel' => 'Dismiss',
			)
		);
		$this->assertStringContainsString(
			'wp-block-aggressive-apparel-modal__close-label',
			$labeled
		);
		$this->assertStringContainsString( '>Dismiss</span>', $labeled );
		$this->assertStringNotContainsString(
			'aria-label="Dismiss"',
			$labeled
		);
	}

	/**
	 * disableOverlay omits the dismissible backdrop.
	 *
	 * @return void
	 */
	public function test_disable_overlay_omits_backdrop(): void {
		$with = $this->render_modal( array() );
		$this->assertStringContainsString(
			'wp-block-aggressive-apparel-modal__backdrop',
			$with
		);

		$without = $this->render_modal( array( 'disableOverlay' => true ) );
		$this->assertStringNotContainsString(
			'wp-block-aggressive-apparel-modal__backdrop',
			$without
		);
	}

	/**
	 * Auto-open / scroll / exit-intent suppress the built-in trigger.
	 *
	 * @return void
	 */
	public function test_auto_triggers_suppress_builtin_trigger(): void {
		foreach (
			array(
				array( 'exitIntentTrigger' => true ),
				array( 'scrollDepthTrigger' => true ),
				array( 'triggerBlockId' => 'some-client-id' ),
			) as $attrs
		) {
			$html = $this->render_modal( $attrs );
			$this->assertStringNotContainsString(
				'wp-block-aggressive-apparel-modal__trigger',
				$html,
				'Built-in trigger should be omitted for ' . wp_json_encode( $attrs )
			);
		}

		$open_on_load = $this->render_modal( array( 'openOnLoad' => true ) );
		$this->assertStringContainsString(
			'wp-block-aggressive-apparel-modal__trigger',
			$open_on_load
		);
	}

	/**
	 * Outside close is rendered as a sibling of the dialog.
	 *
	 * @return void
	 */
	public function test_outside_close_is_sibling_of_dialog(): void {
		$html = $this->render_modal(
			array(
				'modalId'              => 'out-close',
				'closeButtonPlacement' => 'outside-top-right',
			)
		);

		$this->assertMatchesRegularExpression(
			'/id="out-close"[\\s\\S]*<\\/div>\\s*<button[^>]*close-placement-outside-top-right/',
			$html
		);
	}

	/**
	 * Drawer positions use their positional transform for exit animation.
	 *
	 * @return void
	 */
	public function test_drawer_positions_use_positional_exit_animation(): void {
		$html = $this->render_modal(
			array(
				'position' => 'bottom',
			)
		);

		$this->assertStringContainsString( 'modal-position-bottom', $html );
		$this->assertStringContainsString( 'data-exit-animation="position"', $html );
		$this->assertStringContainsString( 'modal-enter-fade', $html );
	}

	/**
	 * Invalid class, range, and inline-style values fall back safely.
	 *
	 * @return void
	 */
	public function test_invalid_attributes_are_constrained(): void {
		$html = $this->render_modal(
			array(
				'position'          => 'not-a-position',
				'animationDuration' => 99999,
				'overlayOpacity'    => 999,
				'dialogMaxWidth'    => '20rem; color: red',
			)
		);

		$this->assertStringContainsString( 'modal-position-center', $html );
		$this->assertStringContainsString( '--aa-modal-duration: 1000ms', $html );
		$this->assertStringContainsString( '--aa-overlay-opacity: 90%', $html );
		$this->assertStringNotContainsString( '--aa-dialog-max-width', $html );
		$this->assertStringNotContainsString( 'color: red', $html );
	}

	/**
	 * Preset shadow support is forwarded onto the dialog panel.
	 *
	 * @return void
	 */
	public function test_preset_shadow_is_forwarded(): void {
		$html = $this->render_modal(
			array(
				'style' => array(
					'shadow' => 'var:preset|shadow|x-large',
				),
			)
		);

		$this->assertStringContainsString(
			'--aa-dialog-shadow: var(--wp--preset--shadow--x-large)',
			$html
		);
	}

	/**
	 * Scalar radius values remain supported.
	 *
	 * @return void
	 */
	public function test_scalar_border_radius_is_forwarded(): void {
		$html = $this->render_modal(
			array(
				'style' => array(
					'border' => array( 'radius' => '12px' ),
				),
			)
		);

		$this->assertStringContainsString( '--aa-dialog-border-radius: 12px', $html );
	}

	/**
	 * Per-corner radius values are serialized in CSS shorthand order.
	 *
	 * @return void
	 */
	public function test_per_corner_border_radius_is_normalized(): void {
		$html = $this->render_modal(
			array(
				'style' => array(
					'border' => array(
						'radius' => array(
							'topLeft'     => '1px',
							'topRight'    => '2px',
							'bottomRight' => '3px',
							'bottomLeft'  => '4px',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( '--aa-dialog-border-radius: 1px 2px 3px 4px', $html );
		$this->assertStringNotContainsString( '--aa-dialog-border-radius: Array', $html );
	}
}
