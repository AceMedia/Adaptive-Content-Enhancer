<?php
/** Admin settings for spam protection. */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public static function register(): void {
		register_setting( 'ace_forms', 'ace_forms_turnstile', array( 'type' => 'object', 'default' => array(), 'sanitize_callback' => array( self::class, 'sanitise' ) ) );
	}

	public static function menu(): void {
		add_submenu_page( 'ace-forms', __( 'Form settings', 'adaptive-content-enhancer' ), __( 'Settings', 'adaptive-content-enhancer' ), Capabilities::SETTINGS, 'ace-forms-settings', array( self::class, 'render' ) );
	}

	public static function sanitise( mixed $value ): array {
		$value = is_array( $value ) ? $value : array();
		return array( 'site_key' => sanitize_text_field( $value['site_key'] ?? '' ), 'secret_key' => sanitize_text_field( $value['secret_key'] ?? '' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::SETTINGS ) ) {
			wp_die( esc_html__( 'You cannot change form settings.', 'adaptive-content-enhancer' ) );
		}
		$settings = Turnstile::settings();
		echo '<div class="wrap"><h1>' . esc_html__( 'Form settings', 'adaptive-content-enhancer' ) . '</h1><form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'ace_forms' );
		echo '<table class="form-table"><tr><th scope="row"><label for="ace-turnstile-site">' . esc_html__( 'Turnstile site key', 'adaptive-content-enhancer' ) . '</label></th><td><input class="regular-text" id="ace-turnstile-site" name="ace_forms_turnstile[site_key]" value="' . esc_attr( $settings['site_key'] ?? '' ) . '"/></td></tr><tr><th scope="row"><label for="ace-turnstile-secret">' . esc_html__( 'Turnstile secret key', 'adaptive-content-enhancer' ) . '</label></th><td><input class="regular-text" type="password" id="ace-turnstile-secret" name="ace_forms_turnstile[secret_key]" value="' . esc_attr( $settings['secret_key'] ?? '' ) . '" autocomplete="new-password"/></td></tr></table>';
		submit_button();
		echo '</form></div>';
	}
}
