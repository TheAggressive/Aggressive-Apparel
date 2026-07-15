<?php

/**
 * WordPress VIP platform function signatures for static analysis.
 *
 * This file is loaded by PHPStan only and is not part of the theme runtime.
 *
 * @param string               $url            Remote URL.
 * @param mixed                $fallback_value Value returned while the circuit is open.
 * @param int                  $threshold      Failures before opening the circuit.
 * @param int                  $timeout        Request timeout in seconds.
 * @param int                  $retry          Circuit reset interval in seconds.
 * @param array<string, mixed> $args           WordPress HTTP arguments.
 */
function vip_safe_wp_remote_get(
	string $url,
	mixed $fallback_value = '',
	int $threshold = 3,
	int $timeout = 1,
	int $retry = 20,
	array $args = array()
): mixed {}
