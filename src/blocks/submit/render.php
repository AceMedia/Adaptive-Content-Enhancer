<?php
defined( 'ABSPATH' ) || exit;
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><button class="ace-form-submit" type="submit"><?php echo esc_html( $attributes['text'] ?? __( 'Send', 'adaptive-content-enhancer' ) ); ?></button></div>
