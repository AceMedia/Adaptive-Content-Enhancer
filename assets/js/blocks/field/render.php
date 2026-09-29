<?php
/** Render a form field. */
defined( 'ABSPATH' ) || exit;

use ACE\AdaptiveContentEnhancer\Fields\Associations;

$type = sanitize_key( $attributes['type'] ?? 'text' );
$key = sanitize_key( $attributes['key'] ?? '' );
if ( '' === $key ) {
	return;
}
$id = wp_unique_id( 'ace-field-' . sanitize_html_class( $key ) . '-' );
$label = wp_strip_all_tags( $attributes['label'] ?? $key );
$required = ! empty( $attributes['required'] );
$placeholder = sanitize_text_field( $attributes['placeholder'] ?? '' );
$floating = 'standard' !== ( $block->context['ace/appearance']['labels'] ?? '' ) && in_array( $type, array( 'text', 'email', 'tel', 'url', 'number', 'textarea', 'select' ), true );
if ( $floating ) {
	$placeholder = ' ';
}
$help = sanitize_text_field( $attributes['help'] ?? '' );
$default = is_array( $attributes['defaultValue'] ?? null ) ? $attributes['defaultValue'] : array();
$default_value = $default['value'] ?? '';
$value = is_scalar( $default_value ) ? (string) $default_value : '';
$selected_values = is_array( $default_value ) ? array_map( 'strval', $default_value ) : array( $value );
if ( 'query' === ( $default['source'] ?? '' ) ) {
	$param = sanitize_key( $default['param'] ?? $key );
	$value = isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
	$selected_values = array( $value );
} elseif ( 'post_title' === ( $default['source'] ?? '' ) ) {
	$value = sanitize_text_field( get_the_title() );
	$selected_values = array( $value );
}
if ( 'hidden' === $type ) {
	echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
	return;
}

$choices_field = in_array( $type, array( 'radio', 'checkbox' ), true );
$wrapper_tag = $choices_field ? 'fieldset' : 'div';
$wrapper_attrs = array( 'class' => 'ace-form-field ace-form-field--' . $type . ( $floating ? ' ace-field--floating' : '' ) . ( $required ? ' ace-form-field--required' : '' ) );
$conditions = is_array( $attributes['conditions'] ?? null ) ? $attributes['conditions'] : array();
if ( ! empty( $conditions['field'] ) ) {
	$wrapper_attrs['data-ace-condition'] = wp_json_encode( array( 'field' => sanitize_key( $conditions['field'] ), 'operator' => '!=' === ( $conditions['operator'] ?? '' ) ? '!=' : '==', 'value' => sanitize_text_field( $conditions['value'] ?? '' ) ) );
}
echo '<' . $wrapper_tag . ' ' . get_block_wrapper_attributes( $wrapper_attrs ) . '>';
echo $choices_field ? '<legend class="ace-form-field__label">' : '<label class="ace-form-field__label" for="' . esc_attr( $id ) . '">';
echo esc_html( $label );
if ( $required ) {
	echo ' <span class="screen-reader-text">' . esc_html__( '(required)', 'adaptive-content-enhancer' ) . '</span>';
}
echo $choices_field ? '</legend>' : '</label>';
if ( $required ) {
	echo '<span class="ace-form-field__requirement" aria-hidden="true">' . esc_html__( 'Required', 'adaptive-content-enhancer' ) . '</span>';
}

$common = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '"' . ( $required ? ' required' : '' );
if ( Associations::is_type( $type ) && 'user' !== $type ) {
	$field = array( 'type' => $type, 'association' => is_array( $attributes['association'] ?? null ) ? $attributes['association'] : array(), 'acf' => is_array( $attributes['acf'] ?? null ) ? $attributes['acf'] : array() );
	$multiple = Associations::multiple( $field );
	$options = Associations::options( $field, '', $selected_values );
	$config = array( 'type' => $type, 'postTypes' => $field['association']['postTypes'] ?? $field['acf']['settings']['post_type'] ?? array(), 'taxonomies' => $field['association']['taxonomies'] ?? $field['acf']['settings']['taxonomy'] ?? array() );
	echo '<input class="ace-association-search" type="search" aria-label="' . esc_attr( sprintf( __( 'Search %s', 'adaptive-content-enhancer' ), $label ) ) . '" placeholder="' . esc_attr__( 'Search choices…', 'adaptive-content-enhancer' ) . '" data-ace-association-search />';
	echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $key . ( $multiple ? '[]' : '' ) ) . '" data-ace-association="' . esc_attr( wp_json_encode( $config ) ) . '" data-ace-association-url="' . esc_url( rest_url( 'ace-fields/v1/association-options' ) ) . '"' . ( $multiple ? ' multiple size="6"' : '' ) . ( $required ? ' required' : '' ) . '>';
	if ( ! $multiple ) {
		echo '<option value="">' . esc_html__( 'Choose…', 'adaptive-content-enhancer' ) . '</option>';
	}
	foreach ( $options as $option ) {
		echo '<option value="' . esc_attr( $option['value'] ) . '"' . selected( in_array( $option['value'], $selected_values, true ), true, false ) . '>' . esc_html( $option['label'] ) . '</option>';
	}
	echo '</select>';
} elseif ( 'textarea' === $type ) {
	echo '<textarea' . $common . ' placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( $value ) . '</textarea>';
} elseif ( 'select' === $type ) {
	echo '<select' . $common . '><option value="">' . esc_html__( 'Choose…', 'adaptive-content-enhancer' ) . '</option>';
	foreach ( $attributes['choices'] ?? array() as $choice ) {
		$choice_value = (string) ( $choice['value'] ?? '' );
		echo '<option value="' . esc_attr( $choice_value ) . '"' . selected( $value, $choice_value, false ) . '>' . esc_html( $choice['label'] ?? '' ) . '</option>';
	}
	echo '</select>';
} elseif ( in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
	foreach ( $attributes['choices'] ?? array() as $choice ) {
		$choice_value = (string) ( $choice['value'] ?? '' );
		echo '<label><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . ( 'checkbox' === $type ? '[]' : '' ) . '" value="' . esc_attr( $choice_value ) . '"' . checked( in_array( $choice_value, $selected_values, true ), true, false ) . ( $required && 'radio' === $type ? ' required' : '' ) . '/> ' . esc_html( $choice['label'] ?? $choice_value ) . '</label>';
	}
} elseif ( in_array( $type, array( 'consent', 'true-false' ), true ) ) {
	echo '<input type="checkbox"' . $common . ' value="1" />';
} else {
	$input_type = in_array( $type, array( 'email', 'tel', 'url', 'number', 'range', 'date', 'time', 'file' ), true ) ? $type : ( 'datetime' === $type ? 'datetime-local' : ( 'colour' === $type ? 'color' : 'text' ) );
	$accept = 'file' === $input_type ? ' accept="' . esc_attr( implode( ',', array_map( static fn( string $extension ): string => '.' . ltrim( $extension, '.' ), $attributes['rules']['allowedFileTypes'] ?? array( 'pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx' ) ) ) ) . '"' : '';
	$limits = '';
	foreach ( array( 'min', 'max', 'step' ) as $limit ) {
		if ( isset( $attributes['rules'][ $limit ] ) && is_numeric( $attributes['rules'][ $limit ] ) ) {
			$limits .= ' ' . $limit . '="' . esc_attr( $attributes['rules'][ $limit ] ) . '"';
		}
	}
	echo '<input type="' . esc_attr( $input_type ) . '"' . $common . $accept . $limits . ( 'file' === $input_type ? '' : ' value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' ) . ' />';
}
if ( '' !== $help ) {
	echo '<p class="ace-form-field__help">' . esc_html( $help ) . '</p>';
}
echo '</' . $wrapper_tag . '>';
