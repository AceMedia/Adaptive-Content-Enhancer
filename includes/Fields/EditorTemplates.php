<?php
/** Native post-type block templates backed by synced patterns. */
namespace ACE\AdaptiveContentEnhancer\Fields;

use ACE\AdaptiveContentEnhancer\Forms\Patterns;

defined( 'ABSPATH' ) || exit;

final class EditorTemplates {
	private const OPTION = 'ace_editor_templates';

	public static function register(): void {
		register_setting( 'ace_editor_templates', self::OPTION, array( 'type' => 'array', 'default' => array(), 'sanitize_callback' => array( self::class, 'sanitize' ) ) );
	}

	public static function menu(): void {
		add_submenu_page( 'adaptive-content-enhancer', __( 'Editor templates', 'adaptive-content-enhancer' ), __( 'Editor templates', 'adaptive-content-enhancer' ), 'manage_options', 'ace-editor-templates', array( self::class, 'render' ) );
	}

	private static function eligible( \WP_Post_Type $type ): bool {
		return $type->show_ui && $type->show_in_rest && post_type_supports( $type->name, 'editor' ) && ! in_array( $type->name, array( 'wp_block', FieldGroups::POST_TYPE, 'attachment' ), true );
	}

	public static function sanitize( $input ): array {
		$result = array();
		foreach ( is_array( $input ) ? $input : array() as $slug => $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}
			$type = get_post_type_object( sanitize_key( $slug ) );
			$pattern = get_post( absint( $config['pattern'] ?? 0 ) );
			if ( ! $type || ! self::eligible( $type ) || ! $pattern || 'publish' !== $pattern->post_status || 'wp_block' !== $pattern->post_type || Patterns::is_form_pattern( $pattern ) ) {
				continue;
			}
			$lock = $config['lock'] ?? false;
			$result[ $type->name ] = array( 'pattern' => $pattern->ID, 'lock' => in_array( $lock, array( 'all', 'insert' ), true ) ? $lock : false );
		}
		return $result;
	}

	public static function apply( string $slug, \WP_Post_Type $type ): void {
		$saved = get_option( self::OPTION, array() );
		$config = is_array( $saved ) ? ( $saved[ $slug ] ?? null ) : null;
		if ( ! is_array( $config ) || ! self::eligible( $type ) ) {
			return;
		}
		$pattern = get_post( (int) ( $config['pattern'] ?? 0 ) );
		if ( ! $pattern || 'publish' !== $pattern->post_status || Patterns::is_form_pattern( $pattern ) ) {
			return;
		}
		$type->template = array( array( 'core/block', array( 'ref' => $pattern->ID ) ) );
		$type->template_lock = in_array( $config['lock'] ?? false, array( 'all', 'insert' ), true ) ? $config['lock'] : false;
	}

	public static function apply_existing(): void {
		foreach ( get_post_types( array(), 'objects' ) as $slug => $type ) {
			self::apply( $slug, $type );
		}
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage editor templates.', 'adaptive-content-enhancer' ) );
		}
		$saved = get_option( self::OPTION, array() );
		$patterns = array_filter( get_posts( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ), static fn( \WP_Post $post ): bool => ! Patterns::is_form_pattern( $post ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'Editor templates', 'adaptive-content-enhancer' ) . '</h1>';
		echo '<p>' . esc_html__( 'Choose a synced content pattern for new posts of each type. Field group values still use registered post meta and the ACE editor controls. Frontend form patterns are kept out of this list.', 'adaptive-content-enhancer' ) . '</p>';
		echo '<p>' . esc_html__( 'Lock all prevents block changes in the post editor; edit the source pattern to change the layout. Existing posts are unaffected.', 'adaptive-content-enhancer' ) . '</p>';
		echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
		settings_fields( 'ace_editor_templates' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( get_post_types( array(), 'objects' ) as $slug => $type ) {
			if ( ! self::eligible( $type ) ) {
				continue;
			}
			$current = $saved[ $slug ] ?? array();
			$prefix = self::OPTION . '[' . esc_attr( $slug ) . ']';
			echo '<tr><th scope="row">' . esc_html( $type->labels->singular_name ) . '</th><td><label>' . esc_html__( 'Default synced pattern', 'adaptive-content-enhancer' ) . ' <select name="' . esc_attr( $prefix . '[pattern]' ) . '"><option value="0">' . esc_html__( 'None', 'adaptive-content-enhancer' ) . '</option>';
			foreach ( $patterns as $pattern ) {
				echo '<option value="' . esc_attr( $pattern->ID ) . '"' . selected( $current['pattern'] ?? 0, $pattern->ID, false ) . '>' . esc_html( get_the_title( $pattern ) ) . '</option>';
			}
			echo '</select></label> <label>' . esc_html__( 'Editor lock', 'adaptive-content-enhancer' ) . ' <select name="' . esc_attr( $prefix . '[lock]' ) . '">';
			foreach ( array( '' => __( 'Unlocked', 'adaptive-content-enhancer' ), 'insert' => __( 'Prevent adding or removing blocks', 'adaptive-content-enhancer' ), 'all' => __( 'Lock all blocks', 'adaptive-content-enhancer' ) ) as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $current['lock'] ?? '', $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label></td></tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save editor templates', 'adaptive-content-enhancer' ) );
		echo '</form></div>';
	}
}
