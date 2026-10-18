<?php
/**
 * Product Specs Migration WP-CLI Command
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Moves specs onto WooCommerce attributes (Product_Specs_Migration).
 *
 * Dry run unless --apply is passed. Safe to re-run: linked specs stay
 * linked, and values already moved are gone from the old meta.
 *
 * ## OPTIONS
 *
 * [--apply]
 * : Link specs and move every value now. Without it nothing is saved.
 *
 * [--format=<format>]
 * : Output format for the spec report.
 * ---
 * default: table
 * options:
 *   - table
 *   - json
 *   - csv
 * ---
 *
 * ## EXAMPLES
 *
 *     # Preview which attribute each spec uses and how many posts hold old values.
 *     wp aggressive-apparel specs-migrate
 *
 *     # Do it.
 *     wp aggressive-apparel specs-migrate --apply
 */
final class Product_Specs_Migration_Command {

	/**
	 * Run the migration.
	 *
	 * @param array<int, string>    $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		$dry_run = ! isset( $assoc_args['apply'] );
		$report  = Product_Specs_Migration::run( $dry_run, true );

		\WP_CLI::log( $dry_run ? 'DRY RUN — nothing saved. Re-run with --apply to write.' : 'Applying changes.' );
		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $report['links'], array( 'spec', 'attribute', 'status' ) );

		foreach ( $report['errors'] as $error ) {
			\WP_CLI::warning( $error );
		}
		if ( array() !== $report['errors'] ) {
			\WP_CLI::error( 'Nothing was moved. Fix the specs above and re-run.' );
		}

		if ( $report['left'] > 0 ) {
			\WP_CLI::warning(
				sprintf(
					'%d post(s) keep some values in theme meta: the product already has a different value for that attribute, the attribute drives variations, or the value is not usable. They still show as before; re-save them on the product to move them.',
					$report['left']
				)
			);
		}
		\WP_CLI::success(
			sprintf(
				$dry_run ? '%d post(s) hold old spec values to move.' : '%d post(s) had old spec values checked and moved where possible.',
				$report['posts']
			)
		);
	}
}
