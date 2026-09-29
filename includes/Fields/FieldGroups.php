<?php
/** Block-authored field groups and native meta registration. */
namespace ACE\AdaptiveContentEnhancer\Fields;

use ACE\AdaptiveContentEnhancer\FieldTypes\Registry;
use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class FieldGroups {
	public const POST_TYPE = 'ace_field_group';
	private static array $local_cache = array();

	public static function register_post_type(): void {
		register_post_type( self::POST_TYPE, array(
			'labels' => array( 'name' => __( 'Field groups', 'adaptive-content-enhancer' ), 'singular_name' => __( 'Field group', 'adaptive-content-enhancer' ), 'add_new_item' => __( 'Add field group', 'adaptive-content-enhancer' ) ),
			'public' => false,
			'show_ui' => true,
			'show_in_rest' => true,
			'show_in_menu' => 'adaptive-content-enhancer',
			'supports' => array( 'title', 'editor', 'revisions', 'custom-fields' ),
			'template' => array( array( 'adaptive-content-enhancer/field-group' ) ),
			'template_lock' => 'all',
			'capabilities' => array(
				'edit_post' => Capabilities::FIELD_GROUPS,
				'read_post' => Capabilities::FIELD_GROUPS,
				'delete_post' => Capabilities::FIELD_GROUPS,
				'edit_posts' => Capabilities::FIELD_GROUPS,
				'edit_others_posts' => Capabilities::FIELD_GROUPS,
				'publish_posts' => Capabilities::FIELD_GROUPS,
				'read_private_posts' => Capabilities::FIELD_GROUPS,
				'delete_posts' => Capabilities::FIELD_GROUPS,
				'create_posts' => Capabilities::FIELD_GROUPS,
			),
		) );
		register_post_meta( self::POST_TYPE, 'ace_source_key', array( 'single' => true, 'type' => 'string', 'show_in_rest' => true, 'auth_callback' => static fn(): bool => current_user_can( Capabilities::FIELD_GROUPS ) ) );
	}

	/** @return array<int, array<string, mixed>> */
	public static function all(): array {
		$blog_id = get_current_blog_id();
		if ( isset( self::$local_cache[ $blog_id ] ) ) {
			return self::$local_cache[ $blog_id ];
		}
		$cached = wp_cache_get( 'all', 'ace_field_groups' );
		if ( is_array( $cached ) ) {
			self::$local_cache[ $blog_id ] = $cached;
			return $cached;
		}
		$posts = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => false ) );
		$groups = array();
		foreach ( $posts as $post ) {
			$group = self::from_post( $post );
			if ( $group ) {
				$groups[] = $group;
			}
		}
		self::$local_cache[ $blog_id ] = $groups;
		wp_cache_set( 'all', $groups, 'ace_field_groups', HOUR_IN_SECONDS );
		return $groups;
	}

	public static function invalidate(): void {
		unset( self::$local_cache[ get_current_blog_id() ] );
		wp_cache_delete( 'all', 'ace_field_groups' );
	}

	public static function invalidate_deleted( int $post_id, \WP_Post $post ): void {
		if ( self::POST_TYPE === $post->post_type ) {
			self::invalidate();
		}
	}

	public static function from_post( \WP_Post $post ): ?array {
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			if ( 'adaptive-content-enhancer/field-group' !== ( $block['blockName'] ?? '' ) ) {
				continue;
			}
			$attrs = $block['attrs'] ?? array();
			return array(
				'id' => $post->ID,
				'key' => sanitize_key( $attrs['groupKey'] ?? $post->post_name ),
				'title' => $post->post_title,
				'location' => is_array( $attrs['location'] ?? null ) ? $attrs['location'] : array(),
				'presentation' => sanitize_key( $attrs['presentation'] ?? 'sidebar' ),
				'fields' => self::collect_fields( $block['innerBlocks'] ?? array() ),
			);
		}
		return null;
	}

	private static function collect_fields( array $blocks ): array {
		$fields = array();
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			if ( in_array( $name, array( 'adaptive-content-enhancer/field', 'adaptive-content-enhancer/field-repeater' ), true ) ) {
				$attrs = $block['attrs'] ?? array();
				$key = sanitize_key( $attrs['key'] ?? '' );
				if ( '' === $key ) {
					continue;
				}
				$attrs['key'] = $key;
				$attrs['type'] = sanitize_key( $attrs['type'] ?? 'text' );
				$attrs['subFields'] = self::collect_fields( $block['innerBlocks'] ?? array() );
				$fields[] = $attrs;
			} else {
				$fields = array_merge( $fields, self::collect_fields( $block['innerBlocks'] ?? array() ) );
			}
		}
		return $fields;
	}

	/** ACF's OR groups of AND rules are kept unchanged in block attributes. */
	public static function matches( array $group, string $param, string $value, array $context = array() ): bool {
		foreach ( $group['location'] as $and_group ) {
			$match = true;
			$has_scope = false;
			foreach ( $and_group as $rule ) {
				$rule_param = (string) ( $rule['param'] ?? '' );
				$has_scope = $has_scope || $rule_param === $param;
				$actual = $rule_param === $param ? $value : ( $context[ $rule_param ] ?? null );
				// Unknown rules must not silently match a post or taxonomy.
				if ( null === $actual ) {
					$match = false;
					break;
				}
				$equal = (string) ( $rule['value'] ?? '' ) === (string) $actual;
				$match = $match && ( '!=' === ( $rule['operator'] ?? '==' ) ? ! $equal : $equal );
			}
			if ( $match && $has_scope ) {
				return true;
			}
		}
		return false;
	}

	public static function register_meta(): void {
		$registered = array();
		foreach ( self::all() as $group ) {
			foreach ( $group['location'] as $and_group ) {
				foreach ( $and_group as $rule ) {
					if ( '!=' === ( $rule['operator'] ?? '==' ) ) {
						continue;
					}
					$param = $rule['param'] ?? '';
					$scope = match ( $param ) {
						'post_type', 'attachment' => 'post',
						'taxonomy' => 'term',
						'user_role' => 'user',
						default => '',
					};
					$subtype = 'attachment' === $param ? 'attachment' : sanitize_key( $rule['value'] ?? '' );
					if ( ! $scope || ! $subtype ) {
						continue;
					}
					foreach ( $group['fields'] as $field ) {
						if ( 'acf_rows' === ( $field['storage'] ?? '' ) || 'group' === ( $field['type'] ?? '' ) ) {
							continue; // ACF rows and nested groups use child meta keys, not a single REST value.
						}
						$key = $field['key'];
						if ( isset( $registered[ $scope . ':' . $subtype . ':' . $key ] ) ) {
							continue;
						}
						$registered[ $scope . ':' . $subtype . ':' . $key ] = true;
						$type = Registry::instance()->get( $field['type'] );
						if ( ! $type ) {
							continue;
						}
						$schema = $type->schema( $field );
						$args = array(
							'single' => true,
							'type' => $schema['type'],
							'show_in_rest' => array( 'schema' => $schema ),
							'auth_callback' => static function ( $allowed, $meta_key, $object_id, $user_id ) use ( $scope ): bool {
								$cap = 'post' === $scope ? 'edit_post' : ( 'term' === $scope ? 'edit_term' : 'edit_user' );
								return user_can( (int) $user_id, $cap, (int) $object_id );
							},
						);
						if ( 'post' === $scope ) {
							register_post_meta( $subtype, $key, $args );
						} elseif ( 'term' === $scope ) {
							register_term_meta( $subtype, $key, $args );
						} else {
							register_meta( 'user', $key, $args );
						}
					}
				}
			}
		}
	}

	public static function register_routes(): void {
		register_rest_route( 'ace-fields/v1', '/groups', array(
			'methods' => 'GET',
			'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ) || current_user_can( Capabilities::FIELD_GROUPS ),
			'callback' => static function ( \WP_REST_Request $request ): \WP_REST_Response {
				$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
				$post_id = absint( $request->get_param( 'post_id' ) );
				$post = $post_id ? get_post( $post_id ) : null;
				if ( $post && $post->post_type !== $post_type ) {
					return new \WP_REST_Response( array(), 400 );
				}
				$context = $post ? array( 'post_status' => $post->post_status, 'page_template' => get_page_template_slug( $post ) ?: 'default' ) : array();
				$groups = array_values( array_filter( self::all(), static fn( array $group ): bool => self::matches( $group, 'post_type', $post_type, $context ) ) );
				return new \WP_REST_Response( $groups );
			},
			'args' => array( 'post_type' => array( 'required' => true, 'type' => 'string' ), 'post_id' => array( 'type' => 'integer' ) ),
		) );
		register_rest_route( 'ace-fields/v1', '/rows/(?P<scope>post|term|user|options)/(?P<object_id>\d+)/(?P<key>[a-z0-9_-]+)', array(
			array(
				'methods' => 'GET',
				'permission_callback' => array( self::class, 'can_edit_rows' ),
				'callback' => static fn( \WP_REST_Request $request ): \WP_REST_Response => new \WP_REST_Response( ValueApi::rows( (string) $request['key'], self::row_object( $request ) ) ),
			),
			array(
				'methods' => 'POST',
				'permission_callback' => array( self::class, 'can_edit_rows' ),
				'callback' => static function ( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
					$rows = $request->get_param( 'rows' );
					if ( ! is_array( $rows ) || ! array_is_list( $rows ) ) {
						return new \WP_Error( 'ace_rows_invalid', __( 'Rows must be a list.', 'adaptive-content-enhancer' ), array( 'status' => 400 ) );
					}
					$result = ValueApi::update_rows( (string) $request['key'], $rows, self::row_object( $request ) );
					if ( is_wp_error( $result ) ) {
						$result->add_data( array( 'status' => 400 ) );
						return $result;
					}
					return new \WP_REST_Response( array( 'rows' => ValueApi::rows( (string) $request['key'], self::row_object( $request ) ) ) );
				},
			),
		) );
		register_rest_route( 'ace-fields/v1', '/values/term/(?P<term_id>\d+)', array(
			array(
				'methods' => 'GET',
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => self::can_edit_term_values( $request ),
				'callback' => static function ( \WP_REST_Request $request ): \WP_REST_Response {
					$values = array();
					foreach ( self::term_fields( absint( $request['term_id'] ) ) as $field ) {
						if ( ! in_array( $field['type'], array( 'flexible', 'repeater', 'group' ), true ) ) {
							$values[ $field['key'] ] = ValueApi::raw( $field['key'], 'term_' . absint( $request['term_id'] ) );
						}
					}
					return new \WP_REST_Response( $values );
				},
			),
			array(
				'methods' => 'POST',
				'permission_callback' => static fn( \WP_REST_Request $request ): bool => self::can_edit_term_values( $request ),
				'callback' => static function ( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
					$input = $request->get_param( 'values' );
					if ( ! is_array( $input ) ) {
						return new \WP_Error( 'ace_values_invalid', __( 'Values must be an object.', 'adaptive-content-enhancer' ), array( 'status' => 400 ) );
					}
					$prepared = array();
					foreach ( self::term_fields( absint( $request['term_id'] ) ) as $field ) {
						$key = $field['key'];
						if ( ! array_key_exists( $key, $input ) || in_array( $field['type'], array( 'flexible', 'repeater', 'group' ), true ) ) {
							continue;
						}
						$type = Registry::instance()->get( $field['type'] );
						if ( ! $type ) {
							continue;
						}
						$value = $type->sanitise( $input[ $key ], $field );
						$valid = $type->validate( $value, $field );
						if ( is_wp_error( $valid ) ) {
							$valid->add_data( array( 'status' => 400, 'field' => $key ) );
							return $valid;
						}
						$prepared[ $key ] = $value;
					}
					foreach ( $prepared as $key => $value ) {
						ValueApi::update( $key, $value, 'term_' . absint( $request['term_id'] ) );
					}
					return new \WP_REST_Response( array( 'updated' => array_keys( $prepared ) ) );
				},
			),
		) );
	}

	public static function term_fields( int $term_id ): array {
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}
		$fields = array();
		foreach ( self::all() as $group ) {
			if ( self::matches( $group, 'taxonomy', $term->taxonomy ) ) {
				$fields = array_merge( $fields, $group['fields'] );
			}
		}
		return $fields;
	}

	private static function can_edit_term_values( \WP_REST_Request $request ): bool {
		$id = absint( $request['term_id'] );
		return $id > 0 && current_user_can( 'edit_term', $id ) && ! empty( self::term_fields( $id ) );
	}

	private static function row_object( \WP_REST_Request $request ): int|string {
		$id = absint( $request['object_id'] );
		return match ( (string) $request['scope'] ) {
			'term' => 'term_' . $id,
			'user' => 'user_' . $id,
			'options' => 'options',
			default => $id,
		};
	}

	public static function can_edit_rows( \WP_REST_Request $request ): bool {
		$id = absint( $request['object_id'] );
		$scope = (string) $request['scope'];
		$object = match ( $scope ) {
			'post' => get_post( $id ),
			'term' => get_term( $id ),
			'user' => get_user_by( 'id', $id ),
			'options' => true,
			default => false,
		};
		if ( ! $object || ( 'options' !== $scope && is_wp_error( $object ) ) ) {
			return false;
		}
		$capability = match ( $scope ) {
			'post' => 'edit_post', 'term' => 'edit_term', 'user' => 'edit_user', default => Capabilities::FIELD_GROUPS,
		};
		if ( ! current_user_can( $capability, $id ) ) {
			return false;
		}
		$key = sanitize_key( (string) $request['key'] );
		foreach ( self::all() as $group ) {
			$matches = match ( $scope ) {
				'post' => self::matches( $group, 'post_type', $object->post_type ),
				'term' => self::matches( $group, 'taxonomy', $object->taxonomy ),
				'user' => self::matches( $group, 'user_role', $object->roles[0] ?? '' ),
				'options' => self::matches( $group, 'options_page', 'options' ),
				default => false,
			};
			if ( $matches ) {
				foreach ( $group['fields'] as $field ) {
					if ( $field['key'] === $key && in_array( $field['type'], array( 'repeater', 'flexible' ), true ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}
}
