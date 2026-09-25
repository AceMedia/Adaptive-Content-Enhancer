<?php
/**
 * Standard scalar and list field implementation.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer\FieldTypes\Types;

use ACE\AdaptiveContentEnhancer\FieldTypes\FieldType;

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
		$schema = array( 'type' => $this->json_type );

		if ( 'array' === $this->json_type ) {
			$schema['items'] = array( 'type' => 'string' );
		}

		if ( isset( $field['rules']['maxLength'] ) && 'string' === $this->json_type ) {
			$schema['maxLength'] = max( 1, (int) $field['rules']['maxLength'] );
		}

		return $schema;
	}

	public function sanitise( mixed $value, array $field ): mixed {
		if ( 'array' === $this->json_type ) {
			return array_values( array_map( 'sanitize_text_field', is_array( $value ) ? $value : array() ) );
		}

		if ( 'boolean' === $this->json_type ) {
			return rest_sanitize_boolean( $value );
		}

		if ( in_array( $this->slug, array( 'number', 'range' ), true ) ) {
			return is_numeric( $value ) ? (float) $value : null;
		}

		if ( in_array( $this->slug, array( 'rich-text', 'wysiwyg' ), true ) ) {
			return wp_kses_post( (string) $value );
		}

		return 'textarea' === $this->slug ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
	}

	public function validate( mixed $value, array $field ): true|\WP_Error {
		if ( ! empty( $field['required'] ) && ( '' === $value || null === $value || array() === $value ) ) {
			$message = $field['rules']['messages']['required'] ?? __( 'Please complete this field.', 'adaptive-content-enhancer' );
			return new \WP_Error( 'ace_required', sanitize_text_field( $message ) );
		}

		if ( 'email' === $this->slug && '' !== $value && ! is_email( (string) $value ) ) {
			return new \WP_Error( 'ace_invalid_email', __( 'Please enter a valid email address.', 'adaptive-content-enhancer' ) );
		}

		if ( isset( $field['choices'] ) && is_array( $field['choices'] ) && ! empty( $field['choices'] ) ) {
			$allowed = array_map( static fn( array $choice ): string => (string) ( $choice['value'] ?? '' ), $field['choices'] );
			$values  = is_array( $value ) ? $value : array( $value );

			if ( array_diff( $values, $allowed ) ) {
				return new \WP_Error( 'ace_invalid_choice', __( 'Please choose a valid option.', 'adaptive-content-enhancer' ) );
			}
		}

		return true;
	}
}
