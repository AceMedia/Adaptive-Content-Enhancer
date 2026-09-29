<?php
/** Import Contact Form 7 forms into synced block patterns. */
namespace ACE\AdaptiveContentEnhancer\Migration;

use ACE\AdaptiveContentEnhancer\Forms\FormRegistry;

defined( 'ABSPATH' ) || exit;

final class Cf7Importer {
	public static function import( bool $dry_run = true, bool $replace = false, bool $refresh_notifications = false ): array {
		$forms = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$map = get_option( 'ace_forms_cf7_map', array() );
		$report = array();
		foreach ( $forms as $post ) {
			$id = (int) $post->ID;
			if ( ! empty( $map[ $id ]['pattern_id'] ) && 'wp_block' === get_post_type( (int) $map[ $id ]['pattern_id'] ) ) {
				$report[] = "Form {$id}: existing pattern {$map[$id]['pattern_id']}" . ( $refresh_notifications ? ' (refresh notifications only)' : '' );
				if ( ! $dry_run && $refresh_notifications ) {
					self::refresh_notifications( $post, (int) $map[ $id ]['pattern_id'], (string) $map[ $id ]['form_id'] );
				}
				continue;
			}
			$uuid = wp_generate_uuid4();
			$content = self::convert( $post, $uuid );
			$report[] = "Form {$id}: {$post->post_title} → synced pattern";
			if ( $dry_run ) {
				continue;
			}
			$pattern_id = wp_insert_post( wp_slash( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => 'Form: ' . $post->post_title, 'post_content' => $content ) ), true );
			if ( is_wp_error( $pattern_id ) ) {
				$report[] = '  ERROR: ' . $pattern_id->get_error_message();
				continue;
			}
			$hash = class_exists( 'WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_instance( $id )?->hash() : '';
			$map[ $id ] = array( 'pattern_id' => (int) $pattern_id, 'form_id' => $uuid, 'hash' => $hash );
			update_option( 'ace_forms_cf7_map', $map, false );
		}
		if ( $replace ) {
			$report = array_merge( $report, self::replace_placements( $map, $dry_run ) );
		}
		return $report;
	}

	private static function refresh_notifications( \WP_Post $source, int $pattern_id, string $form_id ): void {
		global $wpdb;
		$pattern = get_post( $pattern_id );
		if ( ! $pattern ) {
			return;
		}
		$blocks = parse_blocks( $pattern->post_content );
		$converted = parse_blocks( self::convert( $source, $form_id ) );
		$source_settings = $converted[0]['attrs']['settings'] ?? array();
		foreach ( $blocks as &$block ) {
			if ( 'adaptive-content-enhancer/form' === ( $block['blockName'] ?? '' ) ) {
				$block['attrs']['settings'] = array_merge( $block['attrs']['settings'] ?? array(), $source_settings );
			}
		}
		unset( $block );
		wp_save_post_revision( $pattern_id );
		$wpdb->update( $wpdb->posts, array( 'post_content' => serialize_blocks( $blocks ), 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ), array( 'ID' => $pattern_id ) );
		clean_post_cache( $pattern_id );
		FormRegistry::sync_post( $pattern_id, get_post( $pattern_id ) );
	}

	private static function convert( \WP_Post $post, string $uuid ): string {
		$template = (string) get_post_meta( $post->ID, '_form', true );
		$mail = get_post_meta( $post->ID, '_mail', true );
		$mail = is_array( $mail ) ? $mail : array();
		$recipient = str_replace( '[_site_admin_email]', get_option( 'admin_email' ), (string) ( $mail['recipient'] ?? '' ) );
		$settings = array(
			'to' => $recipient ?: get_option( 'admin_email' ),
			'subject' => self::merge_tags( (string) ( $mail['subject'] ?? '' ) ),
			'body' => self::merge_tags( (string) ( $mail['body'] ?? '' ) ),
			'successMessage' => __( 'Thank you. Your enquiry has been received.', 'adaptive-content-enhancer' ),
		);
		$sender = self::sender_template( (string) ( $mail['sender'] ?? '' ) );
		if ( $sender ) {
			$settings['from'] = $sender;
		}
		$additional_headers = (string) ( $mail['additional_headers'] ?? '' );
		foreach ( array( 'Reply-To' => 'replyTo', 'Cc' => 'cc', 'Bcc' => 'bcc' ) as $header => $key ) {
			if ( preg_match( '/^' . preg_quote( $header, '/' ) . ':\s*(.+)$/mi', $additional_headers, $match ) ) {
				$settings[ $key ] = self::merge_tags( trim( $match[1] ) );
			}
		}
		$mail_2 = get_post_meta( $post->ID, '_mail_2', true );
		if ( is_array( $mail_2 ) && ! empty( $mail_2['active'] ) ) {
			$settings['confirmation'] = array(
				'enabled' => true,
				'subject' => self::merge_tags( (string) ( $mail_2['subject'] ?? '' ) ),
				'body' => self::merge_tags( (string) ( $mail_2['body'] ?? '' ) ),
			);
			$confirmation_sender = self::sender_template( (string) ( $mail_2['sender'] ?? '' ) );
			if ( $confirmation_sender ) {
				$settings['confirmation']['from'] = $confirmation_sender;
			}
		}
		$attributes = array( 'formId' => $uuid, 'name' => $post->post_title, 'settings' => $settings, 'className' => 'ace-imported-cf7' );
		$content = '';
		if ( preg_match( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $template, $heading ) ) {
			$text = wp_strip_all_tags( $heading[1] );
			$content .= '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html( $text ) . "</h3><!-- /wp:heading -->\n";
		}
		preg_match_all( '/\[(textarea|text|email|tel|url|number|date|select|checkbox|radio|acceptance|hidden|file|submit)(\*)?\s*([^\]]*)\]/i', $template, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$field_keys = array();
		if ( ! $matches ) {
			$matches = array();
			foreach ( array( array( 'text', 'name', 'Name' ), array( 'email', 'email', 'Email address' ), array( 'textarea', 'message', 'Message' ) ) as $fallback ) {
				$field = array( 'key' => $fallback[1], 'type' => $fallback[0], 'label' => $fallback[2], 'required' => true );
				$field_keys[] = $fallback[1];
				$content .= '<!-- wp:adaptive-content-enhancer/field ' . wp_json_encode( $field ) . " /-->\n";
			}
			$content .= '<!-- wp:adaptive-content-enhancer/submit {"text":"Send enquiry"} /-->' . "\n";
		}
		foreach ( $matches as $match ) {
			$type = strtolower( $match[1][0] );
			$raw = trim( $match[3][0] );
			if ( 'submit' === $type ) {
				$label = trim( $raw, " \t\n\r\0\x0B\"'" ) ?: 'Send enquiry';
				$content .= '<!-- wp:adaptive-content-enhancer/submit ' . wp_json_encode( array( 'text' => $label ) ) . " /-->\n";
				continue;
			}
			$name = strtok( $raw, " \t\n" );
			$key = sanitize_key( $name ?: '' );
			if ( ! $key ) {
				continue;
			}
			$field_keys[] = $key;
			$before = substr( $template, max( 0, $match[0][1] - 300 ), min( 300, $match[0][1] ) );
			$label = ucwords( str_replace( array( '-', '_' ), ' ', $key ) );
			$label_start = strripos( $before, '<label' );
			$label_fragment = false !== $label_start ? substr( $before, $label_start ) : '';
			$label_close = stripos( $label_fragment, '</label>' );
			$label_nearby = false !== $label_start && ( false === $label_close || '' === trim( wp_strip_all_tags( substr( $label_fragment, $label_close + 8 ) ) ) );
			if ( $label_nearby ) {
				$label_text = false === $label_close ? $label_fragment : substr( $label_fragment, 0, $label_close );
				$label = trim( preg_replace( '/^Enter your\s*/i', '', wp_strip_all_tags( $label_text ) ) );
				$label = trim( preg_replace( '/\[(?:text|email|tel)[^\]]*\]/i', '', $label ), " *\t\n" );
			} elseif ( preg_match_all( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $before, $headings ) && ! empty( $headings[1] ) ) {
				$label = wp_strip_all_tags( end( $headings[1] ) );
			}
			$field = array( 'key' => $key, 'type' => 'acceptance' === $type ? 'consent' : $type, 'label' => 'hidden' === $type ? '' : $label, 'required' => '*' === $match[2][0] );
			if ( 'hidden' === $type && str_contains( $raw, 'default:get' ) ) {
				$field['defaultValue'] = array( 'source' => 'query', 'param' => $key );
			}
			if ( in_array( $type, array( 'select', 'checkbox', 'radio' ), true ) ) {
				preg_match_all( '/"([^"]+)"/', $raw, $choices );
				$field['choices'] = array_map( static fn( string $value ): array => array( 'label' => $value, 'value' => $value ), $choices[1] );
			}
			$content .= '<!-- wp:adaptive-content-enhancer/field ' . wp_json_encode( $field ) . " /-->\n";
		}
		$raw_mail_tags = array();
		preg_match_all( '/\[([a-zA-Z0-9_-]+)\]/', (string) ( $mail['body'] ?? '' ), $raw_mail_tags );
		$referenced_fields = array_filter( $raw_mail_tags[1] ?? array(), static fn( string $tag ): bool => ! str_starts_with( $tag, '_' ) );
		if ( ! $settings['body'] || array_diff( $referenced_fields, $field_keys ) ) {
			$settings['body'] = "New enquiry from {site_name}\n\n" . implode( "\n", array_map( static fn( string $key ): string => $key . ': {field:' . $key . '}', $field_keys ) );
		}
		$attributes['settings'] = $settings;
		return '<!-- wp:adaptive-content-enhancer/form ' . wp_json_encode( $attributes ) . " -->\n" . $content . '<!-- /wp:adaptive-content-enhancer/form -->';
	}

	private static function merge_tags( string $text ): string {
		return preg_replace_callback( '/\[([a-zA-Z0-9_-]+)\]/', static function ( array $match ): string {
			return match ( $match[1] ) {
				'_post_title' => '{post_title}', '_url' => '{page_url}', '_site_title' => '{site_name}', '_site_url' => '{site_url}', '_date' => '{date}',
				default => '{field:' . $match[1] . '}',
			};
		}, $text );
	}

	/** Use the current site domain in imported senders, so local .pi addresses do not ship to live. */
	private static function sender_template( string $sender ): string {
		$sender = trim( $sender );
		if ( ! preg_match( '/^(.*?)<([^<>]+)>$/', $sender, $parts ) ) {
			$parts = array( '', '', $sender );
		}
		$email = sanitize_email( trim( $parts[2] ) );
		$site_domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_email( $email ) ) {
			return '';
		}
		$sender_domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );
		$network_domain = is_multisite() ? strtolower( (string) get_network()->domain ) : '';
		$domain_tag = match ( $sender_domain ) {
			strtolower( $site_domain ) => '{site_domain}',
			$network_domain => '{network_domain}',
			default => '',
		};
		if ( ! $domain_tag ) {
			return '';
		}
		$name = self::merge_tags( sanitize_text_field( trim( $parts[1] ) ) );
		return ( $name ? $name . ' ' : '' ) . '<' . substr( $email, 0, strrpos( $email, '@' ) + 1 ) . $domain_tag . '>';
	}

	private static function replace_placements( array $map, bool $dry_run ): array {
		global $wpdb;
		$posts = $wpdb->get_results( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_content LIKE '%contact-form-7%' AND post_status NOT IN ('trash','auto-draft','inherit')" );
		$report = array();
		foreach ( $posts as $post ) {
			$content = $post->post_content;
			$replace = static function ( array $match ) use ( $map ): string {
				if ( ! preg_match( '/(?:id|hash)=["\']([^"\']+)["\']/', $match[0], $found ) && ! preg_match( '/"id":(\d+)/', $match[0], $found ) ) {
					return $match[0];
				}
				$needle = $found[1];
				foreach ( $map as $id => $entry ) {
					if ( empty( $entry['pattern_id'] ) || 'wp_block' !== get_post_type( (int) $entry['pattern_id'] ) ) {
						continue;
					}
					if ( (string) $id === $needle || (string) ( $entry['hash'] ?? '' ) === $needle ) {
						return '<!-- wp:block {"ref":' . (int) $entry['pattern_id'] . '} /-->';
					}
				}
				return $match[0];
			};
			$content = preg_replace_callback( '/<!-- wp:contact-form-7\/contact-form-selector\b.*?<!-- \/wp:contact-form-7\/contact-form-selector -->/s', $replace, $content );
			$content = preg_replace_callback( '/<!-- wp:shortcode -->\s*\[contact-form-7\b[^\]]+\]\s*<!-- \/wp:shortcode -->/s', $replace, $content );
			$content = preg_replace_callback( '/\[contact-form-7\b[^\]]+\]/', $replace, $content );
			if ( $content === $post->post_content ) {
				continue;
			}
			$report[] = "Post {$post->ID}: {$post->post_title} → synced pattern";
			if ( ! $dry_run ) {
				// Save a rollback revision, then update the content directly. Several
				// site-specific save_post handlers terminate WP-CLI after one save.
				wp_save_post_revision( (int) $post->ID );
				$result = $wpdb->update( $wpdb->posts, array( 'post_content' => $content, 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ), array( 'ID' => (int) $post->ID ) );
				clean_post_cache( (int) $post->ID );
				if ( false === $result ) {
					$report[] = '  ERROR: ' . $wpdb->last_error;
				}
			}
		}
		return $report;
	}
}
