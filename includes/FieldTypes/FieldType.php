<?php
/**
 * Field-type contract shared by forms and custom fields.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer\FieldTypes;

defined( 'ABSPATH' ) || exit;

abstract class FieldType {
	abstract public function slug(): string;
	abstract public function label(): string;
	/** @return array<string, mixed> */
	abstract public function schema( array $field ): array;
	abstract public function sanitise( mixed $value, array $field ): mixed;
	abstract public function validate( mixed $value, array $field ): true|\WP_Error;

	public function format( mixed $value, array $field, string $context = 'html' ): string {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		return (string) $value;
	}

	public function is_personal( array $field ): bool {
		return ! empty( $field['personal'] );
	}
}
