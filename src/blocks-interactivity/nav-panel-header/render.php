<?php
/**
 * Nav Panel Header Block Render
 *
 * Outputs a wrapper div the parent navigation-panel block finds via
 * DOMDocument. The parent strips this wrapper and places the inner content to
 * the left of the panel close button. This block never renders on its own —
 * the navigation-panel block consumes it.
 *
 * @package Aggressive_Apparel
 *
 * @var string $content Inner block content.
 */

declare(strict_types=1);

$wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => 'wp-block-aggressive-apparel-nav-panel-header' )
);

printf(
	'<div %s>%s</div>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes().
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are already escaped.
);
