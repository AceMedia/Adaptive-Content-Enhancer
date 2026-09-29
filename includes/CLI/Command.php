<?php
/** WP-CLI entry point for local form migration. */
namespace ACE\AdaptiveContentEnhancer\CLI;

use ACE\AdaptiveContentEnhancer\Migration\Cf7Importer;
use ACE\AdaptiveContentEnhancer\Migration\Cfdb7Importer;
use ACE\AdaptiveContentEnhancer\Migration\AcfImporter;

defined( 'ABSPATH' ) || exit;

final class Command {
	/** Import ACF groups as editable field blocks without rewriting values.
	 *
	 * ## OPTIONS
	 * [--dry-run]
	 * : Report changes without writing.
	 * [--group=<key>]
	 * : Import one ACF group key.
	 * [--overwrite]
	 * : Replace an already imported group from its source, preserving a revision.
	 */
	public function import_acf( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		foreach ( AcfImporter::import( $dry_run, (string) ( $assoc_args['group'] ?? '' ), isset( $assoc_args['overwrite'] ) ) as $line ) {
			\WP_CLI::log( $line );
		}
		\WP_CLI::success( $dry_run ? 'ACF dry run complete.' : 'ACF definitions imported.' );
	}
	/** Import local Contact Form 7 forms into synced patterns.
	 *
	 * ## OPTIONS
	 * [--dry-run]
	 * : Show intended changes without writing.
	 * [--replace-shortcodes]
	 * : Replace CF7 placements stored in local post content, including edited templates.
	 * [--refresh-notifications]
	 * : Update mail settings on existing patterns without changing their blocks or appearance.
	 */
	public function import_cf7( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		$replace = isset( $assoc_args['replace-shortcodes'] );
		foreach ( Cf7Importer::import( $dry_run, $replace, isset( $assoc_args['refresh-notifications'] ) ) as $line ) {
			\WP_CLI::log( $line );
		}
		\WP_CLI::success( $dry_run ? 'Dry run complete.' : 'Import complete.' );
	}

	/** Import historical CFDB7 entries into the ACE entries table.
	 *
	 * ## OPTIONS
	 * [--dry-run]
	 * : Count eligible entries without writing.
	 */
	public function import_cfdb7( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		foreach ( Cfdb7Importer::import( $dry_run ) as $name => $count ) {
			\WP_CLI::log( "{$name}: {$count}" );
		}
		\WP_CLI::success( $dry_run ? 'Dry run complete.' : 'Entry import complete.' );
	}
}
