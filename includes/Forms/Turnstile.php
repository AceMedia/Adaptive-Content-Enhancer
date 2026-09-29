<?php
/** Optional Cloudflare Turnstile provider. */
namespace ACE\AdaptiveContentEnhancer\Forms;

defined( 'ABSPATH' ) || exit;

final class Turnstile {
	public static function settings(): array {
		$value = get_option( 'ace_forms_turnstile', array() );
		return is_array( $value ) ? $value : array();
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
		return '<div id="' . esc_attr( $id ) . '" class="ace-turnstile cf-turnstile" data-sitekey="' . esc_attr( $settings['site_key'] ) . '"></div>';
	}

	public static function verify( string $token ): true|\WP_Error {
		if ( ! self::enabled() ) {
			return true;
		}
		$settings = self::settings();
		$is_development = ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) || 'production' !== wp_get_environment_type();
		if ( '1x0000000000000000000000000000000AA' === ( $settings['secret_key'] ?? '' ) && ! $is_development ) {
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
		return true;
	}
}
