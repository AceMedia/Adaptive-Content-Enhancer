<?php
/** Block Bindings source for ACE values outside core/post-meta's scalar post scope. */
namespace ACE\AdaptiveContentEnhancer\Fields;

defined( 'ABSPATH' ) || exit;

final class Bindings {
	public static function register(): void {
		register_block_bindings_source( 'adaptive-content-enhancer/field', array(
			'label' => __( 'ACE field', 'adaptive-content-enhancer' ),
			'uses_context' => array( 'postId', 'postType' ),
			'get_value_callback' => static function ( array $args, \WP_Block $block, string $attribute ): mixed {
				$key = sanitize_key( $args['key'] ?? '' );
				if ( '' === $key ) {
					return null;
				}
				$scope = sanitize_text_field( $args['scope'] ?? 'post' );
				$object = 'post' === $scope ? (int) ( $block->context['postId'] ?? get_the_ID() ) : $scope;
				$value = ValueApi::get( $key, $object );
				if ( is_array( $value ) ) {
					$field = ValueApi::field( $key );
					if ( in_array( $field['type'] ?? '', array( 'image', 'file' ), true ) ) {
						return $value['url'] ?? null;
					}
					return wp_json_encode( $value );
				}
				return $value;
			},
		) );
	}
}
