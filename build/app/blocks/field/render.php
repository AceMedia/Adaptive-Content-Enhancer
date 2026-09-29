<?php
/** Render a form field. */
defined( 'ABSPATH' ) || exit;

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
$value = (string) ( $default['value'] ?? '' );
if ( 'query' === ( $default['source'] ?? '' ) ) {
	$param = sanitize_key( $default['param'] ?? $key );
	$value = isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
}
if ( 'hidden' === $type ) {
	echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
	return;
}

$choices_field = in_array( $type, array( 'radio', 'checkbox' ), true );
$wrapper_tag = $choices_field ? 'fieldset' : 'div';
echo '<' . $wrapper_tag . ' ' . get_block_wrapper_attributes( array( 'class' => 'ace-form-field ace-form-field--' . $type . ( $floating ? ' ace-field--floating' : '' ) ) ) . '>';
echo $choices_field ? '<legend class="ace-form-field__label">' : '<label class="ace-form-field__label" for="' . esc_attr( $id ) . '">';
echo esc_html( $label );
if ( $required ) {
	echo ' <span aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( '(required)', 'adaptive-content-enhancer' ) . '</span>';
}
echo $choices_field ? '</legend>' : '</label>';

$common = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '"' . ( $required ? ' required' : '' );
if ( 'textarea' === $type ) {
	echo '<textarea' . $common . ' placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( $value ) . '</textarea>';
} elseif ( 'select' === $type ) {
	echo '<select' . $common . '><option value="">' . esc_html__( 'Choose…', 'adaptive-content-enhancer' ) . '</option>';
	foreach ( $attributes['choices'] ?? array() as $choice ) {
		echo '<option value="' . esc_attr( $choice['value'] ?? '' ) . '">' . esc_html( $choice['label'] ?? '' ) . '</option>';
	}
	echo '</select>';
} elseif ( in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
	foreach ( $attributes['choices'] ?? array() as $choice ) {
		$choice_value = (string) ( $choice['value'] ?? '' );
		echo '<label><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . ( 'checkbox' === $type ? '[]' : '' ) . '" value="' . esc_attr( $choice_value ) . '"' . ( $required && 'radio' === $type ? ' required' : '' ) . '/> ' . esc_html( $choice['label'] ?? $choice_value ) . '</label>';
	}
} elseif ( 'consent' === $type ) {
	echo '<input type="checkbox"' . $common . ' value="1" />';
} else {
	$input_type = in_array( $type, array( 'email', 'tel', 'url', 'number', 'date', 'time', 'file' ), true ) ? $type : 'text';
	$accept = 'file' === $input_type ? ' accept="' . esc_attr( implode( ',', array_map( static fn( string $extension ): string => '.' . ltrim( $extension, '.' ), $attributes['rules']['allowedFileTypes'] ?? array( 'pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx' ) ) ) ) . '"' : '';
	echo '<input type="' . esc_attr( $input_type ) . '"' . $common . $accept . ( 'file' === $input_type ? '' : ' value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' ) . ' />';
}
if ( '' !== $help ) {
	echo '<p class="ace-form-field__help">' . esc_html( $help ) . '</p>';
}
echo '</' . $wrapper_tag . '>';
