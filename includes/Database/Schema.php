<?php
/**
 * Per-site database schema.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer\Database;

defined( 'ABSPATH' ) || exit;

final class Schema {
	public const VERSION        = '0.1.0';
	public const VERSION_OPTION = 'ace_ace_db_version';

	public static function maybe_upgrade(): void {
		if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate    = $wpdb->get_charset_collate();
		$forms      = $wpdb->prefix . 'ace_forms';
		$entries    = $wpdb->prefix . 'ace_form_entries';
		$entry_meta = $wpdb->prefix . 'ace_form_entry_meta';

		dbDelta( "CREATE TABLE {$forms} (
			form_id CHAR(36) NOT NULL,
			source_post_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			definition LONGTEXT NOT NULL,
			definition_hash CHAR(40) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (form_id),
			KEY source_post_id (source_post_id),
			KEY status (status)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$entries} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id CHAR(36) NOT NULL,
			source_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			data LONGTEXT NOT NULL,
			search_text TEXT NOT NULL,
			contact_email VARCHAR(254) NOT NULL DEFAULT '',
			contact_name VARCHAR(190) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			context LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY form_status_created (form_id, status, created_at),
			KEY contact_email (contact_email),
			FULLTEXT KEY search_text (search_text)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$entry_meta} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id BIGINT UNSIGNED NOT NULL,
			meta_key VARCHAR(190) NOT NULL,
			meta_value LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY entry_key (entry_id, meta_key)
		) {$collate};" );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}
}
