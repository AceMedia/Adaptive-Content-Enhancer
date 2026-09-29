<?php
/** WordPress personal-data export and erase integration. */
namespace ACE\AdaptiveContentEnhancer\Entries;

defined( 'ABSPATH' ) || exit;

final class Privacy {
	public static function register_exporter( array $exporters ): array {
		$exporters['ace-forms'] = array( 'exporter_friendly_name' => __( 'Form entries', 'adaptive-content-enhancer' ), 'callback' => array( self::class, 'export' ) );
		return $exporters;
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['ace-forms'] = array( 'eraser_friendly_name' => __( 'Form entries', 'adaptive-content-enhancer' ), 'callback' => array( self::class, 'erase' ) );
		return $erasers;
	}

	public static function export( string $email, int $page = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ace_form_entries';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,form_id,data,created_at FROM {$table} WHERE contact_email=%s ORDER BY id LIMIT 100 OFFSET %d", sanitize_email( $email ), ( max( 1, $page ) - 1 ) * 100 ) );
		$data = array();
		foreach ( $rows as $row ) {
			$items = array( array( 'name' => __( 'Received', 'adaptive-content-enhancer' ), 'value' => $row->created_at ) );
			foreach ( (array) json_decode( $row->data, true ) as $key => $value ) {
				$items[] = array( 'name' => $key, 'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
			}
			$data[] = array( 'group_id' => 'ace-forms', 'group_label' => __( 'Form entries', 'adaptive-content-enhancer' ), 'item_id' => 'ace-form-entry-' . $row->id, 'data' => $items );
		}
		return array( 'data' => $data, 'done' => count( $rows ) < 100 );
	}

	public static function erase( string $email, int $page = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ace_form_entries';
		$meta = $wpdb->prefix . 'ace_form_entry_meta';
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE contact_email=%s ORDER BY id LIMIT 100", sanitize_email( $email ) ) ) );
		$root = realpath( trailingslashit( wp_upload_dir()['basedir'] ) . 'ace-forms' );
		$retained = false;
		$removed = false;
		foreach ( $ids as $id ) {
			$records = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$meta} WHERE entry_id=%d AND meta_key LIKE 'file_%%'", $id ) );
			$failed = false;
			foreach ( $records as $json ) {
				$file = json_decode( $json, true );
				$path = is_array( $file ) ? realpath( $file['path'] ?? '' ) : false;
				if ( ! $path ) {
					continue;
				}
				if ( ! $root || ! str_starts_with( $path, $root . DIRECTORY_SEPARATOR ) ) {
					$failed = true;
					continue;
				}
				wp_delete_file( $path );
				$failed = $failed || is_file( $path );
			}
			if ( $failed ) {
				$retained = true;
				continue;
			}
			$wpdb->delete( $meta, array( 'entry_id' => $id ) );
			$wpdb->delete( $table, array( 'id' => $id ) );
			$removed = true;
		}
		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => $retained ? array( __( 'One or more private files could not be removed.', 'adaptive-content-enhancer' ) ) : array(), 'done' => count( $ids ) < 100 );
	}
}
