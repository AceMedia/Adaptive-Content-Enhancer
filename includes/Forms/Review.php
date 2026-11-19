<?php
/** Local quarantine, grouped review and bounded, first-party learning. */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Review {
	/** Hash message trigrams so learned rules contain no message or contact details. */
	public static function signature( array $form, array $values ): array {
		$messages = array();
		foreach ( $form['fields'] ?? array() as $key => $field ) {
			if ( 'textarea' === ( $field['type'] ?? '' ) && is_scalar( $values[ $key ] ?? null ) ) {
				$messages[] = (string) $values[ $key ];
			}
		}
		$text = strtolower( wp_strip_all_tags( implode( ' ', $messages ) ) );
		$text = preg_replace( '~https?://\S+|www\.\S+|[\w.+-]+@[\w.-]+~u', ' contact ', $text );
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
		// Short enquiries are too ambiguous to learn automatically.
		if ( count( $words ) < 8 ) {
			return array();
		}
		$hashes = array();
		for ( $i = 0; $i < min( count( $words ) - 2, 256 ); ++$i ) {
			$hashes[] = hash_hmac( 'sha256', implode( ' ', array_slice( $words, $i, 3 ) ), wp_salt( 'auth' ) );
		}
		return array_values( array_unique( $hashes ) );
	}

	public static function similar( array $left, array $right ): bool {
		if ( ! $left || ! $right ) {
			return false;
		}
		$overlap = count( array_intersect( $left, $right ) );
		return $overlap / max( 1, count( array_unique( array_merge( $left, $right ) ) ) ) >= 0.85;
	}

	public static function assess( array $form, array $values, array $screen ): array {
		$signature = self::signature( $form, $values );
		foreach ( array_reverse( (array) get_option( 'ace_forms_review_learning', array() ) ) as $lesson ) {
			if ( (int) ( $lesson['time'] ?? 0 ) < time() - 90 * DAY_IN_SECONDS ) {
				continue;
			}
			if ( self::similar( $signature, (array) ( $lesson['signature'] ?? array() ) ) ) {
				$screen['verdict'] = 'clean' === ( $lesson['verdict'] ?? '' ) ? 'clean' : 'suspect';
				$screen['reasons'][] = 'Similar to a message explicitly reviewed as ' . $lesson['verdict'];
				break;
			}
		}
		$screen['cluster'] = $signature ? substr( hash( 'sha256', implode( '', $signature ) ), 0, 16 ) : '';
		return $screen;
	}

	private static function learn( array $form, array $values, string $verdict ): void {
		$signature = self::signature( $form, $values );
		if ( ! $signature ) {
			return;
		}
		$lessons = array_filter( (array) get_option( 'ace_forms_review_learning', array() ), static fn( $lesson ) => (int) ( $lesson['time'] ?? 0 ) >= time() - 90 * DAY_IN_SECONDS && ! self::similar( $signature, (array) ( $lesson['signature'] ?? array() ) ) );
		$lessons[] = array( 'signature' => $signature, 'verdict' => $verdict, 'time' => time() );
		update_option( 'ace_forms_review_learning', array_slice( array_values( $lessons ), -200 ), false );
	}

	public static function menu(): void {
		add_submenu_page( 'adaptive-content-enhancer', __( 'Spam review', 'adaptive-content-enhancer' ), __( 'Spam review', 'adaptive-content-enhancer' ), Capabilities::EDIT_ENTRIES, 'ace-forms-review', array( self::class, 'render' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::EDIT_ENTRIES ) ) {
			wp_die( esc_html__( 'You cannot review entries.', 'adaptive-content-enhancer' ), '', 403 );
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ace_form_entries WHERE status='suspect' ORDER BY id DESC LIMIT 200" );
		$clusters = array();
		foreach ( $rows as $row ) {
			$values = (array) json_decode( $row->data, true );
			$form = FormRegistry::get( $row->form_id ) ?? array();
			$signature = self::signature( $form, $values );
			$group = count( $clusters );
			foreach ( $clusters as $index => $cluster ) {
				if ( self::similar( $signature, $cluster['signature'] ) ) {
					$group = $index;
					break;
				}
			}
			$clusters[ $group ]['signature'] = $signature;
			$clusters[ $group ]['rows'][] = $row;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Spam review', 'adaptive-content-enhancer' ) . '</h1><p>' . esc_html__( 'New held enquiries send no notification or confirmation. Earlier flagged entries may already have been emailed. Similar messages are grouped below (latest 200 held entries). Review each entry to confirm spam or approve and send. Decisions teach this site for 90 days; short messages are never learned.', 'adaptive-content-enhancer' ) . '</p>';
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No enquiries awaiting review.', 'adaptive-content-enhancer' ) . '</p>';
		}
		foreach ( $clusters as $cluster ) {
			echo '<h2>' . esc_html( sprintf( __( 'Similar messages: %d', 'adaptive-content-enhancer' ), count( $cluster['rows'] ) ) ) . '</h2><ul>';
			foreach ( $cluster['rows'] as $row ) {
				$url = add_query_arg( array( 'page' => 'ace-forms', 'entry' => $row->id ), admin_url( 'admin.php' ) );
				echo '<li><a href="' . esc_url( $url ) . '">#' . esc_html( $row->id . ' · ' . $row->created_at . ' · ' . $row->contact_name ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	public static function controls( object $row ): void {
		global $wpdb;
		$json = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}ace_form_entry_meta WHERE entry_id=%d AND meta_key='screen' ORDER BY id DESC LIMIT 1", $row->id ) );
		$screen = json_decode( (string) $json, true );
		if ( is_array( $screen ) ) {
			echo '<p>' . esc_html( 'Screening: ' . ( $screen['verdict'] ?? '' ) . ' (score ' . (int) ( $screen['score'] ?? 0 ) . '). ' . implode( '; ', (array) ( $screen['reasons'] ?? array() ) ) ) . '</p>';
		}
		if ( 'suspect' !== $row->status || ! current_user_can( Capabilities::EDIT_ENTRIES ) ) {
			return;
		}
		$mail_json = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}ace_form_entry_meta WHERE entry_id=%d AND meta_key='mail_log' ORDER BY id DESC LIMIT 1", $row->id ) );
		$mail = (array) json_decode( (string) $mail_json, true );
		$already_sent = ! empty( $mail['sent'] ) && empty( $mail['held'] );
		if ( $already_sent ) {
			echo '<p>' . esc_html__( 'This entry was already emailed by the earlier screening process. Reviewing it will not resend the notification.', 'adaptive-content-enhancer' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ace_forms_review"/><input type="hidden" name="entry" value="' . esc_attr( $row->id ) . '"/>';
		wp_nonce_field( 'ace_forms_review_' . $row->id );
		echo '<p><button class="button" name="decision" value="spam">' . esc_html__( 'Confirm spam and learn', 'adaptive-content-enhancer' ) . '</button> <button class="button button-primary" name="decision" value="clean">' . esc_html( $already_sent ? __( 'Mark genuine and learn', 'adaptive-content-enhancer' ) : __( 'Approve, send notification and learn', 'adaptive-content-enhancer' ) ) . '</button></p></form>';
	}

	public static function handle(): void {
		if ( ! current_user_can( Capabilities::EDIT_ENTRIES ) ) {
			wp_die( esc_html__( 'You cannot review entries.', 'adaptive-content-enhancer' ), '', 403 );
		}
		$id = absint( $_POST['entry'] ?? 0 );
		check_admin_referer( 'ace_forms_review_' . $id );
		$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
		if ( ! in_array( $decision, array( 'spam', 'clean' ), true ) ) {
			wp_die( esc_html__( 'Invalid review decision.', 'adaptive-content-enhancer' ), '', 400 );
		}
		$result = self::decide( $id, $decision );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', 409 );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=ace-forms&entry=' . $id ) );
		exit;
	}

	/** Capability checks also apply to internal callers; atomically claim before sending. */
	public static function decide( int $id, string $decision ): true|\WP_Error {
		if ( ! current_user_can( Capabilities::EDIT_ENTRIES ) || ! in_array( $decision, array( 'spam', 'clean' ), true ) ) {
			return new \WP_Error( 'ace_review_forbidden', 'You cannot review this entry.' );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'ace_form_entries';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d AND status='suspect'", $id ) );
		if ( ! $row ) {
			return new \WP_Error( 'ace_review_missing', 'This entry is no longer awaiting review.' );
		}
		$log = array();
		$deliver = false;
		if ( 'clean' === $decision ) {
			$json = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}ace_form_entry_meta WHERE entry_id=%d AND meta_key='mail_log' ORDER BY id DESC LIMIT 1", $id ) );
			$log = (array) json_decode( (string) $json, true );
			$deliver = ! empty( $log['held'] ) && ! empty( $log['to'] );
			if ( ! $deliver && empty( $log['sent'] ) ) {
				return new \WP_Error( 'ace_review_no_mail', 'No held notification is available for this entry.' );
			}
		}
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='reviewing' WHERE id=%d AND status='suspect'", $id ) );
		if ( 1 !== $claimed ) {
			return new \WP_Error( 'ace_review_busy', 'This entry is already being reviewed.' );
		}
		if ( 'clean' === $decision && $deliver ) {
			$log['sent'] = wp_mail( $log['to'], $log['subject'], $log['body'], $log['headers'] );
			$log['held'] = false;
			$log['date'] = current_time( 'mysql', true );
			$wpdb->insert( $wpdb->prefix . 'ace_form_entry_meta', array( 'entry_id' => $id, 'meta_key' => 'mail_log', 'meta_value' => wp_json_encode( $log ) ) );
			if ( ! $log['sent'] ) {
				// Keep the original held envelope available for an explicit retry.
				$log['held'] = true;
				$wpdb->insert( $wpdb->prefix . 'ace_form_entry_meta', array( 'entry_id' => $id, 'meta_key' => 'mail_log', 'meta_value' => wp_json_encode( $log ) ) );
				$wpdb->update( $table, array( 'status' => 'suspect' ), array( 'id' => $id ) );
				return new \WP_Error( 'ace_review_delivery', 'The notification could not be sent. The entry remains held for retry.' );
			}
		}
		$wpdb->insert( $wpdb->prefix . 'ace_form_entry_meta', array( 'entry_id' => $id, 'meta_key' => 'review', 'meta_value' => wp_json_encode( array( 'decision' => $decision, 'user_id' => get_current_user_id(), 'date' => current_time( 'mysql', true ) ) ) ) );
		$wpdb->update( $table, array( 'status' => 'spam' === $decision ? 'spam' : 'approved' ), array( 'id' => $id ) );
		self::learn( FormRegistry::get( $row->form_id ) ?? array(), (array) json_decode( $row->data, true ), $decision );
		return true;
	}
}
