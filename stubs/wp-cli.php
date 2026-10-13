<?php

/**
 * Minimal WP-CLI signatures for static analysis — only what the theme calls.
 *
 * This file is loaded by PHPStan only and is not part of the theme runtime.
 */

namespace {
	class WP_CLI {
		/**
		 * @param string                $name     Command name.
		 * @param callable|class-string $callable Handler.
		 * @param array<string, mixed>  $args     Registration args.
		 */
		public static function add_command( string $name, $callable, array $args = array() ): bool {}

		public static function log( string $message ): void {}

		public static function success( string $message ): void {}

		public static function warning( string $message ): void {}

		/** @return never */
		public static function error( string $message, bool $exit = true ): void {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param string                    $format Output format.
	 * @param array<int, array<mixed>>  $items  Rows.
	 * @param array<int, string>|string $fields Columns.
	 */
	function format_items( string $format, array $items, $fields ): void {}
}
