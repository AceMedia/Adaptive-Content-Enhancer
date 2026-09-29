<?php
/**
 * Main plugin coordinator.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer;

use ACE\AdaptiveContentEnhancer\Database\Schema;
use ACE\AdaptiveContentEnhancer\FieldTypes\Registry;
use ACE\AdaptiveContentEnhancer\Entries\Admin;
use ACE\AdaptiveContentEnhancer\Entries\Privacy;
use ACE\AdaptiveContentEnhancer\Forms\FormRegistry;
use ACE\AdaptiveContentEnhancer\Forms\Patterns;
use ACE\AdaptiveContentEnhancer\Forms\Submission;
use ACE\AdaptiveContentEnhancer\Forms\Settings;
use ACE\AdaptiveContentEnhancer\Forms\Files;
use ACE\AdaptiveContentEnhancer\Fields\FieldGroups;
use ACE\AdaptiveContentEnhancer\Fields\ValueApi;
use ACE\AdaptiveContentEnhancer\Fields\Bindings;
use ACE\AdaptiveContentEnhancer\Fields\ImportPage;
use ACE\AdaptiveContentEnhancer\Fields\TermEditor;
use ACE\AdaptiveContentEnhancer\Fields\EditorTemplates;
use ACE\AdaptiveContentEnhancer\Fields\Associations;
use ACE\AdaptiveContentEnhancer\Migration\OnboardingPage;
use ACE\AdaptiveContentEnhancer\Abilities\Registry as AbilitiesRegistry;
use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		require_once ACE_ACE_DIR . 'includes/Fields/Functions.php';
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_foundation' ), 5 );
		add_action( 'init', array( Schema::class, 'maybe_upgrade' ), 6 );
		add_action( 'init', array( FieldGroups::class, 'register_post_type' ), 4 );
		add_action( 'init', array( FieldGroups::class, 'register_meta' ), 10 );
		add_action( 'init', array( Patterns::class, 'register_category' ), 10 );
		add_action( 'init', array( EditorTemplates::class, 'apply_existing' ), 100 );
		add_action( 'registered_post_type', array( EditorTemplates::class, 'apply' ), 10, 2 );
		add_action( 'save_post_ace_field_group', array( FieldGroups::class, 'invalidate' ) );
		add_action( 'deleted_post', array( FieldGroups::class, 'invalidate_deleted' ), 10, 2 );
		add_action( 'init', array( Bindings::class, 'register' ), 11 );
		add_action( 'wp_abilities_api_categories_init', array( AbilitiesRegistry::class, 'category' ) );
		add_action( 'wp_abilities_api_init', array( AbilitiesRegistry::class, 'register' ) );
		add_action( 'admin_init', array( Capabilities::class, 'grant' ) );
		add_action( 'admin_menu', array( Admin::class, 'menu' ) );
		add_action( 'admin_menu', array( Patterns::class, 'menu' ) );
		add_action( 'admin_menu', array( EditorTemplates::class, 'menu' ) );
		add_action( 'admin_menu', array( Settings::class, 'menu' ) );
		add_action( 'admin_menu', array( ImportPage::class, 'menu' ) );
		add_action( 'admin_menu', array( OnboardingPage::class, 'menu' ) );
		add_action( 'admin_post_ace_fields_import_acf', array( ImportPage::class, 'handle' ) );
		add_action( 'admin_post_ace_data_migration', array( OnboardingPage::class, 'handle' ) );
		add_action( 'admin_init', array( Settings::class, 'register' ) );
		add_action( 'admin_init', array( Patterns::class, 'backfill' ) );
		add_action( 'admin_init', array( EditorTemplates::class, 'register' ) );
		add_action( 'admin_init', array( TermEditor::class, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_permissions' ) );
		add_action( 'admin_post_ace_forms_export', array( Admin::class, 'export_csv' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( Privacy::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( Privacy::class, 'register_eraser' ) );
		add_action( 'wp_after_insert_post', array( FormRegistry::class, 'sync_post' ), 10, 2 );
		add_action( 'wp_after_insert_post', array( Patterns::class, 'categorise' ), 11, 2 );
		add_action( 'init', array( $this, 'register_product_enquiry_pattern' ), 20 );
		add_filter( 'wp_insert_post_data', array( FormRegistry::class, 'uniquify_post_data' ), 10, 2 );
		add_action( 'rest_api_init', array( Submission::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( Files::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( FieldGroups::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( Associations::class, 'register_routes' ) );
		add_action( 'admin_post_nopriv_ace_form_submit', array( Submission::class, 'no_js_submit' ) );
		add_action( 'admin_post_ace_form_submit', array( Submission::class, 'no_js_submit' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ace-forms import-cf7', array( new \ACE\AdaptiveContentEnhancer\CLI\Command(), 'import_cf7' ) );
			\WP_CLI::add_command( 'ace-forms import-cfdb7', array( new \ACE\AdaptiveContentEnhancer\CLI\Command(), 'import_cfdb7' ) );
			\WP_CLI::add_command( 'ace-fields import-acf', array( new \ACE\AdaptiveContentEnhancer\CLI\Command(), 'import_acf' ) );
		}
	}

	public function editor_permissions(): void {
		$asset_file = ACE_ACE_DIR . 'build/editor/index.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;
			wp_enqueue_script( 'ace-field-values-editor', ACE_ACE_URL . 'build/editor/index.js', $asset['dependencies'], $asset['version'], true );
		}
		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$colours = array_merge( is_array( $palette['theme'] ?? null ) ? $palette['theme'] : array(), is_array( $palette['custom'] ?? null ) ? $palette['custom'] : array() );
		wp_add_inline_script(
			'adaptive-content-enhancer-form-editor-script',
			'window.aceFormsCanManage = ' . wp_json_encode( current_user_can( Capabilities::SETTINGS ) ) . ';window.aceFormsPalette = ' . wp_json_encode( $colours ) . ';',
			'before'
		);
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'adaptive-content-enhancer', false, dirname( plugin_basename( ACE_ACE_FILE ) ) . '/languages' );
	}

	public function register_foundation(): void {
		Registry::instance()->register_defaults();

		$blocks_path = ACE_ACE_DIR . 'build/blocks';
		// wp-scripts writes the collection manifest inside the output directory.
		$manifest = $blocks_path . '/blocks-manifest.php';

		if ( is_readable( $manifest ) && is_dir( $blocks_path ) ) {
			wp_register_block_types_from_metadata_collection( $blocks_path, $manifest );
		}
	}

	/** Resolve the shared product template to this site's editable synced form. */
	public function register_product_enquiry_pattern(): void {
		$map = get_option( 'ace_forms_cf7_map', array() );
		$source_id = ! empty( $map[12744]['pattern_id'] ) ? 12744 : 252;
		$pattern_id = isset( $map[ $source_id ]['pattern_id'] ) ? (int) $map[ $source_id ]['pattern_id'] : 0;
		if ( ! $pattern_id || 'wp_block' !== get_post_type( $pattern_id ) || 'publish' !== get_post_status( $pattern_id ) ) {
			return;
		}
		register_block_pattern(
			'adaptive-content-enhancer/product-enquiry',
			array(
				'title' => __( 'Product enquiry form', 'adaptive-content-enhancer' ),
				'content' => '<!-- wp:block {"ref":' . $pattern_id . '} /-->',
			)
		);
	}
}
