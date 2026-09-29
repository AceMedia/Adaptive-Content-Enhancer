<?php
/** Front-end form wrapper. */
namespace ACE\AdaptiveContentEnhancer\Forms;

defined( 'ABSPATH' ) || exit;

final class FormRender {
	public static function render( array $attributes, string $content ): string {
		$id = sanitize_text_field( $attributes['formId'] ?? '' );
		if ( ! wp_is_uuid( $id ) ) {
			return ''; // A saved form must have a stable server-side ID.
		}
		$settings = is_array( $attributes['settings'] ?? null ) ? $attributes['settings'] : array();
		$appearance = is_array( $attributes['appearance'] ?? null ) ? $attributes['appearance'] : array();
		$style = in_array( $appearance['style'] ?? '', array( 'outlined', 'filled', 'underlined', 'soft' ), true ) ? $appearance['style'] : 'outlined';
		$labels = 'standard' === ( $appearance['labels'] ?? '' ) ? 'standard' : 'floating';
		$density = 'compact' === ( $appearance['density'] ?? '' ) ? 'compact' : 'comfortable';
		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$slugs = array();
		foreach ( array( 'theme', 'custom', 'default' ) as $origin ) {
			foreach ( is_array( $palette[ $origin ] ?? null ) ? $palette[ $origin ] : array() as $colour ) {
				if ( is_array( $colour ) && isset( $colour['slug'] ) ) {
					$slugs[] = $colour['slug'];
				}
			}
		}
		$colours = array();
		foreach ( array( 'accent', 'surface', 'text' ) as $part ) {
			$slug = $appearance[ $part ] ?? '';
			if ( is_string( $slug ) && in_array( $slug, $slugs, true ) ) {
				$colours[] = '--ace-form-' . $part . ':var(--wp--preset--color--' . sanitize_html_class( $slug ) . ')';
			}
		}
		$radius = isset( $appearance['radius'] ) ? min( 24, max( 0, (int) $appearance['radius'] ) ) : 8;
		$colours[] = '--ace-form-radius:' . $radius . 'px';
		$success = sanitize_text_field( $settings['successMessage'] ?? __( 'Thank you. Your enquiry has been received.', 'adaptive-content-enhancer' ) );
		$status = isset( $_GET['ace_form_status'] ) && isset( $_GET['ace_form_id'] ) && $id === sanitize_text_field( wp_unslash( $_GET['ace_form_id'] ) ) ? sanitize_key( wp_unslash( $_GET['ace_form_status'] ) ) : '';
		$notice = 'success' === $status ? $success : ( 'error' === $status ? __( 'Please check your details and try again.', 'adaptive-content-enhancer' ) : '' );
		$notice_class = 'success' === $status ? ' ace-form-status--success' : ( 'error' === $status ? ' ace-form-status--error' : '' );
		return sprintf(
			'<form %1$s method="post" enctype="multipart/form-data" action="%2$s" data-ace-form="%3$s" data-rest-root="%10$s" data-success-message="%4$s"><input type="hidden" name="action" value="ace_form_submit"/><input type="hidden" name="form_id" value="%3$s"/><input type="hidden" name="ace_started_at" value="%5$d"/><div class="ace-form-honeypot" aria-hidden="true"><label>Leave this blank <input type="text" name="ace_hp" value="" tabindex="-1" autocomplete="off"/></label></div>%6$s%11$s<noscript><label>To confirm you are a person, what is three plus four? <input type="number" name="ace_nojs_answer" required/></label></noscript><div class="ace-form-status%7$s" role="status" aria-live="polite" tabindex="-1"%8$s>%9$s</div></form>',
			get_block_wrapper_attributes( array( 'class' => 'ace-form-container ace-input--' . $style . ' ace-labels--' . $labels . ' ace-density--' . $density, 'style' => implode( ';', $colours ) ) ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $id ),
			esc_attr( $success ),
			time(),
			$content,
			esc_attr( $notice_class ),
			'' === $notice ? ' hidden' : '',
			esc_html( $notice ),
			esc_url( rest_url() ),
			Turnstile::widget()
		);
	}
}
