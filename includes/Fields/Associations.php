<?php
/** Searchable native WordPress objects for form and meta fields. */
namespace ACE\AdaptiveContentEnhancer\Fields;

defined( 'ABSPATH' ) || exit;

final class Associations {
	public static function is_type( string $type ): bool {
		return in_array( $type, array( 'post-object', 'relationship', 'taxonomy', 'post-type', 'taxonomy-type', 'user' ), true );
	}

	public static function multiple( array $field ): bool {
		return 'relationship' === ( $field['type'] ?? '' ) || ! empty( $field['association']['multiple'] ) || ! empty( $field['acf']['settings']['multiple'] ) || in_array( $field['acf']['settings']['field_type'] ?? '', array( 'multi_select', 'checkbox' ), true );
	}

	private static function names( mixed $value ): array {
		$values = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );
		return array_values( array_filter( array_map( 'sanitize_key', array_filter( $values, 'is_scalar' ) ) ) );
	}

	public static function allowed_post_types( array $field = array() ): array {
		$configured = self::names( $field['association']['postTypes'] ?? $field['acf']['settings']['post_type'] ?? array() );
		$types = array_diff( get_post_types( array( 'public' => true, 'show_in_rest' => true ), 'names' ), array( 'attachment' ) );
		return $configured ? array_values( array_intersect( $configured, $types ) ) : array_values( $types );
	}

	public static function allowed_taxonomies( array $field = array() ): array {
		$configured = self::names( $field['association']['taxonomies'] ?? $field['acf']['settings']['taxonomy'] ?? array() );
		$taxonomies = get_taxonomies( array( 'public' => true, 'show_in_rest' => true ), 'names' );
		return $configured ? array_values( array_intersect( $configured, $taxonomies ) ) : array_values( $taxonomies );
	}

	public static function sources(): array {
		$post_types = array();
		foreach ( self::allowed_post_types() as $slug ) {
			$post_types[] = array( 'value' => $slug, 'label' => get_post_type_object( $slug )->labels->singular_name );
		}
		$taxonomies = array();
		foreach ( self::allowed_taxonomies() as $slug ) {
			$taxonomies[] = array( 'value' => $slug, 'label' => get_taxonomy( $slug )->labels->singular_name );
		}
		return array( 'postTypes' => $post_types, 'taxonomies' => $taxonomies );
	}

	public static function options( array $field, string $search = '', array $selected = array() ): array {
		$type = sanitize_key( $field['type'] ?? '' );
		$search = substr( sanitize_text_field( $search ), 0, 100 );
		$selected = array_slice( array_values( array_filter( $selected, 'is_scalar' ) ), 0, 50 );
		$options = array();
		if ( in_array( $type, array( 'post-object', 'relationship' ), true ) ) {
			$types = self::allowed_post_types( $field );
			if ( ! $types ) {
				return array();
			}
			$posts = get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 30, 's' => $search, 'orderby' => 'title', 'order' => 'ASC' ) );
			foreach ( $selected as $id ) {
				$post = get_post( absint( $id ) );
				if ( $post && 'publish' === $post->post_status && in_array( $post->post_type, $types, true ) ) {
					$posts[] = $post;
				}
			}
			foreach ( $posts as $post ) {
				$options[ $post->ID ] = array( 'value' => (string) $post->ID, 'label' => get_the_title( $post ) ?: '#' . $post->ID );
			}
		} elseif ( 'taxonomy' === $type ) {
			$taxonomies = self::allowed_taxonomies( $field );
			if ( ! $taxonomies ) {
				return array();
			}
			$terms = get_terms( array( 'taxonomy' => $taxonomies, 'hide_empty' => false, 'number' => 30, 'search' => $search ) );
			foreach ( $selected as $id ) {
				$term = get_term( absint( $id ) );
				if ( $term instanceof \WP_Term && in_array( $term->taxonomy, $taxonomies, true ) ) {
					$terms[] = $term;
				}
			}
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$options[ $term->term_id ] = array( 'value' => (string) $term->term_id, 'label' => $term->name . ' (' . get_taxonomy( $term->taxonomy )->labels->singular_name . ')' );
				}
			}
		} elseif ( 'post-type' === $type ) {
			foreach ( self::sources()['postTypes'] as $option ) {
				if ( '' === $search || false !== stripos( $option['label'] . ' ' . $option['value'], $search ) ) {
					$options[ $option['value'] ] = $option;
				}
			}
		} elseif ( 'taxonomy-type' === $type ) {
			foreach ( self::sources()['taxonomies'] as $option ) {
				if ( '' === $search || false !== stripos( $option['label'] . ' ' . $option['value'], $search ) ) {
					$options[ $option['value'] ] = $option;
				}
			}
		} elseif ( 'user' === $type && current_user_can( 'list_users' ) ) {
			$users = get_users( array( 'number' => 30, 'search' => '*' . $search . '*', 'search_columns' => array( 'display_name', 'user_login' ) ) );
			foreach ( $selected as $id ) {
				$user = get_user_by( 'id', absint( $id ) );
				if ( $user ) {
					$users[] = $user;
				}
			}
			foreach ( $users as $user ) {
				$options[ $user->ID ] = array( 'value' => (string) $user->ID, 'label' => $user->display_name );
			}
		}
		return array_values( $options );
	}

	public static function valid( mixed $value, array $field ): bool {
		$type = $field['type'] ?? '';
		$values = self::multiple( $field ) ? ( is_array( $value ) ? $value : array() ) : array( $value );
		foreach ( $values as $item ) {
			if ( '' === $item || null === $item || 0 === $item ) {
				continue;
			}
			if ( in_array( $type, array( 'post-object', 'relationship' ), true ) ) {
				$post = get_post( absint( $item ) );
				if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, self::allowed_post_types( $field ), true ) ) {
					return false;
				}
			} elseif ( 'taxonomy' === $type ) {
				$term = get_term( absint( $item ) );
				if ( ! $term instanceof \WP_Term || ! in_array( $term->taxonomy, self::allowed_taxonomies( $field ), true ) ) {
					return false;
				}
			} elseif ( 'post-type' === $type && ! in_array( sanitize_key( (string) $item ), self::allowed_post_types( $field ), true ) ) {
				return false;
			} elseif ( 'taxonomy-type' === $type && ! in_array( sanitize_key( (string) $item ), self::allowed_taxonomies( $field ), true ) ) {
				return false;
			} elseif ( 'user' === $type && ( ! current_user_can( 'list_users' ) || ! get_user_by( 'id', absint( $item ) ) ) ) {
				return false;
			}
		}
		return true;
	}

	public static function register_routes(): void {
		register_rest_route( 'ace-fields/v1', '/association-sources', array(
			'methods' => 'GET', 'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			'callback' => static fn(): \WP_REST_Response => new \WP_REST_Response( self::sources() ),
		) );
		register_rest_route( 'ace-fields/v1', '/association-options', array(
			'methods' => 'GET', 'permission_callback' => '__return_true',
			'callback' => static function ( \WP_REST_Request $request ): \WP_REST_Response {
				$type = sanitize_key( (string) $request->get_param( 'type' ) );
				$field = array( 'type' => $type, 'association' => array(
					'postTypes' => $request->get_param( 'postTypes' ), 'taxonomies' => $request->get_param( 'taxonomies' ),
				) );
				$selected = $request->get_param( 'selected' );
				return new \WP_REST_Response( self::is_type( $type ) ? self::options( $field, (string) $request->get_param( 'search' ), is_array( $selected ) ? $selected : array() ) : array() );
			},
			'args' => array( 'type' => array( 'required' => true, 'type' => 'string' ), 'search' => array( 'type' => 'string' ) ),
		) );
	}
}
