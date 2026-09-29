<?php
/** Idempotent import of Contact Form 7 database entries. */
namespace ACE\AdaptiveContentEnhancer\Migration;

defined( 'ABSPATH' ) || exit;

final class Cfdb7Importer {
	public static function import( bool $dry_run = true ): array {
		global $wpdb;
		$source = $wpdb->prefix . 'db7_forms';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $source ) ) ) {
			return array( 'CFDB7 table does not exist.' );
		}
		$map = get_option( 'ace_forms_cf7_map', array() );
		$entries = $wpdb->prefix . 'ace_form_entries';
		$meta = $wpdb->prefix . 'ace_form_entry_meta';
		$existing = array_fill_keys( array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$meta} WHERE meta_key = 'cfdb7_id'" ) ), true );
		$report = array( 'source' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$source}" ), 'imported' => 0, 'existing' => 0, 'archived_without_form' => 0, 'preserved_unparsed' => 0, 'failed' => 0 );
		$failed_ids = array();
		$last_error = '';
		foreach ( $wpdb->get_results( "SELECT form_id, form_post_id, form_value, form_date FROM {$source} ORDER BY form_id", ARRAY_A ) as $row ) {
			$legacy_id = (int) $row['form_id'];
			if ( isset( $existing[ $legacy_id ] ) ) {
				++$report['existing'];
				continue;
			}
			$mapping = $map[ (int) $row['form_post_id'] ] ?? null;
			if ( ! $mapping ) {
				++$report['archived_without_form'];
				$mapping = $dry_run ? array( 'form_id' => '' ) : self::archive_form( (int) $row['form_post_id'], $map );
			}
			$data = self::decode( $row['form_value'] );
			$unparsed = ! is_array( $data );
			if ( ! is_array( $data ) ) {
				// Preserve a damaged serialised row exactly for manual recovery.
				// Base64 keeps invalid UTF-8 and embedded NUL bytes JSON-safe.
				$data = array( 'legacy_raw_base64' => base64_encode( $row['form_value'] ) );
				++$report['preserved_unparsed'];
			}
			unset( $data['cfdb7_status'] );
			$data = map_deep( $data, 'sanitize_text_field' );
			$email = '';
			foreach ( $data as $value ) {
				if ( is_string( $value ) && is_email( $value ) ) {
					$email = sanitize_email( $value );
					break;
				}
			}
			if ( $dry_run ) {
				++$report['imported'];
				continue;
			}
			$ok = $wpdb->insert( $entries, array(
				'form_id' => $mapping['form_id'],
				'source_post_id' => 0,
				'status' => $unparsed ? 'needs_review' : 'read',
				'data' => wp_json_encode( $data ),
				'search_text' => $unparsed ? '' : implode( ' ', array_map( 'strval', array_filter( $data, 'is_scalar' ) ) ),
				'contact_email' => $email,
				'contact_name' => mb_substr( sanitize_text_field( $data['your-name'] ?? $data['first_name'] ?? '' ), 0, 190 ),
				'user_id' => 0,
				'ip_hash' => '',
				'user_agent' => '',
				'context' => wp_json_encode( array( 'source' => 'cfdb7', 'legacy_form_id' => (int) $row['form_post_id'], 'unparsed' => $unparsed ) ),
				'created_at' => $row['form_date'],
			) );
			if ( ! $ok ) {
				++$report['failed'];
				$failed_ids[] = $legacy_id;
				$last_error = $wpdb->last_error;
				continue;
			}
			$wpdb->insert( $meta, array( 'entry_id' => (int) $wpdb->insert_id, 'meta_key' => 'cfdb7_id', 'meta_value' => (string) $legacy_id ) );
			++$report['imported'];
		}
		if ( $failed_ids ) {
			$report['failed_ids'] = implode( ',', $failed_ids );
			$report['last_error'] = $last_error;
		}
		return $report;
	}

	private static function decode( string $raw ): mixed {
		$data = maybe_unserialize( $raw );
		if ( is_array( $data ) ) {
			return $data;
		}
		// Some old local rows have stale PHP string lengths after a URL rewrite.
		$repaired = preg_replace_callback( '/s:[0-9]+:"(.*?)";/s', static fn( array $match ): string => 's:' . strlen( $match[1] ) . ':"' . $match[1] . '";', $raw );
		return @unserialize( $repaired, array( 'allowed_classes' => false ) );
	}

	private static function archive_form( int $legacy_form_id, array &$map ): array {
		global $wpdb;
		$id = wp_generate_uuid4();
		$definition = array( 'formId' => $id, 'name' => 'Legacy CF7 #' . $legacy_form_id . ' (archive only)', 'settings' => array(), 'fields' => array() );
		$now = current_time( 'mysql', true );
		$wpdb->insert( $wpdb->prefix . 'ace_forms', array(
			'form_id' => $id,
			'source_post_id' => 0,
			'name' => $definition['name'],
			'definition' => wp_json_encode( $definition ),
			'definition_hash' => sha1( wp_json_encode( $definition ) ),
			'status' => 'closed',
			'created_at' => $now,
			'updated_at' => $now,
		) );
		$map[ $legacy_form_id ] = array( 'form_id' => $id, 'pattern_id' => 0, 'hash' => '' );
		update_option( 'ace_forms_cf7_map', $map, false );
		return $map[ $legacy_form_id ];
	}
}
