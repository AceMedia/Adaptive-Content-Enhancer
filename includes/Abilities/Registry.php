<?php
/** Read-only domain abilities for form and field-group discovery. */
namespace ACE\AdaptiveContentEnhancer\Abilities;

use ACE\AdaptiveContentEnhancer\Fields\FieldGroups;
use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Registry {
	public static function category(): void {
		wp_register_ability_category( 'adaptive-content-enhancer', array(
			'label' => __( 'Adaptive Content Enhancer', 'adaptive-content-enhancer' ),
			'description' => __( 'Forms and custom field groups.', 'adaptive-content-enhancer' ),
		) );
	}

	public static function register(): void {
		$meta = array( 'show_in_rest' => true, 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) );
		wp_register_ability( 'adaptive-content-enhancer/list-forms', array(
			'label' => __( 'List forms', 'adaptive-content-enhancer' ),
			'description' => __( 'List saved form definitions and their source posts.', 'adaptive-content-enhancer' ),
			'category' => 'adaptive-content-enhancer',
			'output_schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array(
				'form_id' => array( 'type' => 'string' ), 'name' => array( 'type' => 'string' ), 'source_post_id' => array( 'type' => 'integer' ), 'status' => array( 'type' => 'string' ),
			) ) ),
			'permission_callback' => static fn(): bool => current_user_can( Capabilities::VIEW_ENTRIES ),
			'execute_callback' => static function (): array {
				global $wpdb;
				$rows = $wpdb->get_results( "SELECT form_id,name,source_post_id,status FROM {$wpdb->prefix}ace_forms ORDER BY name", ARRAY_A );
				return array_map( static fn( array $row ): array => array( 'form_id' => $row['form_id'], 'name' => $row['name'], 'source_post_id' => (int) $row['source_post_id'], 'status' => $row['status'] ), $rows );
			},
			'meta' => $meta,
		) );
		wp_register_ability( 'adaptive-content-enhancer/list-field-groups', array(
			'label' => __( 'List field groups', 'adaptive-content-enhancer' ),
			'description' => __( 'List block-authored field groups and their field counts.', 'adaptive-content-enhancer' ),
			'category' => 'adaptive-content-enhancer',
			'output_schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array(
				'id' => array( 'type' => 'integer' ), 'key' => array( 'type' => 'string' ), 'title' => array( 'type' => 'string' ), 'field_count' => array( 'type' => 'integer' ),
			) ) ),
			'permission_callback' => static fn(): bool => current_user_can( Capabilities::FIELD_GROUPS ),
			'execute_callback' => static fn(): array => array_map( static fn( array $group ): array => array( 'id' => (int) $group['id'], 'key' => $group['key'], 'title' => $group['title'], 'field_count' => count( $group['fields'] ) ), FieldGroups::all() ),
			'meta' => $meta,
		) );
	}
}
