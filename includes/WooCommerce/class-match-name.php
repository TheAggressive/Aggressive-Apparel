<?php
/**
 * Match Name
 *
 * The one way Size & Specs compares names typed by people: a step to a term,
 * an attribute to a spec, a spec to one already there, a guide to its copy
 * on another site. HTML entities are read as the characters they stand for
 * ("Men&#8217;s" is "Men's"), runs of spaces count as one, and case doesn't
 * matter, in any language.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Names, for comparing.
 */
final class Match_Name {

	/**
	 * A name as it compares: entities decoded, spaces collapsed, lowercase.
	 *
	 * @param string $name Name.
	 */
	public static function key( string $name ): string {
		$name = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $name ) ) );
	}
}
