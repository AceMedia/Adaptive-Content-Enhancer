<?php
/** Run on a disposable/local WordPress: wp eval 'require ".../tests/form-spam-review.php";' */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Admin\NetworkSettings;

if ( ! defined( 'WP_LOCAL_DEV' ) || ! WP_LOCAL_DEV || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new \RuntimeException( 'These integration checks require local WP-CLI.' );
}

// Intercept only this plugin namespace. No test email reaches WordPress or SES.
function wp_mail( $to, $subject, $body, $headers = array() ): bool {
	$GLOBALS['ace_review_test_mail'][] = compact( 'to', 'subject', 'body', 'headers' );
	return empty( $GLOBALS['ace_review_test_fail_mail'] );
}

( static function (): void {
	global $wpdb;
	$assert = static function ( bool $ok, string $label ): void {
		if ( ! $ok ) {
			throw new \RuntimeException( $label );
		}
		echo "PASS: {$label}\n";
	};
	$original_user = get_current_user_id();
	$users = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( (int) $users[0] );
	\ACE\AdaptiveContentEnhancer\Security\Capabilities::grant();
	$id = wp_generate_uuid4();
	$entries = array();
	$original_network = get_site_option( NetworkSettings::OPTION, null );
	$original_compat = get_option( 'ace_fields_acf_compat', null );
	$original_settings = get_option( 'ace_forms_turnstile', null );
	$main_settings = is_multisite() ? get_blog_option( get_main_site_id(), 'ace_forms_turnstile', null ) : null;
	$original_learning = get_option( 'ace_forms_review_learning', null );
	$GLOBALS['ace_review_test_mail'] = array();
	$response = array( 'success' => true, 'hostname' => wp_parse_url( home_url(), PHP_URL_HOST ), 'action' => 'ace_form' );
	$http = static function ( $pre, $args, $url ) use ( &$response ) {
		return str_contains( $url, 'challenges.cloudflare.com/turnstile/' ) ? array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $response ) ) : $pre;
	};
	$local = static fn() => 'local';
	add_filter( 'pre_http_request', $http, 10, 3 );
	add_filter( 'ace_forms_storage_provider', $local, PHP_INT_MAX );
	$form = array( 'formId' => $id, 'name' => 'Spam review integration fixture', 'fields' => array( 'email' => array( 'type' => 'email', 'required' => true ), 'message' => array( 'type' => 'textarea', 'required' => true ) ), 'settings' => array( 'to' => 'forms@example.test', 'subject' => 'Test enquiry', 'confirmation' => array( 'enabled' => true, 'subject' => 'Thank you', 'body' => 'Received' ) ) );
	$request = static function ( string $message, bool $nojs = false ): \WP_REST_Request {
		$r = new \WP_REST_Request( 'POST' );
		$r->set_param( 'fields', array( 'email' => 'sender@example.test', 'message' => $message ) );
		$r->set_param( 'ace_started_at', time() - 10 );
		$r->set_param( 'turnstile_token', 'test-token' );
		$r->set_param( 'no_js', $nojs );
		$r->set_param( 'ace_nojs_answer', 7 );
		return $r;
	};
	try {
		update_option( 'ace_forms_review_learning', array(), false );
		$settings = array( 'site_key' => 'integration-site-key', 'secret_key' => 'integration-secret-key', 'network_required' => is_multisite() );
		if ( is_multisite() ) {
			update_site_option( NetworkSettings::OPTION, array( 'turnstile' => $settings ) );
		} else {
			update_option( 'ace_forms_turnstile', $settings );
		}
		if ( is_multisite() ) {
			$super = get_user_by( 'login', get_super_admins()[0] );
			wp_set_current_user( $super->ID );
			$assert( is_wp_error( NetworkSettings::sanitise( array( 'turnstile' => array( 'network_required' => true, 'clear_secret' => true ) ) ) ), 'network enforcement requires both keys' );
			$assert( true === NetworkSettings::update( array( 'turnstile' => $settings, 'acf_compat_default' => true ) ), 'network administrator can save shared settings' );
			$purged = array();
			$capture_purge = static function ( $blog_id ) use ( &$purged ): void { $purged[] = (int) $blog_id; };
			add_action( 'ace_forms_protection_changed', $capture_purge );
			$changed = $settings;
			$changed['site_key'] = 'integration-replacement-site-key';
			NetworkSettings::update( array( 'turnstile' => $changed, 'acf_compat_default' => true ) );
			$expected = array_map( 'intval', get_sites( array( 'number' => 0, 'fields' => 'ids', 'network_id' => get_current_network_id() ) ) );
			sort( $purged ); sort( $expected );
			$assert( $purged === $expected, 'changing network keys invalidates forms on every site' );
			remove_action( 'ace_forms_protection_changed', $capture_purge );
			NetworkSettings::update( array( 'turnstile' => $settings, 'acf_compat_default' => true ) );
			$blank_secret = $settings;
			$blank_secret['secret_key'] = '';
			$assert( true === NetworkSettings::update( array( 'turnstile' => $blank_secret, 'acf_compat_default' => true ) ) && $settings['secret_key'] === NetworkSettings::get()['turnstile']['secret_key'], 'blank secret preserves stored network key' );
			update_option( 'ace_fields_acf_compat', false );
			$assert( false === (bool) get_option( 'ace_fields_acf_compat' ), 'saved site compatibility choice overrides network default' );
			delete_option( 'ace_fields_acf_compat' );
			$assert( true === (bool) get_option( 'ace_fields_acf_compat' ), 'network compatibility default applies when site has no preference' );
			ob_start();
			NetworkSettings::render();
			$network_markup = ob_get_clean();
			$assert( str_contains( $network_markup, 'Ace Content network settings' ) && ! str_contains( $network_markup, $settings['secret_key'] ), 'network screen renders without exposing stored secret' );
			wp_set_current_user( 0 );
			$assert( is_wp_error( NetworkSettings::update( array() ) ), 'non-network administrator cannot save shared settings' );
			wp_set_current_user( (int) $users[0] );
		}
		$wpdb->insert( $wpdb->prefix . 'ace_forms', array( 'form_id' => $id, 'source_post_id' => 0, 'name' => $form['name'], 'definition' => wp_json_encode( $form ), 'definition_hash' => sha1( wp_json_encode( $form ) ), 'status' => 'active', 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ) );
		$assert( true === Turnstile::verify( 'test-token' ), 'correct hostname and action accepted' );
		$assert( is_wp_error( Turnstile::verify( '' ) ), 'missing token rejected' );
		$response['hostname'] = 'other.example.test';
		$assert( is_wp_error( Turnstile::verify( 'test-token' ) ), 'wrong hostname rejected' );
		$response['hostname'] = wp_parse_url( home_url(), PHP_URL_HOST );
		$response['action'] = 'other_action';
		$assert( is_wp_error( Turnstile::verify( 'test-token' ) ), 'wrong action rejected' );
		$response['action'] = 'ace_form';
		$response['success'] = false;
		$assert( is_wp_error( Turnstile::verify( 'expired-token' ) ), 'failed or expired verification rejected' );
		$response['success'] = true;
		$unavailable = static fn() => new \WP_Error( 'http_error', 'Mock outage' );
		add_filter( 'pre_http_request', $unavailable, 20 );
		$assert( is_wp_error( Turnstile::verify( 'test-token' ) ), 'verification outage fails closed' );
		remove_filter( 'pre_http_request', $unavailable, 20 );
		$incomplete = array( 'network_required' => true, 'site_key' => 'integration-site-key' );
		if ( is_multisite() ) {
			update_site_option( NetworkSettings::OPTION, array( 'turnstile' => $incomplete ) );
		} else {
			update_option( 'ace_forms_turnstile', $incomplete );
		}
		$assert( is_wp_error( Turnstile::verify( 'test-token' ) ), 'incomplete required configuration fails closed' );
		if ( is_multisite() ) {
			update_site_option( NetworkSettings::OPTION, array( 'turnstile' => $settings ) );
		} else {
			update_option( 'ace_forms_turnstile', $settings );
		}
		if ( is_multisite() ) {
			foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
				switch_to_blog( $site->blog_id );
				$assert( Turnstile::enabled() && Turnstile::required(), 'network protection inherited by blog ' . $site->blog_id );
				restore_current_blog();
			}
		}
		$r = $request( 'A genuine bin enquiry', true );
		$r->set_param( 'turnstile_token', '' );
		$assert( is_wp_error( Submission::process( $id, $r ) ), 'admin-post no-JavaScript path cannot bypass Turnstile' );
		$r->set_param( 'form_id', $id );
		$assert( is_wp_error( Submission::rest_submit( $r ) ) && false === $r->get_param( 'no_js' ), 'REST callers cannot select no-JavaScript fallback' );
		$spam = 'Dear sir, we offer SEO and backlinks to boost your sales with a free audit of your website.';
		$result = Submission::process( $id, $request( $spam ) );
		$assert( is_array( $result ), 'suspect submission saved successfully' );
		ob_start();
		Review::render();
		$markup = ob_get_clean();
		$assert( str_contains( $markup, 'Similar messages:' ) && str_contains( $markup, 'Spam review' ), 'review screen renders grouped held entries' );
		$entry = (int) $result['entryId'];
		$entries[] = $entry;
		$assert( ! $result['mailSent'] && ! $GLOBALS['ace_review_test_mail'], 'suspect sends neither notification nor confirmation' );
		$assert( 'suspect' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}ace_form_entries WHERE id=%d", $entry ) ), 'suspect retained for review' );
		wp_set_current_user( 0 );
		$assert( is_wp_error( Review::decide( $entry, 'spam' ) ), 'unauthorised review rejected' );
		wp_set_current_user( (int) $users[0] );
		$assert( true === Review::decide( $entry, 'spam' ) && ! $GLOBALS['ace_review_test_mail'], 'confirm spam retains entry without sending' );
		$learned = Screen::assess( $form, array( 'message' => $spam ), '', '' );
		$assert( str_contains( implode( ' ', $learned['reasons'] ), 'explicitly reviewed as spam' ), 'explicit spam decision teaches screening' );
		$assert( ! Review::signature( $form, array( 'message' => 'Please quote' ) ), 'short genuine enquiries never become learned rules' );
		$assert( Review::similar( Review::signature( $form, array( 'message' => $spam . ' https://one.example' ) ), Review::signature( $form, array( 'message' => $spam . ' https://two.example' ) ) ), 'similar campaigns with changed URLs group together' );
		$result = Submission::process( $id, $request( $spam ) );
		$entries[] = (int) $result['entryId'];
		$release = (int) $result['entryId'];
		$GLOBALS['ace_review_test_fail_mail'] = true;
		$assert( is_wp_error( Review::decide( $release, 'clean' ) ), 'delivery failure keeps entry held for retry' );
		$GLOBALS['ace_review_test_fail_mail'] = false;
		$GLOBALS['ace_review_test_mail'] = array();
		$assert( true === Review::decide( $release, 'clean' ) && 1 === count( $GLOBALS['ace_review_test_mail'] ), 'approve sends one held notification' );
		$assert( is_wp_error( Review::decide( $release, 'clean' ) ) && 1 === count( $GLOBALS['ace_review_test_mail'] ), 'repeat approval cannot resend' );
		$learned = Screen::assess( $form, array( 'message' => $spam ), '', '' );
		$assert( 'clean' === $learned['verdict'], 'genuine correction supersedes learned spam' );
		$GLOBALS['ace_review_test_mail'] = array();
		$result = Submission::process( $id, $request( 'Please quote for ten 1100 litre bins delivered to Sheffield.' ) );
		$assert( is_array( $result ), 'genuine submission accepted' );
		$entries[] = (int) $result['entryId'];
		$assert( $result['mailSent'] && 2 === count( $GLOBALS['ace_review_test_mail'] ), 'clean enquiry sends notification and enabled confirmation' );
		update_option( 'ace_forms_review_learning', array(), false );
		$result = Submission::process( $id, $request( $spam ) );
		$legacy_id = (int) $result['entryId'];
		$entries[] = $legacy_id;
		$json = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}ace_form_entry_meta WHERE entry_id=%d AND meta_key='mail_log' ORDER BY id DESC LIMIT 1", $legacy_id ) );
		$legacy_log = json_decode( $json, true );
		unset( $legacy_log['held'] );
		$legacy_log['sent'] = true;
		$wpdb->insert( $wpdb->prefix . 'ace_form_entry_meta', array( 'entry_id' => $legacy_id, 'meta_key' => 'mail_log', 'meta_value' => wp_json_encode( $legacy_log ) ) );
		$GLOBALS['ace_review_test_mail'] = array();
		$assert( true === Review::decide( $legacy_id, 'clean' ) && ! $GLOBALS['ace_review_test_mail'], 'historical already-sent entry can teach a genuine decision without resending' );
	} finally {
		foreach ( $entries as $entry ) {
			$wpdb->delete( $wpdb->prefix . 'ace_form_entry_meta', array( 'entry_id' => $entry ) );
			$wpdb->delete( $wpdb->prefix . 'ace_form_entries', array( 'id' => $entry ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ace_forms', array( 'form_id' => $id ) );
		wp_cache_delete( $id, 'ace_forms' );
		foreach ( array( 'ace_form_rate_', 'ace_form_rate_nojs_' ) as $prefix ) {
			$ip_hash = hash_hmac( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ), wp_salt( 'auth' ) );
			delete_transient( $prefix . md5( $id . $ip_hash ) );
		}
		null === $original_settings ? delete_option( 'ace_forms_turnstile' ) : update_option( 'ace_forms_turnstile', $original_settings );
		if ( is_multisite() ) {
			null === $main_settings ? delete_blog_option( get_main_site_id(), 'ace_forms_turnstile' ) : update_blog_option( get_main_site_id(), 'ace_forms_turnstile', $main_settings );
		}
		null === $original_network ? delete_site_option( NetworkSettings::OPTION ) : update_site_option( NetworkSettings::OPTION, $original_network );
		null === $original_compat ? delete_option( 'ace_fields_acf_compat' ) : update_option( 'ace_fields_acf_compat', $original_compat );
		null === $original_learning ? delete_option( 'ace_forms_review_learning' ) : update_option( 'ace_forms_review_learning', $original_learning, false );
		wp_set_current_user( $original_user );
		remove_filter( 'pre_http_request', $http );
		remove_filter( 'ace_forms_storage_provider', $local, PHP_INT_MAX );
		unset( $GLOBALS['ace_review_test_fail_mail'], $GLOBALS['ace_review_test_mail'] );
	}
} )();
