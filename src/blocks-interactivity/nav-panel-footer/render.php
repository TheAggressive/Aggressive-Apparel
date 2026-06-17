<?php
/**
 * Nav Panel Footer Block Render
 *
 * Outputs a wrapper div the parent navigation-panel block finds via
 * DOMDocument. The parent strips this wrapper and places the inner content in a
 * sticky footer. This block never renders on its own — the navigation-panel
 * block consumes it.
 *
 * @package Aggressive_Apparel
 *
 * @var string $content Inner block content.
 */

declare(strict_types=1);

// Only output if there is inner content to inject.
if ( empty( $content ) ) {
	return;
}

printf(
	'<div class="wp-block-aggressive-apparel-nav-panel-footer">%s</div>',
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are already escaped.
);
