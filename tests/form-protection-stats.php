<?php
/** Local integration: wp eval-file assets/plugins/adaptive-content-enhancer/tests/form-protection-stats.php */
namespace ACE\AdaptiveContentEnhancer\Forms;

if ( ! defined( 'WP_LOCAL_DEV' ) || ! WP_LOCAL_DEV || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new \RuntimeException( 'These checks require local WP-CLI.' );
}

( static function (): void {
	global $wpdb;
	$assert = static function ( bool $ok, string $label ): void {
		if ( ! $ok ) {
			throw new \RuntimeException( $label );
		}
		echo "PASS: {$label}\n";
	};
	$prefix = $wpdb->prefix;
	$fixture_prefix = $prefix . 'test_' . wp_generate_password( 10, false, false ) . '_';
	$table = $fixture_prefix . 'ace_form_protection';
	$user = get_current_user_id();
	$die = static function () { return static function () { throw new \RuntimeException( 'report access denied' ); }; };
	try {
		$wpdb->query( "CREATE TABLE {$table} LIKE {$prefix}ace_form_protection" );
		$wpdb->prefix = $fixture_prefix;
		$assert( array() === ProtectionStats::summary(), 'new report distinguishes an empty period' );
		ProtectionStats::record( 'arbitrary-private-data@example.test' );
		$assert( array() === ProtectionStats::summary(), 'arbitrary strings cannot enter reporting' );
		for ( $i = 0; $i < 15; ++$i ) {
			ProtectionStats::record( 'ace_turnstile_missing' );
		}
		$rows = ProtectionStats::summary();
		$assert( 1 === count( $rows ) && 15 === (int) $rows[0]['attempts'], 'repeated outcomes increment one daily bucket' );
		$assert( array( 'day', 'reason', 'attempts' ) === array_keys( $rows[0] ), 'stored report contains only day category and count' );
		$wpdb->insert( $table, array( 'day' => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ), 'reason' => 'verified', 'attempts' => 999 ) );
		ProtectionStats::record( 'held' );
		$assert( 2 === count( ProtectionStats::summary() ) && 2 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), 'new activity removes expired buckets and separates screening from verification' );
		$wpdb->prefix = $fixture_prefix . 'other_';
		$assert( null === ProtectionStats::summary(), 'missing site table reports unavailable rather than zero' );
		ProtectionStats::record( 'verified' );
		$wpdb->prefix = $fixture_prefix;
		$assert( 2 === count( ProtectionStats::summary() ), 'reporting failure on another site does not touch this site counters' );
		wp_set_current_user( 0 );
		add_filter( 'wp_die_handler', $die );
		foreach ( array( 'render', 'render_network' ) as $method ) {
			$denied = false;
			try { ProtectionStats::$method(); } catch ( \RuntimeException $e ) { $denied = 'report access denied' === $e->getMessage(); }
			$assert( $denied, 'unauthorised access denied: ' . $method );
		}
		remove_filter( 'wp_die_handler', $die );
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		wp_set_current_user( (int) $admins[0] );
		\ACE\AdaptiveContentEnhancer\Security\Capabilities::grant();
		ob_start(); ProtectionStats::render(); $html = ob_get_clean();
		$assert( str_contains( $html, 'Turnstile response missing' ) && str_contains( $html, 'not unique people' ), 'authorised report explains counts and renders outcomes' );
		$assert( ! str_contains( $html, 'arbitrary-private-data' ), 'rejected arbitrary data cannot appear in markup' );
		if ( is_multisite() ) {
			$super = get_user_by( 'login', get_super_admins()[0] );
			wp_set_current_user( $super->ID );
			ob_start(); ProtectionStats::render_network(); $network_html = ob_get_clean();
			foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 50, 'orderby' => 'id', 'order' => 'ASC' ) ) as $site ) {
				$assert( str_contains( $network_html, esc_html( $site->domain . $site->path ) ), 'network report includes mapped site ' . $site->blog_id );
			}
		}
	} finally {
		remove_filter( 'wp_die_handler', $die );
		$wpdb->prefix = $prefix;
		wp_set_current_user( $user );
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
} )();
