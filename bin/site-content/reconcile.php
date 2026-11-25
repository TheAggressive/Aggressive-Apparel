<?php
/**
 * Reconcile saved Site Editor content with the theme (docs/site-content.md).
 *
 * Saved templates and parts override the theme's files, so a theme deploy
 * cannot change them. This edits them IN PLACE: it never resets one, so the
 * saved single-product template keeps its PayPal smart buttons.
 *
 *     wp eval-file bin/site-content/reconcile.php                 # dry run
 *     wp eval-file bin/site-content/reconcile.php apply           # layout only
 *     wp eval-file bin/site-content/reconcile.php apply copy      # + header/footer copy
 *     wp eval-file bin/site-content/reconcile.php apply adopt-home
 *
 * Every entry it changes is first written to ./site-content-backup-<time>/,
 * and each change is idempotent: a second run reports nothing to do. A
 * template whose post-conditions fail (PayPal or Add to cart lost, a target
 * missing) is left untouched. Rollback: wp post update <ID> <backup file>.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPressVIPMinimum.Functions.RestrictedFunctions -- A WP-CLI script writing local backup files.

$aa_flags = isset( $args ) && is_array( $args ) ? $args : array();
$aa_apply = in_array( 'apply', $aa_flags, true );

/**
 * The saved (database) copy of a theme template or part, or null. A trashed
 * copy no longer overrides the theme, so it counts as none.
 *
 * @param string $slug Template slug.
 * @param string $type wp_template or wp_template_part.
 */
function aa_saved_entry( string $slug, string $type ): ?WP_Post {
	$template = get_block_template( get_stylesheet() . '//' . $slug, $type );
	if ( ! $template || 'custom' !== $template->source || ! $template->wp_id ) {
		return null;
	}
	$post = get_post( $template->wp_id );
	return $post instanceof WP_Post && 'trash' !== $post->post_status ? $post : null;
}

/**
 * Text as a reader sees it: tags stripped, entities decoded, curly
 * apostrophes straightened and whitespace collapsed, so the saved copy
 * matches however the editor encoded it.
 *
 * @param string $html Markup.
 */
function aa_plain_text( string $html ): string {
	$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = str_replace( array( "\u{2019}", "\u{2018}" ), "'", $text );
	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Count blocks of one name anywhere in a tree.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
 * @param string                           $name   Block name.
 */
function aa_count_blocks( array $blocks, string $name ): int {
	$count = 0;
	foreach ( $blocks as $block ) {
		$count += ( $block['blockName'] ?? '' ) === $name ? 1 : 0;
		$count += aa_count_blocks( $block['innerBlocks'] ?? array(), $name );
	}
	return $count;
}

/**
 * Depth-first visit with references, so the callback can edit a block.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
 * @param callable                         $visit  fn( array &$block, array &$siblings, int $index ).
 */
function aa_walk( array &$blocks, callable $visit ): void {
	foreach ( array_keys( $blocks ) as $index ) {
		$visit( $blocks[ $index ], $blocks, $index );
		if ( ! empty( $blocks[ $index ]['innerBlocks'] ) ) {
			aa_walk( $blocks[ $index ]['innerBlocks'], $visit );
		}
	}
}

/**
 * Single product: related products at four columns inside gutter padding,
 * and the live free-shipping line after the purchase buttons.
 *
 * @param string        $content Saved template content.
 * @param array<string> $log     Change log, appended to.
 * @throws RuntimeException When a target is missing; the caller leaves the template untouched.
 */
function aa_reconcile_single_product( string $content, array &$log ): string {
	$blocks = parse_blocks( $content );
	$gutter = 'var:preset|spacing|gutter';

	aa_walk(
		$blocks,
		static function ( array &$block ) use ( &$log, $gutter ): void {
			if ( 'woocommerce/product-collection' === $block['blockName']
				&& 'woocommerce/product-collection/related' === ( $block['attrs']['collection'] ?? '' )
				&& 4 !== ( $block['attrs']['displayLayout']['columns'] ?? null ) ) {
				$log[]                                      = sprintf( 'Related products: columns %s -> 4', wp_json_encode( $block['attrs']['displayLayout']['columns'] ?? null ) );
				$block['attrs']['displayLayout']['columns'] = 4;
			}

			if ( 'core/group' !== $block['blockName'] || ! aa_has_related_collection( $block['innerBlocks'] ) ) {
				return;
			}
			$padding = $block['attrs']['style']['spacing']['padding'] ?? array();
			if ( ( $padding['left'] ?? '' ) === $gutter && ( $padding['right'] ?? '' ) === $gutter ) {
				return;
			}
			$block['attrs']['style']['spacing']['padding'] = array_merge(
				$padding,
				array(
					'left'  => $gutter,
					'right' => $gutter,
				)
			);
			// The saved wrapper's inline style must match its attributes, or
			// the editor flags the block as invalid.
			$block['innerContent'][0] = (string) preg_replace_callback(
				'/^(\s*<div\b[^>]*\sstyle=")([^"]*)(")/',
				static function ( array $m ): string {
					$style = (string) preg_replace( '/padding-(?:left|right):[^;"]*;?/', '', $m[2] );
					$style = rtrim( $style, ';' ) . ';padding-right:var(--wp--preset--spacing--gutter);padding-left:var(--wp--preset--spacing--gutter)';
					return $m[1] . ltrim( $style, ';' ) . $m[3];
				},
				(string) $block['innerContent'][0],
				1,
				$count
			);
			if ( 1 !== $count ) {
				throw new RuntimeException( 'Related products group: wrapper has no inline style to extend.' );
			}
			$block['innerHTML'] = implode( '', array_filter( $block['innerContent'], 'is_string' ) );
			$log[]              = 'Related products group: left/right padding -> gutter';
		}
	);

	if ( 0 === aa_count_blocks( $blocks, 'aggressive-apparel/free-shipping-message' ) ) {
		$inserted = false;
		aa_walk(
			$blocks,
			static function ( array &$block ) use ( &$inserted ): void {
				if ( $inserted || ! aa_insert_after_purchase( $block ) ) {
					return;
				}
				$inserted = true;
			}
		);
		if ( ! $inserted ) {
			throw new RuntimeException( 'No Add to cart block found to place the free-shipping line after.' );
		}
		$log[] = 'Inserted Free Shipping Message after the purchase buttons';
	}

	return serialize_blocks( $blocks );
}

/**
 * Whether a related-products collection is a direct child.
 *
 * @param array<int, array<string, mixed>> $children Inner blocks.
 */
function aa_has_related_collection( array $children ): bool {
	foreach ( $children as $child ) {
		if ( 'woocommerce/product-collection' === $child['blockName']
			&& 'woocommerce/product-collection/related' === ( $child['attrs']['collection'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * In the container holding Add to cart, insert the free-shipping line after
 * the last purchase control (PayPal buttons when present, else Add to cart).
 *
 * @param array<string, mixed> $block Container block, edited in place.
 */
function aa_insert_after_purchase( array &$block ): bool {
	$purchase = array( 'woocommerce/add-to-cart-with-options', 'woocommerce/add-to-cart-form', 'woocommerce-paypal-payments/product-smart-buttons' );
	$last     = null;
	foreach ( $block['innerBlocks'] ?? array() as $index => $child ) {
		if ( in_array( $child['blockName'], $purchase, true ) ) {
			$last = $index;
		}
	}
	if ( null === $last ) {
		return false;
	}

	$new = array(
		'blockName'    => 'aggressive-apparel/free-shipping-message',
		'attrs'        => array(),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
	array_splice( $block['innerBlocks'], $last + 1, 0, array( $new ) );

	// innerContent interleaves HTML strings with null placeholders, one per
	// inner block: add a placeholder after the one for $last.
	$seen    = -1;
	$content = array();
	foreach ( $block['innerContent'] as $piece ) {
		$content[] = $piece;
		if ( null === $piece && ++$seen === $last ) {
			$content[] = "\n\n";
			$content[] = null;
		}
	}
	$block['innerContent'] = $content;
	return true;
}

/**
 * Exact, counted text replacements. A string not found exactly once is
 * reported and skipped, never guessed at.
 *
 * @param string                $content Saved content.
 * @param array<string, string> $map     Find => replace.
 * @param array<string>         $log     Change log, appended to.
 */
function aa_replace_copy( string $content, array $map, array &$log ): string {
	foreach ( $map as $find => $replace ) {
		if ( str_contains( $content, $replace ) && ! str_contains( $content, $find ) ) {
			continue;
		}
		$count = substr_count( $content, $find );
		if ( 1 !== $count ) {
			$log[] = sprintf( 'SKIPPED copy "%s": found %d times', $find, $count );
			continue;
		}
		$content = str_replace( $find, $replace, $content );
		$log[]   = sprintf( 'Copy "%s" -> "%s"', trim( $find, '<>' ), trim( $replace, '<>' ) );
	}
	return $content;
}

/**
 * Remove one ticker item (a paragraph with exactly this text) and the icon
 * separating it from the item before. Retired slogans are removed, not
 * replaced with a new one nobody approved.
 *
 * @param string        $content Saved content.
 * @param string        $text    The item's exact text.
 * @param array<string> $log     Change log, appended to.
 */
function aa_drop_ticker_item( string $content, string $text, array &$log ): string {
	$blocks  = parse_blocks( $content );
	$dropped = false;
	aa_walk(
		$blocks,
		static function ( array &$block ) use ( $text, &$dropped ): void {
			if ( $dropped || 'aggressive-blocks/ticker' !== $block['blockName'] ) {
				return;
			}
			foreach ( $block['innerBlocks'] as $index => $child ) {
				if ( 'core/paragraph' !== $child['blockName'] || aa_plain_text( $child['innerHTML'] ) !== aa_plain_text( $text ) ) {
					continue;
				}
				$remove = array( $index );
				if ( $index > 0 && 'aggressive-apparel/icon' === $block['innerBlocks'][ $index - 1 ]['blockName'] ) {
					$remove[] = $index - 1;
				}
				aa_remove_inner_blocks( $block, $remove );
				$dropped = true;
				return;
			}
		}
	);
	if ( ! $dropped ) {
		// Gone already is reconciled; still on the page but not as a ticker
		// item is not, and is reported rather than left live silently.
		if ( str_contains( aa_plain_text( $content ), aa_plain_text( $text ) ) ) {
			$log[] = sprintf( 'SKIPPED ticker item "%s": found, but not as a ticker paragraph', $text );
		}
		return $content;
	}
	$log[] = sprintf( 'Removed ticker item "%s" and its separator icon', $text );
	return serialize_blocks( $blocks );
}

/**
 * Remove inner blocks by index, with their placeholders in innerContent and
 * the whitespace that preceded each.
 *
 * @param array<string, mixed> $block   Container block, edited in place.
 * @param array<int>           $indexes Inner block indexes to remove.
 */
function aa_remove_inner_blocks( array &$block, array $indexes ): void {
	$content = array();
	$seen    = -1;
	foreach ( $block['innerContent'] as $piece ) {
		if ( null === $piece && in_array( ++$seen, $indexes, true ) ) {
			$last = array_key_last( $content );
			if ( null !== $last && is_string( $content[ $last ] ) && '' === trim( $content[ $last ] ) ) {
				array_pop( $content );
			}
			continue;
		}
		$content[] = $piece;
	}
	$block['innerContent'] = $content;
	foreach ( $indexes as $index ) {
		unset( $block['innerBlocks'][ $index ] );
	}
	$block['innerBlocks'] = array_values( $block['innerBlocks'] );
}

$aa_jobs = array(
	array(
		'slug'   => 'single-product',
		'type'   => 'wp_template',
		'edit'   => 'aa_reconcile_single_product',
		'keep'   => array( 'woocommerce-paypal-payments/product-smart-buttons', 'woocommerce/add-to-cart-with-options' ),
		'expect' => array( 'aggressive-apparel/free-shipping-message' => 1 ),
	),
);

// Copy from docs/site-content.md "Copy to change", as approved 2026-11-22.
// Opt-in: copy is approved separately from layout.
if ( in_array( 'copy', $aa_flags, true ) ) {
	$aa_jobs[] = array(
		'slug' => 'header',
		'type' => 'wp_template_part',
		'edit' => static fn( string $c, array &$log ): string => aa_drop_ticker_item(
			aa_replace_copy( $c, array( '>LATEST DROP LIVE NOW<' => '>EST. 2010<' ), $log ),
			'TRENDS DIE. ATTITUDE DOESN’T.',
			$log
		),
	);
	// The newsletter promise ("NEW RELEASES. RESTOCKS. EXCLUSIVES.") stays
	// until it is confirmed against what subscribers actually receive.
	$aa_jobs[] = array(
		'slug' => 'footer',
		'type' => 'wp_template_part',
		'edit' => static fn( string $c, array &$log ): string => aa_replace_copy(
			$c,
			array(
				'>INDEPENDENT BY DESIGN<'         => '>EST. 2010<',
				'>Premium, confident, authentic<' => '>Independent label<',
			),
			$log
		),
	);
}

$aa_backup_dir = getcwd() . '/site-content-backup-' . gmdate( 'Ymd-His' );
$aa_failed     = false;

foreach ( $aa_jobs as $aa_job ) {
	$aa_post = aa_saved_entry( $aa_job['slug'], $aa_job['type'] );
	if ( ! $aa_post ) {
		WP_CLI::log( "{$aa_job['slug']}: no saved copy; the theme file is used. Nothing to do." );
		continue;
	}

	$aa_log = array();
	try {
		$aa_new = ( $aa_job['edit'] )( $aa_post->post_content, $aa_log );
	} catch ( RuntimeException $aa_e ) {
		WP_CLI::warning( "{$aa_job['slug']} (#{$aa_post->ID}): {$aa_e->getMessage()} Left untouched." );
		$aa_failed = true;
		continue;
	}

	$aa_before = parse_blocks( $aa_post->post_content );
	$aa_after  = parse_blocks( $aa_new );
	$aa_errors = array();
	foreach ( $aa_job['keep'] ?? array() as $aa_name ) {
		if ( aa_count_blocks( $aa_after, $aa_name ) !== aa_count_blocks( $aa_before, $aa_name ) ) {
			$aa_errors[] = "{$aa_name} count changed";
		}
	}
	foreach ( $aa_job['expect'] ?? array() as $aa_name => $aa_count ) {
		if ( aa_count_blocks( $aa_after, $aa_name ) !== $aa_count ) {
			$aa_errors[] = "expected {$aa_count} × {$aa_name}";
		}
	}
	if ( $aa_errors ) {
		WP_CLI::warning( "{$aa_job['slug']} (#{$aa_post->ID}): " . implode( '; ', $aa_errors ) . '. Left untouched.' );
		$aa_failed = true;
		continue;
	}

	if ( $aa_new === $aa_post->post_content ) {
		WP_CLI::log( "{$aa_job['slug']} (#{$aa_post->ID}): already reconciled." . ( $aa_log ? ' ' . implode( '; ', $aa_log ) : '' ) );
		continue;
	}

	foreach ( $aa_log as $aa_line ) {
		WP_CLI::log( "{$aa_job['slug']} (#{$aa_post->ID}): {$aa_line}" );
	}
	if ( ! $aa_apply ) {
		continue;
	}

	wp_mkdir_p( $aa_backup_dir );
	file_put_contents( "{$aa_backup_dir}/{$aa_job['slug']}-{$aa_post->ID}.html", $aa_post->post_content );
	$aa_result = wp_update_post(
		array(
			'ID'           => $aa_post->ID,
			'post_content' => wp_slash( $aa_new ),
		),
		true
	);
	if ( is_wp_error( $aa_result ) ) {
		WP_CLI::warning( "{$aa_job['slug']} (#{$aa_post->ID}): {$aa_result->get_error_message()}" );
		$aa_failed = true;
		continue;
	}
	WP_CLI::success( "{$aa_job['slug']} (#{$aa_post->ID}) updated; backup in {$aa_backup_dir}" );
}

// Adopting the editorial homepage discards the saved one. Trashed, not
// deleted, so `wp post update <ID> --post_status=publish` restores it.
if ( in_array( 'adopt-home', $aa_flags, true ) ) {
	$aa_home = aa_saved_entry( 'home', 'wp_template' );
	if ( ! $aa_home ) {
		WP_CLI::log( 'home: no saved copy; the editorial homepage already shows.' );
	} elseif ( ! $aa_apply ) {
		WP_CLI::log( "home (#{$aa_home->ID}, {$aa_home->post_status}): would be moved to the trash." );
	} else {
		wp_mkdir_p( $aa_backup_dir );
		file_put_contents( "{$aa_backup_dir}/home-{$aa_home->ID}.html", $aa_home->post_content );
		if ( wp_trash_post( $aa_home->ID ) ) {
			WP_CLI::success( "home (#{$aa_home->ID}) moved to the trash; backup in {$aa_backup_dir}" );
		} else {
			WP_CLI::warning( "home (#{$aa_home->ID}): could not be moved to the trash." );
			$aa_failed = true;
		}
	}
}

if ( ! $aa_apply ) {
	WP_CLI::log( 'Dry run: nothing was written. Re-run with "apply".' );
}
if ( $aa_failed ) {
	WP_CLI::halt( 1 );
}
