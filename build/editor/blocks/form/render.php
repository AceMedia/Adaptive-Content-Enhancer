<?php
defined( 'ABSPATH' ) || exit;

echo \ACE\AdaptiveContentEnhancer\Forms\FormRender::render( $attributes, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
