<?php
/** Public value API and optional ACF-compatible read functions. */
defined( 'ABSPATH' ) || exit;

function ace_get_field( string $key, int|string|WP_Post|WP_Term|WP_User|null $object = null, bool $formatted = true ): mixed {
	return \ACE\AdaptiveContentEnhancer\Fields\ValueApi::get( $key, $object, $formatted );
}

function ace_the_field( string $key, int|string|WP_Post|WP_Term|WP_User|null $object = null ): void {
	$value = ace_get_field( $key, $object );
	$field = \ACE\AdaptiveContentEnhancer\Fields\ValueApi::field( sanitize_key( $key ) );
	echo in_array( $field['type'] ?? '', array( 'rich-text', 'wysiwyg' ), true ) ? wp_kses_post( (string) $value ) : esc_html( is_scalar( $value ) ? (string) $value : '' );
}

function ace_update_field( string $key, mixed $value, int|string|WP_Post|WP_Term|WP_User|null $object = null ): bool {
	return \ACE\AdaptiveContentEnhancer\Fields\ValueApi::update( $key, $value, $object );
}

function ace_get_rows( string $key, int|string|WP_Post|WP_Term|WP_User|null $object = null ): array {
	return \ACE\AdaptiveContentEnhancer\Fields\ValueApi::rows( $key, $object );
}

// ACF itself is loaded on plugins_loaded. Declare the optional compatibility
// functions afterwards so an active ACF installation always retains ownership.
add_action( 'plugins_loaded', static function (): void {
	if ( ! get_option( 'ace_fields_acf_compat' ) || function_exists( 'get_field' ) ) {
		return;
	}

	function get_field( string $key, mixed $object = null, bool $formatted = true ): mixed {
		return ace_get_field( $key, $object, $formatted );
	}
	function the_field( string $key, mixed $object = null ): void {
		ace_the_field( $key, $object );
	}
	function have_rows( string $key, mixed $object = null ): bool {
		$stack = &$GLOBALS['ace_acf_row_stack'];
		$stack ??= array();
		$object_key = is_object( $object ) ? get_class( $object ) . ':' . ( $object->ID ?? $object->term_id ?? '' ) : (string) $object;
		$top = array_key_last( $stack );
		if ( null === $top || $stack[ $top ]['key'] !== $key || $stack[ $top ]['object_key'] !== $object_key ) {
			$parent_row = $GLOBALS['ace_acf_current_row'] ?? array();
			$rows = is_array( $parent_row[ $key ] ?? null ) ? $parent_row[ $key ] : ace_get_rows( $key, $object );
			$stack[] = array( 'key' => $key, 'object_key' => $object_key, 'rows' => $rows, 'index' => -1 );
			$top = array_key_last( $stack );
		}
		if ( $stack[ $top ]['index'] + 1 < count( $stack[ $top ]['rows'] ) ) {
			return true;
		}
		array_pop( $stack );
		$parent = end( $stack );
		$GLOBALS['ace_acf_current_row'] = $parent && $parent['index'] >= 0 ? $parent['rows'][ $parent['index'] ] : array();
		return false;
	}
	function the_row(): void {
		$top = array_key_last( $GLOBALS['ace_acf_row_stack'] ?? array() );
		if ( null !== $top ) {
			++$GLOBALS['ace_acf_row_stack'][ $top ]['index'];
			$GLOBALS['ace_acf_current_row'] = $GLOBALS['ace_acf_row_stack'][ $top ]['rows'][ $GLOBALS['ace_acf_row_stack'][ $top ]['index'] ];
		}
	}
	function get_sub_field( string $key ): mixed {
		$value = $GLOBALS['ace_acf_current_row'][ $key ] ?? null;
		$field = \ACE\AdaptiveContentEnhancer\Fields\ValueApi::field( sanitize_key( $key ) );
		return $field ? \ACE\AdaptiveContentEnhancer\Fields\ValueApi::format_value( $field, $value ) : $value;
	}
}, 20 );
