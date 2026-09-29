<?php
/** Native and adopted ACF value access. */
namespace ACE\AdaptiveContentEnhancer\Fields;

use ACE\AdaptiveContentEnhancer\FieldTypes\Registry;

defined( 'ABSPATH' ) || exit;

final class ValueApi {
	public static function field( string $key ): ?array {
		foreach ( FieldGroups::all() as $group ) {
			$field = self::find_field( $group['fields'], $key );
			if ( $field ) {
				return $field;
			}
		}
		return null;
	}

	private static function find_field( array $fields, string $key ): ?array {
		foreach ( $fields as $field ) {
			if ( $key === ( $field['key'] ?? '' ) ) {
				return $field;
			}
			$found = self::find_field( $field['subFields'] ?? array(), $key );
			if ( $found ) {
				return $found;
			}
		}
		return null;
	}

	public static function get( string $key, int|string|\WP_Post|\WP_Term|\WP_User|null $object = null, bool $formatted = true ): mixed {
		$key = sanitize_key( $key );
		$field = self::field( $key );
		$value = self::raw( $key, $object );
		if ( ! $formatted || ! $field ) {
			return $value;
		}
		if ( in_array( $field['type'], array( 'flexible', 'repeater' ), true ) ) {
			return self::rows( $key, $object );
		}
		return self::format_value( $field, $value );
	}

	public static function format_value( array $field, mixed $value ): mixed {
		if ( 'true-false' === $field['type'] ) {
			return in_array( (string) $value, array( '1', 'true' ), true );
		}
		if ( in_array( $field['type'], array( 'image', 'file' ), true ) && is_numeric( $value ) ) {
			$format = $field['acf']['settings']['return_format'] ?? ( ! empty( $field['acf'] ) ? 'array' : 'id' );
			if ( 'url' === $format ) {
				return wp_get_attachment_url( (int) $value );
			}
			if ( 'array' === $format ) {
				return self::attachment( (int) $value );
			}
		}
		if ( 'gallery' === $field['type'] && is_array( $value ) ) {
			$format = $field['acf']['settings']['return_format'] ?? 'array';
			return array_map( static fn( mixed $id ): mixed => 'id' === $format ? (int) $id : ( 'url' === $format ? wp_get_attachment_url( (int) $id ) : self::attachment( (int) $id ) ), $value );
		}
		if ( in_array( $field['type'], array( 'post-object', 'relationship' ), true ) ) {
			$format = $field['acf']['settings']['return_format'] ?? 'object';
			if ( 'id' !== $format ) {
				return is_array( $value ) ? array_values( array_filter( array_map( 'get_post', $value ) ) ) : ( is_numeric( $value ) ? get_post( (int) $value ) : null );
			}
		}
		if ( 'taxonomy' === $field['type'] ) {
			$format = $field['acf']['settings']['return_format'] ?? 'object';
			if ( 'id' !== $format ) {
				return is_array( $value ) ? array_values( array_filter( array_map( 'get_term', $value ), static fn( mixed $term ): bool => $term instanceof \WP_Term ) ) : ( is_numeric( $value ) ? get_term( (int) $value ) : null );
			}
		}
		if ( 'user' === $field['type'] && is_numeric( $value ) && 'array' === ( $field['acf']['settings']['return_format'] ?? '' ) ) {
			$user = get_user_by( 'id', (int) $value );
			return $user ? array( 'ID' => $user->ID, 'display_name' => $user->display_name, 'user_email' => $user->user_email ) : array();
		}
		return $value;
	}

	public static function raw( string $key, int|string|\WP_Post|\WP_Term|\WP_User|null $object = null ): mixed {
		if ( $object instanceof \WP_Term ) {
			$object = 'term_' . $object->term_id;
		} elseif ( $object instanceof \WP_User ) {
			$object = 'user_' . $object->ID;
		} elseif ( $object instanceof \WP_Post ) {
			$object = $object->ID;
		}
		if ( null === $object ) {
			$object = get_the_ID() ?: 0;
		}
		if ( is_string( $object ) && in_array( $object, array( 'option', 'options' ), true ) ) {
			return get_option( 'options_' . $key );
		}
		if ( is_string( $object ) && preg_match( '/^term_(\d+)$/', $object, $m ) ) {
			return get_term_meta( (int) $m[1], $key, true );
		}
		if ( is_string( $object ) && preg_match( '/^user_(\d+)$/', $object, $m ) ) {
			return get_user_meta( (int) $m[1], $key, true );
		}
		return get_post_meta( (int) $object, $key, true );
	}

	public static function update( string $key, mixed $value, int|string|\WP_Post|\WP_Term|\WP_User|null $object = null ): bool {
		$key = sanitize_key( $key );
		if ( $object instanceof \WP_Term ) {
			$object = 'term_' . $object->term_id;
		} elseif ( $object instanceof \WP_User ) {
			$object = 'user_' . $object->ID;
		} elseif ( $object instanceof \WP_Post ) {
			$object = $object->ID;
		}
		if ( null === $object ) {
			$object = get_the_ID() ?: 0;
		}
		if ( is_string( $object ) && in_array( $object, array( 'option', 'options' ), true ) ) {
			return update_option( 'options_' . $key, $value );
		}
		if ( is_string( $object ) && preg_match( '/^term_(\d+)$/', $object, $m ) ) {
			return false !== update_term_meta( (int) $m[1], $key, $value );
		}
		if ( is_string( $object ) && preg_match( '/^user_(\d+)$/', $object, $m ) ) {
			return false !== update_user_meta( (int) $m[1], $key, $value );
		}
		return false !== update_post_meta( (int) $object, $key, $value );
	}

	public static function rows( string $key, int|string|\WP_Post|\WP_Term|\WP_User|null $object = null ): array {
		$field = self::field( sanitize_key( $key ) );
		if ( ! $field || ! in_array( $field['type'], array( 'repeater', 'flexible' ), true ) ) {
			return array();
		}
		$root = self::raw( $key, $object );
		if ( 'acf_rows' !== ( $field['storage'] ?? '' ) ) {
			return is_array( $root ) ? $root : array();
		}
		$layouts = 'flexible' === $field['type'] ? ( is_array( $root ) ? $root : array() ) : array_fill( 0, max( 0, (int) $root ), '' );
		$rows = array();
		foreach ( $layouts as $index => $layout_name ) {
			$subfields = $field['subFields'] ?? array();
			if ( 'flexible' === $field['type'] ) {
				foreach ( $subfields as $layout ) {
					if ( $layout['key'] === $layout_name ) {
						$subfields = $layout['subFields'] ?? array();
						break;
					}
				}
			}
			$row = 'flexible' === $field['type'] ? array( 'acf_fc_layout' => $layout_name ) : array();
			foreach ( $subfields as $subfield ) {
				$subkey = $key . '_' . $index . '_' . $subfield['key'];
				$row[ $subfield['key'] ] = self::raw( $subkey, $object );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** Write ACF-compatible row keys, leaving existing scalar field storage intact. */
	public static function update_rows( string $key, array $rows, int|string|\WP_Post|\WP_Term|\WP_User|null $object = null ): true|\WP_Error {
		$key = sanitize_key( $key );
		$field = self::field( $key );
		if ( ! $field || ! in_array( $field['type'], array( 'repeater', 'flexible' ), true ) || 'acf_rows' !== ( $field['storage'] ?? '' ) ) {
			return new \WP_Error( 'ace_rows_unknown', __( 'This row field is unavailable.', 'adaptive-content-enhancer' ) );
		}
		if ( count( $rows ) > 100 ) {
			return new \WP_Error( 'ace_rows_limit', __( 'Too many rows.', 'adaptive-content-enhancer' ) );
		}
		$old_rows = self::rows( $key, $object );
		$prepared = array();
		$layouts = array();
		foreach ( array_values( $rows ) as $index => $row ) {
			if ( ! is_array( $row ) ) {
				return new \WP_Error( 'ace_row_invalid', __( 'Invalid row.', 'adaptive-content-enhancer' ) );
			}
			$subfields = $field['subFields'] ?? array();
			if ( 'flexible' === $field['type'] ) {
				$layout_name = sanitize_key( $row['acf_fc_layout'] ?? '' );
				$layout = null;
				foreach ( $subfields as $candidate ) {
					if ( $candidate['key'] === $layout_name ) {
						$layout = $candidate;
						break;
					}
				}
				if ( ! $layout ) {
					return new \WP_Error( 'ace_row_layout', __( 'Unknown row layout.', 'adaptive-content-enhancer' ) );
				}
				$layouts[] = $layout_name;
				$subfields = $layout['subFields'] ?? array();
			}
			foreach ( $subfields as $subfield ) {
				$subkey = $subfield['key'];
				$type = Registry::instance()->get( $subfield['type'] );
				if ( ! $type ) {
					continue;
				}
				$value = $type->sanitise( $row[ $subkey ] ?? '', $subfield );
				$valid = $type->validate( $value, $subfield );
				if ( is_wp_error( $valid ) ) {
					return new \WP_Error( $valid->get_error_code(), sprintf( '%s, row %d: %s', $subfield['label'] ?: $subkey, $index + 1, $valid->get_error_message() ) );
				}
				$prepared[ $index ][ $subkey ] = array( 'value' => $value, 'acf_key' => $subfield['acf']['key'] ?? '' );
			}
		}
		foreach ( $old_rows as $index => $old_row ) {
			foreach ( array_keys( $old_row ) as $subkey ) {
				if ( 'acf_fc_layout' !== $subkey ) {
					self::delete( $key . '_' . $index . '_' . $subkey, $object );
					self::delete( '_' . $key . '_' . $index . '_' . $subkey, $object );
				}
			}
		}
		foreach ( $prepared as $index => $values ) {
			foreach ( $values as $subkey => $item ) {
				$meta_key = $key . '_' . $index . '_' . $subkey;
				self::update( $meta_key, $item['value'], $object );
				if ( $item['acf_key'] ) {
					self::update( '_' . $meta_key, $item['acf_key'], $object );
				}
			}
		}
		self::update( $key, 'flexible' === $field['type'] ? $layouts : count( $rows ), $object );
		return true;
	}

	private static function delete( string $key, int|string|\WP_Post|\WP_Term|\WP_User|null $object ): void {
		if ( $object instanceof \WP_Term ) {
			delete_term_meta( $object->term_id, $key );
		} elseif ( $object instanceof \WP_User ) {
			delete_user_meta( $object->ID, $key );
		} elseif ( is_string( $object ) && preg_match( '/^term_(\d+)$/', $object, $m ) ) {
			delete_term_meta( (int) $m[1], $key );
		} elseif ( is_string( $object ) && preg_match( '/^user_(\d+)$/', $object, $m ) ) {
			delete_user_meta( (int) $m[1], $key );
		} elseif ( in_array( $object, array( 'option', 'options' ), true ) ) {
			delete_option( 'options_' . $key );
		} else {
			delete_post_meta( (int) ( $object instanceof \WP_Post ? $object->ID : $object ), $key );
		}
	}

	private static function attachment( int $id ): array {
		$post = get_post( $id );
		if ( ! $post ) {
			return array();
		}
		$sizes = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$image = wp_get_attachment_image_src( $id, $size );
			if ( $image ) {
				$sizes[ $size ] = $image[0];
			}
		}
		return array( 'ID' => $id, 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'title' => $post->post_title, 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ), 'sizes' => $sizes );
	}
}
