<?php
/** Bounded, anonymous counts of form protection outcomes, isolated by site. */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class ProtectionStats {
	/** Only fixed categories are stored. Never accept arbitrary errors or request data. */
	public static function labels(): array {
		return array(
			'honeypot' => __( 'Honeypot filled', 'adaptive-content-enhancer' ),
			'fallback_failed' => __( 'Fallback verification failed', 'adaptive-content-enhancer' ),
			'too_fast' => __( 'Submitted too quickly', 'adaptive-content-enhancer' ),
			'rate_limited' => __( 'Too many attempts', 'adaptive-content-enhancer' ),
			'ace_turnstile_missing' => __( 'Turnstile response missing or oversized', 'adaptive-content-enhancer' ),
			'ace_turnstile_failed' => __( 'Turnstile response rejected or expired', 'adaptive-content-enhancer' ),
			'ace_turnstile_context' => __( 'Turnstile hostname or action mismatch', 'adaptive-content-enhancer' ),
			'ace_turnstile_unavailable' => __( 'Verification service unavailable', 'adaptive-content-enhancer' ),
			'ace_turnstile_configuration' => __( 'Verification configuration incomplete', 'adaptive-content-enhancer' ),
			'ace_turnstile_test_keys' => __( 'Test keys rejected on production', 'adaptive-content-enhancer' ),
			'verified' => __( 'Turnstile passed', 'adaptive-content-enhancer' ),
			'unprotected' => __( 'Turnstile not required', 'adaptive-content-enhancer' ),
			'held' => __( 'Content held for review', 'adaptive-content-enhancer' ),
			'screened_clean' => __( 'Content passed screening', 'adaptive-content-enhancer' ),
		);
	}

	public static function record( string $reason ): void {
		if ( ! isset( self::labels()[ $reason ] ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'ace_form_protection';
		// Atomic increments do not lose parallel requests. Logging cannot change acceptance.
		$previous = $wpdb->suppress_errors( true );
		try {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (day, reason, attempts) VALUES (%s, %s, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1", gmdate( 'Y-m-d' ), $reason ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE day < %s", gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ) ) );
		} finally {
			$wpdb->suppress_errors( $previous );
		}
	}

	/** Null means reporting is unavailable, rather than an assertion of zero traffic. */
	public static function summary(): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'ace_form_protection';
		$previous = $wpdb->suppress_errors( true );
		try {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT day, reason, attempts FROM {$table} WHERE day >= %s AND day <= %s ORDER BY day DESC, reason", gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), gmdate( 'Y-m-d' ) ), ARRAY_A );
			return $wpdb->last_error ? null : $rows;
		} finally {
			$wpdb->suppress_errors( $previous );
		}
	}

	public static function menu(): void {
		add_submenu_page( 'adaptive-content-enhancer', __( 'Form protection', 'adaptive-content-enhancer' ), __( 'Form protection', 'adaptive-content-enhancer' ), Capabilities::VIEW_ENTRIES, 'ace-forms-protection', array( self::class, 'render' ) );
	}

	public static function network_menu(): void {
		if ( is_multisite() ) {
			add_submenu_page( 'ace-content-network', __( 'Form protection', 'adaptive-content-enhancer' ), __( 'Form protection', 'adaptive-content-enhancer' ), 'manage_network_options', 'ace-forms-protection', array( self::class, 'render_network' ) );
		}
	}

	private static function intro(): void {
		echo '<h1>' . esc_html__( 'Form protection', 'adaptive-content-enhancer' ) . '</h1><p>' . esc_html__( 'Daily counts for the last 30 days (UTC), collected from this release onwards. Counts are attempts and outcomes, not unique people or confirmed bots. A verified attempt can also appear under content screening; do not add those counts together. Earlier rejections cannot be reconstructed.', 'adaptive-content-enhancer' ) . '</p><p>' . esc_html__( 'Missing or expired verification can affect genuine visitors. Service and configuration failures need attention. No message text, contact details, IP addresses or verification tokens are stored in this report. Counts expire after 30 days when new activity is recorded.', 'adaptive-content-enhancer' ) . '</p>';
	}

	private static function table( ?array $rows ): void {
		if ( null === $rows ) {
			echo '<p>' . esc_html__( 'Reporting is unavailable for this site. Check that the plugin database upgrade has completed.', 'adaptive-content-enhancer' ) . '</p>';
			return;
		}
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No protection outcomes recorded in this period. This does not establish that no attempts occurred.', 'adaptive-content-enhancer' ) . '</p>';
			return;
		}
		$labels = self::labels();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date (UTC)', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Outcome', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Count', 'adaptive-content-enhancer' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			if ( ! isset( $labels[ $row['reason'] ] ) ) {
				continue;
			}
			echo '<tr><td>' . esc_html( $row['day'] ) . '</td><td>' . esc_html( $labels[ $row['reason'] ] ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['attempts'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_ENTRIES ) ) {
			wp_die( esc_html__( 'You cannot view form protection reports.', 'adaptive-content-enhancer' ), '', 403 );
		}
		echo '<div class="wrap">';
		self::intro();
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=ace-forms-review' ) ) . '">' . esc_html__( 'Review held enquiries', 'adaptive-content-enhancer' ) . '</a></p>';
		self::table( self::summary() );
		echo '</div>';
	}

	public static function render_network(): void {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You cannot view network form protection reports.', 'adaptive-content-enhancer' ), '', 403 );
		}
		$page = max( 1, min( 100000, absint( $_GET['protection_page'] ?? 1 ) ) );
		$network = get_current_network_id();
		$sites = get_sites( array( 'network_id' => $network, 'number' => 50, 'offset' => ( $page - 1 ) * 50, 'orderby' => 'id', 'order' => 'ASC' ) );
		$total = (int) get_sites( array( 'network_id' => $network, 'count' => true ) );
		echo '<div class="wrap">';
		self::intro();
		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );
			try {
				echo '<h2><a href="' . esc_url( admin_url( 'admin.php?page=ace-forms-protection' ) ) . '">' . esc_html( $site->domain . $site->path ) . '</a></h2>';
				self::table( self::summary() );
			} finally {
				restore_current_blog();
			}
		}
		$base = network_admin_url( 'admin.php?page=ace-forms-protection' );
		if ( $page > 1 ) {
			echo '<p><a href="' . esc_url( add_query_arg( 'protection_page', $page - 1, $base ) ) . '">' . esc_html__( 'Previous sites', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		if ( $page * 50 < $total ) {
			echo '<p><a href="' . esc_url( add_query_arg( 'protection_page', $page + 1, $base ) ) . '">' . esc_html__( 'More sites', 'adaptive-content-enhancer' ) . '</a></p>';
		}
		echo '</div>';
	}
}
