<?php
/**
 * Campaign covers: the homepage's photographs chosen in the Site Editor.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Request_Cache;

/**
 * Each campaign moment has a template part (Appearance → Editor → Patterns →
 * Template parts) holding a core Cover block: the merchant picks the photo,
 * its focal point, alt text, overlay and which side the copy sits on. A
 * second Cover in the same part is optional: the photo, or another crop of
 * it, for phones. The theme's own parts ship empty, so nothing shows until
 * an approved photo is chosen.
 *
 * The section pattern then renders the merchant's Cover full size with its
 * own copy inside (translated, with live links), so the photo is edited in
 * one place and the words stay the theme's.
 */
final class Editorial_Campaign_Cover {

	/** The opening campaign: the slogan over the photo. */
	public const HERO = 'campaign-image';

	/** The featured drop's campaign. */
	public const DROP = 'drop-campaign-image';

	/** Defiance Division's campaign. */
	public const DEFIANCE = 'defiance-campaign-image';

	/** Layout per part: the class suffix `aa-campaign-cover--<variant>`. */
	public const VARIANTS = array(
		self::HERO     => 'hero',
		self::DROP     => 'drop',
		self::DEFIANCE => 'film',
	);

	/**
	 * The Cover blocks with a photo in a part, in order: the first is the
	 * campaign photo, the second (if any) the phones' version. A drafted or
	 * trashed saved part is not what the site renders, so the theme's file
	 * is read instead.
	 *
	 * @param string $part Template part slug.
	 * @return list<array<string, mixed>> Parsed core/cover blocks.
	 */
	public static function covers( string $part ): array {
		$covers = Request_Cache::remember(
			'editorial-campaign:' . $part,
			static function () use ( $part ): array {
				$id       = get_stylesheet() . '//' . $part;
				$template = get_block_template( $id, 'wp_template_part' );
				if ( $template instanceof \WP_Block_Template && 'custom' === $template->source && 'publish' !== $template->status ) {
					$template = get_block_file_template( $id, 'wp_template_part' );
				}
				if ( ! $template instanceof \WP_Block_Template ) {
					return array();
				}

				return array_values(
					array_filter(
						self::find( parse_blocks( $template->content ) ),
						static fn( array $block ): bool => '' !== self::url( $block )
					)
				);
			}
		);

		return is_array( $covers ) ? $covers : array();
	}

	/**
	 * The campaign photo of a part.
	 *
	 * @param string $part Template part slug.
	 * @return array{image: int, url: string}|null Attachment ID (0 for an external URL) and URL.
	 */
	public static function image( string $part ): ?array {
		$cover = self::covers( $part )[0] ?? null;

		return null === $cover ? null : array(
			'image' => (int) ( $cover['attrs']['id'] ?? 0 ),
			'url'   => self::url( $cover ),
		);
	}

	/**
	 * The phones' Cover of a part, or null when the campaign photo serves
	 * every width.
	 *
	 * @param string $part Template part slug.
	 * @return array<string, mixed>|null Parsed core/cover block.
	 */
	public static function phone( string $part ): ?array {
		return self::covers( $part )[1] ?? null;
	}

	/**
	 * The part's Cover, full size, with the section's copy inside: the
	 * merchant's photo, focal point, overlay and content position, the
	 * theme's words. '' without a campaign photo.
	 *
	 * @param string $part  Template part slug (a key of VARIANTS).
	 * @param string $inner Block markup placed in the Cover ('' for a photo alone).
	 * @param string $name  Block name in the editor's List View.
	 */
	public static function markup( string $part, string $inner = '', string $name = '' ): string {
		$cover = self::covers( $part )[0] ?? null;
		if ( null === $cover ) {
			return '';
		}
		$variant = self::VARIANTS[ $part ] ?? 'drop';
		// The film still sits in its band's wide column; the others are the
		// section, edge to edge.
		$align   = 'film' === $variant ? '' : 'full';
		$classes = 'aa-campaign-cover aa-campaign-cover--' . $variant . ( 'hero' === $variant ? ' aa-cover' : '' );

		$attrs = is_array( $cover['attrs'] ?? null ) ? $cover['attrs'] : array();
		unset( $attrs['minHeight'], $attrs['minHeightUnit'], $attrs['align'] );
		if ( '' !== $align ) {
			$attrs['align'] = $align;
		}
		$attrs['className'] = $classes;
		if ( '' !== $name ) {
			$attrs['metadata'] = array( 'name' => $name );
		}

		$html = implode( '', array_filter( $cover['innerContent'], 'is_string' ) );
		if ( ! preg_match( '#^(.*?<div class="wp-block-cover__inner-container[^"]*">)#s', $html, $open ) ) {
			return '';
		}
		// The part's own class frames it in its editor; here the section's
		// layout replaces it, and the frame's height is the layout's.
		$root = new \WP_HTML_Tag_Processor( $open[1] );
		if ( ! $root->next_tag( array( 'class_name' => 'wp-block-cover' ) ) ) {
			return '';
		}
		$drop = array( 'alignfull', 'alignwide' );
		foreach ( $root->class_list() as $class ) {
			if ( str_starts_with( $class, 'aa-campaign-image' ) ) {
				$drop[] = $class;
			}
		}
		foreach ( $drop as $class ) {
			$root->remove_class( $class );
		}
		foreach ( explode( ' ', trim( ( '' !== $align ? 'align' . $align . ' ' : '' ) . $classes ) ) as $class ) {
			$root->add_class( $class );
		}
		$style = (string) preg_replace( '/min-height:[^;"]*;?/', '', (string) $root->get_attribute( 'style' ) );
		if ( '' === trim( $style ) ) {
			$root->remove_attribute( 'style' );
		} else {
			$root->set_attribute( 'style', $style );
		}
		$blocks = parse_blocks( $inner );
		$blocks = array_values( array_filter( $blocks, static fn( array $block ): bool => null !== $block['blockName'] ) );

		$block = array(
			'blockName'    => 'core/cover',
			'attrs'        => $attrs,
			'innerBlocks'  => $blocks,
			'innerHTML'    => '',
			'innerContent' => array_merge(
				array( $root->get_updated_html() ),
				array_fill( 0, count( $blocks ), null ),
				array( '</div></div>' )
			),
		);

		return serialize_block( $block );
	}

	/**
	 * Every core/cover block, depth first.
	 *
	 * @param array<int|string, mixed> $blocks Parsed blocks.
	 * @return list<array<string, mixed>>
	 */
	private static function find( array $blocks ): array {
		$found = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'core/cover' === ( $block['blockName'] ?? null ) && is_array( $block['attrs'] ?? null ) ) {
				$found[] = $block;
			}
			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$found = array_merge( $found, self::find( $block['innerBlocks'] ) );
			}
		}

		return $found;
	}

	/**
	 * A Cover's photo URL; '' for a video or no image.
	 *
	 * @param array<string, mixed> $cover Parsed core/cover block.
	 */
	private static function url( array $cover ): string {
		$attrs = is_array( $cover['attrs'] ?? null ) ? $cover['attrs'] : array();
		$url   = is_string( $attrs['url'] ?? null ) ? $attrs['url'] : '';

		return 'video' === ( $attrs['backgroundType'] ?? 'image' ) ? '' : $url;
	}
}
