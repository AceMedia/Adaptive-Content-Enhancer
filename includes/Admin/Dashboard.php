<?php
/** One home for forms, fields, imports and their related entries. */
namespace ACE\AdaptiveContentEnhancer\Admin;

use ACE\AdaptiveContentEnhancer\Fields\FieldGroups;
use ACE\AdaptiveContentEnhancer\Forms\Patterns;
use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Dashboard {
	public const MENU = 'adaptive-content-enhancer';

	public static function menu(): void {
		add_menu_page( __( 'Ace Content', 'adaptive-content-enhancer' ), __( 'Ace Content', 'adaptive-content-enhancer' ), Capabilities::VIEW_ENTRIES, self::MENU, array( self::class, 'render' ), 'dashicons-layout', 22 );
		add_submenu_page( self::MENU, __( 'Dashboard', 'adaptive-content-enhancer' ), __( 'Dashboard', 'adaptive-content-enhancer' ), Capabilities::VIEW_ENTRIES, self::MENU, array( self::class, 'render' ) );
	}

	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, self::MENU ) && ! str_contains( $hook, 'ace-' ) ) {
			return;
		}
		$path = ACE_ACE_DIR . 'assets/styles/content-admin.css';
		if ( is_readable( $path ) ) {
			wp_enqueue_style( 'ace-content-admin', ACE_ACE_URL . 'assets/styles/content-admin.css', array(), (string) filemtime( $path ) );
		}
		if ( str_contains( $hook, 'ace-import-' ) ) {
			$wizard = ACE_ACE_DIR . 'assets/styles/import-wizard.css';
			wp_enqueue_style( 'ace-content-import-wizard', ACE_ACE_URL . 'assets/styles/import-wizard.css', array( 'ace-content-admin' ), (string) filemtime( $wizard ) );
		}
	}

	private static function card( string $title, string $value, string $description, string $url, string $link ): void {
		echo '<section class="ace-content-card"><h2>' . esc_html( $title ) . '</h2><p class="ace-content-card__value">' . esc_html( $value ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $url ) . '">' . esc_html( $link ) . ' <span aria-hidden="true">→</span></a></section>';
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_ENTRIES ) ) {
			wp_die( esc_html__( 'You cannot view the content dashboard.', 'adaptive-content-enhancer' ) );
		}
		global $wpdb;
		$patterns = get_posts( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'all' ) );
		$forms = count( array_filter( $patterns, array( Patterns::class, 'is_form_pattern' ) ) );
		$groups = wp_count_posts( FieldGroups::POST_TYPE );
		$group_count = (int) ( $groups->publish ?? 0 );
		$archive_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ace_form_entries" );
		$engagement = 'customer-engagement' === apply_filters( 'ace_forms_storage_provider', 'local' );
		$engagement_count = null;
		if ( $engagement && class_exists( '\\ACE\\AdaptiveCustomerEngagement\\Database\\Schema' ) ) {
			$table = \ACE\AdaptiveCustomerEngagement\Database\Schema::table_name( 'form_submissions' );
			$engagement_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}
		echo '<div class="wrap ace-content-admin"><header class="ace-content-header"><div><p class="ace-content-eyebrow">Ace Content</p><h1>' . esc_html__( 'Content dashboard', 'adaptive-content-enhancer' ) . '</h1><p>' . esc_html__( 'Manage block forms, custom fields, patterns and their connected submissions.', 'adaptive-content-enhancer' ) . '</p></div></header>';
		echo '<div class="ace-content-grid">';
		self::card( __( 'Form patterns', 'adaptive-content-enhancer' ), number_format_i18n( $forms ), __( 'Synced WordPress patterns containing an ACE form.', 'adaptive-content-enhancer' ), admin_url( 'admin.php?page=ace-form-patterns' ), __( 'Manage forms', 'adaptive-content-enhancer' ) );
		self::card( __( 'Field groups', 'adaptive-content-enhancer' ), number_format_i18n( $group_count ), __( 'Block-authored definitions backed by WordPress meta.', 'adaptive-content-enhancer' ), admin_url( 'edit.php?post_type=ace_field_group' ), __( 'Manage fields', 'adaptive-content-enhancer' ) );
		self::card( __( 'ACE entry archive', 'adaptive-content-enhancer' ), number_format_i18n( $archive_count ), __( 'Earlier captures and imported form history retained by ACE.', 'adaptive-content-enhancer' ), admin_url( 'admin.php?page=ace-forms' ), __( 'View archive', 'adaptive-content-enhancer' ) );
		if ( $engagement_count !== null ) {
			self::card( __( 'Customer Engagement', 'adaptive-content-enhancer' ), number_format_i18n( $engagement_count ), __( 'Current captures and transferred history in Customer Engagement.', 'adaptive-content-enhancer' ), admin_url( 'admin.php?page=adaptive-customer-engagement-forms' ), __( 'View submissions', 'adaptive-content-enhancer' ) );
		}
		echo '</div><section class="ace-content-panel"><h2>' . esc_html__( 'Where submissions go', 'adaptive-content-enhancer' ) . '</h2><p>' . esc_html( $engagement ? __( 'Customer Engagement stores new submissions directly. ACE owns the form blocks, validation and notifications; its entry archive holds earlier imports and provides a fallback if Customer Engagement cannot save.', 'adaptive-content-enhancer' ) : __( 'ACE stores submissions locally while Customer Engagement is inactive. Activate Customer Engagement to store new captures there and transfer existing history through the CF7 importer.', 'adaptive-content-enhancer' ) ) . '</p>';
		if ( current_user_can( 'manage_options' ) ) {
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=ace-import-acf' ) ) . '">' . esc_html__( 'Import ACF', 'adaptive-content-enhancer' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=ace-import-cf7' ) ) . '">' . esc_html__( 'Import CF7', 'adaptive-content-enhancer' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=ace-forms-settings' ) ) . '">' . esc_html__( 'Settings', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		echo '</section></div>';
	}
}
