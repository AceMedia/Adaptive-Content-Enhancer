<?php
/** Network-wide plugin defaults and enforced form protection. */
namespace ACE\AdaptiveContentEnhancer\Admin;

use ACE\AdaptiveContentEnhancer\Forms\Turnstile;

defined( 'ABSPATH' ) || exit;

final class NetworkSettings {
	public const OPTION = 'ace_content_network_settings';

	public static function get(): array {
		if ( ! is_multisite() ) {
			return array();
		}
		$value = get_site_option( self::OPTION, null );
		if ( is_array( $value ) ) {
			return $value;
		}
		// Preserve networks already using the main site's earlier enforcement setting.
		$legacy = get_blog_option( get_main_site_id(), 'ace_forms_turnstile', array() );
		return array( 'turnstile' => is_array( $legacy ) && ! empty( $legacy['network_required'] ) ? $legacy : array(), 'acf_compat_default' => false );
	}

	public static function acf_default( mixed $default ): mixed {
		$settings = self::get();
		return array_key_exists( 'acf_compat_default', $settings ) ? (bool) $settings['acf_compat_default'] : $default;
	}

	public static function menu(): void {
		add_menu_page( __( 'Ace Content network settings', 'adaptive-content-enhancer' ), __( 'Ace Content', 'adaptive-content-enhancer' ), 'manage_network_options', 'ace-content-network', array( self::class, 'render' ), 'dashicons-feedback' );
	}

	public static function sanitise( array $input ): array|\WP_Error {
		$existing = self::get();
		$previous = is_array( $existing['turnstile'] ?? null ) ? $existing['turnstile'] : array();
		$raw = is_array( $input['turnstile'] ?? null ) ? $input['turnstile'] : array();
		$secret = sanitize_text_field( is_scalar( $raw['secret_key'] ?? null ) ? (string) $raw['secret_key'] : '' );
		if ( '' === $secret ) {
			$secret = (string) ( $previous['secret_key'] ?? '' );
		}
		if ( ! empty( $raw['clear_secret'] ) ) {
			$secret = '';
		}
		$turnstile = array( 'network_required' => ! empty( $raw['network_required'] ), 'site_key' => sanitize_text_field( is_scalar( $raw['site_key'] ?? null ) ? (string) $raw['site_key'] : '' ), 'secret_key' => $secret );
		if ( $turnstile['network_required'] && ( ! $turnstile['site_key'] || ! $secret ) ) {
			return new \WP_Error( 'ace_network_turnstile_keys', __( 'Enter both production keys before requiring Turnstile across the network.', 'adaptive-content-enhancer' ) );
		}
		$development = ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) || 'production' !== wp_get_environment_type();
		if ( $turnstile['network_required'] && ! $development && preg_match( '/^[123]x0+AA$/', $secret ) ) {
			return new \WP_Error( 'ace_network_turnstile_test', __( 'Cloudflare test keys cannot protect a production network.', 'adaptive-content-enhancer' ) );
		}
		return array( 'turnstile' => $turnstile, 'acf_compat_default' => ! empty( $input['acf_compat_default'] ) );
	}

	public static function update( array $input ): true|\WP_Error {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			return new \WP_Error( 'ace_network_forbidden', __( 'You cannot change network settings.', 'adaptive-content-enhancer' ) );
		}
		$value = self::sanitise( $input );
		if ( is_wp_error( $value ) ) {
			return $value;
		}
		$before = self::get();
		update_site_option( self::OPTION, $value );
		if ( ( $before['turnstile'] ?? array() ) !== $value['turnstile'] ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0, 'network_id' => get_current_network_id() ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					self::purge_site_cache();
				} finally {
					restore_current_blog();
				}
			}
		}
		return true;
	}

	/** Refresh rendered widgets when protection changes, scoped to the current site's host. */
	public static function purge_site_cache(): void {
		if ( function_exists( 'ace_redis_cache' ) ) {
			$cache = ace_redis_cache();
			$manager = $cache && method_exists( $cache, 'get_cache_manager' ) ? $cache->get_cache_manager() : null;
			if ( $manager && method_exists( $manager, 'flush_all_cache' ) ) {
				$manager->flush_all_cache();
				// Invalidate rendered block generations and the site's Varnish bridge too.
				do_action( 'ace_rc_cache_cleared', 'ace-forms-protection' );
			}
		}
		do_action( 'ace_forms_protection_changed', get_current_blog_id() );
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You cannot change network settings.', 'adaptive-content-enhancer' ), '', 403 );
		}
		check_admin_referer( 'ace_content_network_settings' );
		$input = isset( $_POST['ace_content_network'] ) && is_array( $_POST['ace_content_network'] ) ? wp_unslash( $_POST['ace_content_network'] ) : array();
		$result = self::update( $input );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', 400, array( 'back_link' => true ) );
		}
		wp_safe_redirect( network_admin_url( 'admin.php?page=ace-content-network&updated=1' ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You cannot view network settings.', 'adaptive-content-enhancer' ), '', 403 );
		}
		$settings = self::get();
		$turnstile = is_array( $settings['turnstile'] ?? null ) ? $settings['turnstile'] : array();
		echo '<div class="wrap"><h1>' . esc_html__( 'Ace Content network settings', 'adaptive-content-enhancer' ) . '</h1><p>' . esc_html__( 'Manage shared form protection and plugin defaults for every site in this network. Forms, recipients, entries and spam review remain within each site.', 'adaptive-content-enhancer' ) . '</p>';
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Network settings saved.', 'adaptive-content-enhancer' ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( network_admin_url( 'edit.php?action=ace_content_network_settings' ) ) . '">';
		wp_nonce_field( 'ace_content_network_settings' );
		echo '<h2>' . esc_html__( 'Form protection', 'adaptive-content-enhancer' ) . '</h2><p>' . esc_html__( 'Configure one Cloudflare Turnstile widget that allows all mapped hostnames listed below. Enforced network keys override site keys, including on new subsites. Protected forms require JavaScript.', 'adaptive-content-enhancer' ) . '</p><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="ace-network-site-key">' . esc_html__( 'Turnstile site key', 'adaptive-content-enhancer' ) . '</label></th><td><input class="regular-text" id="ace-network-site-key" name="ace_content_network[turnstile][site_key]" value="' . esc_attr( $turnstile['site_key'] ?? '' ) . '" autocomplete="off"/></td></tr>';
		echo '<tr><th scope="row"><label for="ace-network-secret-key">' . esc_html__( 'Turnstile secret key', 'adaptive-content-enhancer' ) . '</label></th><td><input class="regular-text" type="password" id="ace-network-secret-key" name="ace_content_network[turnstile][secret_key]" value="" autocomplete="new-password"/><p class="description">' . esc_html( ! empty( $turnstile['secret_key'] ) ? __( 'A secret is stored. Leave blank to keep it, or enter a replacement.', 'adaptive-content-enhancer' ) : __( 'No network secret is stored.', 'adaptive-content-enhancer' ) ) . '</p><label><input type="checkbox" name="ace_content_network[turnstile][clear_secret]" value="1"/> ' . esc_html__( 'Remove the stored secret (disable network enforcement first)', 'adaptive-content-enhancer' ) . '</label></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Network protection', 'adaptive-content-enhancer' ) . '</th><td><label><input type="checkbox" name="ace_content_network[turnstile][network_required]" value="1"' . checked( ! empty( $turnstile['network_required'] ), true, false ) . '/> ' . esc_html__( 'Require Turnstile on every site in this network', 'adaptive-content-enhancer' ) . '</label></td></tr></tbody></table>';
		echo '<h2>' . esc_html__( 'Field compatibility defaults', 'adaptive-content-enhancer' ) . '</h2><p><label><input type="checkbox" name="ace_content_network[acf_compat_default]" value="1"' . checked( ! empty( $settings['acf_compat_default'] ), true, false ) . '/> ' . esc_html__( 'Enable basic ACF function compatibility where a site has no saved preference', 'adaptive-content-enhancer' ) . '</label></p><p class="description">' . esc_html__( 'Existing site choices take precedence. This does not import fields or replace ACF when ACF is active. Review each site before changing this default.', 'adaptive-content-enhancer' ) . '</p>';
		submit_button( __( 'Save network settings', 'adaptive-content-enhancer' ) );
		echo '</form><h2>' . esc_html__( 'Network sites and protection', 'adaptive-content-enhancer' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Hostname', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Form protection', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Manage', 'adaptive-content-enhancer' ) . '</th></tr></thead><tbody>';
		foreach ( get_sites( array( 'number' => 100, 'network_id' => get_current_network_id() ) ) as $site ) {
			$local = get_blog_option( $site->blog_id, 'ace_forms_turnstile', array() );
			$protected = ! empty( $turnstile['network_required'] );
			$status = $protected ? __( 'Network Turnstile required', 'adaptive-content-enhancer' ) : ( ! empty( $local['site_key'] ) && ! empty( $local['secret_key'] ) ? __( 'Site Turnstile configured', 'adaptive-content-enhancer' ) : __( 'Turnstile not configured', 'adaptive-content-enhancer' ) );
			echo '<tr><td>' . esc_html( $site->domain . $site->path ) . '</td><td>' . esc_html( $status ) . '</td><td><a href="' . esc_url( get_admin_url( $site->blog_id, 'admin.php?page=ace-forms-settings' ) ) . '">' . esc_html__( 'Site settings', 'adaptive-content-enhancer' ) . '</a> · <a href="' . esc_url( get_admin_url( $site->blog_id, 'admin.php?page=ace-forms-review' ) ) . '">' . esc_html__( 'Spam review', 'adaptive-content-enhancer' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
