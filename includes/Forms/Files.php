<?php
/** Private storage and authenticated downloads for submitted files. */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Files {
	public static function store( array $upload, array $field ): array|\WP_Error {
		if ( UPLOAD_ERR_OK !== (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new \WP_Error( 'ace_file_upload', __( 'The file could not be uploaded.', 'adaptive-content-enhancer' ), array( 'status' => 422 ) );
		}
		$size = (int) ( $upload['size'] ?? 0 );
		$limit = min( 10 * MB_IN_BYTES, max( 1, (int) ( $field['rules']['maxFileSize'] ?? 5 ) ) * MB_IN_BYTES );
		if ( $size < 1 || $size > $limit ) {
			return new \WP_Error( 'ace_file_size', __( 'The file is too large or empty.', 'adaptive-content-enhancer' ), array( 'status' => 422 ) );
		}
		$name = sanitize_file_name( $upload['name'] ?? '' );
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$allowed = $field['rules']['allowedFileTypes'] ?? array( 'pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx' );
		if ( ! is_array( $allowed ) || ! in_array( $extension, $allowed, true ) ) {
			return new \WP_Error( 'ace_file_type', __( 'This file type is not allowed.', 'adaptive-content-enhancer' ), array( 'status' => 422 ) );
		}
		$checked = wp_check_filetype_and_ext( $upload['tmp_name'], $name );
		if ( ! $checked['ext'] || ! $checked['type'] || strtolower( $checked['ext'] ) !== $extension ) {
			return new \WP_Error( 'ace_file_mime', __( 'The file content does not match its type.', 'adaptive-content-enhancer' ), array( 'status' => 422 ) );
		}
		$root = trailingslashit( wp_upload_dir()['basedir'] ) . 'ace-forms';
		$directory = $root . '/' . gmdate( 'Y/m' ) . '/' . bin2hex( random_bytes( 16 ) );
		if ( ! wp_mkdir_p( $directory ) ) {
			return new \WP_Error( 'ace_file_storage', __( 'The file could not be saved.', 'adaptive-content-enhancer' ), array( 'status' => 500 ) );
		}
		if ( ! file_exists( $root . '/.htaccess' ) ) {
			$protected = file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" );
			file_put_contents( $root . '/index.php', "<?php\n// Silence is golden.\n" );
			if ( false === $protected ) {
				return new \WP_Error( 'ace_file_protection', __( 'Private file storage is unavailable.', 'adaptive-content-enhancer' ), array( 'status' => 500 ) );
			}
		}
		$id = bin2hex( random_bytes( 16 ) );
		$path = $directory . '/' . $id . '.' . $extension;
		if ( ! move_uploaded_file( $upload['tmp_name'], $path ) ) {
			return new \WP_Error( 'ace_file_move', __( 'The file could not be saved.', 'adaptive-content-enhancer' ), array( 'status' => 500 ) );
		}
		return array( 'id' => $id, 'name' => $name, 'path' => $path, 'mime' => $checked['type'], 'size' => $size );
	}

	public static function register_routes(): void {
		register_rest_route( 'ace-forms/v1', '/entries/(?P<entry_id>\d+)/files/(?P<file_id>[a-f0-9]{32})', array(
			'methods' => 'GET',
			'callback' => array( self::class, 'download' ),
			'permission_callback' => static fn(): bool => current_user_can( Capabilities::VIEW_ENTRIES ),
		) );
	}

	public static function download( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;
		$entry_id = absint( $request['entry_id'] );
		$file_id = sanitize_key( $request['file_id'] );
		$meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}ace_form_entry_meta WHERE entry_id=%d AND meta_key=%s LIMIT 1", $entry_id, 'file_' . $file_id ) );
		$file = $meta ? json_decode( $meta, true ) : null;
		if ( ! is_array( $file ) ) {
			$file = apply_filters( 'ace_forms_get_file_record', null, $entry_id, $file_id );
		}
		$root = realpath( trailingslashit( wp_upload_dir()['basedir'] ) . 'ace-forms' );
		$path = is_array( $file ) ? realpath( $file['path'] ?? '' ) : false;
		if ( ! $root || ! $path || ! str_starts_with( $path, $root . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			return new \WP_Error( 'ace_file_not_found', __( 'File not found.', 'adaptive-content-enhancer' ), array( 'status' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: ' . sanitize_mime_type( $file['mime'] ?? 'application/octet-stream' ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $file['name'] ?? 'download' ) . '"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
