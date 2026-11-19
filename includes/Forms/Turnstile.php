<?php
/** Cloudflare Turnstile verification with optional network enforcement. */
namespace ACE\AdaptiveContentEnhancer\Forms;

defined( 'ABSPATH' ) || exit;

final class Turnstile {
	public static function settings(): array {
		$value = self::local_settings();
		if ( is_multisite() ) {
			$network = \ACE\AdaptiveContentEnhancer\Admin\NetworkSettings::get()['turnstile'] ?? array();
			if ( is_array( $network ) && ! empty( $network['network_required'] ) ) {
				$value = $network;
			}
		}
		return is_array( $value ) ? $value : array();
	}

	public static function local_settings(): array {
		$value = get_option( 'ace_forms_turnstile', array() );
		return is_array( $value ) ? $value : array();
	}

	public static function required(): bool {
		$settings = self::settings();
		return ! empty( $settings['network_required'] ) || ! empty( $settings['site_key'] ) || ! empty( $settings['secret_key'] );
	}

	public static function enabled(): bool {
		$settings = self::settings();
		return ! empty( $settings['site_key'] ) && ! empty( $settings['secret_key'] );
	}

	public static function widget( string $form_id = '' ): string {
		if ( ! self::enabled() ) {
			return '';
		}
		wp_enqueue_script( 'ace-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		$settings = self::settings();
		$id = wp_unique_id( 'ace-turnstile-' . ( wp_is_uuid( $form_id ) ? $form_id . '-' : '' ) );
		return '<div id="' . esc_attr( $id ) . '" class="ace-turnstile" data-action="ace_form" data-sitekey="' . esc_attr( $settings['site_key'] ) . '"></div>';
	}

	public static function verify( string $token ): true|\WP_Error {
		if ( ! self::required() ) {
			return true;
		}
		if ( ! self::enabled() ) {
			return new \WP_Error( 'ace_turnstile_configuration', __( 'Form verification is not configured. Please contact us directly.', 'adaptive-content-enhancer' ), array( 'status' => 503 ) );
		}
		$settings = self::settings();
		$is_development = ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) || 'production' !== wp_get_environment_type();
		if ( preg_match( '/^[123]x0+AA$/', $settings['secret_key'] ?? '' ) && ! $is_development ) {
			return new \WP_Error( 'ace_turnstile_test_keys', __( 'Form verification is not configured for this site.', 'adaptive-content-enhancer' ), array( 'status' => 503 ) );
		}
		if ( '' === $token || strlen( $token ) > 2048 ) {
			return new \WP_Error( 'ace_turnstile_missing', __( 'Please complete the verification and try again.', 'adaptive-content-enhancer' ), array( 'status' => 400 ) );
		}
		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'timeout' => 8,
			'body' => array( 'secret' => $settings['secret_key'], 'response' => $token ),
		) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'ace_turnstile_unavailable', __( 'Verification is temporarily unavailable. Please try again.', 'adaptive-content-enhancer' ), array( 'status' => 503 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['success'] ) ) {
			return new \WP_Error( 'ace_turnstile_failed', __( 'Verification failed. Please try again.', 'adaptive-content-enhancer' ), array( 'status' => 400 ) );
		}
		$is_test = $is_development && preg_match( '/^[123]x0+AA$/', $settings['secret_key'] ?? '' );
		$hostname = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( ! $is_test && ( $hostname !== strtolower( (string) ( $data['hostname'] ?? '' ) ) || 'ace_form' !== ( $data['action'] ?? '' ) ) ) {
			return new \WP_Error( 'ace_turnstile_context', __( 'Verification failed. Please reload the form and try again.', 'adaptive-content-enhancer' ), array( 'status' => 400 ) );
		}
		return true;
	}
}
