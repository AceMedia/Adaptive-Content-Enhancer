<?php
/** Synced form pattern discovery and organisation. */
namespace ACE\AdaptiveContentEnhancer\Forms;

use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Patterns {
	public const CATEGORY = 'ace-forms';

	public static function register_category(): void {
		register_block_pattern_category( self::CATEGORY, array( 'label' => __( 'Forms', 'adaptive-content-enhancer' ) ) );
	}

	public static function menu(): void {
		add_submenu_page( 'adaptive-content-enhancer', __( 'Forms', 'adaptive-content-enhancer' ), __( 'Forms', 'adaptive-content-enhancer' ), Capabilities::SETTINGS, 'ace-form-patterns', array( self::class, 'render' ) );
	}

	public static function contains_form( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( 'adaptive-content-enhancer/form' === ( $block['blockName'] ?? '' ) || self::contains_form( $block['innerBlocks'] ?? array() ) ) {
				return true;
			}
		}
		return false;
	}

	public static function is_form_pattern( \WP_Post $post ): bool {
		return 'wp_block' === $post->post_type && self::contains_form( parse_blocks( $post->post_content ) );
	}

	public static function categorise( int $post_id, \WP_Post $post ): void {
		if ( ! self::is_form_pattern( $post ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$term = term_exists( self::CATEGORY, 'wp_pattern_category' );
		if ( ! $term ) {
			$term = wp_insert_term( __( 'Forms', 'adaptive-content-enhancer' ), 'wp_pattern_category', array( 'slug' => self::CATEGORY ) );
		}
		if ( ! is_wp_error( $term ) ) {
			wp_set_object_terms( $post_id, (int) ( is_array( $term ) ? $term['term_id'] : $term ), 'wp_pattern_category', true );
		}
	}

	/** File imported patterns created before the category existed, once per site. */
	public static function backfill(): void {
		if ( get_option( 'ace_forms_pattern_category_version' ) ) {
			return;
		}
		foreach ( get_posts( array( 'post_type' => 'wp_block', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1 ) ) as $post ) {
			self::categorise( $post->ID, $post );
		}
		update_option( 'ace_forms_pattern_category_version', 1, false );
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::SETTINGS ) ) {
			wp_die( esc_html__( 'You cannot manage form patterns.', 'adaptive-content-enhancer' ) );
		}
		$patterns = get_posts( array( 'post_type' => 'wp_block', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$patterns = array_filter( $patterns, array( self::class, 'is_form_pattern' ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'Form patterns', 'adaptive-content-enhancer' ) . '</h1>';
		echo '<p>' . esc_html__( 'These are synced WordPress patterns containing an ACE form. Edit a pattern once to update every placement. Form submissions and entries are managed separately.', 'adaptive-content-enhancer' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'site-editor.php?path=%2Fpatterns' ) ) . '">' . esc_html__( 'Open all WordPress patterns', 'adaptive-content-enhancer' ) . '</a></p>';
		if ( ! $patterns ) {
			echo '<p>' . esc_html__( 'No saved form patterns yet. Save a Form block as a synced pattern to manage it here.', 'adaptive-content-enhancer' ) . '</p></div>';
			return;
		}
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Pattern', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Status', 'adaptive-content-enhancer' ) . '</th><th>' . esc_html__( 'Updated', 'adaptive-content-enhancer' ) . '</th></tr></thead><tbody>';
		foreach ( $patterns as $post ) {
			$edit_url = get_edit_post_link( $post->ID, '' );
			echo '<tr><td><a href="' . esc_url( $edit_url ?: admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) ) . '">' . esc_html( get_the_title( $post ) ?: __( '(Untitled)', 'adaptive-content-enhancer' ) ) . '</a></td><td>' . esc_html( $post->post_status ) . '</td><td>' . esc_html( get_the_modified_date( '', $post ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
