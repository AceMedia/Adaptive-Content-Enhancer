<?php
/** Shared content settings using WordPress options and a Crawl-style save bar. */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;
use ACE\AdaptiveContentEnhancer\Fields\CompatibilityScan;
use ACE\AdaptiveContentEnhancer\Migration\OnboardingPage;

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
		$scan = CompatibilityScan::results();
		$sources = OnboardingPage::status();
		$acf_active = $sources['acf']['active'];
		$acf_adopted = $sources['acf']['groups'] > 0 && 0 === $sources['acf']['missing_groups'] && 0 === $sources['acf']['missing_fields'];
		$script = ACE_ACE_DIR . 'assets/admin/content-settings.js';
		wp_enqueue_script( 'ace-content-settings', ACE_ACE_URL . 'assets/admin/content-settings.js', array(), (string) filemtime( $script ), true );
		wp_add_inline_script( 'ace-content-settings', 'window.aceContentSettings = ' . wp_json_encode( array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ace_content_settings' ), 'saved' => __( 'Saved', 'adaptive-content-enhancer' ), 'saving' => __( 'Saving…', 'adaptive-content-enhancer' ), 'save' => __( 'Save changes', 'adaptive-content-enhancer' ), 'error' => __( 'Could not save settings. Please try again.', 'adaptive-content-enhancer' ) ) ) . ';', 'before' );
		echo '<div class="wrap ace-content-admin ace-content-settings"><header class="ace-content-header"><div><p class="ace-content-eyebrow">Ace Content</p><h1>' . esc_html__( 'Settings', 'adaptive-content-enhancer' ) . '</h1><p>' . esc_html__( 'Form protection, field compatibility and storage connections.', 'adaptive-content-enhancer' ) . '</p></div></header>';
		echo '<div class="ace-content-layout"><nav class="ace-content-sidebar" aria-label="' . esc_attr__( 'Settings sections', 'adaptive-content-enhancer' ) . '"><a href="#form-protection">' . esc_html__( 'Form protection', 'adaptive-content-enhancer' ) . '</a><a href="#field-compatibility">' . esc_html__( 'Field compatibility', 'adaptive-content-enhancer' ) . '</a><a href="#entry-storage">' . esc_html__( 'Entry storage', 'adaptive-content-enhancer' ) . '</a></nav>';
		echo '<form id="ace-content-settings-form" class="ace-content-settings-form" method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'ace_content_settings' );
		echo '<section class="ace-content-panel" id="form-protection"><h2>' . esc_html__( 'Form protection', 'adaptive-content-enhancer' ) . '</h2><p>' . esc_html__( 'Use site-specific Cloudflare Turnstile keys for public forms.', 'adaptive-content-enhancer' ) . '</p><div class="ace-content-field"><label for="ace-turnstile-site">' . esc_html__( 'Turnstile site key', 'adaptive-content-enhancer' ) . '</label><input class="regular-text" id="ace-turnstile-site" name="ace_forms_turnstile[site_key]" value="' . esc_attr( $settings['site_key'] ?? '' ) . '" autocomplete="off"/></div><div class="ace-content-field"><label for="ace-turnstile-secret">' . esc_html__( 'Turnstile secret key', 'adaptive-content-enhancer' ) . '</label><input class="regular-text" type="password" id="ace-turnstile-secret" name="ace_forms_turnstile[secret_key]" value="' . esc_attr( $settings['secret_key'] ?? '' ) . '" autocomplete="new-password"/></div></section>';
		echo '<section class="ace-content-panel" id="field-compatibility"><h2>' . esc_html__( 'Field compatibility', 'adaptive-content-enhancer' ) . '</h2><p>' . esc_html__( 'ACE stores adopted fields as WordPress post meta. This option helps existing PHP templates and plugins keep reading those values after ACF is retired.', 'adaptive-content-enhancer' ) . '</p><label class="ace-content-check"><input type="hidden" name="ace_fields_acf_compat" value="0"/><input type="checkbox" name="ace_fields_acf_compat" value="1" ' . checked( (bool) get_option( 'ace_fields_acf_compat' ), true, false ) . '/><span><strong>' . esc_html__( 'Enable basic ACF function compatibility', 'adaptive-content-enhancer' ) . '</strong><small>' . esc_html__( 'Provides get_field(), the_field(), have_rows(), the_row() and get_sub_field() only when ACF does not provide them.', 'adaptive-content-enhancer' ) . '</small></span></label>';
		if ( $acf_active ) {
			echo '<p><strong>' . esc_html__( 'ACF is active on this site.', 'adaptive-content-enhancer' ) . '</strong> ' . esc_html__( 'Its own functions take precedence. Review adopted fields and code usage before deactivating it.', 'adaptive-content-enhancer' ) . '</p>';
		} elseif ( $scan['supported'] && $acf_adopted ) {
			echo '<p><strong>' . esc_html__( 'Suggested for this site:', 'adaptive-content-enhancer' ) . '</strong> ' . esc_html__( 'Supported ACF helper calls were found and the stored field definitions have been adopted. Enable compatibility, then test the affected pages.', 'adaptive-content-enhancer' ) . '</p>';
		} elseif ( $scan['supported'] ) {
			echo '<p><strong>' . esc_html__( 'Adopt fields first.', 'adaptive-content-enhancer' ) . '</strong> ' . esc_html__( 'Supported ACF helper calls were found, but source definitions have not all been adopted. Complete the ACF importer before relying on compatibility.', 'adaptive-content-enhancer' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'No supported ACF helper calls were found in the active PHP files sampled here. Leave compatibility off unless other code needs it.', 'adaptive-content-enhancer' ) . '</p>';
		}
		echo '<p>' . esc_html( sprintf( __( 'Scanned %1$d active PHP files: %2$d supported helper calls and %3$d calls needing manual review.', 'adaptive-content-enhancer' ), $scan['files'], $scan['supported'], $scan['review'] ) ) . '</p>';
		if ( $scan['samples'] ) {
			echo '<ul class="ace-content-code-samples">';
			foreach ( $scan['samples'] as $sample ) {
				echo '<li><code>' . esc_html( $sample['call'] ) . '</code> — <code>' . esc_html( $sample['file'] . ':' . $sample['line'] ) . '</code> (' . esc_html( 'review' === $sample['kind'] ? __( 'manual review', 'adaptive-content-enhancer' ) : __( 'supported helper', 'adaptive-content-enhancer' ) ) . ')</li>';
			}
			echo '</ul>';
		}
		if ( $scan['review'] ) {
			echo '<p>' . esc_html__( 'The compatibility option does not cover the calls marked for manual review. Check what those calls do and migrate them to ACE fields or native WordPress APIs.', 'adaptive-content-enhancer' ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'This is a guide based on active theme, plugin and MU-plugin PHP. It skips vendor, build and test files, database snippets and inactive code; a matching function name may belong to another library.', 'adaptive-content-enhancer' ) . ( $scan['truncated'] ? ' ' . esc_html__( 'Sampling reached a safety limit in at least one plugin, so more files may need checking.', 'adaptive-content-enhancer' ) : '' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=ace-import-acf' ) ) . '">' . esc_html__( 'Review detected ACF data', 'adaptive-content-enhancer' ) . '</a></p></section>';
		echo '<section class="ace-content-panel" id="entry-storage"><h2>' . esc_html__( 'Entry storage', 'adaptive-content-enhancer' ) . '</h2><p><strong>' . esc_html( $storage ? __( 'Customer Engagement is active', 'adaptive-content-enhancer' ) : __( 'ACE local storage is active', 'adaptive-content-enhancer' ) ) . '</strong></p><p>' . esc_html( $storage ? __( 'New submissions are saved directly to Customer Engagement. ACE retains older imports and a fallback archive.', 'adaptive-content-enhancer' ) : __( 'New submissions are saved in ACE until Customer Engagement is activated on this site.', 'adaptive-content-enhancer' ) ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=ace-forms' ) ) . '">' . esc_html__( 'Open entry archive', 'adaptive-content-enhancer' ) . '</a></p></section>';
		echo '<p class="ace-content-fallback-save"><button type="submit" class="button button-primary">' . esc_html__( 'Save settings', 'adaptive-content-enhancer' ) . '</button></p></form></div><div class="ace-content-savebar" role="region" aria-label="' . esc_attr__( 'Save settings', 'adaptive-content-enhancer' ) . '"><span class="ace-content-savebar__message" role="status" aria-live="polite"></span><label class="ace-content-savebar__auto"><input type="checkbox" id="ace-content-auto-save"/> ' . esc_html__( 'Auto-save', 'adaptive-content-enhancer' ) . '</label><button type="button" id="ace-content-save" class="button button-primary" disabled>' . esc_html__( 'Saved', 'adaptive-content-enhancer' ) . '</button></div></div>';
	}
}
