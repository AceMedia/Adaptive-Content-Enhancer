<?php
/** Adopt ACF definitions as block-authored field groups without rewriting values. */
namespace ACE\AdaptiveContentEnhancer\Migration;

use ACE\AdaptiveContentEnhancer\Fields\FieldGroups;

defined( 'ABSPATH' ) || exit;

final class AcfImporter {
	private const TYPE_MAP = array(
		'wysiwyg' => 'rich-text', 'true_false' => 'true-false', 'color_picker' => 'colour',
		'date_picker' => 'date', 'date_time_picker' => 'datetime', 'time_picker' => 'time',
		'post_object' => 'post-object', 'google_map' => 'location', 'flexible_content' => 'flexible',
		'page_link' => 'url', 'button_group' => 'radio', 'post_type_selector' => 'post-type',
	);

	/** @return array<int, string> */
	public static function import( bool $dry_run = false, string $only_key = '', bool $overwrite = false ): array {
		$report = array();
		$reachable = array();
		$groups = get_posts( array( 'post_type' => 'acf-field-group', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
		foreach ( $groups as $source ) {
			self::mark_children( $source->ID, $reachable );
			if ( 'trash' === $source->post_status || ( $only_key && $source->post_name !== $only_key ) ) {
				continue;
			}
			$settings = maybe_unserialize( $source->post_content );
			if ( ! is_array( $settings ) ) {
				$report[] = "ERROR {$source->post_name}: invalid group settings";
				continue;
			}
			$fields = self::children( $source->ID );
			$location = is_array( $settings['location'] ?? null ) ? $settings['location'] : array();
			$root = array( 'groupKey' => sanitize_key( $source->post_name ), 'location' => $location, 'presentation' => 'sidebar' );
			$content = '<!-- wp:adaptive-content-enhancer/field-group ' . wp_json_encode( $root ) . " -->\n";
			foreach ( $fields as $field ) {
				$content .= self::field_block( $field );
			}
			$content .= '<!-- /wp:adaptive-content-enhancer/field-group -->';
			$existing = get_posts( array( 'post_type' => FieldGroups::POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => 'ace_source_key', 'meta_value' => $source->post_name ) );
			$target = $existing[0] ?? null;
			if ( $target && ! $overwrite ) {
				$report[] = sprintf( 'Skip %s: already imported as group %d', $source->post_title, $target->ID );
				continue;
			}
			$report[] = sprintf( '%s %s: %d top-level fields%s', $target ? 'Update' : 'Create', $source->post_title, count( $fields ), $dry_run ? ' (dry run)' : '' );
			if ( $dry_run ) {
				continue;
			}
			$postarr = array( 'post_type' => FieldGroups::POST_TYPE, 'post_status' => 'publish', 'post_title' => $source->post_title, 'post_name' => sanitize_title( $source->post_name ), 'post_content' => $content );
			if ( $target ) {
				$postarr['ID'] = $target->ID;
				wp_save_post_revision( $target->ID );
			}
			$id = wp_insert_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $id ) ) {
				$report[] = 'ERROR: ' . $id->get_error_message();
				continue;
			}
			update_post_meta( $id, 'ace_source_key', $source->post_name );
			update_post_meta( $id, 'ace_source_id', $source->ID );
			$report[] = "  Group ID {$id}";
		}
		if ( '' === $only_key || 'acf_orphaned_fields' === $only_key ) {
			$source_fields = get_posts( array( 'post_type' => 'acf-field', 'post_status' => 'any', 'numberposts' => -1 ) );
			$orphans = array_values( array_filter( $source_fields, static fn( \WP_Post $field ): bool => ! isset( $reachable[ $field->ID ] ) && 'trash' !== $field->post_status ) );
			if ( $orphans ) {
				$existing = get_posts( array( 'post_type' => FieldGroups::POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => 'ace_source_key', 'meta_value' => 'acf_orphaned_fields' ) );
				if ( $existing && ! $overwrite ) {
					$report[] = 'Skip recovery group ' . $existing[0]->ID . ': already imported';
					return $report;
				}
				$report[] = sprintf( 'Preserve %d orphaned ACF fields in a location-free recovery group%s', count( $orphans ), $dry_run ? ' (dry run)' : '' );
				if ( ! $dry_run ) {
					$content = '<!-- wp:adaptive-content-enhancer/field-group ' . wp_json_encode( array( 'groupKey' => 'acf_orphaned_fields', 'location' => array(), 'presentation' => 'bindings' ) ) . " -->\n";
					foreach ( $orphans as $orphan ) {
						$content .= self::field_block( $orphan );
					}
					$content .= '<!-- /wp:adaptive-content-enhancer/field-group -->';
					$postarr = array( 'post_type' => FieldGroups::POST_TYPE, 'post_status' => 'publish', 'post_title' => 'Recovered orphaned ACF fields', 'post_content' => $content );
					if ( $existing ) {
						$postarr['ID'] = $existing[0]->ID;
						wp_save_post_revision( $existing[0]->ID );
					}
					$id = wp_insert_post( wp_slash( $postarr ), true );
					if ( is_wp_error( $id ) ) {
						$report[] = 'ERROR: ' . $id->get_error_message();
					} else {
						update_post_meta( $id, 'ace_source_key', 'acf_orphaned_fields' );
						$report[] = "  Recovery group ID {$id}";
					}
				}
			}
		}
		return $report;
	}

	private static function mark_children( int $parent_id, array &$reachable ): void {
		foreach ( self::children( $parent_id ) as $field ) {
			$reachable[ $field->ID ] = true;
			self::mark_children( $field->ID, $reachable );
		}
	}

	private static function children( int $parent_id ): array {
		return get_posts( array( 'post_type' => 'acf-field', 'post_parent' => $parent_id, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'menu_order ID', 'order' => 'ASC' ) );
	}

	private static function field_block( \WP_Post $post ): string {
		$config = maybe_unserialize( $post->post_content );
		$config = is_array( $config ) ? $config : array();
		$acf_type = sanitize_key( $config['type'] ?? 'text' );
		$type = self::TYPE_MAP[ $acf_type ] ?? $acf_type;
		$choices = array();
		foreach ( is_array( $config['choices'] ?? null ) ? $config['choices'] : array() as $value => $label ) {
			$choices[] = array( 'value' => (string) $value, 'label' => (string) $label );
		}
		$attrs = array(
			'key' => sanitize_key( $post->post_excerpt ),
			'label' => $post->post_title,
			'type' => $type,
			'help' => wp_strip_all_tags( $config['instructions'] ?? '' ),
			'required' => ! empty( $config['required'] ),
			'choices' => $choices,
			'defaultValue' => array( 'source' => 'static', 'value' => $config['default_value'] ?? '' ),
			'rules' => array( 'min' => $config['min'] ?? null, 'max' => $config['max'] ?? null, 'maxLength' => $config['maxlength'] ?? null, 'storageFormat' => $config['return_format'] ?? '' ),
			'acf' => array( 'key' => $post->post_name, 'id' => $post->ID, 'type' => $acf_type, 'settings' => $config ),
			'storage' => in_array( $type, array( 'flexible', 'repeater', 'group' ), true ) ? 'acf_rows' : 'native',
		);
		if ( in_array( $type, array( 'flexible', 'repeater', 'group' ), true ) ) {
			$content = '<!-- wp:adaptive-content-enhancer/field-repeater ' . wp_json_encode( $attrs ) . " -->\n";
			$children = self::children( $post->ID );
			if ( 'flexible' === $type ) {
				foreach ( is_array( $config['layouts'] ?? null ) ? $config['layouts'] : array() as $layout ) {
					$layout_attrs = array( 'key' => sanitize_key( $layout['name'] ?? '' ), 'label' => $layout['label'] ?? '', 'type' => 'group', 'storage' => 'acf_rows', 'acf' => array( 'layoutKey' => $layout['key'] ?? '' ) );
					$content .= '<!-- wp:adaptive-content-enhancer/field-repeater ' . wp_json_encode( $layout_attrs ) . " -->\n";
					foreach ( $children as $child ) {
						$child_config = maybe_unserialize( $child->post_content );
						if ( is_array( $child_config ) && ( $child_config['parent_layout'] ?? '' ) === ( $layout['key'] ?? '' ) ) {
							$content .= self::field_block( $child );
						}
					}
					$content .= "<!-- /wp:adaptive-content-enhancer/field-repeater -->\n";
				}
			} else {
				foreach ( $children as $child ) {
					$content .= self::field_block( $child );
				}
			}
			return $content . "<!-- /wp:adaptive-content-enhancer/field-repeater -->\n";
		}
		return '<!-- wp:adaptive-content-enhancer/field ' . wp_json_encode( $attrs ) . " /-->\n";
	}
}
