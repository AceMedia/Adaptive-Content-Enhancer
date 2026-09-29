<?php
/** Public form submission and notification pipeline. */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\FieldTypes\Registry;

defined( 'ABSPATH' ) || exit;

final class Submission {
	public static function register_routes(): void {
		register_rest_route(
			'ace-forms/v1',
			'/forms/(?P<form_id>[0-9a-f-]{36})/entries',
			array(
				'methods' => 'POST',
				'callback' => array( self::class, 'rest_submit' ),
				// Public by design: visitors submit forms without accounts. The pipeline applies spam checks and rate limits.
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function rest_submit( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// The no-JavaScript fallback is only available through admin-post.php.
		// A public REST caller must not be able to skip Turnstile by setting no_js.
		$request->set_param( 'no_js', false );
		$result = self::process( (string) $request['form_id'], $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( $result, 201 );
	}

	public static function no_js_submit(): void {
		$id = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'ace_hp', isset( $_POST['ace_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['ace_hp'] ) ) : '' );
		$request->set_param( 'ace_started_at', isset( $_POST['ace_started_at'] ) ? absint( $_POST['ace_started_at'] ) : 0 );
		$request->set_param( 'page_url', wp_get_referer() ?: home_url() );
		$request->set_param( 'turnstile_token', isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '' );
		$request->set_param( 'no_js', true );
		$request->set_param( 'ace_nojs_answer', isset( $_POST['ace_nojs_answer'] ) ? absint( $_POST['ace_nojs_answer'] ) : 0 );
		$fields = wp_unslash( $_POST );
		foreach ( array( 'action', 'form_id', 'ace_hp', 'ace_started_at', 'cf-turnstile-response', 'ace_nojs_answer' ) as $internal ) {
			unset( $fields[ $internal ] );
		}
		$request->set_param( 'fields', $fields );
		$request->set_file_params( $_FILES );
		$result = self::process( $id, $request );
		$url = wp_get_referer() ?: home_url();
		$url = remove_query_arg( array( 'ace_form_status', 'ace_form_id' ), $url );
		wp_safe_redirect( add_query_arg( array( 'ace_form_status' => is_wp_error( $result ) ? 'error' : 'success', 'ace_form_id' => $id ), $url ) );
		exit;
	}

	public static function process( string $id, \WP_REST_Request $request ): array|\WP_Error {
		$form = FormRegistry::get( $id );
		if ( null === $form ) {
			return new \WP_Error( 'ace_form_not_found', __( 'This form is unavailable.', 'adaptive-content-enhancer' ), array( 'status' => 404 ) );
		}
		if ( '' !== (string) $request->get_param( 'ace_hp' ) ) {
			return new \WP_Error( 'ace_form_spam', __( 'Your enquiry could not be accepted.', 'adaptive-content-enhancer' ), array( 'status' => 400 ) );
		}
		$no_js = true === $request->get_param( 'no_js' );
		if ( $no_js && 7 !== absint( $request->get_param( 'ace_nojs_answer' ) ) ) {
			return new \WP_Error( 'ace_form_no_js_challenge', __( 'Please answer the verification question.', 'adaptive-content-enhancer' ), array( 'status' => 400 ) );
		}
		$started = absint( $request->get_param( 'ace_started_at' ) );
		if ( ! $started || $started > time() || time() - $started < ( $no_js ? 5 : 2 ) ) {
			return new \WP_Error( 'ace_form_timing', __( 'Please wait a moment and try again.', 'adaptive-content-enhancer' ), array( 'status' => 400 ) );
		}
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$ip_hash = hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
		$rate_key = ( $no_js ? 'ace_form_rate_nojs_' : 'ace_form_rate_' ) . md5( $id . $ip_hash );
		$count = (int) get_transient( $rate_key );
		if ( $count >= ( $no_js ? 3 : 12 ) ) {
			return new \WP_Error( 'ace_form_rate_limit', __( 'Too many attempts. Please try again later.', 'adaptive-content-enhancer' ), array( 'status' => 429 ) );
		}
		set_transient( $rate_key, $count + 1, 10 * MINUTE_IN_SECONDS );
		$turnstile = $no_js ? true : Turnstile::verify( (string) $request->get_param( 'turnstile_token' ) );
		if ( is_wp_error( $turnstile ) ) {
			return $turnstile;
		}

		$submitted = $request->get_param( 'fields' );
		if ( is_string( $submitted ) ) {
			$submitted = json_decode( $submitted, true );
		}
		if ( ! is_array( $submitted ) ) {
			return new \WP_Error( 'ace_form_fields', __( 'Please complete the form.', 'adaptive-content-enhancer' ), array( 'status' => 422 ) );
		}
		$values = array();
		$errors = array();
		$file_records = array();
		$uploads = $request->get_file_params();
		foreach ( $form['fields'] as $key => $field ) {
			if ( ! Conditions::visible( $field['conditions'] ?? array(), $submitted ) ) {
				continue;
			}
			$type = Registry::instance()->get( $field['type'] );
			if ( ! $type ) {
				continue;
			}
			if ( 'file' === $field['type'] ) {
				$upload = $uploads[ 'ace_file_' . $key ] ?? $uploads[ $key ] ?? null;
				if ( ! is_array( $upload ) || UPLOAD_ERR_NO_FILE === (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
					if ( ! empty( $field['required'] ) ) {
						$errors[ $key ] = __( 'Please choose a file.', 'adaptive-content-enhancer' );
					}
					$values[ $key ] = '';
					continue;
				}
				$stored = Files::store( $upload, $field );
				if ( is_wp_error( $stored ) ) {
					$errors[ $key ] = $stored->get_error_message();
				} else {
					$values[ $key ] = $stored['name'];
					$file_records[] = $stored;
				}
				continue;
			}
			$raw = $submitted[ $key ] ?? '';
			if ( 'consent' === $field['type'] && ! empty( $field['required'] ) && empty( $raw ) ) {
				$errors[ $key ] = __( 'Please give your consent.', 'adaptive-content-enhancer' );
				continue;
			}
			$value = $type->sanitise( $raw, $field );
			$valid = $type->validate( $value, $field );
			if ( is_wp_error( $valid ) ) {
				$errors[ $key ] = $valid->get_error_message();
			} else {
				$values[ $key ] = $value;
			}
		}
		if ( $errors ) {
			foreach ( $file_records as $file ) {
				wp_delete_file( $file['path'] );
			}
			return new \WP_Error( 'ace_form_validation', __( 'Please check your answers.', 'adaptive-content-enhancer' ), array( 'status' => 422, 'fields' => $errors ) );
		}

		$settings = $form['settings'];
		$page_url = esc_url_raw( (string) $request->get_param( 'page_url' ) );
		$page_id = $page_url ? url_to_postid( $page_url ) : 0;
		$contact_email = '';
		$contact_name = '';
		$first_name = '';
		$last_name = '';
		foreach ( $form['fields'] as $key => $field ) {
			if ( 'email' === $field['type'] && '' === $contact_email ) {
				$contact_email = sanitize_email( $values[ $key ] ?? '' );
			}
			if ( in_array( $key, array( 'name', 'your-name', 'your_name' ), true ) ) {
				$contact_name = sanitize_text_field( $values[ $key ] ?? '' );
			}
			if ( in_array( $key, array( 'first_name', 'first-name' ), true ) ) {
				$first_name = sanitize_text_field( $values[ $key ] ?? '' );
			}
			if ( in_array( $key, array( 'last_name', 'last-name' ), true ) ) {
				$last_name = sanitize_text_field( $values[ $key ] ?? '' );
			}
		}
		if ( '' === $contact_name ) {
			$contact_name = trim( $first_name . ' ' . $last_name );
		}
		$to = self::addresses( $settings['to'] ?? '' );
		$subject = self::merge( $settings['subject'] ?? __( 'New enquiry', 'adaptive-content-enhancer' ), $values, $page_id, $page_url );
		$body = self::merge( $settings['body'] ?? self::default_body( $values ), $values, $page_id, $page_url );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$from = self::mailbox_header( self::merge( (string) ( $settings['from'] ?? '' ), $values, $page_id, $page_url ) );
		if ( $from ) {
			$headers[] = 'From: ' . $from;
		}
		foreach ( self::addresses( self::merge( (string) ( $settings['cc'] ?? '' ), $values, $page_id, $page_url ) ) as $address ) {
			$headers[] = 'Cc: ' . $address;
		}
		foreach ( self::addresses( self::merge( (string) ( $settings['bcc'] ?? '' ), $values, $page_id, $page_url ) ) as $address ) {
			$headers[] = 'Bcc: ' . $address;
		}
		$reply_to = self::mailbox_header( self::merge( (string) ( $settings['replyTo'] ?? '' ), $values, $page_id, $page_url ) );
		if ( ! $reply_to && $contact_email ) {
			$reply_to = self::mailbox_header( $contact_name ? $contact_name . ' <' . $contact_email . '>' : $contact_email );
		}
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		$details = array( 'files' => $file_records );
		$config = array( 'formId' => $id, 'formName' => $form['name'], 'recipient' => $to[0] ?? '', 'subject' => $subject, 'successMessage' => $settings['successMessage'] ?? '', 'errorMessage' => $settings['errorMessage'] ?? '' );
		$provider = apply_filters( 'ace_forms_storage_provider', 'local' );
		$entry_id = 0;
		if ( 'customer-engagement' === $provider ) {
			$entry_id = (int) apply_filters( 'ace_forms_store_submission', 0, $values, $config, $request, false, $details );
		}
		if ( $entry_id < 1 ) {
			$provider = 'local';
			$entry_id = self::store_local( $id, $page_id, $page_url, $values, $contact_email, $contact_name, $ip_hash, $details );
		}
		if ( $entry_id < 1 ) {
			foreach ( $file_records as $file ) {
				wp_delete_file( $file['path'] );
			}
			return new \WP_Error( 'ace_form_storage', __( 'Your enquiry could not be saved.', 'adaptive-content-enhancer' ), array( 'status' => 500 ) );
		}
		$config['entryId'] = $entry_id;
		$config['storage_provider'] = $provider;
		$config['already_stored'] = true;
		do_action( 'ace_forms_entry_created', $entry_id, $form, $values, array( 'page_url' => $page_url, 'storage_provider' => $provider ) );
		$sent = $to ? wp_mail( $to, $subject, $body, $headers ) : false;
		$details['notification'] = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers, 'sent' => $sent, 'date' => current_time( 'mysql', true ) );
		$confirmation = is_array( $settings['confirmation'] ?? null ) ? $settings['confirmation'] : array();
		if ( ! empty( $confirmation['enabled'] ) && $contact_email && is_email( $contact_email ) ) {
			$confirmation_subject = self::merge( (string) ( $confirmation['subject'] ?? __( 'We received your enquiry', 'adaptive-content-enhancer' ) ), $values, $page_id, $page_url );
			$confirmation_body = self::merge( (string) ( $confirmation['body'] ?? '' ), $values, $page_id, $page_url );
			$confirmation_headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			$confirmation_from = self::mailbox_header( self::merge( (string) ( $confirmation['from'] ?? '' ), $values, $page_id, $page_url ) );
			if ( $confirmation_from ) {
				$confirmation_headers[] = 'From: ' . $confirmation_from;
			}
			$confirmation_sent = wp_mail( $contact_email, $confirmation_subject, $confirmation_body, $confirmation_headers );
			$details['confirmation'] = array( 'to' => $contact_email, 'subject' => $confirmation_subject, 'body' => $confirmation_body, 'headers' => $confirmation_headers, 'sent' => $confirmation_sent, 'date' => current_time( 'mysql', true ) );
		}
		if ( 'customer-engagement' === $provider ) {
			apply_filters( 'ace_forms_update_submission', false, $entry_id, $details, $sent );
		} else {
			global $wpdb;
			foreach ( array( 'notification' => 'mail_log', 'confirmation' => 'confirmation_log' ) as $key => $meta_key ) {
				if ( isset( $details[ $key ] ) ) {
					$wpdb->insert( $wpdb->prefix . 'ace_form_entry_meta', array( 'entry_id' => $entry_id, 'meta_key' => $meta_key, 'meta_value' => wp_json_encode( $details[ $key ] ) ) );
				}
			}
		}
		do_action( 'ace_form_submitted', $values, $config, $request, $sent );
		return array( 'success' => true, 'entryId' => $entry_id, 'storageProvider' => $provider, 'formName' => $form['name'], 'message' => sanitize_text_field( $settings['successMessage'] ?? __( 'Thank you. Your enquiry has been received.', 'adaptive-content-enhancer' ) ), 'mailSent' => $sent );
	}

	/** Keep a complete entry when Customer Engagement is unavailable. */
	private static function store_local( string $id, int $page_id, string $page_url, array $values, string $contact_email, string $contact_name, string $ip_hash, array $details ): int {
		global $wpdb;
		$inserted = $wpdb->insert( $wpdb->prefix . 'ace_form_entries', array(
			'form_id' => $id,
			'source_post_id' => $page_id,
			'status' => 'new',
			'data' => wp_json_encode( $values ),
			'search_text' => implode( ' ', array_map( 'strval', array_filter( $values, 'is_scalar' ) ) ),
			'contact_email' => $contact_email,
			'contact_name' => $contact_name,
			'user_id' => get_current_user_id(),
			'ip_hash' => $ip_hash,
			'user_agent' => sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ) ),
			'context' => wp_json_encode( array( 'page_url' => $page_url, 'storage_provider' => 'local' ) ),
			'created_at' => current_time( 'mysql', true ),
		) );
		if ( ! $inserted ) {
			return 0;
		}
		$entry_id = (int) $wpdb->insert_id;
		foreach ( $details['files'] ?? array() as $file ) {
			$wpdb->insert( $wpdb->prefix . 'ace_form_entry_meta', array( 'entry_id' => $entry_id, 'meta_key' => 'file_' . $file['id'], 'meta_value' => wp_json_encode( $file ) ) );
		}
		return $entry_id;
	}

	private static function addresses( string $input ): array {
		return array_values( array_filter( array_map( 'sanitize_email', preg_split( '/[,;\n]+/', $input ) ?: array() ), 'is_email' ) );
	}

	private static function mailbox_header( string $input ): string {
		$input = trim( $input );
		if ( preg_match( '/^(.*?)<([^<>]+)>$/', $input, $parts ) ) {
			$name = trim( preg_replace( '/[<>,":;\\\\]/', '', sanitize_text_field( $parts[1] ) ) );
			$email = sanitize_email( trim( $parts[2] ) );
			return is_email( $email ) ? ( $name ? $name . ' <' . $email . '>' : $email ) : '';
		}
		$email = sanitize_email( $input );
		return is_email( $email ) ? $email : '';
	}

	private static function merge( string $template, array $values, int $page_id, string $page_url ): string {
		$tags = array( 'post_title' => $page_id ? get_the_title( $page_id ) : '', 'page_url' => $page_url, 'site_name' => get_bloginfo( 'name' ), 'site_url' => home_url( '/' ), 'site_domain' => wp_parse_url( home_url(), PHP_URL_HOST ), 'network_domain' => is_multisite() ? get_network()->domain : wp_parse_url( home_url(), PHP_URL_HOST ), 'date' => wp_date( 'j F Y' ) );
		return preg_replace_callback( '/\{(?:field:)?([a-zA-Z0-9_-]+)\}/', static function ( array $match ) use ( $values, $tags ): string {
			$value = $values[ $match[1] ] ?? $tags[ $match[1] ] ?? '';
			return is_scalar( $value ) ? (string) $value : implode( ', ', (array) $value );
		}, $template );
	}

	private static function default_body( array $values ): string {
		$lines = array();
		foreach ( $values as $key => $value ) {
			$lines[] = $key . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : (string) $value );
		}
		return implode( "\n", $lines );
	}
}
