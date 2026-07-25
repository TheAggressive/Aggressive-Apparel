<?php
/**
 * Social Proof — proof-line decoration + parsing.
 *
 * Extracted from Social_Proof to keep each file under the length cap.
 * Composed via `use`; all callers are unchanged.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Social_Proof_Decoration {
	/**
	 * Parses PREFIX|MESSAGE or PREFIX|MESSAGE|URL into visible copy + optional decor + optional link.
	 *
	 * When PREFIX is omitted the entire line is plain text with no badge.
	 * When PREFIX resolves to neither a recognised theme icon slug nor an
	 * HTTPS/HTTP asset URL we fall back to treating the FULL line as the
	 * message so stray pipe characters remain readable.
	 *
	 * PREFIX may be explicit none|, -|, 0| to deliberately hide an icon while
	 * still using structured lines.
	 *
	 * The optional third segment URL accepts absolute http(s) URLs or
	 * root-relative paths (starting with /).
	 *
	 * @param string $single_line Trimmed textarea line contents.
	 * @return array{message: string, decor_html: string, url: string}
	 */
	private function decode_decorated_proof_line( string $single_line ): array {
		if ( '' === $single_line ) {
			return array(
				'message'    => '',
				'decor_html' => '',
				'url'        => '',
			);
		}

		if ( ! str_contains( $single_line, '|' ) ) {
			return array(
				'message'    => sanitize_text_field( $single_line ),
				'decor_html' => '',
				'url'        => '',
			);
		}

		$parts      = explode( '|', $single_line, 3 );
		$left_token = trim( (string) ( $parts[0] ?? '' ) );
		$right_text = trim( (string) ( $parts[1] ?? '' ) );
		$url        = $this->sanitize_proof_line_url( trim( (string) ( $parts[2] ?? '' ) ) );

		if ( '' === $right_text ) {
			return array(
				'message'    => sanitize_text_field( $single_line ),
				'decor_html' => '',
				'url'        => '',
			);
		}

		if ( $this->decor_token_is_explicitly_disabled( $left_token ) ) {
			return array(
				'message'    => sanitize_text_field( $right_text ),
				'decor_html' => '',
				'url'        => $url,
			);
		}

		$decor_markup = $this->resolve_decor_token_markup( $left_token );

		if ( '' === $decor_markup ) {
			return array(
				'message'    => sanitize_text_field( $single_line ),
				'decor_html' => '',
				'url'        => '',
			);
		}

		return array(
			'message'    => sanitize_text_field( $right_text ),
			'decor_html' => $decor_markup,
			'url'        => $url,
		);
	}

	/**
	 * Sanitize a URL from a proof line — allows root-relative paths and http(s) only.
	 *
	 * @param string $raw Raw URL string from the textarea.
	 * @return string Sanitized URL or empty string.
	 */
	private function sanitize_proof_line_url( string $raw ): string {
		if ( '' === $raw ) {
			return '';
		}

		if ( str_starts_with( $raw, '/' ) ) {
			return esc_url_raw( $raw );
		}

		$scheme = wp_parse_url( $raw, PHP_URL_SCHEME );
		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
			return '';
		}

		return esc_url_raw( $raw );
	}

	/**
	 * User-facing tokens that deliberately suppress the PREFIX column.
	 *
	 * @param string $token Left-hand PREFIX trimmed.
	 * @return bool
	 */
	private function decor_token_is_explicitly_disabled( string $token ): bool {
		return in_array(
			strtolower( $token ),
			array( 'none', '-', '0', 'hidden' ),
			true
		);
	}

	/**
	 * Build trusted SVG/HTML for PREFIX slot (theme Icons or HTTPS image URLs).
	 *
	 * Only http(s) URLs are accepted for raster/SVG uploads so admins cannot
	 * inject javascript:-style URIs via the PREFIX field.
	 *
	 * @param string $token Raw PREFIX substring.
	 * @return string Safe HTML string or empty if PREFIX is unsupported.
	 */
	private function resolve_decor_token_markup( string $token ): string {
		if ( preg_match( '#^https?://#i', $token ) ) {
			$url = esc_url_raw( $token );
			if ( '' === $url ) {
				return '';
			}

			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( ! is_string( $scheme )
				|| ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true )
			) {
				return '';
			}

			return $this->build_decor_image_markup( $url );
		}

		$key = sanitize_key( strtolower( str_replace( ' ', '-', $token ) ) );
		if ( '' === $key || ! Icons::exists( $key ) ) {
			return '';
		}

		return Icons::get(
			$key,
			array(
				'width'       => 24,
				'height'      => 24,
				'class'       => 'aggressive-apparel-social-proof__decor-svg',
				'aria-hidden' => 'true',
			)
		);
	}

	/**
	 * Build a sanitized <img /> tag for PREFIX image URLs (custom icons).
	 *
	 * @param string $validated_url Absolute URL validated by PREFIX branch.
	 * @return string
	 */
	private function build_decor_image_markup( string $validated_url ): string {
		return wp_kses(
			sprintf(
				'<img src="%1$s" alt="" width="24" height="24" decoding="async" loading="lazy" class="aggressive-apparel-social-proof__decor-img" />',
				esc_url( $validated_url )
			),
			array(
				'img' => array(
					'src'      => array(),
					'alt'      => array(),
					'width'    => array(),
					'height'   => array(),
					'decoding' => array(),
					'loading'  => array(),
					'class'    => array(),
				),
			),
		);
	}
}
