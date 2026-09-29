<?php
/** Admin listing for forms, entries and notification logs. */
namespace ACE\AdaptiveContentEnhancer\Entries;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Admin {
	public static function menu(): void {
		add_submenu_page( 'adaptive-content-enhancer', __( 'Entry archive', 'adaptive-content-enhancer' ), __( 'Entry archive', 'adaptive-content-enhancer' ), Capabilities::VIEW_ENTRIES, 'ace-forms', array( self::class, 'render' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_ENTRIES ) ) {
			wp_die( esc_html__( 'You cannot view form entries.', 'adaptive-content-enhancer' ) );
		}
		global $wpdb;
		$entries_table = $wpdb->prefix . 'ace_form_entries';
		$forms_table = $wpdb->prefix . 'ace_forms';
		$entry_id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		echo '<div class="wrap"><h1>' . esc_html__( 'Form entries', 'adaptive-content-enhancer' ) . '</h1>';
		if ( 'customer-engagement' === apply_filters( 'ace_forms_storage_provider', 'local' ) ) {
			$url = admin_url( 'admin.php?page=adaptive-customer-engagement-forms' );
			echo '<div class="notice notice-info"><p>' . esc_html__( 'New submissions are stored in Adaptive Customer Engagement. This list contains earlier and imported entries.', 'adaptive-content-enhancer' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'View current submissions', 'adaptive-content-enhancer' ) . '</a></p></div>';
		}
		if ( $entry_id ) {
			self::detail( $entry_id );
			echo '</div>';
			return;
		}
		$form_id = isset( $_GET['form_id'] ) ? sanitize_text_field( wp_unslash( $_GET['form_id'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$where = '1=1';
		$params = array();
		if ( $form_id ) {
			$where .= ' AND e.form_id = %s';
			$params[] = $form_id;
		}
		if ( $search ) {
			$where .= ' AND (e.search_text LIKE %s OR e.contact_email LIKE %s)';
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		$count_sql = "SELECT COUNT(*) FROM {$entries_table} e WHERE {$where}";
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );
		$params[] = 30;
		$params[] = ( $page - 1 ) * 30;
		$sql = "SELECT e.id,e.form_id,e.created_at,e.contact_name,e.contact_email,e.status,f.name AS form_name FROM {$entries_table} e LEFT JOIN {$forms_table} f ON f.form_id=e.form_id WHERE {$where} ORDER BY e.id DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$forms = $wpdb->get_results( "SELECT form_id,name,status FROM {$forms_table} ORDER BY name" );

		echo '<form method="get"><input type="hidden" name="page" value="ace-forms"/><select name="form_id"><option value="">' . esc_html__( 'All forms', 'adaptive-content-enhancer' ) . '</option>';
		foreach ( $forms as $form ) {
			echo '<option value="' . esc_attr( $form->form_id ) . '"' . selected( $form_id, $form->form_id, false ) . '>' . esc_html( $form->name . ( 'closed' === $form->status ? ' (archive)' : '' ) ) . '</option>';
		}
		echo '</select> <input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search entries', 'adaptive-content-enhancer' ) . '"/> <button class="button">' . esc_html__( 'Filter', 'adaptive-content-enhancer' ) . '</button></form>';
		echo '<p>' . esc_html( number_format_i18n( $total ) ) . ' ' . esc_html__( 'entries', 'adaptive-content-enhancer' ) . '</p>';
		if ( current_user_can( Capabilities::MANAGE_ENTRIES ) ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'ace_forms_export', 'form_id' => $form_id ), admin_url( 'admin-post.php' ) ), 'ace_forms_export' );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Received', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Form', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Name', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Email', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Status', 'adaptive-content-enhancer' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$url = add_query_arg( array( 'page' => 'ace-forms', 'entry' => $row->id ), admin_url( 'admin.php' ) );
			echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $row->created_at ) . '</a></td><td>' . esc_html( $row->form_name ?: $row->form_id ) . '</td><td>' . esc_html( $row->contact_name ) . '</td><td>' . esc_html( $row->contact_email ) . '</td><td>' . esc_html( $row->status ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $total > 30 ) {
			echo '<p>';
			for ( $i = 1; $i <= ceil( $total / 30 ); ++$i ) {
				if ( $i === $page ) {
					echo '<strong>' . esc_html( (string) $i ) . '</strong> ';
				} else {
					echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'ace-forms', 'form_id' => $form_id, 's' => $search, 'paged' => $i ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( (string) $i ) . '</a> ';
				}
			}
			echo '</p>';
		}
		echo '</div>';
	}

	private static function detail( int $id ): void {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ace_form_entries WHERE id=%d", $id ) );
		if ( ! $row ) {
			echo '<p>' . esc_html__( 'Entry not found.', 'adaptive-content-enhancer' ) . '</p>';
			return;
		}
		$back = add_query_arg( 'page', 'ace-forms', admin_url( 'admin.php' ) );
		echo '<p><a href="' . esc_url( $back ) . '">← ' . esc_html__( 'All entries', 'adaptive-content-enhancer' ) . '</a></p><h2>' . esc_html__( 'Entry', 'adaptive-content-enhancer' ) . ' #' . esc_html( (string) $id ) . '</h2>';
		echo '<p>' . esc_html( $row->created_at ) . ' · ' . esc_html( $row->status ) . '</p><table class="widefat striped"><tbody>';
		foreach ( (array) json_decode( $row->data, true ) as $key => $value ) {
			echo '<tr><th scope="row">' . esc_html( $key ) . '</th><td>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key,meta_value FROM {$wpdb->prefix}ace_form_entry_meta WHERE entry_id=%d AND meta_key IN ('mail_log','confirmation_log') ORDER BY id", $id ) );
		foreach ( $logs as $record ) {
			$log = json_decode( $record->meta_value, true );
			if ( ! is_array( $log ) ) {
				continue;
			}
			echo '<h3>' . esc_html( 'confirmation_log' === $record->meta_key ? __( 'Confirmation log', 'adaptive-content-enhancer' ) : __( 'Notification log', 'adaptive-content-enhancer' ) ) . '</h3><p><strong>' . esc_html__( 'To:', 'adaptive-content-enhancer' ) . '</strong> ' . esc_html( implode( ', ', (array) ( $log['to'] ?? array() ) ) ) . '<br/><strong>' . esc_html__( 'Subject:', 'adaptive-content-enhancer' ) . '</strong> ' . esc_html( $log['subject'] ?? '' ) . '<br/><strong>' . esc_html__( 'Result:', 'adaptive-content-enhancer' ) . '</strong> ' . esc_html( ! empty( $log['sent'] ) ? 'Accepted by wp_mail' : 'Not sent' ) . '</p><pre style="white-space:pre-wrap">' . esc_html( $log['body'] ?? '' ) . '</pre>';
		}
		$files = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key,meta_value FROM {$wpdb->prefix}ace_form_entry_meta WHERE entry_id=%d AND meta_key LIKE 'file_%%'", $id ) );
		if ( $files ) {
			echo '<h3>' . esc_html__( 'Files', 'adaptive-content-enhancer' ) . '</h3><ul>';
			foreach ( $files as $record ) {
				$file = json_decode( $record->meta_value, true );
				$url = wp_nonce_url( rest_url( 'ace-forms/v1/entries/' . $id . '/files/' . substr( $record->meta_key, 5 ) ), 'wp_rest' );
				echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $file['name'] ?? __( 'Download', 'adaptive-content-enhancer' ) ) . '</a></li>';
			}
			echo '</ul>';
		}
	}

	public static function export_csv(): void {
		if ( ! current_user_can( Capabilities::MANAGE_ENTRIES ) ) {
			wp_die( esc_html__( 'You cannot export form entries.', 'adaptive-content-enhancer' ), '', 403 );
		}
		check_admin_referer( 'ace_forms_export' );
		global $wpdb;
		$form_id = isset( $_GET['form_id'] ) ? sanitize_text_field( wp_unslash( $_GET['form_id'] ) ) : '';
		$table = $wpdb->prefix . 'ace_form_entries';
		$where = $form_id ? $wpdb->prepare( 'WHERE form_id=%s', $form_id ) : '';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="ace-form-entries.csv"' );
		$stream = fopen( 'php://output', 'w' );
		fputcsv( $stream, array( 'ID', 'Form ID', 'Received (UTC)', 'Status', 'Name', 'Email', 'Fields' ) );
		$offset = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,form_id,created_at,status,contact_name,contact_email,data FROM {$table} {$where} ORDER BY id LIMIT %d OFFSET %d", 200, $offset ), ARRAY_A );
			foreach ( $rows as $row ) {
				fputcsv( $stream, array_map( array( self::class, 'csv_safe' ), array_values( $row ) ) );
			}
			$offset += count( $rows );
		} while ( 200 === count( $rows ) );
		fclose( $stream );
		exit;
	}

	private static function csv_safe( mixed $value ): string {
		$text = (string) $value;
		return preg_match( '/^[=+\-@]/', $text ) ? "'" . $text : $text;
	}
}
