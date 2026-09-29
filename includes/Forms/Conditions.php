<?php
/** Small, deterministic conditions shared by the editor definition and submit pipeline. */
namespace ACE\AdaptiveContentEnhancer\Forms;

defined( 'ABSPATH' ) || exit;

final class Conditions {
	public static function visible( array $conditions, array $values ): bool {
		$key = sanitize_key( $conditions['field'] ?? '' );
		if ( '' === $key ) {
			return true;
		}
		$actual = $values[ $key ] ?? '';
		$expected = (string) ( $conditions['value'] ?? '' );
		$actual_values = is_array( $actual ) ? array_map( 'strval', $actual ) : array( (string) $actual );
		$equal = in_array( $expected, $actual_values, true );
		return '!=' === ( $conditions['operator'] ?? '==' ) ? ! $equal : $equal;
	}
}
