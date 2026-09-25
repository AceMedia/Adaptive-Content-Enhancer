<?php
/**
 * Main plugin coordinator.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer;

use ACE\AdaptiveContentEnhancer\Database\Schema;
use ACE\AdaptiveContentEnhancer\FieldTypes\Registry;
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
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_foundation' ), 5 );
		add_action( 'admin_init', array( Capabilities::class, 'grant' ) );
		add_action( 'admin_init', array( Schema::class, 'maybe_upgrade' ) );
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
}
