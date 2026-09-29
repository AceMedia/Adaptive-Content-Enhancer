<?php
/** Guided adoption and optional retirement of ACF and Contact Form 7 data. */
namespace ACE\AdaptiveContentEnhancer\Migration;

use ACE\AdaptiveContentEnhancer\Fields\FieldGroups;
use ACE\AdaptiveContentEnhancer\Forms\Patterns;

defined( 'ABSPATH' ) || exit;

final class OnboardingPage {
	private const ACF_SLUG = 'ace-import-acf';
	private const CF7_SLUG = 'ace-import-cf7';
	private const CONFIRM = 'DELETE LEGACY DATA';
	private static int $current_step = 1;

	public static function menu(): void {
		add_submenu_page( 'adaptive-content-enhancer', __( 'Import ACF', 'adaptive-content-enhancer' ), __( 'Import ACF', 'adaptive-content-enhancer' ), 'manage_options', self::ACF_SLUG, array( self::class, 'render_acf' ) );
		add_submenu_page( 'adaptive-content-enhancer', __( 'Import Contact Form 7', 'adaptive-content-enhancer' ), __( 'Import CF7', 'adaptive-content-enhancer' ), 'manage_options', self::CF7_SLUG, array( self::class, 'render_cf7' ) );
	}

	public static function redirect_legacy(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'ace-data-migration', 'ace-fields-import' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . ( 'ace-fields-import' === $page ? self::ACF_SLUG : self::CF7_SLUG ) ) );
			exit;
		}
	}

	private static function source_posts( string $type ): array {
		return get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
	}

	private static function source_active( array $prefixes ): bool {
		$active = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		foreach ( $active as $plugin ) {
			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( (string) $plugin, $prefix ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function acf_field_ids( array $fields, array &$ids ): void {
		foreach ( $fields as $field ) {
			$id = absint( $field['acf']['id'] ?? 0 );
			if ( $id ) {
				$ids[ $id ] = true;
			}
			self::acf_field_ids( $field['subFields'] ?? array(), $ids );
		}
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/** Live coverage checks are repeated immediately before every retirement action. */
	public static function status(): array {
		global $wpdb;
		$groups = self::source_posts( 'acf-field-group' );
		$fields = self::source_posts( 'acf-field' );
		$forms = self::source_posts( 'wpcf7_contact_form' );
		$group_ids = array();
		$field_ids = array();
		foreach ( FieldGroups::all() as $group ) {
			$id = absint( get_post_meta( $group['id'], 'ace_source_id', true ) );
			if ( $id ) {
				$group_ids[ $id ] = true;
			}
			self::acf_field_ids( $group['fields'], $field_ids );
		}
		$missing_groups = array_filter( $groups, static fn( \WP_Post $post ): bool => ! isset( $group_ids[ $post->ID ] ) );
		$missing_fields = array_filter( $fields, static fn( \WP_Post $post ): bool => ! isset( $field_ids[ $post->ID ] ) );
		$map = get_option( 'ace_forms_cf7_map', array() );
		$missing_forms = array_filter( $forms, static function ( \WP_Post $post ) use ( $map ): bool {
			$pattern = get_post( absint( $map[ $post->ID ]['pattern_id'] ?? 0 ) );
			return ! $pattern || 'publish' !== $pattern->post_status || ! Patterns::is_form_pattern( $pattern );
		} );
		$placements = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%contact-form-7%' AND post_type <> 'wpcf7_contact_form' AND post_status NOT IN ('trash','auto-draft','inherit')" );
		$legacy_table = $wpdb->prefix . 'db7_forms';
		$legacy_ids = self::table_exists( $legacy_table ) ? array_map( 'intval', $wpdb->get_col( "SELECT form_id FROM {$legacy_table}" ) ) : array();
		$imported = array_fill_keys( array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$wpdb->prefix}ace_form_entry_meta WHERE meta_key='cfdb7_id'" ) ), true );
		$missing_entries = array_values( array_filter( $legacy_ids, static fn( int $id ): bool => ! isset( $imported[ $id ] ) ) );
		$engagement = 'customer-engagement' === apply_filters( 'ace_forms_storage_provider', 'local' ) && class_exists( '\\ACE\\AdaptiveCustomerEngagement\\Migration\\AceEntriesImporter' );
		$engagement_pending = null;
		if ( $engagement && ! $missing_entries && $legacy_ids ) {
			$report = \ACE\AdaptiveCustomerEngagement\Migration\AceEntriesImporter::run( true );
			if ( ! isset( $report['error'] ) ) {
				$engagement_pending = (int) ( $report['imported'] ?? 0 ) + (int) ( $report['failed'] ?? 0 );
			}
		}
		return array(
			'acf' => array( 'groups' => count( $groups ), 'fields' => count( $fields ), 'missing_groups' => count( $missing_groups ), 'missing_fields' => count( $missing_fields ), 'active' => self::source_active( array( 'advanced-custom-fields/', 'advanced-custom-fields-pro/', 'acf-pro/' ) ) ),
			'cf7' => array( 'forms' => count( $forms ), 'missing' => count( $missing_forms ), 'placements' => $placements, 'active' => self::source_active( array( 'contact-form-7/' ) ) ),
			'cfdb7' => array( 'entries' => count( $legacy_ids ), 'missing' => count( $missing_entries ), 'engagement' => $engagement, 'engagement_pending' => $engagement_pending, 'active' => self::source_active( array( 'contact-form-cfdb7/' ) ) ),
		);
	}

	private static function action_form( string $action, string $label, bool $retire = false, array $options = array() ): void {
		if ( str_starts_with( $action, 'preview_' ) && 2 !== self::$current_step ) {
			return;
		}
		if ( ( str_starts_with( $action, 'import_' ) || 'transfer_engagement' === $action ) && 3 !== self::$current_step ) {
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ace_data_migration"/><input type="hidden" name="migration_action" value="' . esc_attr( $action ) . '"/>';
		wp_nonce_field( 'ace_data_migration' );
		foreach ( $options as $name => $text ) {
			echo '<p><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"/> ' . esc_html( $text ) . '</label></p>';
		}
		if ( $retire ) {
			echo '<p><label>' . esc_html__( 'Type DELETE LEGACY DATA to confirm', 'adaptive-content-enhancer' ) . ' <input type="text" name="confirm" autocomplete="off" required/></label></p>';
		}
		submit_button( $label, $retire ? 'delete' : 'primary', 'submit', false );
		echo '</form>';
	}

	public static function render_acf(): void {
		self::render( 'acf' );
	}

	public static function render_cf7(): void {
		self::render( 'cf7' );
	}

	private static function render( string $source ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage data migration.', 'adaptive-content-enhancer' ) );
		}
		$status = self::status();
		$step = isset( $_GET['step'] ) ? min( 5, max( 1, absint( $_GET['step'] ) ) ) : 1;
		self::$current_step = $step;
		$report = get_transient( 'ace_data_migration_report_' . get_current_user_id() );
		delete_transient( 'ace_data_migration_report_' . get_current_user_id() );
		echo '<div class="wrap ace-content-admin ace-import-page">';
		$acf_found = $status['acf']['groups'] + $status['acf']['fields'];
		$cf7_found = $status['cf7']['forms'] + $status['cfdb7']['entries'];
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Import source', 'adaptive-content-enhancer' ) . '"><a class="nav-tab ' . ( 'acf' === $source ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . self::ACF_SLUG ) ) . '">' . esc_html__( 'ACF fields', 'adaptive-content-enhancer' ) . ' <span class="count">' . esc_html( $acf_found ? sprintf( __( '%d found', 'adaptive-content-enhancer' ), $acf_found ) : __( 'none found', 'adaptive-content-enhancer' ) ) . '</span></a><a class="nav-tab ' . ( 'cf7' === $source ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . self::CF7_SLUG ) ) . '">' . esc_html__( 'CF7 forms and entries', 'adaptive-content-enhancer' ) . ' <span class="count">' . esc_html( $cf7_found ? sprintf( __( '%d found', 'adaptive-content-enhancer' ), $cf7_found ) : __( 'none found', 'adaptive-content-enhancer' ) ) . '</span></a></nav>';
		$steps = array( __( 'Detect', 'adaptive-content-enhancer' ), __( 'Preview', 'adaptive-content-enhancer' ), __( 'Adopt', 'adaptive-content-enhancer' ), __( 'Verify', 'adaptive-content-enhancer' ), __( 'Retire', 'adaptive-content-enhancer' ) );
		echo '<div class="ace-import-wizard"><nav class="ace-import-steps" aria-label="' . esc_attr__( 'Import progress', 'adaptive-content-enhancer' ) . '">';
		foreach ( $steps as $index => $title ) {
			$number = $index + 1;
			$url = add_query_arg( array( 'page' => 'acf' === $source ? self::ACF_SLUG : self::CF7_SLUG, 'step' => $number ), admin_url( 'admin.php' ) );
			echo '<a class="ace-import-step ' . ( $step === $number ? 'is-current' : ( $step > $number ? 'is-complete' : '' ) ) . '" href="' . esc_url( $url ) . '"' . ( $step === $number ? ' aria-current="step"' : '' ) . '><span>' . esc_html( (string) $number ) . '</span><strong>' . esc_html( $title ) . '</strong></a>';
		}
		echo '</nav><div class="ace-import-card">';
		if ( is_array( $report ) ) {
			echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Last action', 'adaptive-content-enhancer' ) . '</strong></p><ul>';
			foreach ( array_slice( $report, 0, 60 ) as $line ) {
				echo '<li>' . esc_html( (string) $line ) . '</li>';
			}
			echo '</ul></div>';
		}
		if ( 1 === $step ) {
		echo '<h2>' . esc_html__( '1. Review source data', 'adaptive-content-enhancer' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Source', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Found', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Still to adopt', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Source plugin', 'adaptive-content-enhancer' ) . '</th></tr></thead><tbody>';
		if ( 'acf' === $source ) {
		echo '<tr><td>ACF</td><td>' . esc_html( sprintf( '%d groups, %d fields', $status['acf']['groups'], $status['acf']['fields'] ) ) . '</td><td>' . esc_html( sprintf( '%d groups, %d fields', $status['acf']['missing_groups'], $status['acf']['missing_fields'] ) ) . '</td><td>' . esc_html( $status['acf']['active'] ? __( 'Active', 'adaptive-content-enhancer' ) : __( 'Inactive', 'adaptive-content-enhancer' ) ) . '</td></tr>';
		} else {
		echo '<tr><td>Contact Form 7</td><td>' . esc_html( (string) $status['cf7']['forms'] ) . '</td><td>' . esc_html( sprintf( '%d forms, %d placements', $status['cf7']['missing'], $status['cf7']['placements'] ) ) . '</td><td>' . esc_html( $status['cf7']['active'] ? __( 'Active', 'adaptive-content-enhancer' ) : __( 'Inactive', 'adaptive-content-enhancer' ) ) . '</td></tr>';
		echo '<tr><td>CFDB7</td><td>' . esc_html( (string) $status['cfdb7']['entries'] ) . '</td><td>' . esc_html( (string) $status['cfdb7']['missing'] ) . '</td><td>' . esc_html( $status['cfdb7']['active'] ? __( 'Active', 'adaptive-content-enhancer' ) : __( 'Inactive', 'adaptive-content-enhancer' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( ( 'acf' === $source && ! $acf_found ) || ( 'cf7' === $source && ! $cf7_found ) ) {
			echo '<p>' . esc_html__( 'No stored source records were found on this site. You can leave this importer alone unless you have field definitions or forms registered only in custom code.', 'adaptive-content-enhancer' ) . '</p>';
		}
		echo '<p class="ace-import-actions"><a class="button button-primary" href="' . esc_url( add_query_arg( 'step', 2 ) ) . '">' . esc_html__( 'Continue to preview', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		if ( 2 === $step || 3 === $step ) {
		echo '<h2>' . esc_html( 2 === $step ? __( 'Preview the conversion', 'adaptive-content-enhancer' ) : __( 'Adopt the data', 'adaptive-content-enhancer' ) ) . '</h2><p>' . esc_html( 2 === $step ? __( 'Run a dry preview before changing any data.', 'adaptive-content-enhancer' ) : __( 'Imports can be run again safely. Existing edited ACE groups and patterns are kept.', 'adaptive-content-enhancer' ) ) . '</p>';
		if ( 'acf' === $source && ( $status['acf']['groups'] || $status['acf']['fields'] ) ) {
			echo '<h3>ACF → ' . esc_html__( 'Field groups', 'adaptive-content-enhancer' ) . '</h3><p>' . esc_html__( 'Import missing definitions. Existing edited ACE groups are kept.', 'adaptive-content-enhancer' ) . '</p>';
			self::action_form( 'preview_acf', __( 'Preview ACF adoption', 'adaptive-content-enhancer' ) );
			self::action_form( 'import_acf', __( 'Adopt ACF fields', 'adaptive-content-enhancer' ) );
			echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=ace_field_group' ) ) . '">' . esc_html__( 'Review adopted field groups', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		if ( 'cf7' === $source && $status['cf7']['forms'] ) {
			echo '<h3>Contact Form 7 → ' . esc_html__( 'Form patterns', 'adaptive-content-enhancer' ) . '</h3>';
			self::action_form( 'preview_cf7', __( 'Preview CF7 adoption', 'adaptive-content-enhancer' ), false, array( 'replace_placements' => __( 'Include site content replacements in the preview', 'adaptive-content-enhancer' ) ) );
			self::action_form( 'import_cf7', __( 'Adopt CF7 forms', 'adaptive-content-enhancer' ), false, array( 'replace_placements' => __( 'Replace CF7 blocks and shortcodes in site content with synced patterns', 'adaptive-content-enhancer' ), 'refresh_notifications' => __( 'Refresh notifications on forms already imported', 'adaptive-content-enhancer' ) ) );
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=ace-form-patterns' ) ) . '">' . esc_html__( 'Review adopted form patterns', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		if ( 'cf7' === $source && $status['cfdb7']['entries'] ) {
			echo '<h3>CFDB7 → ' . esc_html__( 'Entry history', 'adaptive-content-enhancer' ) . '</h3>';
			self::action_form( 'preview_cfdb7', __( 'Preview CFDB7 adoption', 'adaptive-content-enhancer' ) );
			self::action_form( 'import_cfdb7', __( 'Adopt CFDB7 entries', 'adaptive-content-enhancer' ) );
			if ( $status['cfdb7']['engagement'] ) {
				echo '<p>' . esc_html( null === $status['cfdb7']['engagement_pending'] ? __( 'Adopt CFDB7 entries before checking Customer Engagement coverage.', 'adaptive-content-enhancer' ) : sprintf( __( '%d ACE archive entries still need transferring to Customer Engagement.', 'adaptive-content-enhancer' ), $status['cfdb7']['engagement_pending'] ) ) . '</p>';
				self::action_form( 'transfer_engagement', __( 'Transfer history to Customer Engagement', 'adaptive-content-enhancer' ) );
			} else {
				echo '<p>' . esc_html__( 'Activate Adaptive Customer Engagement as the storage provider before transferring history or retiring the old table.', 'adaptive-content-enhancer' ) . '</p>';
			}
		}
		echo '<p class="ace-import-actions"><a class="button" href="' . esc_url( add_query_arg( 'step', $step - 1 ) ) . '">' . esc_html__( 'Back', 'adaptive-content-enhancer' ) . '</a> <a class="button button-primary" href="' . esc_url( add_query_arg( 'step', $step + 1 ) ) . '">' . esc_html( 2 === $step ? __( 'Continue to adoption', 'adaptive-content-enhancer' ) : __( 'Review coverage', 'adaptive-content-enhancer' ) ) . '</a></p>';
		}
		if ( 4 === $step ) {
			echo '<h2>' . esc_html__( 'Verify the adoption', 'adaptive-content-enhancer' ) . '</h2>';
			if ( 'acf' === $source ) {
				echo '<p>' . esc_html( sprintf( __( '%1$d of %2$d ACF groups and %3$d of %4$d fields are represented in ACE.', 'adaptive-content-enhancer' ), $status['acf']['groups'] - $status['acf']['missing_groups'], $status['acf']['groups'], $status['acf']['fields'] - $status['acf']['missing_fields'], $status['acf']['fields'] ) ) . '</p><p><a href="' . esc_url( admin_url( 'edit.php?post_type=ace_field_group' ) ) . '">' . esc_html__( 'Inspect field groups', 'adaptive-content-enhancer' ) . '</a></p>';
			} else {
				echo '<p>' . esc_html( sprintf( __( '%1$d of %2$d CF7 forms are synced patterns; %3$d old placements remain.', 'adaptive-content-enhancer' ), $status['cf7']['forms'] - $status['cf7']['missing'], $status['cf7']['forms'], $status['cf7']['placements'] ) ) . '</p><p>' . esc_html( sprintf( __( '%1$d of %2$d CFDB7 entries are in the ACE archive.', 'adaptive-content-enhancer' ), $status['cfdb7']['entries'] - $status['cfdb7']['missing'], $status['cfdb7']['entries'] ) ) . '</p>';
				if ( $status['cfdb7']['engagement'] ) {
					echo '<p>' . esc_html( null === $status['cfdb7']['engagement_pending'] ? __( 'Transfer coverage could not be checked yet.', 'adaptive-content-enhancer' ) : sprintf( __( '%d entries still need transferring to Customer Engagement.', 'adaptive-content-enhancer' ), $status['cfdb7']['engagement_pending'] ) ) . '</p>';
				}
				echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=ace-form-patterns' ) ) . '">' . esc_html__( 'Inspect form patterns', 'adaptive-content-enhancer' ) . '</a></p>';
			}
			echo '<p class="ace-import-actions"><a class="button" href="' . esc_url( add_query_arg( 'step', 3 ) ) . '">' . esc_html__( 'Back to adoption', 'adaptive-content-enhancer' ) . '</a> <a class="button button-primary" href="' . esc_url( add_query_arg( 'step', 5 ) ) . '">' . esc_html__( 'Review old data', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		if ( 5 === $step ) {
		echo '<h2>' . esc_html__( 'Retire old data', 'adaptive-content-enhancer' ) . '</h2>';
		echo '<p>' . esc_html__( 'Retirement is optional and separate from adoption. Review the adopted blocks and any custom ACF code, then deactivate each source plugin. ACE keeps native meta values, synced patterns and imported entries.', 'adaptive-content-enhancer' ) . '</p>';
		if ( 'acf' === $source ) {
			self::retirement_row( 'acf', $status['acf']['groups'] + $status['acf']['fields'], ! $status['acf']['missing_groups'] && ! $status['acf']['missing_fields'] && ! $status['acf']['active'], __( 'Delete old ACF field definitions', 'adaptive-content-enhancer' ), __( 'ACF definitions remain, are not fully adopted, or ACF is still active.', 'adaptive-content-enhancer' ) );
		} else {
			self::retirement_row( 'cf7', $status['cf7']['forms'], ! $status['cf7']['missing'] && ! $status['cf7']['placements'] && ! $status['cf7']['active'], __( 'Delete old CF7 form definitions', 'adaptive-content-enhancer' ), __( 'CF7 forms or placements remain, or CF7 is still active.', 'adaptive-content-enhancer' ) );
			self::retirement_row( 'cfdb7', $status['cfdb7']['entries'], ! $status['cfdb7']['missing'] && $status['cfdb7']['engagement'] && 0 === $status['cfdb7']['engagement_pending'] && ! $status['cfdb7']['active'], __( 'Delete old CFDB7 entry table', 'adaptive-content-enhancer' ), __( 'Entries are not fully transferred to Customer Engagement, or CFDB7 is still active.', 'adaptive-content-enhancer' ) );
		}
		echo '<p class="ace-import-actions"><a class="button" href="' . esc_url( add_query_arg( 'step', 4 ) ) . '">' . esc_html__( 'Back to verification', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		echo '</div></div></div>';
	}

	private static function retirement_row( string $source, int $count, bool $ready, string $label, string $reason ): void {
		if ( ! $count ) {
			return;
		}
		echo '<h3>' . esc_html( strtoupper( $source ) ) . '</h3>';
		if ( $ready ) {
			self::action_form( 'retire_' . $source, $label, true );
		} else {
			echo '<p>' . esc_html( $reason ) . '</p>';
		}
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage data migration.', 'adaptive-content-enhancer' ) );
		}
		check_admin_referer( 'ace_data_migration' );
		$action = isset( $_POST['migration_action'] ) ? sanitize_key( wp_unslash( $_POST['migration_action'] ) ) : '';
		$report = array();
		if ( str_starts_with( $action, 'retire_' ) ) {
			$confirmed = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
			if ( self::CONFIRM !== $confirmed ) {
				$report[] = __( 'Retirement was not confirmed.', 'adaptive-content-enhancer' );
			} else {
				$report = self::retire( substr( $action, 7 ) );
			}
		} elseif ( 'import_acf' === $action || 'preview_acf' === $action ) {
			$report = AcfImporter::import( 'preview_acf' === $action );
		} elseif ( 'import_cf7' === $action || 'preview_cf7' === $action ) {
			$report = Cf7Importer::import( 'preview_cf7' === $action, ! empty( $_POST['replace_placements'] ), ! empty( $_POST['refresh_notifications'] ) );
		} elseif ( 'import_cfdb7' === $action || 'preview_cfdb7' === $action ) {
			$result = Cfdb7Importer::import( 'preview_cfdb7' === $action );
			foreach ( $result as $key => $value ) {
				$report[] = $key . ': ' . ( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
			}
		} elseif ( 'transfer_engagement' === $action && class_exists( '\\ACE\\AdaptiveCustomerEngagement\\Migration\\AceEntriesImporter' ) && 'customer-engagement' === apply_filters( 'ace_forms_storage_provider', 'local' ) ) {
			$result = \ACE\AdaptiveCustomerEngagement\Migration\AceEntriesImporter::run( false );
			foreach ( $result as $key => $value ) {
				$report[] = $key . ': ' . ( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
			}
		} else {
			$report[] = __( 'Unknown or unavailable migration action.', 'adaptive-content-enhancer' );
		}
		set_transient( 'ace_data_migration_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
		$step = str_starts_with( $action, 'preview_' ) ? 2 : ( str_starts_with( $action, 'retire_' ) ? 5 : 4 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . ( str_contains( $action, 'acf' ) ? self::ACF_SLUG : self::CF7_SLUG ) . '&step=' . $step ) );
		exit;
	}

	private static function retire( string $source ): array {
		global $wpdb;
		$status = self::status();
		if ( 'acf' === $source && $status['acf']['groups'] + $status['acf']['fields'] > 0 && ! $status['acf']['missing_groups'] && ! $status['acf']['missing_fields'] && ! $status['acf']['active'] ) {
			$count = 0;
			foreach ( array_merge( self::source_posts( 'acf-field' ), self::source_posts( 'acf-field-group' ) ) as $post ) {
				$count += (bool) wp_delete_post( $post->ID, true );
			}
			return array( sprintf( __( 'Deleted %d old ACF definition posts. Native meta values remain.', 'adaptive-content-enhancer' ), $count ) );
		}
		if ( 'cf7' === $source && $status['cf7']['forms'] > 0 && ! $status['cf7']['missing'] && ! $status['cf7']['placements'] && ! $status['cf7']['active'] ) {
			$count = 0;
			foreach ( self::source_posts( 'wpcf7_contact_form' ) as $post ) {
				$count += (bool) wp_delete_post( $post->ID, true );
			}
			return array( sprintf( __( 'Deleted %d old CF7 form definitions. ACE patterns remain.', 'adaptive-content-enhancer' ), $count ) );
		}
		if ( 'cfdb7' === $source && $status['cfdb7']['entries'] > 0 && ! $status['cfdb7']['missing'] && $status['cfdb7']['engagement'] && 0 === $status['cfdb7']['engagement_pending'] && ! $status['cfdb7']['active'] ) {
			$table = $wpdb->prefix . 'db7_forms';
			$result = $wpdb->query( "DROP TABLE `{$table}`" );
			return array( false === $result ? __( 'Could not delete the CFDB7 table.', 'adaptive-content-enhancer' ) : __( 'Deleted the old CFDB7 entry table. Customer Engagement retains the adopted history.', 'adaptive-content-enhancer' ) );
		}
		return array( __( 'Retirement is unavailable until all source data is adopted and its plugin is inactive.', 'adaptive-content-enhancer' ) );
	}
}
