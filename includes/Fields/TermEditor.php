<?php
/** WordPress component based value editor for imported taxonomy field groups. */
namespace ACE\AdaptiveContentEnhancer\Fields;

defined( 'ABSPATH' ) || exit;

final class TermEditor {
	public static function register(): void {
		$all_groups = FieldGroups::all();
		foreach ( get_taxonomies( array(), 'names' ) as $taxonomy ) {
			$groups = array_values( array_filter( $all_groups, static fn( array $group ): bool => FieldGroups::matches( $group, 'taxonomy', $taxonomy ) ) );
			if ( $groups ) {
				add_action( $taxonomy . '_edit_form_fields', array( self::class, 'render' ) );
			}
		}
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function render( \WP_Term $term ): void {
		if ( ! current_user_can( 'edit_term', $term->term_id ) || ! FieldGroups::term_fields( $term->term_id ) ) {
			return;
		}
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'ACE field groups', 'adaptive-content-enhancer' ) . '</th><td><div id="ace-term-fields-editor"></div><p class="description">' . esc_html__( 'Edit the fields here. Save field values separately from the term name and description.', 'adaptive-content-enhancer' ) . '</p></td></tr>';
	}

	public static function enqueue( string $hook ): void {
		if ( 'term.php' !== $hook || ! isset( $_GET['tag_ID'] ) ) {
			return;
		}
		$id = absint( $_GET['tag_ID'] );
		$term = get_term( $id );
		if ( ! $term || is_wp_error( $term ) || ! current_user_can( 'edit_term', $id ) ) {
			return;
		}
		$groups = array_values( array_filter( FieldGroups::all(), static fn( array $group ): bool => FieldGroups::matches( $group, 'taxonomy', $term->taxonomy ) ) );
		if ( ! $groups ) {
			return;
		}
		$asset_file = ACE_ACE_DIR . 'build/admin/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_media();
		wp_enqueue_script( 'ace-term-fields-editor', ACE_ACE_URL . 'build/admin/index.js', $asset['dependencies'], $asset['version'], true );
		if ( is_readable( ACE_ACE_DIR . 'build/admin/style-index.css' ) ) {
			wp_enqueue_style( 'ace-term-fields-editor', ACE_ACE_URL . 'build/admin/style-index.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_add_inline_script( 'ace-term-fields-editor', 'window.aceTermFields=' . wp_json_encode( array( 'termId' => $id, 'groups' => $groups ) ) . ';', 'before' );
	}
}
