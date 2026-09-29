<?php
/** Local-friendly ACF import screen backed by the same idempotent importer as WP-CLI. */
namespace ACE\AdaptiveContentEnhancer\Fields;

use ACE\AdaptiveContentEnhancer\Migration\AcfImporter;
use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class ImportPage {
	public static function menu(): void {
		add_submenu_page( 'adaptive-content-enhancer', __( 'Import ACF', 'adaptive-content-enhancer' ), __( 'Import ACF', 'adaptive-content-enhancer' ), Capabilities::FIELD_GROUPS, 'ace-fields-import', array( self::class, 'render' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::FIELD_GROUPS ) ) {
			wp_die( esc_html__( 'You cannot manage field groups.', 'adaptive-content-enhancer' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Import ACF field groups', 'adaptive-content-enhancer' ) . '</h1>';
		$report = get_transient( 'ace_fields_import_report_' . get_current_user_id() );
		if ( is_array( $report ) ) {
			delete_transient( 'ace_fields_import_report_' . get_current_user_id() );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Import finished.', 'adaptive-content-enhancer' ) . '</p><ul>';
			foreach ( $report as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul></div>';
		}
		echo '<p>' . esc_html__( 'ACF definitions become editable field-group blocks. Existing values and ACF source records are preserved.', 'adaptive-content-enhancer' ) . '</p>';
		echo '<h2>' . esc_html__( 'Preview', 'adaptive-content-enhancer' ) . '</h2><ul>';
		foreach ( AcfImporter::import( true ) as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ace_fields_import_acf"/>';
		wp_nonce_field( 'ace_fields_import_acf' );
		submit_button( __( 'Import or update field groups', 'adaptive-content-enhancer' ) );
		echo '</form></div>';
	}

	public static function handle(): void {
		if ( ! current_user_can( Capabilities::FIELD_GROUPS ) ) {
			wp_die( esc_html__( 'You cannot manage field groups.', 'adaptive-content-enhancer' ) );
		}
		check_admin_referer( 'ace_fields_import_acf' );
		$report = AcfImporter::import();
		set_transient( 'ace_fields_import_report_' . get_current_user_id(), $report, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=ace-fields-import&imported=1' ) );
		exit;
	}
}
