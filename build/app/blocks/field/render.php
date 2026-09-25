<?php
/**
 * Field block rendering.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$type  = sanitize_key( $attributes['type'] ?? 'text' );
$key   = sanitize_key( $attributes['key'] ?? '' );
$label = wp_kses_post( $attributes['label'] ?? '' );
$id    = 'ace-' . sanitize_html_class( $block->context['ace/formId'] ?? 'field' ) . '-' . $key;

if ( '' === $key ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'ace-field ace-field--' . $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<label for="<?php echo esc_attr( $id ); ?>"><?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php if ( ! empty( $attributes['required'] ) ) : ?> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( '(required)', 'adaptive-content-enhancer' ); ?></span><?php endif; ?></label>
	<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( in_array( $type, array( 'email', 'tel', 'url', 'number', 'date', 'time', 'file' ), true ) ? $type : 'text' ); ?>" <?php echo ! empty( $attributes['required'] ) ? 'required' : ''; ?> />
</div>
