<?php
/**
 * Collection Migration WP-CLI Command
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Copies Men's / Women's / Unisex from the Fit attribute (pa_fit) to the
 * Collection attribute (pa_collection).
 *
 * Dry run unless --apply is passed. Safe to re-run: products that already
 * carry the matching Collection terms are reported as unchanged.
 *
 * ## OPTIONS
 *
 * [--apply]
 * : Write the changes. Without it nothing is saved.
 *
 * [--remove-source]
 * : Also remove the migrated audience terms from pa_fit. Skipped for products
 * where pa_fit is used for variations. Other pa_fit terms are always kept.
 *
 * [--format=<format>]
 * : Output format for the per-product report.
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
 *     # Preview.
 *     wp aggressive-apparel collection-migrate
 *
 *     # Copy audience terms to Collection, keep pa_fit as-is.
 *     wp aggressive-apparel collection-migrate --apply
 *
 *     # After verifying the storefront, drop the audience terms from pa_fit.
 *     wp aggressive-apparel collection-migrate --apply --remove-source
 */
final class Collection_Migration_Command {

	/**
	 * Run the migration.
	 *
	 * @param array<int, string>    $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		$dry_run = ! isset( $assoc_args['apply'] );
		$format  = $assoc_args['format'] ?? 'table';

		try {
			$report = ( new Collection_Attribute_Migration() )->run( $dry_run, isset( $assoc_args['remove-source'] ) );
		} catch ( \RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		\WP_CLI::log( $dry_run ? 'DRY RUN — nothing saved. Re-run with --apply to write.' : 'Applying changes.' );
		\WP_CLI::log( sprintf( 'Collection attribute: %s', $report['attribute'] ) );
		\WP_CLI::log( sprintf( 'Collection terms %s: %s', $dry_run ? 'to create' : 'created', array() === $report['terms'] ? 'none' : implode( ', ', $report['terms'] ) ) );

		$rows = array_map(
			static fn( array $entry ): array => array_merge(
				$entry,
				array(
					'add'    => implode( ',', $entry['add'] ),
					'remove' => implode( ',', $entry['remove'] ),
				)
			),
			$report['products']
		);
		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'name', 'type', 'status', 'add', 'remove', 'note' ) );

		$counts = array_count_values( array_column( $report['products'], 'status' ) );
		$held   = count( array_keys( array_column( $report['products'], 'note' ), Collection_Attribute_Migration::NOTE_VARIATION, true ) );
		if ( $held > 0 ) {
			\WP_CLI::warning( sprintf( '%d product(s) keep pa_fit because it drives their variations — fix by hand (see "note").', $held ) );
		}
		\WP_CLI::success(
			sprintf(
				'%d product(s) checked: %d updated, %d would update, %d unchanged, %d skipped.',
				count( $report['products'] ),
				$counts[ Collection_Attribute_Migration::STATUS_UPDATED ] ?? 0,
				$counts[ Collection_Attribute_Migration::STATUS_WOULD_UPDATE ] ?? 0,
				$counts[ Collection_Attribute_Migration::STATUS_UNCHANGED ] ?? 0,
				$counts[ Collection_Attribute_Migration::STATUS_SKIPPED ] ?? 0
			)
		);
	}
}
