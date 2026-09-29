<?php
/** Shared content settings using WordPress options and a Crawl-style save bar. */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public static function register(): void {
		register_setting( 'ace_content_settings', 'ace_forms_turnstile', array( 'type' => 'object', 'default' => array(), 'sanitize_callback' => array( self::class, 'sanitise' ) ) );
		register_setting( 'ace_content_settings', 'ace_fields_acf_compat', array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	}

	public static function menu(): void {
		add_submenu_page( 'adaptive-content-enhancer', __( 'Content settings', 'adaptive-content-enhancer' ), __( 'Settings', 'adaptive-content-enhancer' ), Capabilities::SETTINGS, 'ace-forms-settings', array( self::class, 'render' ) );
	}

	public static function sanitise( mixed $value ): array {
		$value = is_array( $value ) ? $value : array();
		return array( 'site_key' => sanitize_text_field( $value['site_key'] ?? '' ), 'secret_key' => sanitize_text_field( $value['secret_key'] ?? '' ) );
	}

	public static function ajax_save(): void {
		if ( ! current_user_can( Capabilities::SETTINGS ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot change content settings.', 'adaptive-content-enhancer' ) ), 403 );
		}
		check_ajax_referer( 'ace_content_settings', 'nonce' );
		$turnstile = isset( $_POST['ace_forms_turnstile'] ) ? wp_unslash( $_POST['ace_forms_turnstile'] ) : array();
		update_option( 'ace_forms_turnstile', self::sanitise( $turnstile ) );
		update_option( 'ace_fields_acf_compat', ! empty( $_POST['ace_fields_acf_compat'] ) );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'adaptive-content-enhancer' ) ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::SETTINGS ) ) {
			wp_die( esc_html__( 'You cannot change content settings.', 'adaptive-content-enhancer' ) );
		}
		$settings = Turnstile::settings();
		$storage = 'customer-engagement' === apply_filters( 'ace_forms_storage_provider', 'local' );
		$script = ACE_ACE_DIR . 'assets/admin/content-settings.js';
		wp_enqueue_script( 'ace-content-settings', ACE_ACE_URL . 'assets/admin/content-settings.js', array(), (string) filemtime( $script ), true );
		wp_add_inline_script( 'ace-content-settings', 'window.aceContentSettings = ' . wp_json_encode( array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ace_content_settings' ), 'saved' => __( 'Saved', 'adaptive-content-enhancer' ), 'saving' => __( 'Saving…', 'adaptive-content-enhancer' ), 'save' => __( 'Save changes', 'adaptive-content-enhancer' ), 'error' => __( 'Could not save settings. Please try again.', 'adaptive-content-enhancer' ) ) ) . ';', 'before' );
		echo '<div class="wrap ace-content-admin ace-content-settings"><header class="ace-content-header"><div><p class="ace-content-eyebrow">Ace Content</p><h1>' . esc_html__( 'Settings', 'adaptive-content-enhancer' ) . '</h1><p>' . esc_html__( 'Form protection, field compatibility and storage connections.', 'adaptive-content-enhancer' ) . '</p></div></header>';
		echo '<div class="ace-content-layout"><nav class="ace-content-sidebar" aria-label="' . esc_attr__( 'Settings sections', 'adaptive-content-enhancer' ) . '"><a href="#form-protection">' . esc_html__( 'Form protection', 'adaptive-content-enhancer' ) . '</a><a href="#field-compatibility">' . esc_html__( 'Field compatibility', 'adaptive-content-enhancer' ) . '</a><a href="#entry-storage">' . esc_html__( 'Entry storage', 'adaptive-content-enhancer' ) . '</a></nav>';
		echo '<form id="ace-content-settings-form" class="ace-content-settings-form" method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'ace_content_settings' );
		echo '<section class="ace-content-panel" id="form-protection"><h2>' . esc_html__( 'Form protection', 'adaptive-content-enhancer' ) . '</h2><p>' . esc_html__( 'Use site-specific Cloudflare Turnstile keys for public forms.', 'adaptive-content-enhancer' ) . '</p><div class="ace-content-field"><label for="ace-turnstile-site">' . esc_html__( 'Turnstile site key', 'adaptive-content-enhancer' ) . '</label><input class="regular-text" id="ace-turnstile-site" name="ace_forms_turnstile[site_key]" value="' . esc_attr( $settings['site_key'] ?? '' ) . '" autocomplete="off"/></div><div class="ace-content-field"><label for="ace-turnstile-secret">' . esc_html__( 'Turnstile secret key', 'adaptive-content-enhancer' ) . '</label><input class="regular-text" type="password" id="ace-turnstile-secret" name="ace_forms_turnstile[secret_key]" value="' . esc_attr( $settings['secret_key'] ?? '' ) . '" autocomplete="new-password"/></div></section>';
		echo '<section class="ace-content-panel" id="field-compatibility"><h2>' . esc_html__( 'Field compatibility', 'adaptive-content-enhancer' ) . '</h2><label class="ace-content-check"><input type="hidden" name="ace_fields_acf_compat" value="0"/><input type="checkbox" name="ace_fields_acf_compat" value="1" ' . checked( (bool) get_option( 'ace_fields_acf_compat' ), true, false ) . '/><span><strong>' . esc_html__( 'Enable basic ACF function compatibility', 'adaptive-content-enhancer' ) . '</strong><small>' . esc_html__( 'Adds ACE wrappers for get_field() and related helpers only when ACF is inactive. Check custom PHP before retiring ACF.', 'adaptive-content-enhancer' ) . '</small></span></label></section>';
		echo '<section class="ace-content-panel" id="entry-storage"><h2>' . esc_html__( 'Entry storage', 'adaptive-content-enhancer' ) . '</h2><p><strong>' . esc_html( $storage ? __( 'Customer Engagement is active', 'adaptive-content-enhancer' ) : __( 'ACE local storage is active', 'adaptive-content-enhancer' ) ) . '</strong></p><p>' . esc_html( $storage ? __( 'New submissions are saved directly to Customer Engagement. ACE retains older imports and a fallback archive.', 'adaptive-content-enhancer' ) : __( 'New submissions are saved in ACE until Customer Engagement is activated on this site.', 'adaptive-content-enhancer' ) ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=ace-forms' ) ) . '">' . esc_html__( 'Open entry archive', 'adaptive-content-enhancer' ) . '</a></p></section>';
		echo '<p class="ace-content-fallback-save"><button type="submit" class="button button-primary">' . esc_html__( 'Save settings', 'adaptive-content-enhancer' ) . '</button></p></form></div><div class="ace-content-savebar" role="region" aria-label="' . esc_attr__( 'Save settings', 'adaptive-content-enhancer' ) . '"><span class="ace-content-savebar__message" role="status" aria-live="polite"></span><label class="ace-content-savebar__auto"><input type="checkbox" id="ace-content-auto-save"/> ' . esc_html__( 'Auto-save', 'adaptive-content-enhancer' ) . '</label><button type="button" id="ace-content-save" class="button button-primary" disabled>' . esc_html__( 'Saved', 'adaptive-content-enhancer' ) . '</button></div></div>';
	}
}
