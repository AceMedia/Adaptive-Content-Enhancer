<?php
/** Render a step without hiding it until frontend JavaScript is active. */
defined( 'ABSPATH' ) || exit;

echo '<section ' . get_block_wrapper_attributes( array( 'class' => 'ace-form-step' ) ) . ' data-ace-step>';
echo '<h3>' . esc_html( $attributes['title'] ?? __( 'Step', 'adaptive-content-enhancer' ) ) . '</h3>';
if ( ! empty( $attributes['description'] ) ) {
	echo '<p>' . esc_html( $attributes['description'] ) . '</p>';
}
echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered inner blocks.
echo '</section>';
