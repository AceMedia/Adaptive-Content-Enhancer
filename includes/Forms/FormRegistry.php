<?php
/**
 * Server-side definitions for forms authored in blocks.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class FormRegistry {
	public static function uniquify_post_data( array $data, array $postarr ): array {
		$content = wp_unslash( $data['post_content'] ?? '' );
		if ( ! str_contains( $content, 'wp:adaptive-content-enhancer/form' ) ) {
			return $data;
		}
		$blocks = parse_blocks( $content );
		$seen = array();
		$changed = self::uniquify_blocks( $blocks, (int) ( $postarr['ID'] ?? 0 ), $seen );
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( Capabilities::SETTINGS ) ) {
			$changed = self::protect_settings( $blocks ) || $changed;
		}
		if ( $changed ) {
			$data['post_content'] = wp_slash( serialize_blocks( $blocks ) );
		}
		return $data;
	}

	private static function uniquify_blocks( array &$blocks, int $post_id, array &$seen ): bool {
		global $wpdb;
		$changed = false;
		$table = $wpdb->prefix . 'ace_forms';
		foreach ( $blocks as &$block ) {
			if ( 'adaptive-content-enhancer/form' === ( $block['blockName'] ?? '' ) ) {
				$id = $block['attrs']['formId'] ?? '';
				$owner = wp_is_uuid( $id ) ? $wpdb->get_var( $wpdb->prepare( "SELECT source_post_id FROM {$table} WHERE form_id=%s", $id ) ) : null;
				if ( ! wp_is_uuid( $id ) || isset( $seen[ $id ] ) || ( null !== $owner && (int) $owner !== $post_id ) ) {
					$id = wp_generate_uuid4();
					$block['attrs']['formId'] = $id;
					$changed = true;
				}
				$seen[ $id ] = true;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$changed = self::uniquify_blocks( $block['innerBlocks'], $post_id, $seen ) || $changed;
			}
		}
		unset( $block );
		return $changed;
	}

	private static function protect_settings( array &$blocks ): bool {
		global $wpdb;
		$changed = false;
		foreach ( $blocks as &$block ) {
			if ( 'adaptive-content-enhancer/form' === ( $block['blockName'] ?? '' ) ) {
				$id = $block['attrs']['formId'] ?? '';
				$json = wp_is_uuid( $id ) ? $wpdb->get_var( $wpdb->prepare( "SELECT definition FROM {$wpdb->prefix}ace_forms WHERE form_id=%s", $id ) ) : null;
				$existing = is_string( $json ) ? json_decode( $json, true ) : null;
				$settings = is_array( $existing ) ? ( $existing['settings'] ?? array() ) : array();
				if ( ( $block['attrs']['settings'] ?? array() ) !== $settings ) {
					$block['attrs']['settings'] = $settings;
					$changed = true;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$changed = self::protect_settings( $block['innerBlocks'] ) || $changed;
			}
		}
		unset( $block );
		return $changed;
	}

	public static function sync_post( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ace_forms';
		$found = array();
		if ( ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			self::collect( parse_blocks( $post->post_content ), $found );
		}
		$seen = array();

		foreach ( $found as $form ) {
			$id = $form['formId'];
			$owner = $wpdb->get_var( $wpdb->prepare( "SELECT source_post_id FROM {$table} WHERE form_id = %s", $id ) );
			if ( null !== $owner && (int) $owner !== $post_id ) {
				continue; // Another post owns this ID; copied forms must be given a new one.
			}

			$now = current_time( 'mysql', true );
			$created = $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$table} WHERE form_id=%s", $id ) );
			$definition = wp_json_encode( $form );
			$wpdb->replace(
				$table,
				array(
					'form_id' => $id,
					'source_post_id' => $post_id,
					'name' => $form['name'],
					'definition' => $definition,
					'definition_hash' => sha1( $definition ),
					'status' => 'active',
					'created_at' => $created ?: $now,
					'updated_at' => $now,
				)
			);
			wp_cache_delete( $id, 'ace_forms' );
			$seen[] = $id;
		}

		$owned = $wpdb->get_col( $wpdb->prepare( "SELECT form_id FROM {$table} WHERE source_post_id = %d", $post_id ) );
		foreach ( $owned as $id ) {
			if ( ! in_array( $id, $seen, true ) ) {
				$wpdb->update( $table, array( 'status' => 'orphaned' ), array( 'form_id' => $id ) );
				wp_cache_delete( $id, 'ace_forms' );
			}
		}
	}

	private static function collect( array $blocks, array &$found ): void {
		foreach ( $blocks as $block ) {
			if ( 'adaptive-content-enhancer/form' === $block['blockName'] ) {
				$attrs = $block['attrs'] ?? array();
				$id = sanitize_text_field( $attrs['formId'] ?? '' );
				if ( ! wp_is_uuid( $id ) ) {
					continue;
				}
				$fields = array();
				self::collect_fields( $block['innerBlocks'] ?? array(), $fields );
				$found[] = array(
					'formId' => $id,
					'name' => sanitize_text_field( $attrs['name'] ?? 'Form' ),
					'settings' => is_array( $attrs['settings'] ?? null ) ? $attrs['settings'] : array(),
					'fields' => $fields,
				);
			} else {
				self::collect( $block['innerBlocks'] ?? array(), $found );
			}
		}
	}

	private static function collect_fields( array $blocks, array &$fields ): void {
		foreach ( $blocks as $block ) {
			if ( 'adaptive-content-enhancer/field' === $block['blockName'] ) {
				$attrs = $block['attrs'] ?? array();
				$key = sanitize_key( $attrs['key'] ?? '' );
				if ( '' !== $key && ! isset( $fields[ $key ] ) ) {
					$fields[ $key ] = array(
						'key' => $key,
						'type' => sanitize_key( $attrs['type'] ?? 'text' ),
						'label' => wp_strip_all_tags( $attrs['label'] ?? $key ),
						'required' => ! empty( $attrs['required'] ),
						'choices' => is_array( $attrs['choices'] ?? null ) ? $attrs['choices'] : array(),
						'association' => is_array( $attrs['association'] ?? null ) ? $attrs['association'] : array(),
						'defaultValue' => is_array( $attrs['defaultValue'] ?? null ) ? $attrs['defaultValue'] : array(),
						'rules' => is_array( $attrs['rules'] ?? null ) ? $attrs['rules'] : array(),
						'conditions' => is_array( $attrs['conditions'] ?? null ) ? $attrs['conditions'] : array(),
					);
				}
			}
			self::collect_fields( $block['innerBlocks'] ?? array(), $fields );
		}
	}

	public static function get( string $id ): ?array {
		if ( ! wp_is_uuid( $id ) ) {
			return null;
		}
		$cached = wp_cache_get( $id, 'ace_forms' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'ace_forms';
		$json = $wpdb->get_var( $wpdb->prepare( "SELECT definition FROM {$table} WHERE form_id = %s AND status = 'active'", $id ) );
		$definition = is_string( $json ) ? json_decode( $json, true ) : null;
		if ( is_array( $definition ) ) {
			wp_cache_set( $id, $definition, 'ace_forms', HOUR_IN_SECONDS );
		}
		return is_array( $definition ) ? $definition : null;
	}
}
