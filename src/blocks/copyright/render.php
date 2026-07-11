<?php
/**
 * Copyright Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array         $attributes Block attributes.
 * @var string        $content    Block default content.
 * @var WP_Block|null $block      Block instance.
 */

declare(strict_types=1);

use Aggressive_Apparel\Blocks\Copyright;

echo aggressive_apparel_trusted_html(
	Copyright::render( $attributes, $block ?? null )
);
