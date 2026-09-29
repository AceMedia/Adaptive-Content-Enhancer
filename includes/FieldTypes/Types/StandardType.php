<?php
/**
 * Standard scalar and list field implementation.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer\FieldTypes\Types;

use ACE\AdaptiveContentEnhancer\FieldTypes\FieldType;
use ACE\AdaptiveContentEnhancer\Fields\Associations;

defined( 'ABSPATH' ) || exit;

final class StandardType extends FieldType {
	public function __construct(
		private readonly string $slug,
		private readonly string $label,
		private readonly string $json_type = 'string'
	) {}

	public function slug(): string {
		return $this->slug;
	}

	public function label(): string {
		return $this->label;
	}

	public function schema( array $field ): array {
		$type = $this->json_type;
		if ( Associations::is_type( $this->slug ) ) {
			$type = Associations::multiple( $field ) ? 'array' : ( in_array( $this->slug, array( 'post-type', 'taxonomy-type' ), true ) ? 'string' : 'integer' );
		}
		if ( ! empty( $field['acf'] ) && in_array( $this->slug, array( 'image', 'file', 'post-object', 'user' ), true ) && ! Associations::multiple( $field ) ) {
			$type = 'integer';
		}
		if ( ! empty( $field['acf'] ) && 'true-false' === $this->slug ) {
			$type = 'string'; // Adopt ACF's stored '1' and '0' values.
		}
		$schema = array( 'type' => $type );

		if ( 'array' === $type ) {
			$schema['items'] = array( 'type' => in_array( $this->slug, array( 'gallery', 'relationship', 'post-object', 'taxonomy', 'user' ), true ) ? 'integer' : ( in_array( $this->slug, array( 'repeater', 'flexible' ), true ) ? 'object' : 'string' ) );
		}
		if ( 'object' === $type ) {
			$schema['properties'] = match ( $this->slug ) {
				'link' => array( 'url' => array( 'type' => 'string' ), 'title' => array( 'type' => 'string' ), 'target' => array( 'type' => 'string' ) ),
				'location' => array( 'address' => array( 'type' => 'string' ), 'lat' => array( 'type' => 'string' ), 'lng' => array( 'type' => 'string' ) ),
				default => array(),
			};
		}

		if ( ! empty( $field['rules']['maxLength'] ) && 'string' === $this->json_type ) {
			$schema['maxLength'] = max( 1, (int) $field['rules']['maxLength'] );
		}

		return $schema;
	}

	public function sanitise( mixed $value, array $field ): mixed {
		$schema = $this->schema( $field );
		if ( Associations::is_type( $this->slug ) ) {
			if ( 'array' === $schema['type'] ) {
				$values = is_array( $value ) ? $value : ( '' === $value ? array() : array( $value ) );
				return array_values( array_unique( array_filter( array_map( static fn( mixed $item ): int|string => in_array( $field['type'], array( 'post-type', 'taxonomy-type' ), true ) ? sanitize_key( (string) $item ) : absint( $item ), $values ) ) ) );
			}
			return in_array( $this->slug, array( 'post-type', 'taxonomy-type' ), true ) ? sanitize_key( is_scalar( $value ) ? (string) $value : '' ) : absint( is_scalar( $value ) ? $value : 0 );
		}
		if ( 'array' === $schema['type'] ) {
			$values = is_array( $value ) ? $value : array();
			return array_values( array_map( static fn( mixed $item ): mixed => 'integer' === $schema['items']['type'] ? absint( $item ) : ( is_array( $item ) ? map_deep( $item, 'sanitize_text_field' ) : sanitize_text_field( (string) $item ) ), $values ) );
		}
		if ( 'object' === $schema['type'] ) {
			return is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : array();
		}
		if ( 'integer' === $schema['type'] ) {
			return absint( $value );
		}
		if ( 'boolean' === $schema['type'] ) {
			return rest_sanitize_boolean( $value );
		}
		if ( 'true-false' === $this->slug ) {
			return rest_sanitize_boolean( $value ) ? '1' : '0';
		}

		if ( in_array( $this->slug, array( 'number', 'range' ), true ) ) {
			return is_numeric( $value ) ? (float) $value : null;
		}

		if ( in_array( $this->slug, array( 'rich-text', 'wysiwyg' ), true ) ) {
			return wp_kses_post( (string) $value );
		}
		if ( 'email' === $this->slug ) {
			return sanitize_email( (string) $value );
		}
		if ( 'url' === $this->slug ) {
			return esc_url_raw( (string) $value );
		}
		if ( 'colour' === $this->slug ) {
			return sanitize_hex_color( (string) $value ) ?: '';
		}

		return 'textarea' === $this->slug ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
	}

	public function validate( mixed $value, array $field ): true|\WP_Error {
		if ( ! empty( $field['required'] ) && ( '' === $value || null === $value || 0 === $value || array() === $value ) ) {
			$message = $field['rules']['messages']['required'] ?? __( 'Please complete this field.', 'adaptive-content-enhancer' );
			return new \WP_Error( 'ace_required', sanitize_text_field( $message ) );
		}

		if ( 'email' === $this->slug && '' !== $value && ! is_email( (string) $value ) ) {
			return new \WP_Error( 'ace_invalid_email', __( 'Please enter a valid email address.', 'adaptive-content-enhancer' ) );
		}
		if ( in_array( $this->slug, array( 'number', 'range' ), true ) && null === $value && ! empty( $field['required'] ) ) {
			return new \WP_Error( 'ace_invalid_number', __( 'Please enter a number.', 'adaptive-content-enhancer' ) );
		}
		if ( is_numeric( $value ) && is_numeric( $field['rules']['min'] ?? null ) && (float) $value < (float) $field['rules']['min'] ) {
			return new \WP_Error( 'ace_below_minimum', __( 'The value is below the minimum.', 'adaptive-content-enhancer' ) );
		}
		if ( is_numeric( $value ) && is_numeric( $field['rules']['max'] ?? null ) && (float) $value > (float) $field['rules']['max'] ) {
			return new \WP_Error( 'ace_above_maximum', __( 'The value is above the maximum.', 'adaptive-content-enhancer' ) );
		}
		if ( is_string( $value ) && ! empty( $field['rules']['maxLength'] ) && mb_strlen( $value ) > (int) $field['rules']['maxLength'] ) {
			return new \WP_Error( 'ace_too_long', __( 'This value is too long.', 'adaptive-content-enhancer' ) );
		}

		if ( isset( $field['choices'] ) && is_array( $field['choices'] ) && ! empty( $field['choices'] ) ) {
			$allowed = array_map( static fn( array $choice ): string => (string) ( $choice['value'] ?? '' ), $field['choices'] );
			$values  = is_array( $value ) ? $value : array( $value );

			if ( array_diff( $values, $allowed ) ) {
				return new \WP_Error( 'ace_invalid_choice', __( 'Please choose a valid option.', 'adaptive-content-enhancer' ) );
			}
		}
		if ( Associations::is_type( $this->slug ) && ! Associations::valid( $value, $field ) ) {
			return new \WP_Error( 'ace_invalid_association', __( 'Please choose an available item.', 'adaptive-content-enhancer' ) );
		}

		return true;
	}
}
